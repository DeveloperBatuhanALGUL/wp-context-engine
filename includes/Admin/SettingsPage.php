<?php

defined( 'ABSPATH' ) || exit;

class WPCE_SettingsPage {

    const OPTION_KEY   = 'wpce_settings';
    const MENU_SLUG    = 'wp-context-engine';
    const NONCE_ACTION = 'wpce_save_settings';
    const NONCE_FIELD  = 'wpce_nonce';

    public static function init(): void {
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'admin_menu',    [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_init',    [ __CLASS__, 'handle_save' ] );
        add_action( 'admin_notices', [ __CLASS__, 'maybe_show_notice' ] );
        add_action( 'wp_ajax_wpce_reindex', [ __CLASS__, 'handle_reindex' ] );
    }

    public static function enqueue_assets( string $hook ): void {
        if ( 'settings_page_' . self::MENU_SLUG !== $hook ) {
            return;
        }
        wp_enqueue_style(
            'wpce-admin',
            WPCE_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WPCE_VERSION
        );
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
            'text-embedding-3-small' => 'text-embedding-3-small — Recommended',
            'text-embedding-3-large' => 'text-embedding-3-large — Higher accuracy',
            'text-embedding-ada-002' => 'text-embedding-ada-002 — Legacy',
        ];
        $key_set = defined( 'WPCE_OPENAI_API_KEY' ) && ! empty( WPCE_OPENAI_API_KEY );
        ?>
        <div id="wpce-wrap">
            <div class="wpce-layout">
                <aside class="wpce-sidebar">
                    <div class="wpce-sidebar-logo">
                        <h2>Context Engine</h2>
                        <span>v<?php echo esc_html( WPCE_VERSION ); ?></span>
                    </div>
                    <ul class="wpce-nav">
                        <li class="active"><a href="#">Overview</a></li>
                        <li><a href="#">Settings</a></li>
                        <li><a href="#">Indexing</a></li>
                        <li><a href="#">Documentation</a></li>
                    </ul>
                </aside>

                <main class="wpce-main">
                    <div class="wpce-page-header">
                        <h1>Overview</h1>
                        <p>Manage your semantic memory layer, embedding configuration, and indexing pipeline.</p>
                    </div>

                    <?php if ( ! $key_set ) : ?>
                    <div class="wpce-notice info">
                        API key not detected. Add <span class="wpce-code">define( 'WPCE_OPENAI_API_KEY', 'sk-...' );</span> to your <span class="wpce-code">wp-config.php</span> to enable embeddings.
                    </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $_GET['saved'] ) ) : ?>
                    <div class="wpce-notice success">Settings saved successfully.</div>
                    <?php endif; ?>

                    <div class="wpce-metrics">
                        <div class="wpce-metric-card">
                            <div class="label">API Status</div>
                            <div class="value" style="font-size:16px;margin-top:4px;">
                                <?php if ( $key_set ) : ?>
                                <span class="wpce-badge success">Connected</span>
                                <?php else : ?>
                                <span class="wpce-badge error">Not configured</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="wpce-metric-card">
                            <div class="label">Active Model</div>
                            <div class="value" style="font-size:13px;margin-top:6px;"><?php echo esc_html( $settings['model'] ); ?></div>
                        </div>
                        <div class="wpce-metric-card">
                            <div class="label">Visitor Widget</div>
                            <div class="value" style="font-size:16px;margin-top:4px;">
                                <?php if ( ! empty( $settings['visitor_widget'] ) ) : ?>
                                <span class="wpce-badge success">Enabled</span>
                                <?php else : ?>
                                <span class="wpce-badge warning">Disabled</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <form method="post">
                        <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>

                        <div class="wpce-section">
                            <div class="wpce-section-header">
                                <div>
                                    <h3>Embedding Model</h3>
                                    <p>Select the OpenAI model used to generate vector embeddings.</p>
                                </div>
                            </div>
                            <div class="wpce-section-body">
                                <div class="wpce-field">
                                    <label for="wpce_model">Model</label>
                                    <select id="wpce_model" name="wpce_model" class="wpce-select">
                                        <?php foreach ( $models as $value => $label ) : ?>
                                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['model'], $value ); ?>>
                                                <?php echo esc_html( $label ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="wpce-section">
                            <div class="wpce-section-header">
                                <div>
                                    <h3>Content Indexing</h3>
                                    <p>Choose which post types are included in the semantic index.</p>
                                </div>
                            </div>
                            <div class="wpce-section-body">
                                <div class="wpce-field">
                                    <label>Post Types</label>
                                    <div class="wpce-checkbox-group">
                                        <?php foreach ( $all_types as $type ) : ?>
                                            <label class="wpce-checkbox-label">
                                                <input
                                                    type="checkbox"
                                                    name="wpce_post_types[]"
                                                    value="<?php echo esc_attr( $type->name ); ?>"
                                                    <?php checked( in_array( $type->name, $settings['post_types'], true ) ); ?>
                                                />
                                                <?php echo esc_html( $type->label ); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="wpce-section">
                            <div class="wpce-section-header">
                                <div>
                                    <h3>Visitor Widget</h3>
                                    <p>Enable the public-facing semantic search widget for site visitors.</p>
                                </div>
                            </div>
                            <div class="wpce-section-body">
                                <div class="wpce-field">
                                    <label class="wpce-toggle">
                                        <input type="checkbox" name="wpce_visitor_widget" value="1" <?php checked( $settings['visitor_widget'], 1 ); ?> />
                                        <span class="wpce-toggle-track"></span>
                                        <span class="wpce-toggle-label">Enable visitor widget</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="wpce-actions">
                            <button type="submit" class="wpce-btn wpce-btn-primary">Save Settings</button>
                        </div>
                    </form>

                    <hr class="wpce-divider">

                    <div class="wpce-section">
                        <div class="wpce-section-header">
                            <div>
                                <h3>Re-index Content</h3>
                                <p>Queue all published posts for re-indexing. Runs in the background without affecting visitors.</p>
                            </div>
                        </div>
                        <div class="wpce-section-body">
                            <div class="wpce-actions" style="padding-top:0;border-top:none;margin-top:0;">
                                <button id="wpce-reindex-btn" class="wpce-btn wpce-btn-secondary">Start Re-index</button>
                                <span id="wpce-reindex-status" class="wpce-status-text"></span>
                            </div>
                        </div>
                    </div>

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
                </main>
            </div>
        </div>
        <?php
    }
}
