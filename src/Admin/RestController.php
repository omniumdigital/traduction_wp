<?php
/**
 * Admin REST API.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Admin;

use Omnium\Translate\Core\TranslationCachePurger;
use Omnium\Translate\Glossary\GlossaryCsv;
use Omnium\Translate\Glossary\GlossaryRepository;
use Omnium\Translate\Queue\Estimator;
use Omnium\Translate\Queue\PublicContent;
use Omnium\Translate\Queue\QueueState;
use Omnium\Translate\Security\Capabilities;
use Omnium\Translate\Security\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Private REST routes for glossary management and the segment editor.
 */
final class RestController {
	private const NAMESPACE = 'omtr/v1';

	/**
	 * Constructor.
	 *
	 * @param GlossaryRepository      $glossary Glossary repository.
	 * @param GlossaryCsv             $csv      CSV service.
	 * @param SegmentEditorRepository $segments Segment editor repository.
	 * @param Estimator|null          $estimator Estimator.
	 * @param PublicContent|null      $content Public content resolver.
	 * @param QueueState|null         $queue_state Queue pause state.
	 */
	public function __construct(
		private GlossaryRepository $glossary,
		private GlossaryCsv $csv,
		private SegmentEditorRepository $segments,
		private ?Estimator $estimator = null,
		private ?PublicContent $content = null,
		private ?QueueState $queue_state = null
	) {}

	/** Register every private route. */
	public function register_routes(): void {
		$permission = array( Capabilities::class, 'rest_permission' );
		register_rest_route(
			self::NAMESPACE,
			'/queue/(?P<command>pause|resume)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'queue_command' ),
				'permission_callback' => $permission,
				'args'                => array(
					'command' => array(
						'type'              => 'string',
						'required'          => true,
						'enum'              => array( 'pause', 'resume' ),
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/estimate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'estimate' ),
				'permission_callback' => $permission,
				'args'                => array(
					'post_ids'  => array(
						'type'              => 'array',
						'items'             => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'sanitize_callback' => static fn( array $ids ): array => array_values( array_filter( array_map( 'absint', $ids ) ) ),
					),
					'urls'      => array(
						'type'              => 'array',
						'items'             => array(
							'type'   => 'string',
							'format' => 'uri',
						),
						'sanitize_callback' => static fn( array $urls ): array => array_values( array_map( 'esc_url_raw', $urls ) ),
					),
					'all'       => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'languages' => array(
						'type'              => 'array',
						'items'             => array(
							'type'    => 'string',
							'pattern' => '^[a-z]{2,3}(?:-[a-z]{2})?$',
						),
						'sanitize_callback' => static fn( array $languages ): array => array_values( array_map( 'sanitize_key', $languages ) ),
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/glossary',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_glossary' ),
					'permission_callback' => $permission,
					'args'                => $this->glossary_list_args(),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_glossary' ),
					'permission_callback' => $permission,
					'args'                => $this->glossary_write_args(),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/glossary/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_glossary' ),
					'permission_callback' => $permission,
					'args'                => array_merge( $this->id_args(), $this->glossary_write_args() ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_glossary' ),
					'permission_callback' => $permission,
					'args'                => $this->id_args(),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/glossary/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import_glossary' ),
				'permission_callback' => $permission,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/glossary/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'export_glossary' ),
				'permission_callback' => $permission,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/segments',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_segments' ),
				'permission_callback' => $permission,
				'args'                => $this->segment_list_args(),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/segments/(?P<id>\d+)',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'edit_segment' ),
				'permission_callback' => $permission,
				'args'                => array_merge( $this->id_args(), $this->translation_args( true ) ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/segments/(?P<id>\d+)/lock',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'lock_segment' ),
					'permission_callback' => $permission,
					'args'                => array_merge( $this->id_args(), $this->translation_args( false ) ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'unlock_segment' ),
					'permission_callback' => $permission,
					'args'                => array_merge( $this->id_args(), $this->translation_args( false ) ),
				),
			)
		);
	}

	/**
	 * Pause or resume the translation queue.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function queue_command( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( null === $this->queue_state ) {
			return new \WP_Error( 'omtr_unavailable', __( 'The translation queue is unavailable.', 'omnium-translate' ), array( 'status' => 503 ) );
		}
		if ( 'pause' === (string) $request['command'] ) {
			$this->queue_state->pause();
		} else {
			$this->queue_state->resume();
		}
		return new \WP_REST_Response( array( 'paused' => $this->queue_state->is_paused() ) );
	}

	/**
	 * Estimate public posts without enqueuing work.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function estimate( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( null === $this->estimator || null === $this->content ) {
			return new \WP_Error( 'omtr_unavailable', __( 'The estimator is unavailable.', 'omnium-translate' ), array( 'status' => 503 ) );
		}
		$params  = $request->get_params();
		$targets = ! empty( $params['all'] ) ? $this->content->all() : array();
		foreach ( (array) ( $params['post_ids'] ?? array() ) as $post_id ) {
			$resolved = $this->content->resolve( (int) $post_id );
			if ( null !== $resolved ) {
				$targets[ $resolved['url'] ] = $resolved;
			}
		}
		foreach ( (array) ( $params['urls'] ?? array() ) as $url ) {
			$resolved = $this->content->resolve( (string) $url );
			if ( null !== $resolved ) {
				$targets[ $resolved['url'] ] = $resolved;
			}
		}
		if ( array() === $targets ) {
			return new \WP_Error( 'omtr_no_public_target', __( 'No published public content was selected.', 'omnium-translate' ), array( 'status' => 400 ) );
		}
		$languages = isset( $params['languages'] ) && is_array( $params['languages'] ) ? $params['languages'] : null;
		return new \WP_REST_Response( $this->estimator->estimate( array_values( $targets ), $languages ) );
	}

	/**
	 * List glossary rules.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function list_glossary( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( $this->glossary->all( (string) $request['orderby'], (string) $request['order'] ) );
	}

	/**
	 * Create a glossary rule.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function create_glossary( \WP_REST_Request $request ): \WP_REST_Response {
		$id = $this->glossary->add( (string) $request['term'], (string) $request['target_lang'], (string) $request['translation'], (string) $request['rule'] );
		return new \WP_REST_Response( array( 'id' => $id ), 201 );
	}

	/**
	 * Update a glossary rule.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function update_glossary( \WP_REST_Request $request ): \WP_REST_Response {
		$updated = $this->glossary->update( (int) $request['id'], (string) $request['term'], (string) $request['target_lang'], (string) $request['translation'], (string) $request['rule'] );
		return new \WP_REST_Response( array( 'updated' => $updated ) );
	}

	/**
	 * Delete a glossary rule.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function delete_glossary( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( array( 'deleted' => $this->glossary->delete( (int) $request['id'] ) ) );
	}

	/**
	 * Import an uploaded CSV file.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function import_glossary( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$files = $request->get_file_params();
		$path  = isset( $files['file']['tmp_name'] ) ? (string) $files['file']['tmp_name'] : '';
		$name  = isset( $files['file']['name'] ) ? (string) $files['file']['name'] : '';
		if ( '' === $path || 'csv' !== strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			return new \WP_Error( 'omtr_invalid_csv', __( 'Provide a valid CSV file.', 'omnium-translate' ), array( 'status' => 400 ) );
		}
		try {
			return new \WP_REST_Response( array( 'imported' => $this->csv->import( $path, $name ) ), 201 );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'omtr_invalid_csv', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	/** Export glossary CSV. */
	public function export_glossary(): \WP_REST_Response {
		$response = new \WP_REST_Response( $this->csv->export() );
		$response->header( 'Content-Type', 'text/csv; charset=utf-8' );
		$response->header( 'Content-Disposition', 'attachment; filename="omnium-glossary.csv"' );
		return $response;
	}

	/**
	 * List translated segments.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function list_segments( \WP_REST_Request $request ): \WP_REST_Response {
		$result   = $this->segments->list( $request->get_params() );
		$response = new \WP_REST_Response( $result['items'] );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		return $response;
	}

	/**
	 * Edit a translation and mark it edited.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function edit_segment( \WP_REST_Request $request ): \WP_REST_Response {
		$this->segments->edit( (int) $request['id'], (string) $request['target_lang'], Sanitizer::inline_html( (string) $request['text'] ) );
		$post_id = (int) ( $request['post_id'] ?? 0 );
		if ( $post_id > 0 && null !== $this->content && null !== $this->content->resolve( $post_id ) ) {
			TranslationCachePurger::purge( $post_id, (string) $request['target_lang'] );
		}
		return new \WP_REST_Response( array( 'status' => 'edited' ) );
	}

	/**
	 * Lock a translation.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function lock_segment( \WP_REST_Request $request ): \WP_REST_Response {
		return $this->lock_response( $request, true );
	}

	/**
	 * Unlock a translation to automatic status.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function unlock_segment( \WP_REST_Request $request ): \WP_REST_Response {
		return $this->lock_response( $request, false );
	}

	/**
	 * Build lock response.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @param bool             $locked  Whether the translation is locked.
	 */
	private function lock_response( \WP_REST_Request $request, bool $locked ): \WP_REST_Response {
		$this->segments->set_locked( (int) $request['id'], (string) $request['target_lang'], $locked );
		return new \WP_REST_Response( array( 'status' => $locked ? 'locked' : 'auto' ) );
	}

	/**
	 * Route id argument schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function id_args(): array {
		return array(
			'id' => array(
				'type'              => 'integer',
				'required'          => true,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Glossary list argument schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function glossary_list_args(): array {
		return array(
			'orderby' => array(
				'type'              => 'string',
				'default'           => 'term',
				'enum'              => array( 'id', 'term', 'target_lang', 'translation', 'rule' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'order'   => array(
				'type'              => 'string',
				'default'           => 'ASC',
				'enum'              => array( 'ASC', 'DESC' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Glossary write argument schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function glossary_write_args(): array {
		return array(
			'term'        => array(
				'type'              => 'string',
				'required'          => true,
				'minLength'         => 1,
				'maxLength'         => 255,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'target_lang' => $this->language_arg(),
			'translation' => array(
				'type'              => 'string',
				'default'           => '',
				'maxLength'         => 255,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'rule'        => array(
				'type'              => 'string',
				'required'          => true,
				'enum'              => array( 'translate', 'keep' ),
				'sanitize_callback' => 'sanitize_key',
			),
		);
	}

	/**
	 * Segment list argument schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function segment_list_args(): array {
		return array(
			'page'        => array(
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page'    => array(
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			),
			'target_lang' => array_merge( $this->language_arg(), array( 'required' => false ) ),
			'status'      => array(
				'type'              => 'string',
				'enum'              => array( 'auto', 'edited', 'locked', 'failed' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'search'      => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'orderby'     => array(
				'type'              => 'string',
				'default'           => 'updated_at',
				'enum'              => array( 'id', 'source_text', 'target_lang', 'status', 'updated_at' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'order'       => array(
				'type'              => 'string',
				'default'           => 'DESC',
				'enum'              => array( 'ASC', 'DESC' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Translation argument schema.
	 *
	 * @param bool $with_text Whether the translated text is required.
	 * @return array<string, array<string, mixed>>
	 */
	private function translation_args( bool $with_text ): array {
		$args = array( 'target_lang' => $this->language_arg() );
		if ( $with_text ) {
			$args['post_id'] = array(
				'type'              => 'integer',
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			);
			$args['text']    = array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => array( Sanitizer::class, 'inline_html' ),
			);
		}
		return $args;
	}

	/**
	 * Language argument schema.
	 *
	 * @return array<string, mixed>
	 */
	private function language_arg(): array {
		return array(
			'type'              => 'string',
			'required'          => true,
			'pattern'           => '^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$',
			'sanitize_callback' => 'sanitize_key',
		);
	}
}
