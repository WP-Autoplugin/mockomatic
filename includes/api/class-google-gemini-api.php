<?php
/**
 * Google Gemini API wrapper.
 *
 * @package Mockomatic
 */

namespace Mockomatic\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Google Gemini API class.
 */
class Google_Gemini_API extends API {

	/**
	 * Model.
	 *
	 * @var string
	 */
	protected $model = 'gemini-1.5-flash';

	/**
	 * Base URL.
	 *
	 * @var string
	 */
	protected $api_url = 'https://generativelanguage.googleapis.com/v1beta/models';

	/**
	 * Temperature.
	 *
	 * @var float
	 */
	protected $temperature = 0.4;

	/**
	 * Max tokens.
	 *
	 * @var int
	 */
	protected $max_tokens = 8192;

	/**
	 * Set model.
	 *
	 * @param string $model Model name.
	 */
	public function set_model( $model ) {
		$this->model = sanitize_text_field( $model );
	}

	/**
	 * Send prompt.
	 *
	 * @param string $prompt         Prompt.
	 * @param string $system_message System message.
	 *
	 * @return string|\WP_Error
	 */
	public function send_prompt( $prompt, $system_message = '' ) {
		$prompt = $this->trim_prompt( $prompt );

		$url = $this->api_url . '/' . $this->model . ':generateContent?key=' . rawurlencode( $this->api_key );

		$messages = [];
		if ( $system_message ) {
			$messages[] = $system_message;
		}
		$messages[] = $prompt;

		$parts = [];
		foreach ( $messages as $message ) {
			$parts[] = [ 'text' => $message ];
		}

		$body = [
			'contents'         => [
				[
					'parts' => $parts,
				],
			],
			'generationConfig' => [
				'temperature'     => $this->temperature,
				'maxOutputTokens' => $this->max_tokens,
			],
		];

		/**
		 * Filter the Google Gemini API request body before sending.
		 *
		 * @param array  $body           Request body parameters.
		 * @param string $model          Model name.
		 * @param string $prompt         User prompt.
		 * @param string $system_message System message.
		 */
		$body = apply_filters( 'mockomatic_gemini_request_body', $body, $this->model, $prompt, $system_message );

		$headers  = [ 'Content-Type' => 'application/json' ];
		$response = $this->request(
			$url,
			[
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			return new \WP_Error(
				'gemini_api_error',
				$this->build_api_error_message( $data, $code, $response )
			);
		}

		if ( is_array( $data ) && isset( $data['error']['message'] ) ) {
			return new \WP_Error(
				'gemini_api_error',
				$this->build_api_error_message( $data, $code, $response )
			);
		}

		if ( empty( $data['candidates'][0]['content']['parts'] ) ) {
			return new \WP_Error(
				'gemini_api_error',
				$this->build_api_error_message( $data, $code, $response )
			);
		}

		$parts     = $data['candidates'][0]['content']['parts'];
		$last_part = end( $parts );

		return isset( $last_part['text'] ) ? $last_part['text'] : '';
	}

	/**
	 * Build a safe, user-facing Gemini error message.
	 *
	 * @param array|null $data     Decoded response body.
	 * @param int        $code     HTTP status code.
	 * @param array      $response Raw WP HTTP response.
	 *
	 * @return string
	 */
	protected function build_api_error_message( $data, $code, $response ) {
		$message = '';

		if ( is_array( $data ) ) {
			if ( isset( $data['error']['message'] ) ) {
				$message = (string) $data['error']['message'];
			} elseif ( isset( $data['error']['status'] ) && is_string( $data['error']['status'] ) ) {
				$message = (string) $data['error']['status'];
			}
		}

		if ( '' === $message ) {
			$message = (string) wp_remote_retrieve_response_message( $response );
		}

		if ( '' === $message ) {
			$message = __( 'Error communicating with the Google Gemini API.', 'mockomatic' );
		}

		$message = sanitize_text_field( $message );
		if ( strlen( $message ) > 400 ) {
			$message = substr( $message, 0, 400 ) . '...';
		}

		if ( $code > 0 ) {
			/* translators: 1: HTTP status code, 2: error message */
			return sprintf( __( 'Gemini API error (%1$d): %2$s', 'mockomatic' ), $code, $message );
		}

		return $message;
	}
}
