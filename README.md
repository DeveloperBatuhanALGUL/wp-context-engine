# WP Context Engine

> WordPress has always known a lot about your content. It just never told the AI.

WP Context Engine is a native WordPress plugin that builds a semantic memory layer directly inside your installation — no external services, no third-party vector databases, no duct tape. It indexes your content, understands meaning, and serves relevant context to AI features for both the people who write your site and the people who read it.

---

## The Problem

Every WordPress AI tool today starts from zero. They do not know your site. They do not know what you have already written, what topics you cover, or what voice you write in. They are strangers borrowing your keyboard.

WP Context Engine fixes this at the foundation.

---

## How It Works

When a post is saved, the engine quietly chunks the content and stores embeddings in your own database. When context is needed — by the block editor or a frontend widget — it runs a cosine similarity search and returns the most relevant passages from across your entire site.

    Content Published
           │
           ▼
      ContentIndexer        strips, chunks, queues
           │
           ▼
       VectorStore          stores chunks + embeddings in native WP tables
           │
           ▼
      ContextQuery          cosine similarity search at query time
           │
          ┌┴─────────────────┐
          ▼                  ▼
     EditorBridge       VisitorBridge
     (writers)          (readers)

---

## Features

- **Native storage** — embeddings live in your WordPress database, zero external dependencies required
- **Automatic indexing** — hooks into save_post, indexes asynchronously, never blocks the editor
- **Dual-mode context** — serves writers inside Gutenberg and readers on the frontend via separate REST endpoints
- **Rate limiting** — visitor endpoint is protected with IP-based rate limiting out of the box
- **Model agnostic** — swap embedding models via a single filter hook
- **Extensible post types** — index any post type with one filter

---

## Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 6.4 |
| PHP | 8.1 |
| MySQL | 5.7 |

---

## Installation

    cd wp-content/plugins
    git clone https://github.com/DeveloperBatuhanALGUL/wp-context-engine.git

Activate the plugin from **Plugins → Installed Plugins**. On activation, two database tables are created automatically.

---

## Embedding Provider

The engine ships without a default embedding provider — it is intentionally decoupled. Hook into `wpce_embed` to connect any model you prefer:

    add_filter( 'wpce_embed', function ( $default, string $text, string $model ): array {
        // call your preferred embedding API here
        // return a plain array of floats
        return my_embedding_provider( $text, $model );
    }, 10, 3 );

OpenAI, Cohere, a locally hosted Ollama instance — the engine does not care. Bring your own.

---

## REST API

### POST /wp-json/wpce/v1/suggest
*Requires authentication. For use inside the block editor.*

| Parameter | Type | Required | Description |
|---|---|---|---|
| input | string | yes | The text to find context for |
| post_id | integer | no | Scope results to a single post |

### POST /wp-json/wpce/v1/ask
*Public endpoint. Rate limited to 10 requests per minute per IP.*

| Parameter | Type | Required | Description |
|---|---|---|---|
| question | string | yes | The visitor's question (max 500 chars) |

---

## Filter Hooks

    // Change which post types get indexed
    add_filter( 'wpce_indexable_post_types', fn( $types ) => array_merge( $types, [ 'product' ] ) );

    // Swap the active embedding model
    add_filter( 'wpce_active_model', fn() => 'text-embedding-3-large' );

    // Enable the frontend visitor widget
    add_filter( 'wpce_visitor_widget_enabled', '__return_true' );

---

## Project Structure

    wp-context-engine/
    ├── includes/
    │   ├── Storage/VectorStore.php       database layer
    │   ├── Indexer/ContentIndexer.php    chunking + async queue
    │   ├── ContextAPI/ContextQuery.php   similarity search
    │   └── Bridges/
    │       ├── EditorBridge.php          Gutenberg REST endpoint
    │       └── VisitorBridge.php         public REST endpoint
    ├── src/
    │   ├── editor/                       block editor JS
    │   └── visitor/                      frontend widget JS
    ├── tests/
    └── wp-context-engine.php

---

## Contributing

Contributions are welcome. Please read CONTRIBUTING.md before opening a pull request.

---

## License

GPL-2.0-only — see LICENSE for details.

---

Built with intention by Batuhan ALGÜL — https://github.com/DeveloperBatuhanALGUL
