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
		add_options_page(
			__( 'Mockomatic', 'mockomatic' ),
			__( 'Mockomatic', 'mockomatic' ),
			'manage_options',
			'mockomatic-settings',
			[ $this, 'render_settings_page' ]
		);

		add_management_page(
			__( 'Mockomatic', 'mockomatic' ),
			__( 'Mockomatic', 'mockomatic' ),
			'edit_posts',
			'mockomatic-generate',
			[ $this, 'render_generate_page' ]
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
		$options = get_option( self::OPTION_SETTINGS, [] );
		$current = isset( $options['default_text_model'] ) ? $options['default_text_model'] : Models::get_default_text_model();
		$models  = Models::get_text_models();
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
		$generate_url = admin_url( 'tools.php?page=mockomatic-generate' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mockomatic Settings', 'mockomatic' ); ?></h1>
			<p>
				<a href="<?php echo esc_url( $generate_url ); ?>"><?php esc_html_e( 'Go to Mockomatic Generator', 'mockomatic' ); ?></a>
			</p>
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
		$settings_url = admin_url( 'options-general.php?page=mockomatic-settings' );

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

		if ( in_array( $hook, [ 'tools_page_mockomatic-generate', 'toplevel_page_mockomatic-generate' ], true ) ) {
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
					'restUrl'       => esc_url_raw( rest_url( 'mockomatic/v1' ) ),
					'nonce'         => wp_create_nonce( 'wp_rest' ),
					'adminEditBase' => esc_url_raw( admin_url( 'post.php' ) ),
					'siteUrl'       => esc_url_raw( home_url( '/' ) ),
					'defaults'      => [
						'posts'        => 5,
						'pages'        => 2,
						'categories'   => true,
						'tags'         => true,
						'images'       => false,
						'instructions' => '',
						'textModel'    => $default_text,
						'imageModel'   => $default_img,
					],
					'textModels'    => Models::get_text_models(),
					'imageModels'   => Models::get_replicate_models(),
				]
			);
		}
	}
}
