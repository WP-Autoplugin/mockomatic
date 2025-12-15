<?php
/**
 * Content generator.
 *
 * @package Mockomatic
 */

namespace Mockomatic;

use Mockomatic\API\OpenAI_API;
use Mockomatic\API\Google_Gemini_API;
use Mockomatic\API\Replicate_Image_API;
use WP_REST_Request;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generator class.
 */
class Generator {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			'mockomatic/v1',
			'/titles',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rest_generate_titles' ],
				'permission_callback' => [ $this, 'can_generate' ],
				'args'                => [
					'posts'           => [
						'type'    => 'integer',
						'default' => 0,
					],
					'pages'           => [
						'type'    => 'integer',
						'default' => 0,
					],
					'generate_images' => [
						'type'    => 'boolean',
						'default' => true,
					],
					'instructions'    => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
					],
					'model'           => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			'mockomatic/v1',
			'/post',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rest_generate_post' ],
				'permission_callback' => [ $this, 'can_generate' ],
				'args'                => [
					'title'                    => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'post_type'                => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'instructions'             => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
					],
					'model'                    => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'generate_image'           => [
						'type'    => 'boolean',
						'default' => false,
					],
					'image_model'              => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'categories'               => [
						'type'     => 'array',
						'required' => false,
					],
					'tags'                     => [
						'type'     => 'array',
						'required' => false,
					],
					'illustration_description' => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
					],
				],
			]
		);

		register_rest_route(
			'mockomatic/v1',
			'/taxonomies',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rest_generate_taxonomies' ],
				'permission_callback' => [ $this, 'can_generate' ],
				'args'                => [
					'items'        => [
						'type'     => 'array',
						'required' => true,
					],
					'categories'   => [
						'type'    => 'boolean',
						'default' => false,
					],
					'tags'         => [
						'type'    => 'boolean',
						'default' => false,
					],
					'instructions' => [
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
					],
					'model'        => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Permission callback.
	 *
	 * @return bool
	 */
	public function can_generate() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Get configured API keys.
	 *
	 * @return array
	 */
	protected function get_api_keys() {
		$options = get_option( Admin::OPTION_API_KEYS, [] );
		return [
			'openai'    => isset( $options['openai'] ) ? $options['openai'] : '',
			'google'    => isset( $options['google'] ) ? $options['google'] : '',
			'replicate' => isset( $options['replicate'] ) ? $options['replicate'] : '',
		];
	}

	/**
	 * Instantiate text API (OpenAI or Gemini) based on model.
	 *
	 * @param string $model Model.
	 *
	 * @return OpenAI_API|Google_Gemini_API|WP_Error
	 */
	protected function get_text_api( $model ) {
		$keys = $this->get_api_keys();

		if ( 0 === strpos( $model, 'gpt-' ) || 0 === strpos( $model, 'chatgpt' ) ) {
			if ( empty( $keys['openai'] ) ) {
				return new WP_Error( 'missing_openai_key', __( 'OpenAI API key is missing.', 'mockomatic' ) );
			}
			$api = new OpenAI_API();
			$api->set_api_key( $keys['openai'] );
			$api->set_model( $model );
			return $api;
		}

		if ( 0 === strpos( $model, 'gemini-' ) ) {
			if ( empty( $keys['google'] ) ) {
				return new WP_Error( 'missing_google_key', __( 'Google Gemini API key is missing.', 'mockomatic' ) );
			}
			$api = new Google_Gemini_API();
			$api->set_api_key( $keys['google'] );
			$api->set_model( $model );
			return $api;
		}

		return new WP_Error(
			'unknown_text_model',
			/* translators: %s: model id */
			sprintf( __( 'Unknown text model: %s', 'mockomatic' ), $model )
		);
	}

	/**
	 * Instantiate Replicate image API.
	 *
	 * @param string $image_model Model.
	 *
	 * @return Replicate_Image_API|WP_Error
	 */
	protected function get_image_api( $image_model ) {
		$keys = $this->get_api_keys();
		if ( empty( $keys['replicate'] ) ) {
			return new WP_Error( 'missing_replicate_key', __( 'Replicate API key is missing.', 'mockomatic' ) );
		}
		if ( empty( $image_model ) ) {
			return new WP_Error( 'missing_image_model', __( 'Image model is not configured.', 'mockomatic' ) );
		}

		$api = new Replicate_Image_API();
		$api->set_api_key( $keys['replicate'] );
		$api->set_model( $image_model );
		return $api;
	}

	/**
	 * Build titles prompt.
	 *
	 * @param int    $posts        Number of posts.
	 * @param int    $pages        Number of pages.
	 * @param string $instructions Extra instructions.
	 * @param bool   $generate_images Whether to request illustration ideas.
	 *
	 * @return string
	 */
	protected function build_titles_prompt( $posts, $pages, $instructions, $generate_images = true ) {
		$site_name        = get_bloginfo( 'name' );
		$site_description = get_bloginfo( 'description' );
		$base             = "You are an assistant that generates dummy content structures for a WordPress site.\n\n";

		if ( '' === trim( $instructions ) ) {
			$instructions = 'Invent a plausible site topic and style yourself (blog, business, portfolio, etc.) and keep everything internally consistent.';
		}

		$base .= "Site name: {$site_name}\n";
		if ( '' !== trim( $site_description ) ) {
			$base .= "Site description: {$site_description}\n";
		}
		$base .= "User instructions: {$instructions}\n\n";
		$base .= "Generate a JSON object with two arrays: \"posts\" and \"pages\".\n";
		$base .= "- Generate exactly {$posts} post titles and {$pages} page titles.\n";
		if ( $generate_images ) {
			$base .= "- For posts, include taxonomy and image cues: {\"title\": \"...\", \"categories\": [\"...\"], \"tags\": [\"...\"], \"illustration_description\": \"...\"}.\n";
		} else {
			$base .= "- For posts, include taxonomy cues only: {\"title\": \"...\", \"categories\": [\"...\"], \"tags\": [\"...\"]}.\n";
		}
		$base .= "- For pages, only include the title object: {\"title\": \"...\"}.\n";
		$base .= "- Titles must be unique and coherent within the same site.\n";
		$base .= "- Categories are broad, reusable site topics (aim for 2–6 across all posts). Keep names short and human-friendly.\n";
		$base .= "- Tags are more specific topics per post (aim for 3–7 tags each) and reuse tags where sensible.\n";
		if ( $generate_images ) {
			$base .= "- \"illustration_description\" is a vivid, single-sentence visual idea for a safe-for-work featured image aligned with the post.\n\n";
		} else {
			$base .= "\n";
		}
		$base .= 'Return ONLY valid JSON, without markdown code fences or commentary.';

		/**
		 * Filter the titles prompt before it is sent to the text model.
		 *
		 * @param string $base             Prompt text.
		 * @param int    $posts            Number of post titles.
		 * @param int    $pages            Number of page titles.
		 * @param string $instructions     Extra user instructions.
		 * @param bool   $generate_images  Whether to request image ideas.
		 * @param string $site_name        Blog name.
		 * @param string $site_description Blog description/tagline.
		 */
		$base = apply_filters( 'mockomatic_titles_prompt', $base, $posts, $pages, $instructions, $generate_images, $site_name, $site_description );

		return $base;
	}

	/**
	 * Build content prompt for a single post/page.
	 *
	 * @param string $title        Title.
	 * @param string $post_type    Post type.
	 * @param string $instructions Extra instructions.
	 *
	 * @return string
	 */
	protected function build_post_prompt( $title, $post_type, $instructions ) {
		$is_page = ( 'page' === $post_type );

		if ( '' === trim( $instructions ) ) {
			$instructions = 'Use clear, natural English and make it look like a realistic website.';
		}

		$base  = "You are generating dummy content for a WordPress {$post_type} titled: \"{$title}\".\n\n";
		$base .= "User instructions for the overall site: {$instructions}\n\n";

		if ( $is_page ) {
			$base .= "Generate content suitable for a page (about, contact, services, etc.).\n";
			$base .= "- Around 4–6 paragraphs with 2–3 headings.\n";
		} else {
			$base .= "Generate content suitable for a blog post.\n";
			$base .= "- At least 10 paragraphs with multiple headings, lists, quotes, and rich structure.\n";
		}

		$base .= "- Format the content as WordPress Gutenberg blocks using HTML comment delimiters.\n";
		$base .= "- Each block starts with <!-- wp:blocktype --> and ends with <!-- /wp:blocktype -->.\n";
		$base .= "- Available blocks: wp:paragraph, wp:heading, wp:list, wp:list-item, wp:quote, wp:code, wp:image, wp:separator, wp:buttons, wp:button, wp:columns, wp:column, wp:group, etc.\n";
		$base .= "- For headings, use level 2 or 3: <!-- wp:heading --> or <!-- wp:heading {\"level\":3} -->.\n";
		$base .= "- For lists, wrap each item: <!-- wp:list-item --><li>Item text</li><!-- /wp:list-item -->.\n";
		$base .= "- For quotes, nest a paragraph inside: <!-- wp:quote --><blockquote class=\"wp-block-quote\"><!-- wp:paragraph --><p>Quote text</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote -->.\n";
		$base .= "- Use proper CSS classes like wp-block-heading, wp-block-list, wp-block-quote, wp-block-code, etc.\n";
		$base .= "- Do NOT include <html>, <body>, <head>, or the title as an <h1>.\n";
		$base .= "- Mix different block types naturally (paragraphs, headings, lists, quotes, code blocks where appropriate).\n\n";
		$base .= "Example format:\n";
		$base .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Section Title</h2>\n<!-- /wp:heading -->\n\n";
		$base .= "<!-- wp:paragraph -->\n<p>This is a paragraph with <strong>bold text</strong> and <em>italic text</em>.</p>\n<!-- /wp:paragraph -->\n\n";
		$base .= "<!-- wp:list -->\n<ul class=\"wp-block-list\"><!-- wp:list-item -->\n<li>First item</li>\n<!-- /wp:list-item -->\n\n<!-- wp:list-item -->\n<li>Second item</li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->\n\n";
		$base .= 'Return ONLY the Gutenberg block markup, without JSON wrappers, markdown code fences, or explanations.';

		/**
		 * Filter the post/page content prompt before it is sent to the text model.
		 *
		 * @param string $base         Prompt text.
		 * @param string $title        Post/page title.
		 * @param string $post_type    Post type.
		 * @param string $instructions Extra user instructions.
		 * @param bool   $is_page      Whether the post type is a page.
		 */
		$base = apply_filters( 'mockomatic_post_prompt', $base, $title, $post_type, $instructions, $is_page );

		return $base;
	}

	/**
	 * Build taxonomy prompt.
	 *
	 * @param array  $items            Items [ [ 'post_id' => int, 'title' => string ], ... ].
	 * @param bool   $create_categories Whether to generate categories.
	 * @param bool   $create_tags       Whether to generate tags.
	 * @param string $instructions     Extra instructions.
	 *
	 * @return string
	 */
	protected function build_taxonomy_prompt( $items, $create_categories, $create_tags, $instructions ) {
		$list = [];
		foreach ( $items as $item ) {
			if ( empty( $item['title'] ) ) {
				continue;
			}
			$list[] = $item['title'];
		}

		$titles_json = wp_json_encode( $list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( '' === trim( $instructions ) ) {
			$instructions = 'Organize posts into logical topic clusters as you see fit.';
		}

		$prompt  = "You are designing categories and tags for a WordPress blog.\n\n";
		$prompt .= "User instructions: {$instructions}\n\n";
		$prompt .= "Here is the list of post titles as a JSON array:\n{$titles_json}\n\n";
		$prompt .= "Create a coherent taxonomy for these posts.\n";
		if ( $create_categories ) {
			$prompt .= "- Choose a sensible number of categories (usually 2–6) and group posts accordingly.\n";
		}
		if ( $create_tags ) {
			$prompt .= "- Choose a sensible number of tags (roughly 5–15) for detailed topics.\n";
		}
		$prompt .= "- Use broad topics for categories and more specific concepts for tags.\n\n";

		$prompt .= "Return a JSON object containing only the sections you generate (categories and/or tags). Example shape:\n{\n";
		if ( $create_categories ) {
			$prompt .= "  \"categories\": [\n";
			$prompt .= "    {\"name\": \"...\", \"slug\": \"...\", \"description\": \"...\", \"posts\": [\"Post title 1\", \"Post title 2\"]}\n";
			$prompt .= '  ]';
			if ( $create_tags ) {
				$prompt .= ",\n";
			} else {
				$prompt .= "\n";
			}
		}
		if ( $create_tags ) {
			$prompt .= "  \"tags\": [\n";
			$prompt .= "    {\"name\": \"...\", \"slug\": \"...\", \"description\": \"\", \"posts\": [\"Post title 1\"]}\n";
			$prompt .= "  ]\n";
		}
		$prompt .= "}\n\n";
		$prompt .= "Rules:\n";
		$prompt .= "- Every title can belong to multiple tags, but usually 1–3 categories total across all posts.\n";
		$prompt .= "- Use URL-friendly slugs (lowercase, hyphens, no special characters).\n";
		$prompt .= "- Descriptions are short (1–2 sentences) and optional.\n\n";
		$prompt .= 'Return ONLY valid JSON without markdown code fences or commentary.';

		/**
		 * Filter the taxonomy prompt before it is sent to the text model.
		 *
		 * @param string $prompt            Prompt text.
		 * @param array  $items             Items [ [ 'post_id' => int, 'title' => string ], ... ].
		 * @param bool   $create_categories Whether to generate categories.
		 * @param bool   $create_tags       Whether to generate tags.
		 * @param string $instructions      Extra user instructions.
		 */
		$prompt = apply_filters( 'mockomatic_taxonomy_prompt', $prompt, $items, $create_categories, $create_tags, $instructions );

		return $prompt;
	}

	/**
	 * Sanitize an array of term names.
	 *
	 * @param array $terms Raw terms.
	 *
	 * @return array
	 */
	protected function sanitize_term_names( $terms ) {
		$cleaned = [];

		if ( ! is_array( $terms ) ) {
			return $cleaned;
		}

		foreach ( $terms as $term ) {
			if ( is_string( $term ) ) {
				$term = sanitize_text_field( $term );
			} elseif ( is_array( $term ) && isset( $term['name'] ) ) {
				$term = sanitize_text_field( $term['name'] );
			} else {
				continue;
			}

			if ( '' !== $term ) {
				$cleaned[] = $term;
			}
		}

		return array_values( array_unique( $cleaned ) );
	}

	/**
	 * Normalize post title payload from AI.
	 *
	 * @param array $items Raw items.
	 * @param bool  $include_illustrations Whether to keep illustration prompts.
	 *
	 * @return array
	 */
	protected function normalize_post_title_items( $items, $include_illustrations = true ) {
		$out = [];

		foreach ( $items as $item ) {
			if ( is_string( $item ) ) {
				$out[] = [
					'title'                    => $item,
					'categories'               => [],
					'tags'                     => [],
					'illustration_description' => $include_illustrations ? '' : '',
				];
				continue;
			}

			if ( ! is_array( $item ) || empty( $item['title'] ) ) {
				continue;
			}

			$categories = isset( $item['categories'] ) ? $this->sanitize_term_names( $item['categories'] ) : [];
			$tags       = isset( $item['tags'] ) ? $this->sanitize_term_names( $item['tags'] ) : [];
			$illust     = ( $include_illustrations && isset( $item['illustration_description'] ) ) ? sanitize_textarea_field( $item['illustration_description'] ) : '';

			$out[] = [
				'title'                    => (string) $item['title'],
				'categories'               => $categories,
				'tags'                     => $tags,
				'illustration_description' => $illust,
			];
		}

		return $out;
	}

	/**
	 * Normalize page title payload from AI.
	 *
	 * @param array $items Raw items.
	 *
	 * @return array
	 */
	protected function normalize_page_title_items( $items ) {
		$out = [];

		foreach ( $items as $item ) {
			if ( is_string( $item ) ) {
				$out[] = [ 'title' => $item ];
				continue;
			}

			if ( is_array( $item ) && ! empty( $item['title'] ) ) {
				$out[] = [ 'title' => (string) $item['title'] ];
			}
		}

		return $out;
	}

	/**
	 * Create or re-use terms and assign them to a post.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @param array  $categories Categories to assign.
	 * @param array  $tags       Tags to assign.
	 */
	protected function assign_terms_to_post( $post_id, $post_type, $categories, $tags ) {
		if ( 'post' !== $post_type ) {
			return;
		}

		if ( ! empty( $categories ) ) {
			foreach ( $categories as $cat_name ) {
				$cat_name = sanitize_text_field( $cat_name );
				if ( '' === $cat_name ) {
					continue;
				}
				$slug   = sanitize_title( $cat_name );
				$exists = term_exists( $cat_name, 'category' );
				$term   = 0;

				if ( 0 === $exists || null === $exists ) {
					$insert = wp_insert_term(
						$cat_name,
						'category',
						[
							'slug' => $slug,
						]
					);
					if ( ! is_wp_error( $insert ) && isset( $insert['term_id'] ) ) {
						$term = (int) $insert['term_id'];
					}
				} else {
					$term = (int) $exists['term_id'];
				}

				if ( $term ) {
					wp_set_post_terms( $post_id, [ $term ], 'category', true );
				}
			}

			// Remove "Uncategorized" if other categories were added.
			$uncat = get_category_by_slug( 'uncategorized' );
			if ( $uncat ) {
				wp_remove_object_terms( $post_id, [ (int) $uncat->term_id ], 'category' );
			}
		}

		if ( ! empty( $tags ) ) {
			foreach ( $tags as $tag_name ) {
				$tag_name = sanitize_text_field( $tag_name );
				if ( '' === $tag_name ) {
					continue;
				}
				$slug   = sanitize_title( $tag_name );
				$exists = term_exists( $tag_name, 'post_tag' );
				$term   = 0;

				if ( 0 === $exists || null === $exists ) {
					$insert = wp_insert_term(
						$tag_name,
						'post_tag',
						[
							'slug' => $slug,
						]
					);
					if ( ! is_wp_error( $insert ) && isset( $insert['term_id'] ) ) {
						$term = (int) $insert['term_id'];
					}
				} else {
					$term = (int) $exists['term_id'];
				}

				if ( $term ) {
					wp_set_post_terms( $post_id, [ $term ], 'post_tag', true );
				}
			}
		}
	}

	/**
	 * REST callback: generate titles.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function rest_generate_titles( WP_REST_Request $request ) {
		$posts           = max( 0, (int) $request->get_param( 'posts' ) );
		$pages           = max( 0, (int) $request->get_param( 'pages' ) );
		$model           = $request->get_param( 'model' );
		$instructions    = (string) $request->get_param( 'instructions' );
		$generate_images = rest_sanitize_boolean( $request->get_param( 'generate_images' ) );

		if ( $posts <= 0 && $pages <= 0 ) {
			return new WP_Error( 'invalid_counts', __( 'You must request at least one post or page.', 'mockomatic' ) );
		}

		$api = $this->get_text_api( $model );
		if ( is_wp_error( $api ) ) {
			return $api;
		}

		$prompt = $this->build_titles_prompt( $posts, $pages, $instructions, $generate_images );
		$result = $api->send_prompt( $prompt );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Strip potential code fences.
		$json = preg_replace( '/^```(json)?\s*/', '', trim( $result ) );
		$json = preg_replace( '/```$/', '', $json );

		$data = json_decode( $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new WP_Error( 'invalid_titles_json', __( 'The AI returned an invalid JSON structure for titles.', 'mockomatic' ) );
		}

		$posts_arr = isset( $data['posts'] ) && is_array( $data['posts'] ) ? $data['posts'] : [];
		$pages_arr = isset( $data['pages'] ) && is_array( $data['pages'] ) ? $data['pages'] : [];

		$posts_arr = array_slice( $this->normalize_post_title_items( $posts_arr, $generate_images ), 0, $posts );
		$pages_arr = array_slice( $this->normalize_page_title_items( $pages_arr ), 0, $pages );

		return rest_ensure_response(
			[
				'posts' => $posts_arr,
				'pages' => $pages_arr,
			]
		);
	}

	/**
	 * REST callback: generate a single post/page.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function rest_generate_post( WP_REST_Request $request ) {
		$title          = (string) $request->get_param( 'title' );
		$post_type      = (string) $request->get_param( 'post_type' );
		$instructions   = (string) $request->get_param( 'instructions' );
		$model          = (string) $request->get_param( 'model' );
		$generate_image = (bool) $request->get_param( 'generate_image' );
		$image_model    = (string) $request->get_param( 'image_model' );
		$categories     = $this->sanitize_term_names( (array) $request->get_param( 'categories' ) );
		$tags           = $this->sanitize_term_names( (array) $request->get_param( 'tags' ) );
		$illustration   = sanitize_textarea_field( (string) $request->get_param( 'illustration_description' ) );

		if ( empty( $title ) ) {
			return new WP_Error( 'missing_title', __( 'Title is required.', 'mockomatic' ) );
		}

		if ( ! in_array( $post_type, [ 'post', 'page' ], true ) ) {
			return new WP_Error( 'invalid_post_type', __( 'Only posts and pages are supported.', 'mockomatic' ) );
		}

		$api = $this->get_text_api( $model );
		if ( is_wp_error( $api ) ) {
			return $api;
		}

		$prompt = $this->build_post_prompt( $title, $post_type, $instructions );
		$html   = $api->send_prompt( $prompt );

		if ( is_wp_error( $html ) ) {
			return $html;
		}

		$content = trim( (string) $html );

		$postarr = [
			'post_title'   => $title,
			'post_content' => wp_kses_post( $content ),
			'post_status'  => 'publish',
			'post_type'    => $post_type,
		];

		$post_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$this->assign_terms_to_post( $post_id, $post_type, $categories, $tags );

		$attachment_id = 0;

		if ( $generate_image ) {
			$image_api = $this->get_image_api( $image_model );
			if ( is_wp_error( $image_api ) ) {
				// Return success but include image error to show in UI.
				return rest_ensure_response(
					[
						'post_id'       => $post_id,
						'title'         => $title,
						'image_error'   => $image_api->get_error_message(),
						'attachment_id' => 0,
					]
				);
			}

			$image_prompt = sprintf(
				/* translators: 1: post type, 2: post title */
				__( 'Featured image for a WordPress %1$s titled "%2$s".', 'mockomatic' ),
				$post_type,
				$title
			);

			if ( '' !== $illustration ) {
				$image_prompt .= ' Visual direction: ' . $illustration;
			}
			if ( '' !== $instructions ) {
				$image_prompt .= ' Site context: ' . $instructions;
			}

			/**
			 * Filter the featured image prompt before it is sent to the image model.
			 *
			 * @param string $image_prompt     Prompt text.
			 * @param string $post_type        Post type.
			 * @param string $title            Post/page title.
			 * @param int    $post_id          Post ID.
			 * @param string $illustration     Illustration description.
			 * @param string $instructions     Extra user instructions.
			 * @param string $image_model      Image model.
			 */
			$image_prompt = apply_filters( 'mockomatic_image_prompt', $image_prompt, $post_type, $title, $post_id, $illustration, $instructions, $image_model );

			$image_bytes = $image_api->send_prompt( $image_prompt );
			if ( ! is_wp_error( $image_bytes ) && $image_bytes ) {
				$upload = wp_upload_bits(
					'mockomatic-' . $post_type . '-' . $post_id . '-' . time() . '.png',
					null,
					$image_bytes
				);
				if ( empty( $upload['error'] ) && ! empty( $upload['file'] ) ) {
					$file_path = $upload['file'];
					$file_name = basename( $file_path );
					$file_type = wp_check_filetype( $file_name, null );

					$attachment = [
						'post_mime_type' => $file_type['type'],
						'post_title'     => sanitize_file_name( $file_name ),
						'post_content'   => '',
						'post_status'    => 'inherit',
					];

					$attachment_id = wp_insert_attachment( $attachment, $file_path, $post_id );
					if ( ! is_wp_error( $attachment_id ) ) {
						if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
							require_once ABSPATH . 'wp-admin/includes/image.php';
						}
						$metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
						wp_update_attachment_metadata( $attachment_id, $metadata );
						set_post_thumbnail( $post_id, $attachment_id );
					} else {
						$attachment_id = 0;
					}
				}
			}
		}

		return rest_ensure_response(
			[
				'post_id'       => $post_id,
				'title'         => $title,
				'post_type'     => $post_type,
				'attachment_id' => $attachment_id,
				'categories'    => $categories,
				'tags'          => $tags,
			]
		);
	}

	/**
	 * REST callback: generate taxonomies and assign terms.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response|WP_Error
	 */
	public function rest_generate_taxonomies( WP_REST_Request $request ) {
		$items        = (array) $request->get_param( 'items' );
		$categories   = rest_sanitize_boolean( $request->get_param( 'categories' ) );
		$tags         = rest_sanitize_boolean( $request->get_param( 'tags' ) );
		$model        = (string) $request->get_param( 'model' );
		$instructions = (string) $request->get_param( 'instructions' );

		if ( ! $categories && ! $tags ) {
			return new WP_Error( 'no_taxonomies_requested', __( 'No categories or tags requested.', 'mockomatic' ) );
		}

		if ( empty( $items ) ) {
			return new WP_Error( 'no_items', __( 'No posts were provided for taxonomy generation.', 'mockomatic' ) );
		}

		$api = $this->get_text_api( $model );
		if ( is_wp_error( $api ) ) {
			return $api;
		}

		$prompt = $this->build_taxonomy_prompt( $items, $categories, $tags, $instructions );
		$result = $api->send_prompt( $prompt );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$json = preg_replace( '/^```(json)?\s*/', '', trim( $result ) );
		$json = preg_replace( '/```$/', '', $json );

		$data = json_decode( $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return new WP_Error( 'invalid_tax_json', __( 'The AI returned an invalid JSON structure for taxonomies.', 'mockomatic' ) );
		}

		$categories_data = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : [];
		$tags_data       = isset( $data['tags'] ) && is_array( $data['tags'] ) ? $data['tags'] : [];

		// Build lookup: title => [post_ids].
		$title_to_ids = [];
		foreach ( $items as $item ) {
			if ( empty( $item['title'] ) || empty( $item['post_id'] ) ) {
				continue;
			}
			$title = (string) $item['title'];
			$id    = (int) $item['post_id'];
			if ( ! isset( $title_to_ids[ $title ] ) ) {
				$title_to_ids[ $title ] = [];
			}
			$title_to_ids[ $title ][] = $id;
		}

		$summary = [
			'categories_created' => 0,
			'tags_created'       => 0,
			'assignments'        => [],
		];

		// Handle categories.
		if ( $categories && ! empty( $categories_data ) ) {
			foreach ( $categories_data as $cat ) {
				if ( empty( $cat['name'] ) ) {
					continue;
				}
				$name        = sanitize_text_field( $cat['name'] );
				$slug        = ! empty( $cat['slug'] ) ? sanitize_title( $cat['slug'] ) : sanitize_title( $name );
				$description = ! empty( $cat['description'] ) ? sanitize_textarea_field( $cat['description'] ) : '';

				$term    = term_exists( $name, 'category' );
				$term_id = 0;
				if ( 0 === $term || null === $term ) {
					$insert = wp_insert_term(
						$name,
						'category',
						[
							'slug'        => $slug,
							'description' => $description,
						]
					);
					if ( ! is_wp_error( $insert ) && isset( $insert['term_id'] ) ) {
						$term_id = (int) $insert['term_id'];
						++$summary['categories_created'];
					}
				} else {
					$term_id = (int) $term['term_id'];
				}

				if ( $term_id && ! empty( $cat['posts'] ) && is_array( $cat['posts'] ) ) {
					foreach ( $cat['posts'] as $title ) {
						$title = (string) $title;
						if ( empty( $title_to_ids[ $title ] ) ) {
							continue;
						}
						foreach ( $title_to_ids[ $title ] as $post_id ) {
							wp_set_post_terms( $post_id, [ $term_id ], 'category', true );
							$summary['assignments'][] = [
								'post_id' => $post_id,
								'term_id' => $term_id,
								'type'    => 'category',
							];
						}
					}
				}
			}
		}

		// Handle tags.
		if ( $tags && ! empty( $tags_data ) ) {
			foreach ( $tags_data as $tag ) {
				if ( empty( $tag['name'] ) ) {
					continue;
				}
				$name        = sanitize_text_field( $tag['name'] );
				$slug        = ! empty( $tag['slug'] ) ? sanitize_title( $tag['slug'] ) : sanitize_title( $name );
				$description = ! empty( $tag['description'] ) ? sanitize_textarea_field( $tag['description'] ) : '';

				$term    = term_exists( $name, 'post_tag' );
				$term_id = 0;
				if ( 0 === $term || null === $term ) {
					$insert = wp_insert_term(
						$name,
						'post_tag',
						[
							'slug'        => $slug,
							'description' => $description,
						]
					);
					if ( ! is_wp_error( $insert ) && isset( $insert['term_id'] ) ) {
						$term_id = (int) $insert['term_id'];
						++$summary['tags_created'];
					}
				} else {
					$term_id = (int) $term['term_id'];
				}

				if ( $term_id && ! empty( $tag['posts'] ) && is_array( $tag['posts'] ) ) {
					foreach ( $tag['posts'] as $title ) {
						$title = (string) $title;
						if ( empty( $title_to_ids[ $title ] ) ) {
							continue;
						}
						foreach ( $title_to_ids[ $title ] as $post_id ) {
							wp_set_post_terms( $post_id, [ $term_id ], 'post_tag', true );
							$summary['assignments'][] = [
								'post_id' => $post_id,
								'term_id' => $term_id,
								'type'    => 'tag',
							];
						}
					}
				}
			}
		}

		return rest_ensure_response( $summary );
	}
}
