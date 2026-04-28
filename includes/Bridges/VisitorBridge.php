<?php

defined( 'ABSPATH' ) || exit;

class WPCE_VisitorBridge {

    const RATE_LIMIT_WINDOW   = 60;
    const RATE_LIMIT_MAX      = 10;

    public static function init(): void {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function enqueue(): void {
        if ( ! apply_filters( 'wpce_visitor_widget_enabled', false ) ) {
            return;
        }

        wp_enqueue_script(
            'wpce-visitor',
            WPCE_PLUGIN_URL . 'src/visitor/index.js',
            [],
            WPCE_VERSION,
            true
        );

        wp_localize_script( 'wpce-visitor', 'wpceVisitor', [
            'restUrl' => esc_url_raw( rest_url( 'wpce/v1' ) ),
            'nonce'   => wp_create_nonce( 'wpce_visitor' ),
        ] );
    }

    public static function register_routes(): void {
        register_rest_route( 'wpce/v1', '/ask', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'handle_ask' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'question' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => fn( $v ) => strlen( trim( $v ) ) > 2 && strlen( $v ) <= 500,
                ],
            ],
        ] );
    }

    public static function handle_ask( WP_REST_Request $request ): WP_REST_Response {
        $ip = self::get_client_ip();

        if ( ! self::check_rate_limit( $ip ) ) {
            return new WP_REST_Response( [ 'error' => 'rate_limit_exceeded' ], 429 );
        }

        $question = $request->get_param( 'question' );

        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! wp_verify_nonce( $nonce, 'wpce_visitor' ) ) {
            return new WP_REST_Response( [ 'error' => 'invalid_nonce' ], 403 );
        }

        $results = WPCE_ContextQuery::query( $question, [
            'top_k' => 5,
        ] );

        $context = array_map( fn( $r ) => [
            'post_id' => $r['post_id'],
            'content' => $r['content'],
            'score'   => round( $r['score'], 4 ),
            'url'     => get_permalink( $r['post_id'] ),
            'title'   => get_the_title( $r['post_id'] ),
        ], $results );

        return rest_ensure_response( [
            'question' => $question,
            'context'  => $context,
        ] );
    }

    private static function check_rate_limit( string $ip ): bool {
        $key     = 'wpce_rl_' . md5( $ip );
        $current = (int) get_transient( $key );

        if ( $current >= self::RATE_LIMIT_MAX ) {
            return false;
        }

        set_transient( $key, $current + 1, self::RATE_LIMIT_WINDOW );

        return true;
    }

    private static function get_client_ip(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        ];

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) )[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
