( function () {
    'use strict';

    var config  = window.wpceVisitor || {};
    var restUrl = config.restUrl || '';
    var nonce   = config.nonce   || '';

    if ( ! restUrl ) return;

    var MIN_CHARS   = 3;
    var MAX_CHARS   = 500;
    var DEBOUNCE_MS = 600;

    function debounce( fn, ms ) {
        var t;
        return function () {
            var a = arguments;
            clearTimeout( t );
            t = setTimeout( function () { fn.apply( null, a ); }, ms );
        };
    }

    function sanitize( str ) {
        var d = document.createElement( 'div' );
        d.textContent = str;
        return d.innerHTML;
    }

    function icon( type ) {
        if ( type === 'search' ) {
            return '<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
        }
        if ( type === 'close' ) {
            return '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        }
        if ( type === 'arrow' ) {
            return '<svg class="wpce-result-arrow" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
        }
        return '';
    }

    function buildWidget() {
        var trigger = document.createElement( 'button' );
        trigger.id          = 'wpce-trigger';
        trigger.setAttribute( 'aria-label', 'Search site content' );
        trigger.innerHTML   = icon( 'search' );

        var widget = document.createElement( 'div' );
        widget.id = 'wpce-widget';
        widget.innerHTML = [
            '<div class="wpce-widget-header">',
                '<div class="wpce-widget-header-left">',
                    '<div class="wpce-widget-icon">' + icon( 'search' ) + '</div>',
                    '<div>',
                        '<div class="wpce-widget-title">Site Search</div>',
                        '<div class="wpce-widget-subtitle">Semantic search powered by AI</div>',
                    '</div>',
                '</div>',
                '<button class="wpce-widget-close" id="wpce-close" aria-label="Close">',
                    icon( 'close' ),
                '</button>',
            '</div>',
            '<div class="wpce-search-wrap">',
                icon( 'search' ),
                '<input id="wpce-input" type="text" placeholder="Ask anything..." maxlength="' + MAX_CHARS + '" autocomplete="off" />',
            '</div>',
            '<div class="wpce-results" id="wpce-results">',
                '<div class="wpce-empty"><p>Start typing to search across all site content.</p></div>',
            '</div>',
            '<div class="wpce-widget-footer">',
                '<span class="wpce-footer-hint">Press Esc to close</span>',
                '<span class="wpce-footer-brand">WP Context Engine</span>',
            '</div>',
        ].join( '' );

        document.body.appendChild( trigger );
        document.body.appendChild( widget );

        return { trigger: trigger, widget: widget };
    }

    function renderSkeleton( container ) {
        var html = '<div class="wpce-skeleton">';
        for ( var i = 0; i < 3; i++ ) {
            html += [
                '<div class="wpce-skeleton-card">',
                    '<div class="wpce-skeleton-line" style="width:55%"></div>',
                    '<div class="wpce-skeleton-line"></div>',
                    '<div class="wpce-skeleton-line"></div>',
                '</div>',
            ].join( '' );
        }
        html += '</div>';
        container.innerHTML = html;
    }

    function renderResults( container, chunks ) {
        if ( ! chunks || chunks.length === 0 ) {
            container.innerHTML = '<div class="wpce-empty"><p>No results found for your query.</p></div>';
            return;
        }

        var html = chunks.map( function ( chunk ) {
            var title   = sanitize( chunk.title   || 'Untitled' );
            var content = sanitize( chunk.content || '' );
            var url     = chunk.url || '#';
            var score   = chunk.score ? Math.round( chunk.score * 100 ) + '% match' : '';
            var excerpt = content.length > 120 ? content.slice( 0, 120 ) + '...' : content;

            return [
                '<a href="' + sanitize( url ) + '" class="wpce-result-card" target="_blank" rel="noopener">',
                    '<div class="wpce-result-title">' + title + '</div>',
                    '<div class="wpce-result-excerpt">' + excerpt + '</div>',
                    '<div class="wpce-result-meta">',
                        '<span class="wpce-result-score">' + score + '</span>',
                        icon( 'arrow' ),
                    '</div>',
                '</a>',
            ].join( '' );
        } ).join( '' );

        container.innerHTML = html;
    }

    function renderError( container ) {
        container.innerHTML = '<div class="wpce-empty"><p>Something went wrong. Please try again.</p></div>';
    }

    function ask( question, container ) {
        if ( question.length < MIN_CHARS ) {
            container.innerHTML = '<div class="wpce-empty"><p>Start typing to search across all site content.</p></div>';
            return;
        }

        renderSkeleton( container );

        var body = new FormData();
        body.append( 'question', question.slice( 0, MAX_CHARS ) );

        fetch( restUrl + '/ask', {
            method:  'POST',
            headers: { 'X-WP-Nonce': nonce },
            body:    body,
        } )
        .then( function ( r ) {
            if ( ! r.ok ) throw new Error( r.status );
            return r.json();
        } )
        .then( function ( data ) {
            renderResults( container, data.context || [] );
        } )
        .catch( function () {
            renderError( container );
        } );
    }

    function init() {
        var els      = buildWidget();
        var trigger  = els.trigger;
        var widget   = els.widget;
        var input    = document.getElementById( 'wpce-input' );
        var results  = document.getElementById( 'wpce-results' );
        var closeBtn = document.getElementById( 'wpce-close' );

        var debouncedAsk = debounce( function ( q ) { ask( q, results ); }, DEBOUNCE_MS );

        trigger.addEventListener( 'click', function () {
            var open = widget.classList.contains( 'open' );
            widget.classList.toggle( 'open', ! open );
            if ( ! open ) input.focus();
        } );

        closeBtn.addEventListener( 'click', function () {
            widget.classList.remove( 'open' );
        } );

        input.addEventListener( 'input', function () {
            debouncedAsk( input.value.trim() );
        } );

        document.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Escape' ) widget.classList.remove( 'open' );
        } );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

} )();
