<?php
/**
 * Local-only HTTP fixture for the four provider APIs.
 * Mounted as a mu-plugin by the three wp-env configurations; excluded from releases.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'pre_http_request',
	static function ( $preempt, $args, $url ) {
		$kind = null;
		if ( 'https://api.anthropic.com/v1/messages' === $url ) {
			$kind = 'claude';
		} elseif ( 'https://api.openai.com/v1/responses' === $url ) {
			$kind = 'openai';
		} elseif ( 'https://api.eu.mistral.ai/v1/chat/completions' === $url ) {
			$kind = 'mistral';
		} elseif ( preg_match( '~^https://generativelanguage\.googleapis\.com/v1beta/models/[a-zA-Z0-9._-]+:generateContent$~D', $url ) ) {
			$kind = 'gemini';
		}
		if ( null === $kind ) {
			return $preempt;
		}

		update_option( 'omtr_mock_calls', (int) get_option( 'omtr_mock_calls', 0 ) + 1, false );
		$request = json_decode( (string) ( $args['body'] ?? '' ), true );
		if ( ! is_array( $request ) ) {
			return new WP_Error( 'omtr_mock_bad_request', 'Invalid mock request JSON.' );
		}

		$system = '';
		$user = '';
		switch ( $kind ) {
			case 'claude':
				$system = (string) ( $request['system'] ?? '' );
				$user = (string) ( $request['messages'][0]['content'] ?? '' );
				break;
			case 'openai':
				$system = (string) ( $request['instructions'] ?? '' );
				$user = (string) ( $request['input'] ?? '' );
				break;
			case 'mistral':
				$system = (string) ( $request['messages'][0]['content'] ?? '' );
				$user = (string) ( $request['messages'][1]['content'] ?? $request['messages'][0]['content'] ?? '' );
				break;
			case 'gemini':
				$system = (string) ( $request['systemInstruction']['parts'][0]['text'] ?? '' );
				$user = (string) ( $request['contents'][0]['parts'][0]['text'] ?? '' );
				break;
		}

		$text = 'pong';
		if ( preg_match( '/<segments>\s*(\{.*?\})\s*<\/segments>/s', $user, $matches ) ) {
			$segments = json_decode( $matches[1], true );
			if ( ! is_array( $segments ) ) {
				return new WP_Error( 'omtr_mock_bad_segments', 'Invalid mock segments.' );
			}
			$target = preg_match( '/into language "([a-zA-Z-]+)"/', $system, $language ) ? strtoupper( $language[1] ) : 'EN';
			$translated = array();
			foreach ( $segments as $number => $source ) {
				$translated[ (string) $number ] = '[' . $target . '] ' . $source;
			}
			$text = wp_json_encode( $translated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		switch ( $kind ) {
			case 'claude':
				$body = array( 'content' => array( array( 'type' => 'text', 'text' => $text ) ) );
				break;
			case 'openai':
				$body = array( 'output' => array( array( 'type' => 'message', 'content' => array( array( 'type' => 'output_text', 'text' => $text ) ) ) ) );
				break;
			case 'mistral':
				$body = array( 'choices' => array( array( 'message' => array( 'content' => $text ) ) ) );
				break;
			default:
				$body = array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'text' => $text ) ) ) ) ) );
		}

		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);
