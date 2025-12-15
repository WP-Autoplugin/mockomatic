<?php
/**
 * Plugin Name: Mockomatic - AI Demo Content Generator
 * Plugin URI:  https://github.com/WP-Autoplugin/mockomatic
 * Description: Generate realistic demo content (posts, pages, taxonomies, featured images) using OpenAI, Google Gemini and Replicate.
 * Version:     0.1.1
 * Author:      Balázs Piller
 * Author URI:  https://wp-autoplugin.com/
 * Text Domain: mockomatic
 * Domain Path: /languages
 *
 * @package Mockomatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MOCKOMATIC_VERSION', '0.1.1' );
define( 'MOCKOMATIC_DIR', plugin_dir_path( __FILE__ ) );
define( 'MOCKOMATIC_URL', plugin_dir_url( __FILE__ ) );
define( 'MOCKOMATIC_BASENAME', plugin_basename( __FILE__ ) );

require_once MOCKOMATIC_DIR . 'vendor/autoload.php';

Mockomatic\Plugin::init();
