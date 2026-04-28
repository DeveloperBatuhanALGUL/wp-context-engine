<?php

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

Mockery::globalHelpers();

define( 'ABSPATH',       '/tmp/wordpress/' );
define( 'WPCE_VERSION',  '0.1.0' );
define( 'WPCE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCE_PLUGIN_URL', 'http://localhost/wp-content/plugins/wp-context-engine/' );
define( 'DAY_IN_SECONDS', 86400 );

require_once WPCE_PLUGIN_DIR . 'includes/Storage/VectorStore.php';
require_once WPCE_PLUGIN_DIR . 'includes/Indexer/ContentIndexer.php';
require_once WPCE_PLUGIN_DIR . 'includes/ContextAPI/ContextQuery.php';
require_once WPCE_PLUGIN_DIR . 'includes/Bridges/EditorBridge.php';
require_once WPCE_PLUGIN_DIR . 'includes/Bridges/VisitorBridge.php';
