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

		// Bootstrap after plugins_loaded so constants are defined.
		add_action( 'init', [ __CLASS__, 'bootstrap' ], 5 );
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
