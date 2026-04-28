<?php

defined( 'ABSPATH' ) || exit;

class WPCE_SettingsPage {

    const OPTION_KEY   = 'wpce_settings';
    const MENU_SLUG    = 'wp-context-engine';
    const NONCE_ACTION = 'wpce_save_settings';
    const NONCE_FIELD  = 'wpce_nonce';

    public static function init(): void {
        add_action( 'admin_menu',    [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_init',    [ __CLASS__, 'handle_save' ] );
        add_action( 'admin_notices', [ __CLASS__, 'maybe_show_notice' ] );
        add_action( 'wp_ajax_wpce_reindex', [ __CLASS__, 'handle_reindex' ] );
    }

    public static function register_menu(): void {
        add_options_page(
            'WP Context Engine',
            'Context Engine',
            'manage_options',
            self::MENU_SLUG,
            [ __CLASS__, 'render' ]
        );
    }

    public static function handle_save(): void {
        if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
            wp_die( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        $post_types = isset( $_POST['wpce_post_types'] ) && is_array( $_POST['wpce_post_types'] )
            ? array_map( 'sanitize_key', wp_unslash( $_POST['wpce_post_types'] ) )
            : [];

        $settings = [
            'model'          => isset( $_POST['wpce_model'] )
                ? sanitize_key( wp_unslash( $_POST['wpce_model'] ) )
                : 'text-embedding-3-small',
            'post_types'     => $post_types,
            'visitor_widget' => isset( $_POST['wpce_visitor_widget'] ) ? 1 : 0,
        ];

        update_option( self::OPTION_KEY, $settings );
        wp_safe_redirect( add_query_arg( [ 'page' => self::MENU_SLUG, 'saved' => '1' ], admin_url( 'options-general.php' ) ) );
        exit;
    }

    public static function maybe_show_notice(): void {
        $screen = get_current_screen();

        if ( ! $screen || 'settings_page_' . self::MENU_SLUG !== $screen->id ) {
            return;
        }

        if ( ! defined( 'WPCE_OPENAI_API_KEY' ) || empty( WPCE_OPENAI_API_KEY ) ) {
            echo '<div class="notice notice-error"><p><strong>WP Context Engine:</strong> API key not found. Add <code>define( \'WPCE_OPENAI_API_KEY\', \'sk-...\' );</code> to your <code>wp-config.php</code>.</p></div>';
        }

        if ( ! empty( $_GET['saved'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        }

        if ( ! empty( $_GET['reindexed'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>Re-index queued for all published posts.</p></div>';
        }
    }

    public static function handle_reindex(): void {
        check_ajax_referer( self::NONCE_ACTION, self::NONCE_FIELD );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.', 403 );
        }

        $settings   = self::get();
        $post_types = ! empty( $settings['post_types'] ) ? $settings['post_types'] : [ 'post', 'page' ];

        $ids = get_posts( [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        foreach ( $ids as $id ) {
            if ( ! wp_next_scheduled( WPCE_ContentIndexer::ASYNC_ACTION, [ $id ] ) ) {
                wp_schedule_single_event( time(), WPCE_ContentIndexer::ASYNC_ACTION, [ $id ] );
            }
        }

        wp_send_json_success( [ 'queued' => count( $ids ) ] );
    }

    public static function get(): array {
        return wp_parse_args( get_option( self::OPTION_KEY, [] ), [
            'model'          => 'text-embedding-3-small',
            'post_types'     => [ 'post', 'page' ],
            'visitor_widget' => 0,
        ] );
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings  = self::get();
        $all_types = get_post_types( [ 'public' => true ], 'objects' );
        $models    = [
            'text-embedding-3-small' => 'text-embedding-3-small (recommended)',
            'text-embedding-3-large' => 'text-embedding-3-large (higher accuracy)',
            'text-embedding-ada-002' => 'text-embedding-ada-002 (legacy)',
        ];
        $key_set = defined( 'WPCE_OPENAI_API_KEY' ) && ! empty( WPCE_OPENAI_API_KEY );
        ?>
        <div class="wrap">
            <h1>WP Context Engine</h1>

            <?php if ( ! $key_set ) : ?>
            <div class="notice notice-warning inline">
                <p>Add the following line to your <code>wp-config.php</code> to enable embeddings:</p>
                <code>define( 'WPCE_OPENAI_API_KEY', 'sk-...' );</code>
            </div>
            <?php else : ?>
            <div class="notice notice-success inline"><p>API key detected.</p></div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="wpce_model">Embedding Model</label></th>
                        <td>
                            <select id="wpce_model" name="wpce_model">
                                <?php foreach ( $models as $value => $label ) : ?>
                                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['model'], $value ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Post Types to Index</th>
                        <td>
                            <?php foreach ( $all_types as $type ) : ?>
                                <label style="display:block;margin-bottom:4px;">
                                    <input
                                        type="checkbox"
                                        name="wpce_post_types[]"
                                        value="<?php echo esc_attr( $type->name ); ?>"
                                        <?php checked( in_array( $type->name, $settings['post_types'], true ) ); ?>
                                    />
                                    <?php echo esc_html( $type->label ); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Visitor Widget</th>
                        <td>
                            <label>
                                <input type="checkbox" name="wpce_visitor_widget" value="1" <?php checked( $settings['visitor_widget'], 1 ); ?> />
                                Enable the public-facing context widget
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Save Settings' ); ?>
            </form>

            <hr />
            <h2>Re-index Content</h2>
            <p>Queue all published posts for re-indexing. Runs in the background.</p>
            <button id="wpce-reindex-btn" class="button button-secondary">Start Re-index</button>
            <span id="wpce-reindex-status" style="margin-left:12px;"></span>
            <script>
            document.getElementById('wpce-reindex-btn').addEventListener('click', function() {
                var btn = this, status = document.getElementById('wpce-reindex-status');
                btn.disabled = true;
                status.textContent = 'Queueing...';
                var data = new FormData();
                data.append('action', 'wpce_reindex');
                data.append('<?php echo esc_js( self::NONCE_FIELD ); ?>', '<?php echo esc_js( wp_create_nonce( self::NONCE_ACTION ) ); ?>');
                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(function(r) { return r.json(); })
                    .then(function(r) {
                        status.textContent = r.success ? r.data.queued + ' posts queued.' : 'Failed.';
                        btn.disabled = false;
                    });
            });
            </script>
        </div>
        <?php
    }
}
