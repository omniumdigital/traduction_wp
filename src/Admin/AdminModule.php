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
use Omnium\Translate\Queue\ActionSchedulerQueue;
use Omnium\Translate\Queue\Crawler;

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
			TranslationActions::class,
			static fn( Container $c ) => new TranslationActions( $c->get( ActionSchedulerQueue::class ), $c->get( PublicContent::class ) )
		);
		$container->set(
			TriggerController::class,
			static fn( Container $c ) => new TriggerController( $c->get( ActionSchedulerQueue::class ), $c->get( PublicContent::class ), $c->get( Crawler::class ) )
		);
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
		add_action( 'rest_api_init', array( $container->get( TriggerController::class ), 'register_routes' ) );
		$actions = $container->get( TranslationActions::class );
		add_filter( 'post_row_actions', array( $actions, 'row_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( $actions, 'row_action' ), 10, 2 );
		add_action( 'admin_bar_menu', array( $actions, 'admin_bar' ), 100 );
		add_action( 'admin_post_omtr_translate_post', array( $actions, 'handle' ) );
	}
}
