<?php

defined( 'ABSPATH' ) || exit;

class WPCE_ContentIndexer {

    const CHUNK_SIZE    = 600;
    const CHUNK_OVERLAP = 50;
    const ASYNC_ACTION  = 'wpce_index_post';

    public static function init(): void {
        add_action( 'save_post',    [ __CLASS__, 'on_save' ], 20, 2 );
        add_action( 'delete_post',  [ __CLASS__, 'on_delete' ] );
        add_action( self::ASYNC_ACTION, [ __CLASS__, 'process' ] );
    }

    public static function on_save( int $post_id, WP_Post $post ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        if ( ! in_array( $post->post_status, [ 'publish', 'private' ], true ) ) {
            return;
        }

        if ( ! in_array( $post->post_type, self::indexable_post_types(), true ) ) {
            return;
        }

        if ( ! wp_next_scheduled( self::ASYNC_ACTION, [ $post_id ] ) ) {
            wp_schedule_single_event( time(), self::ASYNC_ACTION, [ $post_id ] );
        }
    }

    public static function on_delete( int $post_id ): void {
        WPCE_VectorStore::delete_by_post( $post_id );
    }

    public static function process( int $post_id ): void {
        $post = get_post( $post_id );

        if ( ! $post ) {
            return;
        }

        $text   = self::extract_text( $post );
        $chunks = self::chunk( $text );

        if ( empty( $chunks ) ) {
            return;
        }

        WPCE_VectorStore::upsert_chunks( $post_id, $chunks );
    }

    private static function extract_text( WP_Post $post ): string {
        $parts = [
            $post->post_title,
            wp_strip_all_tags( $post->post_excerpt ),
            wp_strip_all_tags( do_shortcode( $post->post_content ) ),
        ];

        return implode( ' ', array_filter( $parts ) );
    }

    private static function chunk( string $text ): array {
        $words  = preg_split( '/\s+/', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
        $chunks = [];
        $total  = count( $words );
        $step   = self::CHUNK_SIZE - self::CHUNK_OVERLAP;

        for ( $i = 0; $i < $total; $i += $step ) {
            $slice   = array_slice( $words, $i, self::CHUNK_SIZE );
            $content = implode( ' ', $slice );

            $chunks[] = [
                'content'     => $content,
                'token_count' => count( $slice ),
            ];

            if ( $i + self::CHUNK_SIZE >= $total ) {
                break;
            }
        }

        return $chunks;
    }

    public static function indexable_post_types(): array {
        return apply_filters( 'wpce_indexable_post_types', [ 'post', 'page' ] );
    }
}
