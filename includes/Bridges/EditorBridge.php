<?php

defined( 'ABSPATH' ) || exit;

class WPCE_EditorBridge {

    public static function init(): void {
        add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue' ] );
        add_action( 'rest_api_init',               [ __CLASS__, 'register_routes' ] );
    }

    public static function enqueue(): void {
        $screen = get_current_screen();

        if ( ! $screen || ! $screen->is_block_editor() ) {
            return;
        }

        wp_enqueue_script(
            'wpce-editor',
            WPCE_PLUGIN_URL . 'src/editor/index.js',
            [ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch' ],
            WPCE_VERSION,
            true
        );

        wp_localize_script( 'wpce-editor', 'wpceEditor', [
            'restUrl' => esc_url_raw( rest_url( 'wpce/v1' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'postId'  => get_the_ID(),
        ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'wpce/v1', '/suggest', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'handle_suggest' ],
            'permission_callback' => [ __CLASS__, 'can_edit' ],
            'args'                => [
                'input'   => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => fn( $v ) => strlen( trim( $v ) ) > 2,
                ],
                'post_id' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );
    }

    public static function handle_suggest( WP_REST_Request $request ): WP_REST_Response {
        $input   = $request->get_param( 'input' );
        $post_id = $request->get_param( 'post_id' );

        $results = WPCE_ContextQuery::query( $input, [
            'top_k'   => 3,
            'post_id' => $post_id ?: null,
        ] );

        return rest_ensure_response( [
            'chunks' => $results,
        ] );
    }

    public static function can_edit(): bool {
        return current_user_can( 'edit_posts' );
    }
}
