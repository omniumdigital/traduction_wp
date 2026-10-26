<?php
/**
 * Plugin capability.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Security;

use Omnium\Translate\Core\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Single capability guarding every admin action and REST route.
 */
final class Capabilities {

	public const MANAGE = 'omtr_manage';

	/**
	 * Grant the capability to administrators.
	 *
	 * @return bool Whether a role actually received the capability.
	 */
	public static function grant(): bool {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return false;
		}
		$role->add_cap( self::MANAGE );
		return true;
	}

	/**
	 * Grant the management capability once, unless already done or removed.
	 *
	 * Installs activated while the bootstrap bailed on a missing prerequisite
	 * (1.0.x) then updated through "Replace existing" never re-ran the
	 * activation routine, so the admin menu never appeared. The flag option
	 * keeps this one-shot: when it is present the capability is never
	 * re-granted, preserving a deliberate removal by an administrator.
	 */
	public static function ensure_granted(): void {
		if ( false !== get_option( Options::CAPS_GRANTED ) ) {
			return;
		}
		if ( self::grant() ) {
			update_option( Options::CAPS_GRANTED, 1, false );
		}
	}

	/**
	 * Remove the capability from every role.
	 */
	public static function revoke(): void {
		foreach ( wp_roles()->role_objects as $role ) {
			$role->remove_cap( self::MANAGE );
		}
	}

	/**
	 * Whether the current user can manage the plugin.
	 */
	public static function current_user_can_manage(): bool {
		return current_user_can( self::MANAGE );
	}

	/**
	 * REST permission_callback helper.
	 */
	public static function rest_permission(): bool {
		return self::current_user_can_manage();
	}
}
