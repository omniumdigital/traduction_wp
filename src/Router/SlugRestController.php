<?php
/**
 * Private REST endpoint for translated slugs.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Router;

use Omnium\Translate\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/** Allows authorized administrators to set translated slugs. */
final class SlugRestController {

	/**
	 * Constructor.
	 *
	 * @param SlugGenerator $generator Slug generator.
	 */
	public function __construct( private SlugGenerator $generator ) {}

	/** Register the private endpoint. */
	public function register_routes(): void {
		register_rest_route(
			'omtr/v1',
			'/slugs',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_slug' ),
				'permission_callback' => array( Capabilities::class, 'rest_permission' ),
				'args'                => array(
					'post_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'lang'    => array(
						'type'              => 'string',
						'required'          => true,
						'pattern'           => '^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$',
						'sanitize_callback' => 'sanitize_key',
					),
					'slug'    => array(
						'type'      => 'string',
						'required'  => true,
						'minLength' => 1,
						'maxLength' => 200,
					),
				),
			)
		);
		register_rest_route(
			'omtr/v1',
			'/slugs/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'refresh_slugs' ),
				'permission_callback' => array( Capabilities::class, 'rest_permission' ),
			)
		);
	}

	/**
	 * Request a background refresh of every translated slug.
	 */
	public function refresh_slugs(): \WP_REST_Response {
		SlugRefresher::request();
		return new \WP_REST_Response( array( 'scheduled' => true ) );
	}

	/**
	 * Validate and save a manual translated slug.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function set_slug( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$slug = $this->generator->set( (int) $request['post_id'], (string) $request['lang'], (string) $request['slug'] );
		if ( null === $slug ) {
			return new \WP_Error( 'omtr_invalid_slug', __( 'The translated slug is invalid.', 'omnium-translate' ), array( 'status' => 400 ) );
		}
		return new \WP_REST_Response( array( 'slug' => $slug ) );
	}
}
