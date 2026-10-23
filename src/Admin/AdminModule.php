<?php
/**
 * Admin module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Admin;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\ModuleInterface;
use Omnium\Translate\Glossary\GlossaryCsv;
use Omnium\Translate\Glossary\GlossaryRepository;
use Omnium\Translate\Queue\Estimator;
use Omnium\Translate\Queue\PublicContent;
use Omnium\Translate\Queue\QueueState;

defined( 'ABSPATH' ) || exit;

/**
 * Settings, wizard, dashboard, segment editor; React UI with @wordpress/components
 * (CLAUDE.md section 9). Every action: capability omtr_manage + nonce. TODO(jalon 3 and 5).
 */
final class AdminModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set( SegmentEditorRepository::class, static fn() => new SegmentEditorRepository( $GLOBALS['wpdb'] ) );
		$container->set(
			RestController::class,
			static fn( Container $c ) => new RestController(
				$c->get( GlossaryRepository::class ),
				$c->get( GlossaryCsv::class ),
				$c->get( SegmentEditorRepository::class ),
				$c->get( Estimator::class ),
				$c->get( PublicContent::class ),
				$c->get( QueueState::class )
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
		add_action( 'rest_api_init', array( $container->get( RestController::class ), 'register_routes' ) );
	}
}
