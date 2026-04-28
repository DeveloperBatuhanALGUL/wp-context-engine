# Contributing to WP Context Engine

First off — thank you. Every issue filed, every line reviewed, every PR opened makes this better.

---

## Before You Start

Check open issues and pull requests before starting work. If you have a new idea, open an issue first so we can discuss the direction before you invest time in code.

---

## Development Setup

    git clone https://github.com/DeveloperBatuhanALGUL/wp-context-engine.git
    cd wp-context-engine

You need a local WordPress installation with PHP 8.1+ and MySQL 5.7+. Drop the plugin folder inside wp-content/plugins and activate it.

---

## Branching

Branch off main. Name your branch clearly:

    feat/your-feature-name
    fix/what-you-are-fixing
    docs/what-you-are-documenting
    refactor/what-you-are-refactoring

---

## Code Standards

- PHP follows WordPress Coding Standards
- No inline comments inside function bodies — code should speak for itself
- Every public method must be type-hinted (parameters and return types)
- Security first — sanitize inputs, escape outputs, verify nonces, check capabilities
- No external HTTP calls from the plugin core — use filter hooks so users bring their own providers
- Keep server cost in mind — avoid redundant DB queries, batch where possible

---

## Commit Messages

Use conventional commits:

    feat: add bulk re-indexing command
    fix: handle empty post content gracefully
    docs: expand filter hooks section in README
    refactor: simplify cosine similarity loop
    test: add unit tests for ContentIndexer chunking

One concern per commit. Do not bundle unrelated changes.

---

## Pull Requests

- Keep PRs focused — one feature or fix per PR
- Fill out the PR description fully — what changed, why, how to test
- All CI checks must pass before review
- Do not bump version numbers in PRs — maintainers handle releases

---

## Reporting Issues

Include: WordPress version, PHP version, steps to reproduce, expected vs actual behavior. The more precise, the faster it gets resolved.

---

## License

By contributing, you agree that your code will be licensed under GPL-2.0-only.
