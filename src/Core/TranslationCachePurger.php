<?php
/** Translation page cache invalidation.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Core;

defined( 'ABSPATH' ) || exit;

/** Purges a post after its translations change. */
final class TranslationCachePurger {
	/** Purge WordPress and supported page caches, then notify integrations.
	 *
	 * @param int    $post_id Source post id.
	 * @param string $lang    Target language.
	 */
	public static function purge( int $post_id, string $lang ): void {
		if ( $post_id <= 0 ) {
			return;
		}
		clean_post_cache( $post_id );
		if ( has_action( 'litespeed_purge_post' ) ) {
			do_action( 'litespeed_purge_post', $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed's documented hook.
		}
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $post_id );
		}
		/** Fires after translations for a known post change.
		 *
		 * @param int    $post_id Source post id.
		 * @param string $lang    Target language.
		 */
		do_action( 'omtr_translations_updated', $post_id, $lang );
	}
}
