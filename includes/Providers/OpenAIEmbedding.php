<?php

defined( 'ABSPATH' ) || exit;

class WPCE_OpenAIEmbedding {

    const API_URL     = 'https://api.openai.com/v1/embeddings';
    const CACHE_GROUP = 'wpce_embeddings';
    const CACHE_TTL   = DAY_IN_SECONDS * 30;

    public static function init(): void {
        add_filter( 'wpce_embed',         [ __CLASS__, 'embed' ], 10, 3 );
        add_filter( 'wpce_active_model',  [ __CLASS__, 'active_model' ] );
        add_filter( 'wpce_visitor_widget_enabled', [ __CLASS__, 'widget_enabled' ] );
    }

    public static function embed( array $default, string $text, string $model ): array {
        if ( ! empty( $default ) ) {
            return $default;
        }

        $settings = WPCE_SettingsPage::get();
        $api_key  = $settings['api_key'] ?? '';

        if ( empty( $api_key ) ) {
            return [];
        }

        $cache_key = 'wpce_' . md5( $model . $text );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( is_array( $cached ) ) {
            return $cached;
        }

        $response = wp_remote_post( self::API_URL, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [
                'input' => mb_substr( $text, 0, 8192 ),
                'model' => $model,
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [];
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( 200 !== (int) $code ) {
            return [];
        }

        $body   = json_decode( wp_remote_retrieve_body( $response ), true );
        $vector = $body['data'][0]['embedding'] ?? [];

        if ( ! empty( $vector ) ) {
            wp_cache_set( $cache_key, $vector, self::CACHE_GROUP, self::CACHE_TTL );
        }

        return $vector;
    }

    public static function active_model( string $default ): string {
        $settings = WPCE_SettingsPage::get();
        return ! empty( $settings['model'] ) ? $settings['model'] : $default;
    }

    public static function widget_enabled( bool $default ): bool {
        $settings = WPCE_SettingsPage::get();
        return ! empty( $settings['visitor_widget'] ) ? (bool) $settings['visitor_widget'] : $default;
    }
}
