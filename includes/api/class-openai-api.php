<?php
/**
 * OpenAI API wrapper.
 *
 * @package Mockomatic
 */

namespace Mockomatic\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OpenAI API class.
 */
class OpenAI_API extends API {

	/**
	 * Model.
	 *
	 * @var string
	 */
	protected $model = 'gpt-4o-mini';

	/**
	 * Temperature.
	 *
	 * @var float
	 */
	protected $temperature = 0.7;

	/**
	 * Max tokens.
	 *
	 * @var int
	 */
	protected $max_tokens = 4096;

	/**
	 * Max completion tokens (for GPT-5 series).
	 *
	 * @var int|null
	 */
	protected $max_completion_tokens = null;

	/**
	 * Endpoint.
	 *
	 * @var string
	 */
	protected $api_url = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Set model.
	 *
	 * @param string $model Model ID.
	 */
	public function set_model( $model ) {
		$this->model = sanitize_text_field( $model );

		$params = [
			'gpt-4o'            => [
				'temperature' => 0.7,
				'max_tokens'  => 4096,
			],
			'chatgpt-4o-latest' => [
				'temperature' => 0.7,
				'max_tokens'  => 16384,
			],
			'gpt-4o-mini'       => [
				'temperature' => 0.7,
				'max_tokens'  => 4096,
			],
			// GPT-5 series uses max_completion_tokens.
			'gpt-5'             => [
				'temperature'           => 1.0,
				'max_completion_tokens' => 128000,
			],
			'gpt-5-mini'        => [
				'temperature'           => 1.0,
				'max_completion_tokens' => 128000,
			],
			'gpt-5-nano'        => [
				'temperature'           => 1.0,
				'max_completion_tokens' => 128000,
			],
		];

		if ( isset( $params[ $this->model ] ) ) {
			$this->temperature = $params[ $this->model ]['temperature'];

			if ( isset( $params[ $this->model ]['max_completion_tokens'] ) ) {
				$this->max_completion_tokens = $params[ $this->model ]['max_completion_tokens'];
				$this->max_tokens            = $this->max_completion_tokens;
			} else {
				$this->max_tokens            = $params[ $this->model ]['max_tokens'];
				$this->max_completion_tokens = null;
			}
		}
	}

	/**
	 * Send prompt.
	 *
	 * @param string $prompt         User prompt.
	 * @param string $system_message System message.
	 *
	 * @return string|\WP_Error
	 */
	public function send_prompt( $prompt, $system_message = '' ) {
		$prompt = $this->trim_prompt( $prompt );

		$messages = [];
		if ( $system_message ) {
			$messages[] = [
				'role'    => 'system',
				'content' => $system_message,
			];
		}
		$messages[] = [
			'role'    => 'user',
			'content' => $prompt,
		];

		$body = [
			'model'    => $this->model,
			'messages' => $messages,
		];

		if ( $this->supports_temperature() && 1.0 !== (float) $this->temperature ) {
			$body['temperature'] = $this->temperature;
		}

		// GPT-5 series models reject max_tokens; send max_completion_tokens instead.
		if ( $this->uses_max_completion_tokens() ) {
			$body['max_completion_tokens'] = ( null !== $this->max_completion_tokens )
				? $this->max_completion_tokens
				: $this->max_tokens;
		} else {
			$body['max_tokens'] = $this->max_tokens;
		}

		/**
		 * Filter the OpenAI API request body before sending.
		 *
		 * @param array  $body           Request body parameters.
		 * @param string $model          Model name.
		 * @param string $prompt         User prompt.
		 * @param string $system_message System message.
		 */
		$body = apply_filters( 'mockomatic_openai_request_body', $body, $this->model, $prompt, $system_message );

		$headers = [
			'Authorization' => 'Bearer ' . $this->api_key,
			'Content-Type'  => 'application/json',
		];

		$response = $this->request(
			$this->api_url,
			[
				'timeout' => 300,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body_raw = wp_remote_retrieve_body( $response );
		$data     = json_decode( $body_raw, true );
		$code     = (int) wp_remote_retrieve_response_code( $response );

		// Surface OpenAI error payloads for non-2xx responses.
		if ( $code >= 400 ) {
			return new \WP_Error(
				'openai_api_error',
				$this->build_api_error_message( $data, $code, $response )
			);
		}

		// Handle OpenAI error objects even if response code is unexpected.
		if ( is_array( $data ) && isset( $data['error']['message'] ) ) {
			return new \WP_Error(
				'openai_api_error',
				$this->build_api_error_message( $data, $code, $response )
			);
		}

		if ( empty( $data['choices'][0]['message']['content'] ) ) {
			return new \WP_Error(
				'openai_api_error',
				$this->build_api_error_message( $data, $code, $response )
			);
		}

		return $data['choices'][0]['message']['content'];
	}

	/**
	 * Whether the current model expects max_completion_tokens.
	 *
	 * @return bool
	 */
	protected function uses_max_completion_tokens() {
		return 0 === strpos( $this->model, 'gpt-5' );
	}

	/**
	 * Whether the current model supports custom temperature.
	 *
	 * @return bool
	 */
	protected function supports_temperature() {
		return ! $this->uses_max_completion_tokens();
	}

	/**
	 * Build a safe, user-facing OpenAI error message.
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
			} elseif ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
				$message = $data['error'];
			}
		}

		if ( '' === $message ) {
			$message = (string) wp_remote_retrieve_response_message( $response );
		}

		if ( '' === $message ) {
			$message = __( 'Error communicating with the OpenAI API.', 'mockomatic' );
		}

		$message = sanitize_text_field( $message );
		if ( strlen( $message ) > 400 ) {
			$message = substr( $message, 0, 400 ) . '...';
		}

		if ( $code > 0 ) {
			/* translators: 1: HTTP status code, 2: error message */
			return sprintf( __( 'OpenAI API error (%1$d): %2$s', 'mockomatic' ), $code, $message );
		}

		return $message;
	}
}
