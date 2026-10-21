<?php
/**
 * Plugin capability.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Single capability guarding every admin action and REST route.
 */
final class Capabilities {

	public const MANAGE = 'omtr_manage';

	/**
	 * Grant the capability to administrators.
	 */
	public static function grant(): void {
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( self::MANAGE );
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
