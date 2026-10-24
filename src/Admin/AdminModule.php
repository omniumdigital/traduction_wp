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
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Providers\ProviderRegistry;
use Omnium\Translate\Providers\Pricing;
use Omnium\Translate\Security\ApiKeyStore;
use Omnium\Translate\Queue\RunLock;
use Omnium\Translate\Queue\WordCap;
use Omnium\Translate\Seo\TranslationCoverage;

defined( 'ABSPATH' ) || exit;

/**
 * Private administration routes and React page bootstrap.
 */
final class AdminModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set(
			Administration::class,
			static fn( Container $c ) => new Administration(
				$c->get( Settings::class ),
				$c->get( ProviderRegistry::class ),
				$c->get( Pricing::class ),
				$c->get( ApiKeyStore::class ),
				$c->get( GlossaryRepository::class ),
				$c->get( ActionSchedulerQueue::class ),
				$c->get( QueueState::class ),
				$c->get( RunLock::class ),
				$c->get( WordCap::class ),
				$c->get( PublicContent::class ),
				$c->get( TranslationCoverage::class ),
				$GLOBALS['wpdb']
			)
		);
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
		$administration = $container->get( Administration::class );
		add_action( 'admin_menu', array( $administration, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $administration, 'enqueue' ) );
		add_action( 'admin_init', array( $administration, 'privacy_policy' ) );
		add_action( 'rest_api_init', array( $administration, 'register_routes' ) );
		add_action( 'rest_api_init', array( $container->get( RestController::class ), 'register_routes' ) );
		add_action( 'rest_api_init', array( $container->get( TriggerController::class ), 'register_routes' ) );
		$actions = $container->get( TranslationActions::class );
		add_filter( 'post_row_actions', array( $actions, 'row_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( $actions, 'row_action' ), 10, 2 );
		add_action( 'admin_bar_menu', array( $actions, 'admin_bar' ), 100 );
		add_action( 'admin_post_omtr_translate_post', array( $actions, 'handle' ) );
	}
}
