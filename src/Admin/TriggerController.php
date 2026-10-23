<?php
/**
 * Private trigger REST routes.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Admin;

use Omnium\Translate\Queue\ActionSchedulerQueue;
use Omnium\Translate\Queue\Crawler;
use Omnium\Translate\Queue\PublicContent;
use Omnium\Translate\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/** Exposes the milestone-three triggers to authenticated managers. */
final class TriggerController {
	private const NAMESPACE = 'omtr/v1';

	/**
	 * Constructor.
	 *
	 * @param ActionSchedulerQueue $queue Translation queue.
	 * @param PublicContent        $content Public content service.
	 * @param Crawler              $crawler Public crawler.
	 */
	public function __construct(
		private ActionSchedulerQueue $queue,
		private PublicContent $content,
		private Crawler $crawler
	) {}

	/** Register private trigger routes. */
	public function register_routes(): void {
		$permission = array( Capabilities::class, 'rest_permission' );
		register_rest_route(
			self::NAMESPACE,
			'/translate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'translate' ),
				'permission_callback' => $permission,
				'args'                => array(
					'post_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/crawl',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'crawl' ),
				'permission_callback' => $permission,
			)
		);
	}

	/**
	 * Enqueue a published public post.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function translate( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$target = $this->content->resolve( (int) $request['post_id'] );
		if ( null === $target ) {
			return new \WP_Error( 'omtr_invalid_post', __( 'Only published public content can be translated.', 'omnium-translate' ), array( 'status' => 400 ) );
		}
		if ( ! $this->queue->enqueue_url( $target['url'], $target['post_id'] ) ) {
			return new \WP_Error( 'omtr_enqueue_refused', $this->queue->last_reason(), array( 'status' => 409 ) );
		}
		return new \WP_REST_Response( array( 'queued' => true ), 202 );
	}

	/** Run the internal crawler. */
	public function crawl(): \WP_REST_Response {
		return new \WP_REST_Response( $this->crawler->crawl() );
	}
}
