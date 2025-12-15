<?php
/**
 * Core Plugin bootstrap.
 *
 * @package Mockomatic
 */

namespace Mockomatic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin class.
 */
class Plugin {

	/**
	 * Admin instance.
	 *
	 * @var Admin
	 */
	protected static $admin;

	/**
	 * Generator instance.
	 *
	 * @var Generator
	 */
	protected static $generator;

	/**
	 * Initialize the plugin.
	 */
	public static function init() {
		// Load textdomain.
		add_action( 'plugins_loaded', [ __CLASS__, 'load_textdomain' ] );

		add_filter( 'plugin_action_links_' . MOCKOMATIC_BASENAME, [ __CLASS__, 'add_plugin_action_links' ] );

		// Bootstrap after plugins_loaded so constants are defined.
		add_action( 'init', [ __CLASS__, 'bootstrap' ], 5 );
	}

	/**
	 * Add plugin action links.
	 *
	 * @param string[] $links Action links.
	 *
	 * @return string[]
	 */
	public static function add_plugin_action_links( $links ) {
		$generate_url = admin_url( 'tools.php?page=mockomatic-generate' );
		$settings_url = admin_url( 'options-general.php?page=mockomatic-settings' );

		$mockomatic_links = [
			'<a href="' . esc_url( $generate_url ) . '">' . esc_html__( 'Generate', 'mockomatic' ) . '</a>',
			'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'mockomatic' ) . '</a>',
		];

		return array_merge( $mockomatic_links, $links );
	}

	/**
	 * Load plugin textdomain.
	 */
	public static function load_textdomain() {
		load_plugin_textdomain(
			'mockomatic',
			false,
			dirname( MOCKOMATIC_BASENAME ) . '/languages'
		);
	}

	/**
	 * Bootstrap plugin components.
	 */
	public static function bootstrap() {
		self::$admin     = new Admin();
		self::$generator = new Generator();
	}

	/**
	 * Get Admin instance.
	 *
	 * @return Admin
	 */
	public static function admin() {
		return self::$admin;
	}

	/**
	 * Get Generator instance.
	 *
	 * @return Generator
	 */
	public static function generator() {
		return self::$generator;
	}
}
