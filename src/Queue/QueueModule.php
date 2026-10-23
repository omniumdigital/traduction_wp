<?php
/**
 * Translation queue module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\LoggerInterface;
use Omnium\Translate\Core\ModuleInterface;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Glossary\GlossaryService;
use Omnium\Translate\Providers\ProviderRegistry;
use Omnium\Translate\Providers\Pricing;
use Omnium\Translate\Render\SegmentExtractorInterface;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\RequestGuard;
use Omnium\Translate\Store\PageRepository;
use Omnium\Translate\Store\SegmentRepositoryInterface;
use Omnium\Translate\Store\TranslationRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Action Scheduler jobs (group "omtr"), batching, retry/backoff, rate limit,
 * lock and word cap (CLAUDE.md section 5). No provider is ever called during
 * a visitor request: everything runs in scheduled actions.
 */
final class QueueModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set( RunLock::class, static fn() => new RunLock() );
		$container->set( WordCap::class, static fn( Container $c ) => new WordCap( $c->get( Settings::class ) ) );
		$container->set( HourlyQuota::class, static fn( Container $c ) => new HourlyQuota( $c->get( Settings::class ) ) );
		$container->set( PublicContent::class, static fn() => new PublicContent() );
		$container->set( QueueState::class, static fn() => new QueueState() );
		if ( ! $container->has( Pricing::class ) ) {
			$container->set( Pricing::class, static fn() => new Pricing() );
		}
		$container->set(
			Estimator::class,
			static fn( Container $c ): Estimator => new Estimator(
				$c->get( Settings::class ),
				$c->get( PublicContent::class ),
				$c->get( SegmentExtractorInterface::class ),
				$c->get( TranslationRepositoryInterface::class ),
				$c->get( WordCap::class ),
				$c->get( ProviderRegistry::class ),
				$c->get( Pricing::class )
			)
		);

		$container->set(
			ActionSchedulerQueue::class,
			static fn( Container $c ): ActionSchedulerQueue => new ActionSchedulerQueue(
				$c->get( Settings::class ),
				$c->get( RunLock::class ),
				$c->get( WordCap::class ),
				$c->get( HourlyQuota::class ),
				$c->get( ProviderRegistry::class ),
				$c->get( LoggerInterface::class ),
				$c->get( SegmentRepositoryInterface::class ),
				$c->get( TranslationRepositoryInterface::class ),
				$c->get( QueueState::class )
			)
		);

		$container->set(
			ScanJob::class,
			static fn( Container $c ): ScanJob => new ScanJob(
				$c->get( Settings::class ),
				$c->get( RunLock::class ),
				$c->get( WordCap::class ),
				$c->get( ActionSchedulerQueue::class ),
				$c->get( SegmentExtractorInterface::class ),
				$c->get( SegmentRepositoryInterface::class ),
				$c->get( TranslationRepositoryInterface::class ),
				$c->get( LoggerInterface::class ),
				$c->get( QueueState::class )
			)
		);

		$container->set(
			BatchJob::class,
			static fn( Container $c ): BatchJob => new BatchJob(
				$c->get( Settings::class ),
				$c->get( RunLock::class ),
				$c->get( ActionSchedulerQueue::class ),
				$c->get( SegmentRepositoryInterface::class ),
				$c->get( TranslationRepositoryInterface::class ),
				$c->get( LoggerInterface::class ),
				$c->get( GlossaryService::class ),
				$c->get( QueueState::class )
			)
		);

		$container->set(
			Crawler::class,
			static fn( Container $c ): Crawler => new Crawler(
				$c->get( PublicContent::class ),
				$c->get( PageRepository::class ),
				$c->get( ActionSchedulerQueue::class ),
				$c->get( LoggerInterface::class )
			)
		);
		$container->set(
			AutoTrigger::class,
			static fn( Container $c ): AutoTrigger => new AutoTrigger(
				$c->get( Settings::class ),
				$c->get( PublicContent::class ),
				$c->get( ActionSchedulerQueue::class )
			)
		);
		$container->set(
			OnTheFlyTrigger::class,
			static fn( Container $c ): OnTheFlyTrigger => new OnTheFlyTrigger(
				$c->get( Settings::class ),
				$c->get( RequestGuard::class ),
				$c->get( CurrentLanguage::class ),
				$c->get( PublicContent::class ),
				$c->get( SegmentExtractorInterface::class ),
				$c->get( SegmentRepositoryInterface::class ),
				$c->get( TranslationRepositoryInterface::class ),
				$c->get( ActionSchedulerQueue::class ),
				$c->get( WordCap::class ),
				$c->get( HourlyQuota::class ),
				$c->get( QueueState::class )
			)
		);

		$container->set(
			TranslateCli::class,
			static fn( Container $c ): TranslateCli => new TranslateCli(
				$c->get( ActionSchedulerQueue::class ),
				$c->get( Settings::class ),
				$c->get( RunLock::class ),
				$c->get( WordCap::class ),
				$c->get( HourlyQuota::class ),
				$c->get( Estimator::class ),
				$c->get( PublicContent::class ),
				$c->get( QueueState::class ),
				$c->get( Crawler::class )
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
		// Hook registration only: services are resolved lazily when an action
		// actually fires, so a missing binding cannot break the whole boot.
		add_action(
			ActionSchedulerQueue::ACTION_SCAN,
			static function ( string $url, int $post_id, string $run_id ) use ( $container ): void {
				$container->get( ScanJob::class )->handle( $url, $post_id, $run_id );
			},
			10,
			3
		);
		$auto = $container->get( AutoTrigger::class );
		add_action( 'save_post', array( $auto, 'on_save' ), 20, 3 );
		add_action( AutoTrigger::ACTION, array( $auto, 'handle' ) );
		add_action( 'shutdown', array( $container->get( OnTheFlyTrigger::class ), 'flush' ), 20 );

		add_action(
			ActionSchedulerQueue::ACTION_BATCH,
			static function ( array $segments, string $lang, string $run_id, int $attempt ) use ( $container ): void {
				$container->get( BatchJob::class )->handle( $segments, $lang, $run_id, $attempt );
			},
			10,
			4
		);

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$container->get( TranslateCli::class )->register();
		}
	}
}
