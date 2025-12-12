<?php
/**
 * Admin UI and settings.
 *
 * @package Mockomatic
 */

namespace Mockomatic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class.
 */
class Admin {

	/**
	 * Option name for API keys.
	 *
	 * @var string
	 */
	const OPTION_API_KEYS = 'mockomatic_api_keys';

	/**
	 * Option name for general settings.
	 *
	 * @var string
	 */
	const OPTION_SETTINGS = 'mockomatic_settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menus' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Register admin menus.
	 */
	public function register_menus() {
		$capability = 'manage_options';

		add_menu_page(
			__( 'Mockomatic', 'mockomatic' ),
			__( 'Mockomatic', 'mockomatic' ),
			$capability,
			'mockomatic-generate',
			[ $this, 'render_generate_page' ],
			'data:image/svg+xml;base64,' . base64_encode( '<svg width="24" height="24" fill="currentColor" viewBox="0 0 1000 1000" xmlns="http://www.w3.org/2000/svg"><path d="M 500 100C 500 100 500 100 500 100C 541 100 575 134 575 175C 575 207 554 235 525 246C 525 246 525 300 525 300C 525 300 650 300 650 300C 738 300 811 365 823 450C 823 450 825 450 825 450C 866 450 900 484 900 525C 900 525 900 625 900 625C 900 666 866 700 825 700C 825 700 823 700 823 700C 811 785 738 850 650 850C 650 850 350 850 350 850C 262 850 189 785 177 700C 177 700 175 700 175 700C 134 700 100 666 100 625C 100 625 100 525 100 525C 100 484 134 450 175 450C 175 450 177 450 177 450C 189 365 262 300 350 300C 350 300 475 300 475 300C 475 300 475 246 475 246C 446 235 425 207 425 175C 425 134 459 100 500 100M 500 150C 500 150 500 150 500 150C 486 150 475 161 475 175C 475 188 484 198 497 200C 498 200 499 200 500 200C 501 200 502 200 503 200C 516 198 525 188 525 175C 525 161 514 150 500 150M 350 350C 350 350 350 350 350 350C 280 350 225 405 225 475C 225 475 225 675 225 675C 225 745 280 800 350 800C 350 800 650 800 650 800C 720 800 775 745 775 675C 775 675 775 475 775 475C 775 405 720 350 650 350C 650 350 504 350 504 350C 501 350 499 350 496 350C 496 350 350 350 350 350M 352 400C 352 400 352 400 352 400C 407 400 452 445 452 500C 452 555 407 600 352 600C 297 600 252 555 252 500C 252 445 297 400 352 400M 650 400C 650 400 650 400 650 400C 705 400 750 445 750 500C 750 555 705 600 650 600C 595 600 550 555 550 500C 550 445 595 400 650 400M 352 450C 352 450 352 450 352 450C 325 450 302 472 302 500C 302 528 325 550 352 550C 380 550 402 528 402 500C 402 472 380 450 352 450M 650 450C 650 450 650 450 650 450C 622 450 600 472 600 500C 600 528 622 550 650 550C 678 550 700 528 700 500C 700 472 678 450 650 450M 175 500C 175 500 175 500 175 500C 161 500 150 511 150 525C 150 525 150 625 150 625C 150 639 161 650 175 650C 175 650 175 500 175 500M 825 500C 825 500 825 650 825 650C 839 650 850 639 850 625C 850 625 850 525 850 525C 850 511 839 500 825 500M 425 675C 425 675 575 675 575 675C 584 675 592 680 597 687C 601 695 601 705 597 713C 592 720 584 725 575 725C 575 725 425 725 425 725C 416 725 408 720 403 713C 399 705 399 695 403 687C 408 680 416 675 425 675"/></svg>' ),
			58
		);

		add_submenu_page(
			'mockomatic-generate',
			__( 'Generate Content', 'mockomatic' ),
			__( 'Generate Content', 'mockomatic' ),
			$capability,
			'mockomatic-generate',
			[ $this, 'render_generate_page' ]
		);

		add_submenu_page(
			'mockomatic-generate',
			__( 'Settings', 'mockomatic' ),
			__( 'Settings', 'mockomatic' ),
			$capability,
			'mockomatic-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		// API keys.
		register_setting(
			'mockomatic_settings_group',
			self::OPTION_API_KEYS,
			[
				'sanitize_callback' => [ $this, 'sanitize_api_keys' ],
			]
		);

		// General settings (default models).
		register_setting(
			'mockomatic_settings_group',
			self::OPTION_SETTINGS,
			[
				'sanitize_callback' => [ $this, 'sanitize_general_settings' ],
			]
		);

		// Settings section.
		add_settings_section(
			'mockomatic_api_section',
			__( 'API Keys', 'mockomatic' ),
			function () {
				echo '<p>' . esc_html__( 'Enter your API keys for OpenAI, Google Gemini and Replicate.', 'mockomatic' ) . '</p>';
			},
			'mockomatic-settings'
		);

		add_settings_field(
			'mockomatic_openai_key',
			__( 'OpenAI API Key', 'mockomatic' ),
			[ $this, 'field_openai_key' ],
			'mockomatic-settings',
			'mockomatic_api_section'
		);

		add_settings_field(
			'mockomatic_google_key',
			__( 'Google Gemini API Key', 'mockomatic' ),
			[ $this, 'field_google_key' ],
			'mockomatic-settings',
			'mockomatic_api_section'
		);

		add_settings_field(
			'mockomatic_replicate_key',
			__( 'Replicate API Key', 'mockomatic' ),
			[ $this, 'field_replicate_key' ],
			'mockomatic-settings',
			'mockomatic_api_section'
		);

		// Default models section.
		add_settings_section(
			'mockomatic_models_section',
			__( 'Default Models', 'mockomatic' ),
			function () {
				echo '<p>' . esc_html__( 'Choose default models for text and image generation.', 'mockomatic' ) . '</p>';
			},
			'mockomatic-settings'
		);

		add_settings_field(
			'mockomatic_default_text_model',
			__( 'Default Text Model', 'mockomatic' ),
			[ $this, 'field_default_text_model' ],
			'mockomatic-settings',
			'mockomatic_models_section'
		);

		add_settings_field(
			'mockomatic_default_image_model',
			__( 'Default Image Model (Replicate)', 'mockomatic' ),
			[ $this, 'field_default_image_model' ],
			'mockomatic-settings',
			'mockomatic_models_section'
		);
	}

	/**
	 * Sanitize API keys.
	 *
	 * @param array $input Raw input.
	 *
	 * @return array
	 */
	public function sanitize_api_keys( $input ) {
		$output = [
			'openai'    => '',
			'google'    => '',
			'replicate' => '',
		];

		if ( is_array( $input ) ) {
			$output['openai']    = isset( $input['openai'] ) ? sanitize_text_field( $input['openai'] ) : '';
			$output['google']    = isset( $input['google'] ) ? sanitize_text_field( $input['google'] ) : '';
			$output['replicate'] = isset( $input['replicate'] ) ? sanitize_text_field( $input['replicate'] ) : '';
		}

		return $output;
	}

	/**
	 * Sanitize general settings.
	 *
	 * @param array $input Raw input.
	 *
	 * @return array
	 */
	public function sanitize_general_settings( $input ) {
		$output = [
			'default_text_model'  => Models::get_default_text_model(),
			'default_image_model' => Models::get_default_image_model(),
		];

		if ( is_array( $input ) ) {
			if ( isset( $input['default_text_model'] ) ) {
				$output['default_text_model'] = sanitize_text_field( $input['default_text_model'] );
			}
			if ( isset( $input['default_image_model'] ) ) {
				$output['default_image_model'] = sanitize_text_field( $input['default_image_model'] );
			}
		}

		return $output;
	}

	/**
	 * Settings field: OpenAI key.
	 */
	public function field_openai_key() {
		$options = get_option( self::OPTION_API_KEYS, [] );
		$value   = isset( $options['openai'] ) ? $options['openai'] : '';
		echo '<input type="password" class="regular-text" name="' . esc_attr( self::OPTION_API_KEYS ) . '[openai]" value="' . esc_attr( $value ) . '" autocomplete="off" />';
	}

	/**
	 * Settings field: Google key.
	 */
	public function field_google_key() {
		$options = get_option( self::OPTION_API_KEYS, [] );
		$value   = isset( $options['google'] ) ? $options['google'] : '';
		echo '<input type="password" class="regular-text" name="' . esc_attr( self::OPTION_API_KEYS ) . '[google]" value="' . esc_attr( $value ) . '" autocomplete="off" />';
	}

	/**
	 * Settings field: Replicate key.
	 */
	public function field_replicate_key() {
		$options = get_option( self::OPTION_API_KEYS, [] );
		$value   = isset( $options['replicate'] ) ? $options['replicate'] : '';
		echo '<input type="password" class="regular-text" name="' . esc_attr( self::OPTION_API_KEYS ) . '[replicate]" value="' . esc_attr( $value ) . '" autocomplete="off" />';
	}

	/**
	 * Settings field: Default text model.
	 */
	public function field_default_text_model() {
		$options    = get_option( self::OPTION_SETTINGS, [] );
		$current    = isset( $options['default_text_model'] ) ? $options['default_text_model'] : Models::get_default_text_model();
		$models     = Models::get_text_models();
		echo '<select name="' . esc_attr( self::OPTION_SETTINGS ) . '[default_text_model]">';
		foreach ( $models as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Applies as default on the Generate Content page (can be overridden).', 'mockomatic' ) . '</p>';
	}

	/**
	 * Settings field: Default image model.
	 */
	public function field_default_image_model() {
		$options = get_option( self::OPTION_SETTINGS, [] );
		$current = isset( $options['default_image_model'] ) ? $options['default_image_model'] : Models::get_default_image_model();
		$models  = Models::get_replicate_models();
		echo '<select name="' . esc_attr( self::OPTION_SETTINGS ) . '[default_image_model]">';
		foreach ( $models as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Replicate model used for generating featured images.', 'mockomatic' ) . '</p>';
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mockomatic' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mockomatic Settings', 'mockomatic' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'mockomatic_settings_group' );
				do_settings_sections( 'mockomatic-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render Generate Content page.
	 */
	public function render_generate_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mockomatic' ) );
		}

		?>
		<div class="wrap mockomatic-wrap">
			<h1><?php esc_html_e( 'Mockomatic', 'mockomatic' ); ?></h1>
			<div id="mockomatic-generate-root"></div>
			<noscript>
				<p><?php esc_html_e( 'Mockomatic requires JavaScript to run. Please enable JavaScript to generate content.', 'mockomatic' ); ?></p>
			</noscript>
		</div>
		<?php
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current screen hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'mockomatic' ) ) {
			return;
		}

		wp_enqueue_style(
			'mockomatic-admin-css',
			MOCKOMATIC_URL . 'assets/admin/css/mockomatic.css',
			[],
			MOCKOMATIC_VERSION
		);

		if ( 'toplevel_page_mockomatic-generate' === $hook ) {
			$asset_path = MOCKOMATIC_DIR . 'assets/admin/js/mockomatic-generate.asset.php';
			$asset_data = [
				'dependencies' => [],
				'version'      => MOCKOMATIC_VERSION,
			];

			if ( file_exists( $asset_path ) ) {
				$asset_data = include $asset_path;
			}

			$settings     = get_option( self::OPTION_SETTINGS, [] );
			$default_text = isset( $settings['default_text_model'] ) ? $settings['default_text_model'] : Models::get_default_text_model();
			$default_img  = isset( $settings['default_image_model'] ) ? $settings['default_image_model'] : Models::get_default_image_model();

			wp_enqueue_style( 'wp-components' );
			wp_enqueue_style( 'dashicons' );

			wp_enqueue_script(
				'mockomatic-generate-js',
				MOCKOMATIC_URL . 'assets/admin/js/mockomatic-generate.js',
				$asset_data['dependencies'],
				$asset_data['version'],
				true
			);

			wp_localize_script(
				'mockomatic-generate-js',
				'MockomaticSettings',
				[
					'restUrl' => esc_url_raw( rest_url( 'mockomatic/v1' ) ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
					'adminEditBase' => esc_url_raw( admin_url( 'post.php' ) ),
					'siteUrl' => esc_url_raw( home_url( '/' ) ),
					'defaults' => [
						'posts'        => 5,
						'pages'        => 2,
						'categories'   => true,
						'tags'         => true,
						'images'       => false,
						'instructions' => '',
						'textModel'    => $default_text,
						'imageModel'   => $default_img,
					],
					'textModels' => Models::get_text_models(),
					'imageModels' => Models::get_replicate_models(),
				]
			);
		}
	}
}
