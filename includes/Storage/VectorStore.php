<?php

defined( 'ABSPATH' ) || exit;

class WPCE_VectorStore {

    const TABLE_EMBEDDINGS = 'wpce_embeddings';
    const TABLE_CHUNKS     = 'wpce_chunks';

    public static function install(): void {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();
        $chunks  = $wpdb->prefix . self::TABLE_CHUNKS;
        $embeds  = $wpdb->prefix . self::TABLE_EMBEDDINGS;

        $sql = "
            CREATE TABLE IF NOT EXISTS {$chunks} (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                post_id     BIGINT UNSIGNED NOT NULL,
                chunk_index SMALLINT UNSIGNED NOT NULL,
                content     LONGTEXT NOT NULL,
                token_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_post_id (post_id)
            ) {$charset};

            CREATE TABLE IF NOT EXISTS {$embeds} (
                id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                chunk_id   BIGINT UNSIGNED NOT NULL,
                model      VARCHAR(64) NOT NULL,
                vector     LONGTEXT NOT NULL,
                dimensions SMALLINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY idx_chunk_model (chunk_id, model),
                KEY idx_model (model)
            ) {$charset};
        ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'wpce_db_version', WPCE_VERSION );
    }

    public static function uninstall(): void {
        global $wpdb;

        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}" . self::TABLE_EMBEDDINGS );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}" . self::TABLE_CHUNKS );

        delete_option( 'wpce_db_version' );
        delete_option( 'wpce_settings' );
    }

    public static function upsert_chunks( int $post_id, array $chunks ): bool {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_CHUNKS;

        $wpdb->delete( $table, [ 'post_id' => $post_id ], [ '%d' ] );

        foreach ( $chunks as $index => $chunk ) {
            $inserted = $wpdb->insert(
                $table,
                [
                    'post_id'     => $post_id,
                    'chunk_index' => $index,
                    'content'     => $chunk['content'],
                    'token_count' => $chunk['token_count'],
                ],
                [ '%d', '%d', '%s', '%d' ]
            );

            if ( ! $inserted ) {
                return false;
            }
        }

        return true;
    }

    public static function upsert_embedding( int $chunk_id, string $model, array $vector ): bool {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_EMBEDDINGS;

        $existing = $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE chunk_id = %d AND model = %s", $chunk_id, $model )
        );

        $data   = [
            'chunk_id'   => $chunk_id,
            'model'      => $model,
            'vector'     => wp_json_encode( $vector ),
            'dimensions' => count( $vector ),
        ];
        $format = [ '%d', '%s', '%s', '%d' ];

        if ( $existing ) {
            return (bool) $wpdb->update( $table, $data, [ 'id' => $existing ], $format, [ '%d' ] );
        }

        return (bool) $wpdb->insert( $table, $data, $format );
    }

    public static function get_chunks_by_post( int $post_id ): array {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_CHUNKS;

        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d ORDER BY chunk_index ASC", $post_id ),
            ARRAY_A
        ) ?: [];
    }

    public static function get_all_embeddings( string $model ): array {
        global $wpdb;

        $chunks = $wpdb->prefix . self::TABLE_CHUNKS;
        $embeds = $wpdb->prefix . self::TABLE_EMBEDDINGS;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.id, e.chunk_id, e.vector, e.dimensions, c.post_id, c.content
                 FROM {$embeds} e
                 INNER JOIN {$chunks} c ON c.id = e.chunk_id
                 WHERE e.model = %s",
                $model
            ),
            ARRAY_A
        ) ?: [];
    }

    public static function delete_by_post( int $post_id ): void {
        global $wpdb;

        $chunks = $wpdb->prefix . self::TABLE_CHUNKS;
        $embeds = $wpdb->prefix . self::TABLE_EMBEDDINGS;

        $chunk_ids = $wpdb->get_col(
            $wpdb->prepare( "SELECT id FROM {$chunks} WHERE post_id = %d", $post_id )
        );

        if ( ! empty( $chunk_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $chunk_ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$embeds} WHERE chunk_id IN ({$placeholders})", ...$chunk_ids ) );
        }

        $wpdb->delete( $chunks, [ 'post_id' => $post_id ], [ '%d' ] );
    }
}
