<?php
/**
 * Base API class.
 *
 * @package Mockomatic
 */

namespace Mockomatic\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API base class.
 */
abstract class API {

	/**
	 * API key.
	 *
	 * @var string
	 */
	protected $api_key = '';

	/**
	 * Last raw response.
	 *
	 * @var array|\WP_Error
	 */
	protected $last_response;

	/**
	 * Response time in ms.
	 *
	 * @var int
	 */
	protected $response_timer = 0;

	/**
	 * Set API key.
	 *
	 * @param string $key Key.
	 */
	public function set_api_key( $key ) {
		$this->api_key = sanitize_text_field( $key );
	}

	/**
	 * Trim prompt.
	 *
	 * @param string $prompt Prompt.
	 *
	 * @return string
	 */
	public function trim_prompt( $prompt ) {
		return trim( (string) $prompt );
	}

	/**
	 * Perform HTTP request.
	 *
	 * @param string $url    URL.
	 * @param array  $config Config.
	 *
	 * @return array|\WP_Error
	 */
	protected function request( $url, $config ) {
		$default = [
			'timeout' => 300,
			'headers' => [
				'Content-Type' => 'application/json',
			],
		];

		$config = wp_parse_args( $config, $default );

		$start   = microtime( true );
		$result  = wp_remote_post( $url, $config );
		$elapsed = microtime( true ) - $start;

		$this->response_timer = (int) round( $elapsed * 1000 );
		$this->last_response  = $result;

		return $result;
	}

	/**
	 * Get last response.
	 *
	 * @return array|\WP_Error
	 */
	public function get_last_response() {
		return $this->last_response;
	}

	/**
	 * Get response time.
	 *
	 * @return int
	 */
	public function get_response_time() {
		return $this->response_timer;
	}

	/**
	 * Token usage stub.
	 *
	 * @return array
	 */
	public function get_token_usage() {
		return [
			'input_tokens'  => 0,
			'output_tokens' => 0,
		];
	}
}
