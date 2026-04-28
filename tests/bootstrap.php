<?php

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

use Brain\Monkey;

Monkey\setUp();

function apply_filters( string $tag, $value, ...$args ) {
    return $value;
}

function add_action( string $tag, $callback, int $priority = 10, int $args = 1 ): bool {
    return true;
}

function add_filter( string $tag, $callback, int $priority = 10, int $args = 1 ): bool {
    return true;
}

function get_option( string $option, $default = false ) {
    return $default;
}

function wp_parse_args( $args, $defaults = [] ): array {
    return array_merge( $defaults, (array) $args );
}

define( 'ABSPATH',        '/tmp/wordpress/' );
define( 'WPCE_VERSION',   '0.1.0' );
define( 'WPCE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCE_PLUGIN_URL', 'http://localhost/wp-content/plugins/wp-context-engine/' );
define( 'DAY_IN_SECONDS',  86400 );

require_once WPCE_PLUGIN_DIR . 'includes/Storage/VectorStore.php';
require_once WPCE_PLUGIN_DIR . 'includes/Indexer/ContentIndexer.php';
require_once WPCE_PLUGIN_DIR . 'includes/ContextAPI/ContextQuery.php';
