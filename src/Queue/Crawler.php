<?php
/**
 * Public content crawler.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

use Omnium\Translate\Core\LoggerInterface;
use Omnium\Translate\Store\PageRepository;

defined( 'ABSPATH' ) || exit;

/** Queues only public pages whose anonymously rendered HTML changed. */
final class Crawler {
	/**
	 * Constructor.
	 *
	 * @param PublicContent        $content Public content service.
	 * @param PageRepository       $pages Page scan repository.
	 * @param ActionSchedulerQueue $queue Translation queue.
	 * @param LoggerInterface      $logger Logger.
	 */
	public function __construct(
		private PublicContent $content,
		private PageRepository $pages,
		private ActionSchedulerQueue $queue,
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
			$content_hash = hash( 'sha256', $html );
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
