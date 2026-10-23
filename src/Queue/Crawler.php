<?php
/**
 * Public content crawler.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

use Omnium\Translate\Core\LoggerInterface;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Render\SegmentExtractorInterface;
use Omnium\Translate\Store\PageRepository;

defined( 'ABSPATH' ) || exit;

/** Queues only public pages whose anonymously rendered HTML changed. */
final class Crawler {
	/**
	 * Constructor.
	 *
	 * @param PublicContent             $content Public content service.
	 * @param PageRepository            $pages Page scan repository.
	 * @param ActionSchedulerQueue      $queue Translation queue.
	 * @param SegmentExtractorInterface $extractor Segment extractor.
	 * @param Settings                  $settings Plugin settings.
	 * @param LoggerInterface           $logger Logger.
	 */
	public function __construct(
		private PublicContent $content,
		private PageRepository $pages,
		private ActionSchedulerQueue $queue,
		private SegmentExtractorInterface $extractor,
		private Settings $settings,
		private LoggerInterface $logger
	) {}

	/**
	 * Crawl all public posts and enqueue changed pages.
	 *
	 * @return array{scanned: int, changed: int, unchanged: int, failed: int, queued: bool, reason: string}
	 */
	public function crawl(): array {
		$changed   = array();
		$scanned   = 0;
		$unchanged = 0;
		$failed    = 0;
		foreach ( $this->content->all() as $target ) {
			$html = $this->content->fetch( $target['url'] );
			if ( null === $html ) {
				++$failed;
				continue;
			}
			++$scanned;
			$segments = $this->extractor->extract( $html, $this->settings->source_lang() );
			ksort( $segments );
			$content_hash = hash( 'sha256', (string) wp_json_encode( $segments ) );
			if ( $this->pages->content_hash( $target['url'] ) === $content_hash ) {
				++$unchanged;
				continue;
			}
			$changed[] = array_merge( $target, array( 'content_hash' => $content_hash ) );
		}
		$queued = array() !== $changed && $this->queue->enqueue_urls( $changed );
		if ( $queued ) {
			foreach ( $changed as $target ) {
				$this->pages->save_scan( $target['post_id'], $target['url'], $target['content_hash'] );
			}
		}
		$this->logger->log( 'info', 'crawler', sprintf( 'Crawl complete (scanned=%d, changed=%d, failed=%d).', $scanned, count( $changed ), $failed ) );
		return array(
			'scanned'   => $scanned,
			'changed'   => count( $changed ),
			'unchanged' => $unchanged,
			'failed'    => $failed,
			'queued'    => $queued,
			'reason'    => $queued || array() === $changed ? '' : $this->queue->last_reason(),
		);
	}
}
