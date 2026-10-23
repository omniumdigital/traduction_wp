<?php
/**
 * Public content discovery and anonymous rendering.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves published public posts and fetches their final HTML anonymously.
 */
final class PublicContent {

	/**
	 * Resolve a post id or same-site URL.
	 *
	 * @param int|string $target Post id or URL.
	 * @return array{post_id: int, url: string}|null
	 */
	public function resolve( int|string $target ): ?array {
		$post_id = is_int( $target ) || ctype_digit( (string) $target ) ? (int) $target : 0;
		$url     = $post_id > 0 ? get_permalink( $post_id ) : (string) $target;
		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}
		$normalized = $this->normalize_url( $url );
		if ( null === $normalized ) {
			return null;
		}
		if ( 0 === $post_id ) {
			$post_id = (int) url_to_postid( $normalized );
		}
		if ( $post_id <= 0 || ! $this->is_public_post( $post_id ) || $this->is_sensitive_post( $post_id ) ) {
			return null;
		}
		return array(
			'post_id' => $post_id,
			'url'     => $normalized,
		);
	}

	/**
	 * List every published post belonging to a public post type.
	 *
	 * @return list<array{post_id: int, url: string}>
	 */
	public function all(): array {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$ids        = get_posts(
			array(
				'post_type'      => array_values( $post_types ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		$result     = array();
		foreach ( $ids as $id ) {
			$resolved = $this->resolve( (int) $id );
			if ( null !== $resolved ) {
				$result[] = $resolved;
			}
		}
		return $result;
	}

	/**
	 * Fetch final public HTML without cookies and without following redirects.
	 *
	 * @param string $url Same-site normalized URL.
	 */
	public function fetch( string $url ): ?string {
		$normalized = $this->normalize_url( $url );
		if ( null === $normalized ) {
			return null;
		}
		$home      = wp_parse_url( (string) home_url() );
		$home_host = is_array( $home ) && isset( $home['host'] ) ? strtolower( (string) $home['host'] ) : '';
		$home_port = is_array( $home ) && isset( $home['port'] ) ? (int) $home['port'] : ( 'https' === ( $home['scheme'] ?? '' ) ? 443 : 80 );
		if ( '' === $home_host ) {
			return null;
		}

		$allow_home_host = static fn( bool $is_external, string $host ): bool => strtolower( $host ) === $home_host ? true : $is_external;
		$allow_home_port = static function ( array $ports ) use ( $home_port ): array {
			if ( ! in_array( $home_port, $ports, true ) ) {
				$ports[] = $home_port;
			}
			return $ports;
		};
		add_filter( 'http_request_host_is_external', $allow_home_host, 10, 2 );
		add_filter( 'http_allowed_safe_ports', $allow_home_port );
		try {
			$response = wp_safe_remote_get(
				$normalized,
				array(
					'timeout'     => 30,
					'redirection' => 0,
					'sslverify'   => true,
					'cookies'     => array(),
					'headers'     => array( 'Cookie' => '' ),
					'user-agent'  => 'Omnium-Translate/' . OMTR_VERSION . ' (internal crawler)',
				)
			);
		} finally {
			remove_filter( 'http_allowed_safe_ports', $allow_home_port );
			remove_filter( 'http_request_host_is_external', $allow_home_host, 10 );
		}
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		$body = (string) wp_remote_retrieve_body( $response );
		return '' === $body ? null : $body;
	}

	/**
	 * Normalize a same-site URL and strip its query and fragment.
	 *
	 * @param string $url Candidate URL.
	 */
	public function normalize_url( string $url ): ?string {
		$parts = wp_parse_url( trim( $url ) );
		$home  = wp_parse_url( (string) home_url() );
		if ( ! is_array( $parts ) || ! is_array( $home ) || ! isset( $parts['scheme'], $parts['host'], $home['scheme'], $home['host'] ) ) {
			return null;
		}
		$scheme      = strtolower( (string) $parts['scheme'] );
		$home_scheme = strtolower( (string) $home['scheme'] );
		$port        = isset( $parts['port'] ) ? (int) $parts['port'] : ( 'https' === $scheme ? 443 : 80 );
		$home_port   = isset( $home['port'] ) ? (int) $home['port'] : ( 'https' === $home_scheme ? 443 : 80 );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || $scheme !== $home_scheme || strtolower( (string) $parts['host'] ) !== strtolower( (string) $home['host'] ) || $port !== $home_port ) {
			return null;
		}
		$path      = '/' . ltrim( (string) ( $parts['path'] ?? '/' ), '/' );
		$home_path = '/' . trim( (string) ( $home['path'] ?? '' ), '/' );
		$home_path = '/' === $home_path ? '' : $home_path;
		if ( '' !== $home_path && $path !== $home_path && 0 !== strpos( $path, $home_path . '/' ) ) {
			return null;
		}
		$relative = trim( '' === $home_path ? $path : (string) substr( $path, strlen( $home_path ) ), '/' );
		foreach ( array( 'wp-admin', 'wp-login.php', 'wp-register.php', 'wp-json', 'xmlrpc.php', 'cart', 'checkout', 'my-account', 'order-received' ) as $blocked ) {
			if ( $relative === $blocked || 0 === strpos( $relative, $blocked . '/' ) ) {
				return null;
			}
		}
		$port_part = isset( $home['port'] ) ? ':' . (int) $home['port'] : '';
		return $home_scheme . '://' . strtolower( (string) $home['host'] ) . $port_part . $path;
	}

	/**
	 * Determine whether a post is published, unprotected and publicly queryable.
	 *
	 * @param int $post_id Post id.
	 */
	private function is_public_post( int $post_id ): bool {
		$post_type = get_post_type( $post_id );
		$object    = is_string( $post_type ) ? get_post_type_object( $post_type ) : null;
		return 'publish' === get_post_status( $post_id ) && ! post_password_required( $post_id ) && null !== $object && (bool) $object->public;
	}

	/**
	 * Exclude WooCommerce pages that may render customer data.
	 *
	 * @param int $post_id Post id.
	 */
	private function is_sensitive_post( int $post_id ): bool {
		foreach ( array( 'woocommerce_cart_page_id', 'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id' ) as $option ) {
			if ( (int) get_option( $option, 0 ) === $post_id ) {
				return true;
			}
		}
		return false;
	}
}
