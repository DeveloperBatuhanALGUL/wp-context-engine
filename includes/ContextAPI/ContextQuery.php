<?php

defined( 'ABSPATH' ) || exit;

class WPCE_ContextQuery {

    const DEFAULT_TOP_K  = 5;
    const MAX_CHUNKS     = 2000;
    const MAX_INPUT_LEN  = 500;

    public static function query( string $input, array $options = [] ): array {
        if ( mb_strlen( $input ) > self::MAX_INPUT_LEN ) {
            $input = mb_substr( $input, 0, self::MAX_INPUT_LEN );
        }

        $model  = $options['model']   ?? self::active_model();
        $top_k  = min( (int) ( $options['top_k'] ?? self::DEFAULT_TOP_K ), 20 );
        $post_id = $options['post_id'] ?? null;

        $input_vector = self::embed( $input, $model );

        if ( empty( $input_vector ) ) {
            return [];
        }

        $rows = self::get_embeddings_bounded( $model, $post_id );

        if ( empty( $rows ) ) {
            return [];
        }

        return self::rank( $input_vector, $rows, $top_k );
    }

    private static function get_embeddings_bounded( string $model, ?int $post_id ): array {
        global $wpdb;

        $chunks = $wpdb->prefix . 'wpce_chunks';
        $embeds = $wpdb->prefix . 'wpce_embeddings';

        if ( $post_id ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT e.chunk_id, e.vector, e.dimensions, c.post_id, c.content
                     FROM {$embeds} e
                     INNER JOIN {$chunks} c ON c.id = e.chunk_id
                     WHERE e.model = %s AND c.post_id = %d
                     LIMIT %d",
                    $model,
                    $post_id,
                    self::MAX_CHUNKS
                ),
                ARRAY_A
            ) ?: [];
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.chunk_id, e.vector, e.dimensions, c.post_id, c.content
                 FROM {$embeds} e
                 INNER JOIN {$chunks} c ON c.id = e.chunk_id
                 WHERE e.model = %s
                 ORDER BY e.id DESC
                 LIMIT %d",
                $model,
                self::MAX_CHUNKS
            ),
            ARRAY_A
        ) ?: [];
    }

    private static function rank( array $input_vector, array $rows, int $top_k ): array {
        $heap  = new SplMinHeap();
        $count = 0;

        foreach ( $rows as $row ) {
            $vector = json_decode( $row['vector'], true );

            if ( ! is_array( $vector ) ) {
                continue;
            }

            $score = self::cosine_similarity( $input_vector, $vector );

            if ( $count < $top_k ) {
                $heap->insert( [ 'score' => $score, 'row' => $row ] );
                $count++;
            } elseif ( $score > $heap->top()['score'] ) {
                $heap->extract();
                $heap->insert( [ 'score' => $score, 'row' => $row ] );
            }
        }

        $results = [];
        while ( ! $heap->isEmpty() ) {
            $results[] = $heap->extract();
        }

        usort( $results, fn( $a, $b ) => $b['score'] <=> $a['score'] );

        return array_map( fn( $item ) => [
            'post_id'  => (int) $item['row']['post_id'],
            'chunk_id' => (int) $item['row']['chunk_id'],
            'content'  => $item['row']['content'],
            'score'    => $item['score'],
        ], $results );
    }

    public static function embed( string $text, string $model = '' ): array {
        if ( empty( $model ) ) {
            $model = self::active_model();
        }

        return apply_filters( 'wpce_embed', [], $text, $model );
    }

    public static function active_model(): string {
        return apply_filters( 'wpce_active_model', get_option( 'wpce_embedding_model', 'text-embedding-3-small' ) );
    }

    private static function cosine_similarity( array $a, array $b ): float {
        $dot  = 0.0;
        $magA = 0.0;
        $magB = 0.0;
        $len  = min( count( $a ), count( $b ) );

        for ( $i = 0; $i < $len; $i++ ) {
            $dot  += $a[ $i ] * $b[ $i ];
            $magA += $a[ $i ] ** 2;
            $magB += $b[ $i ] ** 2;
        }

        $denom = sqrt( $magA ) * sqrt( $magB );

        return $denom > 0.0 ? $dot / $denom : 0.0;
    }
}
