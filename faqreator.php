<?php
/**
 * FAQreator – Guided FAQ Generator
 *
 * @package           RafyCo\FAQreator
 * @wordpress-plugin
 * Plugin Name:       FAQreator
 * Plugin URI:        https://rafy.site/projects/faqreator
 * Description:       Guided AI-based FAQ generator for your custom post types.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Rafy
 * Author URI:        https://rafy.site
 * Text Domain:       faqreator
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI:        https://rafy.site/projects/faqreator
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class FAQreator {
	private static $instance = null;

	private $api_key, $auth_token, $post_type_check, $post_type_question;
	private $meta_key, $default_max_items, $model, $max_tokens;
	private $temperature, $timeout, $error_messages;

	public static function get_instance(): FAQreator {
		if ( null === self::$instance ) {
			self::$instance = new self();
			load_plugin_textdomain( 'faqreator', false, basename( __DIR__ ) . '/languages' );
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_settings();
		$this->add_hooks();
	}

	private function load_settings(): void
    {
        $opts = get_option('faqreator_settings', []);

        $this->api_key            = !empty($opts['openai_api_key']) ? $opts['openai_api_key'] : '';
        $this->auth_token         = !empty($opts['auth_token']) ? $opts['auth_token'] : '';
        $this->post_type_check    = !empty($opts['post_type_check']) ? $opts['post_type_check'] : 'check';
        $this->post_type_question = !empty($opts['post_type_question']) ? $opts['post_type_question'] : 'question';
        $this->meta_key           = !empty($opts['meta_key']) ? $opts['meta_key'] : 'faq_check';

        $this->default_max_items = (isset($opts['default_max_items']) && is_numeric($opts['default_max_items']))
            ? intval($opts['default_max_items'])
            : 5;

        $this->model = !empty($opts['model']) ? $opts['model'] : 'gpt-4o-mini';

        $this->max_tokens = (isset($opts['max_tokens']) && is_numeric($opts['max_tokens']))
            ? intval($opts['max_tokens'])
            : 1000;

        $this->temperature = (isset($opts['temperature']) && is_numeric($opts['temperature']))
            ? floatval($opts['temperature'])
            : 0.7;

        $this->timeout = (isset($opts['timeout']) && is_numeric($opts['timeout']))
            ? intval($opts['timeout'])
            : 30;

        $this->error_messages = [
            'no_api_key'    => !empty($opts['error_no_api_key']) ? $opts['error_no_api_key'] : __('API key not set.', 'faqreator'),
            'invalid_check' => !empty($opts['error_invalid_check']) ? $opts['error_invalid_check'] : __('Invalid check_id.', 'faqreator'),
            'api_error'     => !empty($opts['error_api_error']) ? $opts['error_api_error'] : __('API request failed.', 'faqreator'),
            'json_error'    => !empty($opts['error_json_error']) ? $opts['error_json_error'] : __('Invalid JSON response.', 'faqreator'),
        ];
    }

	private function add_hooks(): void {
		add_action( 'admin_menu',    [ $this, 'add_settings_page' ] );
		add_action( 'admin_init',    [ $this, 'register_settings' ] );
		add_shortcode( 'faqreator',  [ $this, 'render_faqs' ] );
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_action( 'faqreator_generate_faq_event', [ $this, 'trigger_generate_faq' ], 10, 2 );
	}

	public function add_settings_page(): void {
		add_options_page(
			__( 'FAQreator Settings', 'faqreator' ),
			__( 'FAQreator', 'faqreator' ),
			'manage_options',
			'faqreator',
			[ $this, 'settings_page_html' ]
		);
	}

	public function register_settings(): void {
        register_setting( 'faqreator', 'faqreator_settings' );

        add_settings_section(
            'faqreator_main',
            __( 'Configurações Gerais', 'faqreator' ),
            function () {
                echo '<p>' . esc_html__( 'Configure as opções da integração com a API e os tipos de post.', 'faqreator' ) . '</p>';
            },
            'faqreator'
        );

        $fields = [
            'openai_api_key', 'auth_token',
            'post_type_check','post_type_question',
            'meta_key','default_max_items',
            'model','max_tokens','temperature','timeout',
            'error_no_api_key','error_invalid_check','error_api_error','error_json_error'
        ];

        foreach ( $fields as $field ) {
            add_settings_field(
                $field,
                ucwords( str_replace( '_', ' ', $field ) ),
                [ $this, 'field_callback' ],
                'faqreator',
                'faqreator_main',
                [ 'label_for' => $field ]
            );
        }
    }

	public function field_callback( array $args ): void {
		$opts = get_option( 'faqreator_settings', [] );
		$val  = $opts[ $args['label_for'] ] ?? '';
		printf(
			'<input type="text" id="%1$s" name="faqreator_settings[%1$s]" value="%2$s" class="regular-text" />',
			esc_attr( $args['label_for'] ),
			esc_attr( $val )
		);
	}

	public function settings_page_html(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;
		echo '<div class="wrap"><h1>' . esc_html__( 'FAQreator Settings', 'faqreator' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'faqreator' );
		do_settings_sections( 'faqreator' );
		submit_button();
		echo '</form></div>';
	}

	public function render_faqs(): string {
        if ( ! is_singular() ) {
            return '';
        }

        global $post;

        $related = get_posts([
            'post_type'      => $this->post_type_question,
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => $this->meta_key,
                    'value'   => '"' . $post->ID . '"',
                    'compare' => 'LIKE',
                ],
            ],
        ]);

        if ( empty( $related ) ) {
            return '<div>' . esc_html__( 'Nenhuma pergunta frequente encontrada.', 'faqreator' ) . '</div>';
        }

        ob_start();
        echo '<div class="faqreator">';
        foreach ( $related as $faq ) {
            echo '<div class="faq-item">';
            echo '<h3>' . esc_html( get_the_title( $faq ) ) . '</h3>';
            echo '<div>' . wp_kses_post( apply_filters( 'the_content', $faq->post_content ) ) . '</div>';
            echo '</div>';
        }
        echo '</div>';
        return ob_get_clean();
    }

	public function register_routes(): void {
		register_rest_route( 'faqreator/v1', '/generate-faqs', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_faq_generation' ],
			'permission_callback' => [ $this, 'validate_token' ],
			'args'                => [
				'post_id' => [
					'required'          => true,
					'validate_callback' => function( $param, $request = null, $key = null ) {
                        return is_numeric( $param );
                    },
					'sanitize_callback' => 'absint',
				],
			],
		] );

        register_rest_route( 'faqreator/v1', '/schedule-faqs/', [
            'methods'  => 'GET',
            'callback' => [ $this, 'schedule_faq_generation' ],
            'permission_callback' => '__return_true',
        ] );
	}

    public function schedule_faq_generation( WP_REST_Request $request ): WP_REST_Response {
        $posts = get_posts([
            'post_type'      => $this->post_type_check,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        if ( empty( $posts ) ) {
            return new WP_REST_Response([ 'message' => __( 'No posts found.', 'faqreator' ) ], 200);
        }

        $offset = 0;
        $interval = 60; // seconds

        foreach ( $posts as $post_id ) {
            wp_schedule_single_event( time() + $offset, 'faqreator_generate_faq_event', [ $post_id, $this->auth_token ] );
            $offset += $interval;
        }

        return new WP_REST_Response([ 'scheduled' => count($posts) ], 200);
    }

    public function trigger_generate_faq( int $post_id, string $token ): void {
        $url = add_query_arg([
            'post_id' => $post_id,
            'token'   => $token,
        ], rest_url( 'faqreator/v1/generate-faqs/' ));

        wp_remote_get( $url, [ 'timeout' => 30 ] );
    }

	public function validate_token( WP_REST_Request $request ): bool {
        $token = $request->get_param('token');
        return $token == $this->auth_token;
    }

    /**
     * Get post excerpt or fallback to first 500 characters of content.
     *
     * @param WP_Post $post The post object.
     * @return string The excerpt or trimmed content.
     */
    public function get_post_summary( WP_Post $post ): string {
        if ( ! empty( $post->post_excerpt ) ) {
            return wp_strip_all_tags( $post->post_excerpt );
        }

        $content = wp_strip_all_tags( $post->post_content );
        $trimmed = mb_substr( $content, 0, 500 );

        return $trimmed;
    }

	public function handle_faq_generation( WP_REST_Request $request ) {
		$post_id = $request->get_param( 'post_id' );
		$post    = get_post( $post_id );

		if ( ! $post || $post->post_type !== $this->post_type_check ) {
			return new WP_Error( 'invalid_post', $this->error_messages['invalid_check'], [ 'status' => 400 ] );
		}

		$prompt = sprintf(
			'Escrevi um artigo entitulado "%s", com o seguinte resumo "%s", pense como um leitor com conhecimento razoável e crie %d perguntas comuns que possam surgir da leitura desse artigo. Responda a essas perguntas criadas de forma técnica, porém, de fácil entendimento.',
			$post->post_title,
			$this->get_post_summary( $post ),
			$this->default_max_items
		);

		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			],
			'body' => json_encode([
				'model'    => $this->model,
				'messages' => [
					[ 'role' => 'system', 'content' => 'Você é muito bom em decifrar o assunto a que se refere um artigo tendo somente o título e o início do texto. Responda somente com JSON no formato: {"perguntas":[{"pergunta": "aqui você faz sua pergunta","resposta": "aqui você responde sua pergunta",{...}]},'],
					[ 'role' => 'user',   'content' => $prompt ],
				],
				'temperature'    => $this->temperature,
				'max_tokens'     => $this->max_tokens,
				'response_format'=> [ 'type' => 'json_object' ],
			]),
			'timeout' => $this->timeout,
		]);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_error', $this->error_messages['api_error'], [ 'status' => 500 ] );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$content = $body['choices'][0]['message']['content'] ?? '';
		$data = json_decode( $content, true );
        $perguntas = $data['perguntas'];

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $perguntas ) ) {
			return new WP_Error( 'json_error', $this->error_messages['json_error'], [ 'status' => 500 ] );
		}

		$results = [];

		foreach ( $perguntas as $item ) {
			$title   = sanitize_text_field( $item['pergunta'] ?? '' );
			$content = sanitize_textarea_field( $item['resposta'] ?? '' );

			if ( empty( $title ) || empty( $content ) ) continue;

			$question_id = wp_insert_post([
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => $this->post_type_question,
			]);

			if ( ! is_wp_error( $question_id ) ) {
				update_field( $this->meta_key, [ (int) $post_id ], $question_id );
				$results[] = [
					'question_id' => $question_id,
					'title'       => $title,
				];
			}
		}

		return $results;
	}
}

FAQreator::get_instance();
