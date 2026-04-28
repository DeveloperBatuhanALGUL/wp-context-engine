( function () {
    'use strict';

    var config  = window.wpceVisitor || {};
    var restUrl = config.restUrl || '';
    var nonce   = config.nonce   || '';

    if ( ! restUrl ) {
        return;
    }

    var WIDGET_ID   = 'wpce-visitor-widget';
    var TRIGGER_ID  = 'wpce-visitor-trigger';
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

    function buildWidget() {
        var trigger = document.createElement( 'button' );
        trigger.id        = TRIGGER_ID;
        trigger.innerHTML = '&#128269;';
        trigger.setAttribute( 'aria-label', 'Search site content' );
        trigger.style.cssText = [
            'position:fixed',
            'bottom:24px',
            'right:24px',
            'width:48px',
            'height:48px',
            'border-radius:50%',
            'background:#007cba',
            'color:#fff',
            'border:none',
            'font-size:20px',
            'cursor:pointer',
            'box-shadow:0 2px 8px rgba(0,0,0,.25)',
            'z-index:99998',
            'display:flex',
            'align-items:center',
            'justify-content:center',
        ].join( ';' );

        var widget = document.createElement( 'div' );
        widget.id         = WIDGET_ID;
        widget.style.cssText = [
            'position:fixed',
            'bottom:84px',
            'right:24px',
            'width:320px',
            'max-height:480px',
            'background:#fff',
            'border:1px solid #ddd',
            'border-radius:8px',
            'box-shadow:0 4px 16px rgba(0,0,0,.15)',
            'z-index:99999',
            'display:none',
            'flex-direction:column',
            'overflow:hidden',
            'font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif',
            'font-size:13px',
        ].join( ';' );

        widget.innerHTML = [
            '<div style="padding:12px;border-bottom:1px solid #eee;display:flex;gap:8px;align-items:center;">',
                '<input id="wpce-visitor-input" type="text" placeholder="Search this site..." ',
                    'style="flex:1;border:1px solid #ddd;border-radius:4px;padding:6px 10px;font-size:13px;outline:none;" ',
                    'maxlength="' + MAX_CHARS + '" autocomplete="off" />',
                '<button id="wpce-visitor-close" aria-label="Close" ',
                    'style="background:none;border:none;cursor:pointer;font-size:16px;color:#888;padding:0 4px;">&#x2715;</button>',
            '</div>',
            '<div id="wpce-visitor-results" style="overflow-y:auto;padding:12px;flex:1;"></div>',
        ].join( '' );

        document.body.appendChild( trigger );
        document.body.appendChild( widget );

        return { trigger: trigger, widget: widget };
    }

    function renderResults( container, question, chunks ) {
        if ( ! chunks || chunks.length === 0 ) {
            container.innerHTML = '<p style="color:#888;margin:0;">No results found.</p>';
            return;
        }

        var html = chunks.map( function ( chunk ) {
            var title   = sanitize( chunk.title   || '' );
            var content = sanitize( chunk.content || '' );
            var url     = chunk.url || '#';
            var score   = chunk.score ? ( chunk.score * 100 ).toFixed( 1 ) + '%' : '';
            var excerpt = content.length > 150 ? content.slice( 0, 150 ) + '...' : content;

            return [
                '<div style="margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid #f0f0f0;">',
                    title
                        ? '<a href="' + sanitize( url ) + '" style="font-weight:600;color:#007cba;text-decoration:none;display:block;margin-bottom:4px;">' + title + '</a>'
                        : '',
                    '<div style="color:#555;line-height:1.5;">' + excerpt + '</div>',
                    score
                        ? '<div style="margin-top:4px;color:#bbb;font-size:11px;">Relevance: ' + score + '</div>'
                        : '',
                '</div>',
            ].join( '' );
        } ).join( '' );

        container.innerHTML = html;
    }

    function renderLoading( container ) {
        container.innerHTML = '<p style="color:#888;margin:0;">Searching...</p>';
    }

    function renderError( container ) {
        container.innerHTML = '<p style="color:#c00;margin:0;">Something went wrong. Please try again.</p>';
    }

    function ask( question, container ) {
        if ( question.length < MIN_CHARS ) {
            container.innerHTML = '';
            return;
        }

        renderLoading( container );

        var body = new FormData();
        body.append( 'question', question.slice( 0, MAX_CHARS ) );

        fetch( restUrl + '/ask', {
            method:  'POST',
            headers: { 'X-WP-Nonce': nonce },
            body:    body,
        } )
        .then( function ( r ) {
            if ( ! r.ok ) {
                throw new Error( r.status );
            }
            return r.json();
        } )
        .then( function ( data ) {
            renderResults( container, question, data.context || [] );
        } )
        .catch( function () {
            renderError( container );
        } );
    }

    function init() {
        var els       = buildWidget();
        var trigger   = els.trigger;
        var widget    = els.widget;
        var input     = widget.querySelector( '#wpce-visitor-input' );
        var results   = widget.querySelector( '#wpce-visitor-results' );
        var closeBtn  = widget.querySelector( '#wpce-visitor-close' );
        var debouncedAsk = debounce( function ( q ) { ask( q, results ); }, DEBOUNCE_MS );

        trigger.addEventListener( 'click', function () {
            var open = widget.style.display === 'flex';
            widget.style.display = open ? 'none' : 'flex';
            if ( ! open ) {
                input.focus();
            }
        } );

        closeBtn.addEventListener( 'click', function () {
            widget.style.display = 'none';
        } );

        input.addEventListener( 'input', function () {
            debouncedAsk( input.value.trim() );
        } );

        document.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Escape' ) {
                widget.style.display = 'none';
            }
        } );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

} )();
