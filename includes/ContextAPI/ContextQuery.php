<?php

defined( 'ABSPATH' ) || exit;

class WPCE_ContextQuery {

    const DEFAULT_TOP_K = 5;

    public static function query( string $input, array $options = [] ): array {
        $model  = $options['model']  ?? self::active_model();
        $top_k  = $options['top_k']  ?? self::DEFAULT_TOP_K;
        $post_id = $options['post_id'] ?? null;

        $input_vector = self::embed( $input, $model );

        if ( empty( $input_vector ) ) {
            return [];
        }

        $rows = WPCE_VectorStore::get_all_embeddings( $model );

        if ( empty( $rows ) ) {
            return [];
        }

        if ( $post_id ) {
            $rows = array_filter( $rows, fn( $r ) => (int) $r['post_id'] === $post_id );
        }

        $scored = [];

        foreach ( $rows as $row ) {
            $vector = json_decode( $row['vector'], true );

            if ( ! is_array( $vector ) ) {
                continue;
            }

            $scored[] = [
                'post_id'  => (int) $row['post_id'],
                'chunk_id' => (int) $row['chunk_id'],
                'content'  => $row['content'],
                'score'    => self::cosine_similarity( $input_vector, $vector ),
            ];
        }

        usort( $scored, fn( $a, $b ) => $b['score'] <=> $a['score'] );

        return array_slice( $scored, 0, $top_k );
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
