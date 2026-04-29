<?php

defined( 'ABSPATH' ) || exit;

class WPCE_VisitorBridge {

    const RATE_LIMIT_WINDOW   = 60;
    const RATE_LIMIT_MAX      = 10;
    const DAILY_LIMIT_MAX     = 200;
    const SUSPICIOUS_RATIO    = 0.8;

    private const CF_IP_RANGES = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22',
        '103.31.4.0/22',   '141.101.64.0/18', '108.162.192.0/18',
        '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22',
        '198.41.128.0/17', '162.158.0.0/15',  '104.16.0.0/13',
        '104.24.0.0/14',   '172.64.0.0/13',   '131.0.72.0/22',
    ];

    public static function init(): void {
        add_action( 'rest_api_init',      [ __CLASS__, 'register_routes' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function enqueue(): void {
        if ( ! apply_filters( 'wpce_visitor_widget_enabled', false ) ) {
            return;
        }

        wp_enqueue_style(
            'wpce-visitor',
            WPCE_PLUGIN_URL . 'assets/css/visitor.css',
            [],
            WPCE_VERSION
        );

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

        if ( ! self::check_daily_limit( $ip ) ) {
            return new WP_REST_Response( [ 'error' => 'daily_limit_exceeded' ], 429 );
        }

        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! wp_verify_nonce( $nonce, 'wpce_visitor' ) ) {
            return new WP_REST_Response( [ 'error' => 'invalid_nonce' ], 403 );
        }

        $question = $request->get_param( 'question' );

        if ( self::is_suspicious( $question ) ) {
            return new WP_REST_Response( [ 'error' => 'invalid_input' ], 400 );
        }

        $results = WPCE_ContextQuery::query( $question, [ 'top_k' => 5 ] );

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

    private static function check_daily_limit( string $ip ): bool {
        $key     = 'wpce_dl_' . md5( $ip ) . '_' . gmdate( 'Ymd' );
        $current = (int) get_transient( $key );

        if ( $current >= self::DAILY_LIMIT_MAX ) {
            return false;
        }

        set_transient( $key, $current + 1, DAY_IN_SECONDS );
        return true;
    }

    private static function is_suspicious( string $input ): bool {
        $len = mb_strlen( $input );

        if ( $len < 3 ) {
            return true;
        }

        $counts = array_count_values( mb_str_split( $input ) );
        arsort( $counts );
        $top_count = reset( $counts );

        if ( $top_count / $len >= self::SUSPICIOUS_RATIO ) {
            return true;
        }

        if ( ! preg_match( '/\pL/u', $input ) ) {
            return true;
        }

        return false;
    }

    private static function get_client_ip(): string {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (
            defined( 'WPCE_TRUST_CLOUDFLARE' ) && WPCE_TRUST_CLOUDFLARE &&
            ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) &&
            self::is_cloudflare_ip( $remote )
        ) {
            $cf_ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
            if ( filter_var( $cf_ip, FILTER_VALIDATE_IP ) ) {
                return $cf_ip;
            }
        }

        if ( filter_var( $remote, FILTER_VALIDATE_IP ) ) {
            return $remote;
        }

        return '0.0.0.0';
    }

    private static function is_cloudflare_ip( string $ip ): bool {
        foreach ( self::CF_IP_RANGES as $range ) {
            if ( self::ip_in_cidr( $ip, $range ) ) {
                return true;
            }
        }
        return false;
    }

    private static function ip_in_cidr( string $ip, string $cidr ): bool {
        [ $subnet, $bits ] = explode( '/', $cidr );
        $ip_long     = ip2long( $ip );
        $subnet_long = ip2long( $subnet );
        $mask        = ~( ( 1 << ( 32 - (int) $bits ) ) - 1 );
        return ( $ip_long & $mask ) === ( $subnet_long & $mask );
    }
}
