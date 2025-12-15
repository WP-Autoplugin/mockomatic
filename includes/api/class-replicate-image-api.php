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
	 * Poll interval in seconds when waiting on async predictions.
	 *
	 * @var int
	 */
	protected $poll_interval = 2;

	/**
	 * Maximum additional poll duration in seconds.
	 *
	 * @var int
	 */
	protected $poll_timeout = 60;

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
				// Replicate synchronous wait limit is 60 seconds.
				'timeout' => 65,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'replicate_api_error', __( 'Invalid response from the Replicate API.', 'mockomatic' ) );
		}

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

		if ( $this->should_poll_status( isset( $data['status'] ) ? $data['status'] : '' ) && empty( $data['output'] ) ) {
			$data = $this->poll_prediction( $data, $response, $headers );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
		}

		$output = isset( $data['output'] ) ? $data['output'] : '';
		if ( empty( $output ) ) {
			if ( $this->should_poll_status( isset( $data['status'] ) ? $data['status'] : '' ) ) {
				return new \WP_Error( 'replicate_timeout', __( 'Replicate prediction did not finish in time.', 'mockomatic' ) );
			}
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
	 * Determine if a prediction status warrants polling.
	 *
	 * @param mixed $status Status string.
	 *
	 * @return bool
	 */
	protected function should_poll_status( $status ) {
		if ( ! is_string( $status ) || '' === $status ) {
			return false;
		}
		$status = strtolower( $status );
		return in_array( $status, [ 'starting', 'processing' ], true );
	}

	/**
	 * Poll a pending prediction until completion or timeout.
	 *
	 * @param array $data     Initial prediction payload.
	 * @param array $response Initial HTTP response.
	 * @param array $headers  Request headers.
	 *
	 * @return array|\WP_Error
	 */
	protected function poll_prediction( $data, $response, $headers ) {
		$poll_url = '';
		if ( isset( $data['urls']['get'] ) && is_string( $data['urls']['get'] ) ) {
			$poll_url = $data['urls']['get'];
		}
		if ( '' === $poll_url ) {
			$location = wp_remote_retrieve_header( $response, 'location' );
			if ( is_string( $location ) && '' !== $location ) {
				$poll_url = $location;
			}
		}
		if ( '' === $poll_url ) {
			return $data;
		}

		$poll_interval = (int) apply_filters( 'mockomatic_replicate_poll_interval', $this->poll_interval, $data );
		$poll_timeout  = (int) apply_filters( 'mockomatic_replicate_poll_timeout', $this->poll_timeout, $data );

		$poll_interval = max( 1, $poll_interval );
		$poll_timeout  = max( 1, $poll_timeout );
		$deadline      = time() + $poll_timeout;
		$attempt       = 0;

		while ( $this->should_poll_status( isset( $data['status'] ) ? $data['status'] : '' ) && time() < $deadline ) {
			++$attempt;

			$poll_response = $this->request(
				$poll_url,
				[
					'method'  => 'GET',
					'timeout' => 30,
					'headers' => $headers,
				]
			);

			if ( is_wp_error( $poll_response ) ) {
				return $poll_response;
			}

			$poll_data = json_decode( wp_remote_retrieve_body( $poll_response ), true );
			$poll_code = (int) wp_remote_retrieve_response_code( $poll_response );

			if ( $poll_code >= 400 ) {
				return new \WP_Error(
					'replicate_api_error',
					$this->build_api_error_message( is_array( $poll_data ) ? $poll_data : null, $poll_code, $poll_response )
				);
			}

			if ( ! is_array( $poll_data ) ) {
				return new \WP_Error( 'replicate_api_error', __( 'Invalid response from the Replicate API while polling.', 'mockomatic' ) );
			}

			if ( isset( $poll_data['error'] ) && $poll_data['error'] ) {
				return new \WP_Error(
					'replicate_api_error',
					$this->build_api_error_message( $poll_data, $poll_code, $poll_response )
				);
			}

			$data = $poll_data;
			if ( ! empty( $data['output'] ) ) {
				return $data;
			}

			if ( ! $this->should_poll_status( isset( $data['status'] ) ? $data['status'] : '' ) ) {
				break;
			}

			sleep( $poll_interval );
		}

		return $data;
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
