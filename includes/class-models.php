<?php
/**
 * Models configuration.
 *
 * @package Mockomatic
 */

namespace Mockomatic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Models class.
 *
 * Centralized configuration for all AI models.
 */
class Models {

	/**
	 * Get all text models (Google + OpenAI).
	 *
	 * @return array Associative array of model ID => label.
	 */
	public static function get_text_models() {
		$google = self::get_google_models();
		$openai = self::get_openai_models();

		$models = [];
		foreach ( $google as $key => $label ) {
			$models[ $key ] = $label;
		}
		foreach ( $openai as $key => $label ) {
			$models[ $key ] = $label;
		}

		return $models;
	}

	/**
	 * Get Google text models.
	 *
	 * @return array Associative array of model ID => label.
	 */
	public static function get_google_models() {
		return [
			'gemini-3-pro-preview'  => 'Gemini 3 Pro Preview',
			'gemini-2.5-pro'        => 'Gemini 2.5 Pro',
			'gemini-2.5-flash'      => 'Gemini 2.5 Flash',
			'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash Lite',
			'gemma-3-27b-it'        => 'Gemma 3 27B',
		];
	}

	/**
	 * Get OpenAI text models.
	 *
	 * @return array Associative array of model ID => label.
	 */
	public static function get_openai_models() {
		return [
			'gpt-5.1'           => 'GPT-5.1',
			'gpt-5'             => 'GPT-5',
			'gpt-5-mini'        => 'GPT-5 mini',
			'gpt-5-nano'        => 'GPT-5 nano',
			'gpt-5-chat-latest' => 'ChatGPT-5-latest',
			'gpt-4.5-preview'   => 'GPT-4.5 Preview',
			'gpt-4.1'           => 'GPT-4.1',
			'gpt-4.1-mini'      => 'GPT-4.1 mini',
			'gpt-4.1-nano'      => 'GPT-4.1 nano',
			'gpt-4o'            => 'GPT-4o',
			'gpt-4o-mini'       => 'GPT-4o mini',
			'chatgpt-4o-latest' => 'ChatGPT-4o-latest',
		];
	}

	/**
	 * Get Replicate image models.
	 *
	 * @return array Associative array of model ID => label.
	 */
	public static function get_replicate_models() {
		return [
			'google/nano-banana-pro'           => 'Gemini 3 Pro Image (Nano-Banana Pro)',
			'google/gemini-2.5-flash-image'    => 'Gemini 2.5 Flash Image (Nano-Banana)',
			'google/imagen-4'                  => 'Imagen 4',
			'google/imagen-4-ultra'            => 'Imagen 4 Ultra',
			'google/imagen-4-fast'             => 'Imagen 4 Fast',
			'google/imagen-3'                  => 'Imagen 3',
			'google/imagen-3-fast'             => 'Imagen 3 Fast',
			'black-forest-labs/flux-1.1-pro'   => 'Flux 1.1 Pro',
			'black-forest-labs/flux-dev'       => 'Flux Dev',
			'black-forest-labs/flux-schnell'   => 'Flux Schnell',
			'black-forest-labs/flux-pro'       => 'Flux Pro',
			'recraft-ai/recraft-v3'            => 'Recraft v3',
			'ideogram-ai/ideogram-v3-turbo'    => 'Ideogram v3 Turbo',
			'ideogram-ai/ideogram-v3-quality'  => 'Ideogram v3 Quality',
			'ideogram-ai/ideogram-v3-balanced' => 'Ideogram v3 Balanced',
			'bytedance/seedream-4.5'           => 'Seedream 4.5',
		];
	}

	/**
	 * Get the default text model ID.
	 *
	 * @return string Default model ID.
	 */
	public static function get_default_text_model() {
		return 'gpt-4o-mini';
	}

	/**
	 * Get the default image model ID.
	 *
	 * @return string Default model ID.
	 */
	public static function get_default_image_model() {
		return 'black-forest-labs/flux-dev';
	}

	/**
	 * Check if a model is from Google.
	 *
	 * @param string $model Model ID.
	 * @return bool
	 */
	public static function is_google_model( $model ) {
		return array_key_exists( $model, self::get_google_models() );
	}

	/**
	 * Check if a model is from OpenAI.
	 *
	 * @param string $model Model ID.
	 * @return bool
	 */
	public static function is_openai_model( $model ) {
		return array_key_exists( $model, self::get_openai_models() );
	}
}
