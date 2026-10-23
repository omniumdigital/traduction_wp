<?php
/**
 * Glossary domain service.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Glossary;

use Omnium\Translate\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the provider glossary for a target language.
 */
final class GlossaryService {

	/**
	 * Constructor.
	 *
	 * @param GlossaryRepository $repository Glossary repository.
	 * @param Settings           $settings   Plugin settings.
	 */
	public function __construct(
		private GlossaryRepository $repository,
		private Settings $settings
	) {}

	/**
	 * Build rules, tone and context for one target language.
	 *
	 * @param string $target Target language.
	 */
	public function for_language( string $target ): Glossary {
		$translate = array();
		$keep      = array();

		foreach ( $this->repository->for_language( $target ) as $row ) {
			if ( 'keep' === $row['rule'] ) {
				$keep[] = $row['term'];
			} elseif ( 'translate' === $row['rule'] ) {
				$translate[ $row['term'] ] = $row['translation'];
			}
		}

		return new Glossary(
			$translate,
			$keep,
			(string) $this->settings->get( 'site_tone' ),
			(string) $this->settings->get( 'site_context' )
		);
	}

	/**
	 * Propose auto translations affected by a glossary rule.
	 *
	 * This method is read-only: callers decide whether to retranslate later.
	 *
	 * @param int $rule_id Glossary rule id.
	 * @return list<array{segment_id: int, source_text: string, translation_id: int, translation: string, target_lang: string, status: string}>
	 */
	public function affected( int $rule_id ): array {
		return $this->repository->affected( $rule_id );
	}
}
