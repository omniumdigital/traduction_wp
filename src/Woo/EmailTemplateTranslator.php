<?php
/**
 * Stored translations for WooCommerce email templates.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Woo;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Queue\TranslationQueueInterface;
use Omnium\Translate\Render\SegmentExtractorInterface;
use Omnium\Translate\Render\Translator;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Store\SegmentRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Registers safe, data-free email strings and applies existing translations.
 */
final class EmailTemplateTranslator {

	public function __construct(
		private CurrentLanguage $current_language,
		private Settings $settings,
		private SegmentExtractorInterface $extractor,
		private SegmentRepositoryInterface $segments,
		private TranslationQueueInterface $queue,
		private Translator $translator
	) {}

	/** Register WooCommerce email filters. */
	public function register_hooks(): void {
		add_filter( 'woocommerce_email_classes', array( $this, 'register_email_filters' ), 10, 1 );
		add_filter( 'woocommerce_email_headers', array( $this, 'translate_headers' ), 10, 4 );
	}

	/** Add per-email filters after WooCommerce has built its email registry. */
	public function register_email_filters( mixed $emails ): mixed {
		if ( ! is_array( $emails ) ) {
			return $emails;
		}
		foreach ( $emails as $email ) {
			if ( ! is_object( $email ) || ! isset( $email->id ) ) {
				continue;
			}
			$id = sanitize_key( (string) $email->id );
			if ( '' === $id ) {
				continue;
			}
			add_filter( 'woocommerce_email_subject_' . $id, array( $this, 'translate_subject' ), 10, 3 );
			add_filter( 'woocommerce_email_heading_' . $id, array( $this, 'translate_heading' ), 10, 3 );
			add_filter( 'woocommerce_email_additional_content_' . $id, array( $this, 'translate_additional_content' ), 10, 3 );
		}
		return $emails;
	}

	/** Translate only the data-free subject template, never the formatted value. */
	public function translate_subject( mixed $formatted, mixed $object, mixed $email ): mixed {
		return $this->translate_email_template( $formatted, $object, $email, 'subject', 'get_default_subject' );
	}

	/** Translate only the data-free heading template, never the formatted value. */
	public function translate_heading( mixed $formatted, mixed $object, mixed $email ): mixed {
		return $this->translate_email_template( $formatted, $object, $email, 'heading', 'get_default_heading' );
	}

	/** Translate configured additional content without order data. */
	public function translate_additional_content( mixed $formatted, mixed $object, mixed $email ): mixed {
		return $this->translate_email_template( $formatted, $object, $email, 'additional_content', null );
	}

	/** Headers contain addresses and are intentionally left byte-for-byte intact. */
	public function translate_headers( mixed $headers, mixed $email_id, mixed $object, mixed $email ): mixed {
		unset( $email_id, $email );
		// WooCommerce header labels are protocol data; translating them would risk
		// corrupting recipient data and provides no customer-facing template text.
		return $headers;
	}

	/**
	 * Register a template and return its existing translation for the order language.
	 *
	 * @param mixed       $formatted Already formatted WooCommerce value (never inspected).
	 * @param mixed       $object    Email object, possibly an order.
	 * @param mixed       $email     WooCommerce email instance.
	 * @param string      $setting   Setting key.
	 * @param string|null $default   Default getter on the email instance.
	 */
	private function translate_email_template( mixed $formatted, mixed $object, mixed $email, string $setting, ?string $default ): mixed {
		$order_lang = $this->order_language( $object );
		if ( null === $order_lang || ! in_array( $order_lang, $this->settings->target_langs(), true ) || ! is_object( $email ) ) {
			return $formatted;
		}
		$template = $this->template( $email, $setting, $default );
		if ( '' === $template ) {
			return '';
		}
		$segments = $this->extractor->extract( '<div>' . $template . '</div>', $this->current_language->source() );
		if ( array() === $segments ) {
			return $template;
		}
		$ids = $this->segments->upsert_many( $segments, $this->current_language->source() );
		$this->queue->enqueue_segments( array_values( $ids ), $order_lang );
		$previous = $this->current_language->get();
		$this->current_language->set( $order_lang );
		$translated = $this->translator->translate( '<div>' . $template . '</div>' );
		$this->current_language->set( $previous );
		$document = str_replace( array( '<div>', '</div>' ), '', $translated );
		return $document;
	}

	/** Read only the configured/default template, never the formatted filter value. */
	private function template( object $email, string $setting, ?string $default ): string {
		if ( null !== $default && method_exists( $email, $default ) ) {
			$value = $email->{$default}();
		} else {
			$id      = isset( $email->id ) ? sanitize_key( (string) $email->id ) : '';
			$options = '' !== $id ? get_option( 'woocommerce_' . $id . '_settings', array() ) : array();
			$value   = is_array( $options ) ? ( $options[ $setting ] ?? '' ) : '';
		}
		return is_string( $value ) ? $value : '';
	}

	/** Return an order language only when WooCommerce exposes the order API. */
	private function order_language( mixed $object ): ?string {
		if ( ! is_object( $object ) || ! method_exists( $object, 'get_meta' ) ) {
			return null;
		}
		$lang = $object->get_meta( OrderLanguage::META_KEY, true );
		return is_string( $lang ) && '' !== $lang ? sanitize_key( $lang ) : null;
	}
}
