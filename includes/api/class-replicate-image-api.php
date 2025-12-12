<?php
/**
 * Replicate Image API wrapper.
 *
 * @package Mockomatic
 */

namespace Mockomatic\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replicate image generation.
 */
class Replicate_Image_API extends API {

	/**
	 * Model slug.
	 *
	 * @var string
	 */
	protected $model = '';

	/**
	 * Base URL.
	 *
	 * @var string
	 */
	protected $api_url = 'https://api.replicate.com/v1/models';

	/**
	 * Set model.
	 *
	 * @param string $model Model.
	 */
	public function set_model( $model ) {
		$this->model = sanitize_text_field( $model );
	}

	/**
	 * Send prompt for image generation.
	 *
	 * @param string $prompt Prompt.
	 *
	 * @return string|\WP_Error Raw image bytes or WP_Error.
	 */
	public function send_prompt( $prompt ) {
		$prompt = $this->trim_prompt( $prompt );

		if ( ! $this->model ) {
			return new \WP_Error( 'replicate_model_missing', __( 'Replicate model is not configured.', 'mockomatic' ) );
		}

		$url = trailingslashit( $this->api_url ) . $this->model . '/predictions';

		$body = [
			'input' => [
				'prompt' => $prompt,
			],
		];

		/**
		 * Filter the Replicate API request body before sending.
		 *
		 * @param array  $body   Request body parameters.
		 * @param string $model  Model name/path.
		 * @param string $prompt User prompt.
		 */
		$body = apply_filters( 'mockomatic_replicate_request_body', $body, $this->model, $prompt );

		$headers = [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $this->api_key,
			'Prefer'        => 'wait',
		];

		$response = $this->request(
			$url,
			[
				// Todo: implement polling for long-running requests.
				// 60 second is the maximum wait time for Replicate, then it returns a 202 and we would need to poll.
				'timeout' => 60,
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
				'replicate_api_error',
				$this->build_api_error_message( $data, $code, $response )
			);
		}

		if ( isset( $data['error'] ) && $data['error'] ) {
			return new \WP_Error(
				'replicate_api_error',
				$this->build_api_error_message( $data, $code, $response )
			);
		}

		$output = isset( $data['output'] ) ? $data['output'] : '';
		if ( empty( $output ) ) {
			return new \WP_Error( 'replicate_no_output', __( 'Replicate API returned no output.', 'mockomatic' ) );
		}

		if ( is_array( $output ) ) {
			$output = reset( $output );
		}

		$image_response = wp_remote_get( $output );
		if ( is_wp_error( $image_response ) ) {
			return $image_response;
		}

		$image_code = (int) wp_remote_retrieve_response_code( $image_response );
		if ( 200 !== $image_code ) {
			$image_message = (string) wp_remote_retrieve_response_message( $image_response );
			$image_message = sanitize_text_field( $image_message );
			if ( '' === $image_message ) {
				$image_message = __( 'Failed to download generated image from Replicate.', 'mockomatic' );
			}
			/* translators: 1: HTTP status code, 2: error message */
			return new \WP_Error( 'replicate_download_error', sprintf( __( 'Replicate image download error (%1$d): %2$s', 'mockomatic' ), $image_code, $image_message ) );
		}

		return wp_remote_retrieve_body( $image_response );
	}

	/**
	 * Build a safe, user-facing Replicate error message.
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
			if ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
				$message = (string) $data['error'];
			} elseif ( isset( $data['error']['detail'] ) ) {
				$message = (string) $data['error']['detail'];
			} elseif ( isset( $data['detail'] ) ) {
				$message = (string) $data['detail'];
			}
		}

		if ( '' === $message ) {
			$message = (string) wp_remote_retrieve_response_message( $response );
		}

		if ( '' === $message ) {
			$message = __( 'Error communicating with the Replicate API.', 'mockomatic' );
		}

		$message = sanitize_text_field( $message );
		if ( strlen( $message ) > 400 ) {
			$message = substr( $message, 0, 400 ) . '...';
		}

		if ( $code > 0 ) {
			/* translators: 1: HTTP status code, 2: error message */
			return sprintf( __( 'Replicate API error (%1$d): %2$s', 'mockomatic' ), $code, $message );
		}

		return $message;
	}
}
