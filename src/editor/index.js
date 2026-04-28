( function ( wp ) {
    var el          = wp.element.createElement;
    var useState    = wp.element.useState;
    var useEffect   = wp.element.useEffect;
    var useSelect   = wp.data.useSelect;
    var apiFetch    = wp.apiFetch;
    var Panel       = wp.components.Panel;
    var PanelBody   = wp.components.PanelBody;
    var Spinner     = wp.components.Spinner;
    var Notice      = wp.components.Notice;
    var registerPlugin     = wp.plugins.registerPlugin;
    var PluginSidebar      = wp.editPost.PluginSidebar;
    var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;

    function debounce( fn, delay ) {
        var timer;
        return function () {
            var args = arguments;
            clearTimeout( timer );
            timer = setTimeout( function () { fn.apply( null, args ); }, delay );
        };
    }

    function ContextChunk( props ) {
        var chunk = props.chunk;
        var title = chunk.post_id
            ? ( window.wpceEditor && window.wpceEditor.postTitles && window.wpceEditor.postTitles[ chunk.post_id ] ) || ( 'Post #' + chunk.post_id )
            : '';

        return el(
            'div',
            {
                style: {
                    borderLeft: '3px solid #007cba',
                    paddingLeft: '10px',
                    marginBottom: '16px',
                    fontSize: '12px',
                    lineHeight: '1.6',
                }
            },
            title && el(
                'div',
                { style: { fontWeight: '600', marginBottom: '4px', color: '#007cba' } },
                title
            ),
            el(
                'div',
                { style: { color: '#444', wordBreak: 'break-word' } },
                chunk.content.length > 200 ? chunk.content.slice( 0, 200 ) + '...' : chunk.content
            ),
            el(
                'div',
                { style: { marginTop: '4px', color: '#999' } },
                'Score: ' + ( chunk.score * 100 ).toFixed( 1 ) + '%'
            )
        );
    }

    function ContextPanel() {
        var postId  = useSelect( function ( select ) {
            return select( 'core/editor' ).getCurrentPostId();
        } );

        var content = useSelect( function ( select ) {
            return select( 'core/editor' ).getEditedPostAttribute( 'content' );
        } );

        var title = useSelect( function ( select ) {
            return select( 'core/editor' ).getEditedPostAttribute( 'title' );
        } );

        var chunks   = useState( [] );
        var loading  = useState( false );
        var error    = useState( null );

        var setChunks  = chunks[1]; chunks  = chunks[0];
        var setLoading = loading[1]; loading = loading[0];
        var setError   = error[1]; error   = error[0];

        var fetchContext = debounce( function ( input, pid ) {
            if ( ! input || input.length < 10 ) {
                setChunks( [] );
                return;
            }

            setLoading( true );
            setError( null );

            apiFetch( {
                url:    wpceEditor.restUrl + '/suggest',
                method: 'POST',
                headers: { 'X-WP-Nonce': wpceEditor.nonce },
                data:   { input: input.slice( 0, 500 ), post_id: pid },
            } )
            .then( function ( res ) {
                setChunks( res.chunks || [] );
                setLoading( false );
            } )
            .catch( function () {
                setError( 'Could not fetch context. Check your API key.' );
                setLoading( false );
            } );
        }, 800 );

        useEffect( function () {
            var input = ( title || '' ) + ' ' + ( content || '' );
            fetchContext( input.trim(), postId );
        }, [ title, content ] );

        return el(
            Panel,
            null,
            el(
                PanelBody,
                { title: 'Related Content', initialOpen: true },
                loading && el(
                    'div',
                    { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
                    el( Spinner ),
                    el( 'span', { style: { fontSize: '12px', color: '#888' } }, 'Searching your content...' )
                ),
                error && el(
                    Notice,
                    { status: 'error', isDismissible: false },
                    error
                ),
                ! loading && ! error && chunks.length === 0 && el(
                    'p',
                    { style: { fontSize: '12px', color: '#888', margin: 0 } },
                    'Start writing to see related content from your site.'
                ),
                ! loading && chunks.map( function ( chunk, i ) {
                    return el( ContextChunk, { key: i, chunk: chunk } );
                } )
            )
        );
    }

    registerPlugin( 'wpce-context-sidebar', {
        render: function () {
            return el(
                wp.element.Fragment,
                null,
                el(
                    PluginSidebarMoreMenuItem,
                    { target: 'wpce-context-sidebar' },
                    'Context Engine'
                ),
                el(
                    PluginSidebar,
                    {
                        name:  'wpce-context-sidebar',
                        title: 'Context Engine',
                        icon:  'search',
                    },
                    el( ContextPanel )
                )
            );
        }
    } );

} )( window.wp );
