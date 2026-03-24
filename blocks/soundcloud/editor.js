/**
 * SoundCloud Block — Editor Script
 *
 * Registers the block type and provides the track-picker editor UI.
 * Vanilla JS only — no JSX, no build step required.
 *
 * @package Headless
 */
( function ( blocks, element, blockEditor, apiFetch, components ) {
    'use strict';

    var el         = element.createElement;
    var useState   = element.useState;
    var useEffect  = element.useEffect;
    var Spinner    = components.Spinner;

    blocks.registerBlockType( 'headless/soundcloud', {

        edit: function ( props ) {
            var attributes   = props.attributes;
            var setAttributes = props.setAttributes;

            // State: tracks list, loading flag, error message, current view, filter text.
            var stateHook = useState( {
                tracks:  [],
                loading: false,
                error:   null,
                view:    attributes.track_url ? 'selected' : 'list',
                filter:  '',
            } );
            var state    = stateHook[0];
            var setState = stateHook[1];

            function merge( patch ) {
                setState( function ( s ) { return Object.assign( {}, s, patch ); } );
            }

            // Fetch tracks when the list view is active and tracks are not yet loaded.
            useEffect( function () {
                if ( state.view !== 'list' || state.tracks.length > 0 || state.loading ) {
                    return;
                }
                merge( { loading: true, error: null } );
                apiFetch( { path: '/headless/v1/soundcloud/tracks' } )
                    .then( function ( tracks ) {
                        merge( { tracks: tracks, loading: false } );
                    } )
                    .catch( function ( err ) {
                        merge( {
                            error:   ( err && err.message ) ? err.message : 'Failed to load tracks.',
                            loading: false,
                        } );
                    } );
            }, [ state.view ] );  // re-run whenever view changes to 'list'

            // ---- SELECTED VIEW ----
            if ( state.view === 'selected' && attributes.track_url ) {
                return el( 'div', { className: 'headless-soundcloud-block' },
                    el( 'div', {
                            style: {
                                display:     'flex',
                                gap:         '12px',
                                alignItems:  'center',
                                padding:     '12px',
                                background:  '#f6f7f7',
                                borderRadius: '4px',
                                border:      '1px solid #e0e0e0',
                            },
                        },
                        attributes.track_artwork
                            ? el( 'img', {
                                src:   attributes.track_artwork,
                                width:  56,
                                height: 56,
                                style: { objectFit: 'cover', borderRadius: '4px', flexShrink: 0 },
                              } )
                            : el( 'div', {
                                style: {
                                    width: 56, height: 56, background: '#ff5500',
                                    borderRadius: '4px', flexShrink: 0,
                                },
                              } ),
                        el( 'div', { style: { overflow: 'hidden', flex: 1 } },
                            el( 'div', {
                                style: {
                                    fontWeight: 600,
                                    overflow:   'hidden',
                                    textOverflow: 'ellipsis',
                                    whiteSpace: 'nowrap',
                                },
                            }, attributes.track_title || 'SoundCloud Track' ),
                            attributes.track_duration
                                ? el( 'div', { style: { fontSize: '12px', color: '#757575', marginTop: '2px' } },
                                    attributes.track_duration )
                                : null
                        )
                    ),
                    el( 'button', {
                        onClick: function () { merge( { view: 'list' } ); },
                        style:   { marginTop: '8px', cursor: 'pointer' },
                    }, 'Change track' )
                );
            }

            // ---- LOADING STATE ----
            if ( state.loading ) {
                return el( 'div', {
                        className: 'headless-soundcloud-block',
                        style:     { padding: '16px', textAlign: 'center' },
                    },
                    el( Spinner, null ),
                    el( 'p', { style: { marginTop: '8px' } }, 'Loading tracks\u2026' )
                );
            }

            // ---- ERROR STATE ----
            if ( state.error ) {
                return el( 'div', {
                        className: 'headless-soundcloud-block',
                        style:     { padding: '16px' },
                    },
                    el( 'p', { style: { color: '#cc0000', margin: '0 0 8px' } }, state.error ),
                    el( 'button', {
                        onClick: function () { merge( { error: null, loading: false, tracks: [] } ); },
                        style:   { cursor: 'pointer' },
                    }, 'Retry' )
                );
            }

            // ---- LIST VIEW ----
            var filtered = state.tracks.filter( function ( t ) {
                return ! state.filter ||
                    t.title.toLowerCase().indexOf( state.filter.toLowerCase() ) !== -1;
            } );

            return el( 'div', { className: 'headless-soundcloud-block' },

                // Filter input
                el( 'input', {
                    type:        'text',
                    placeholder: 'Filter tracks\u2026',
                    value:       state.filter,
                    onChange:    function ( e ) { merge( { filter: e.target.value } ); },
                    style:       {
                        width:       '100%',
                        marginBottom: '6px',
                        padding:     '6px 8px',
                        boxSizing:   'border-box',
                        border:      '1px solid #ccc',
                        borderRadius: '3px',
                    },
                } ),

                // Track list
                el( 'div', {
                        style: {
                            maxHeight:   '320px',
                            overflowY:   'auto',
                            border:      '1px solid #e0e0e0',
                            borderRadius: '4px',
                        },
                    },
                    filtered.length === 0
                        ? el( 'p', { style: { padding: '12px', color: '#757575', margin: 0 } }, 'No tracks found.' )
                        : filtered.map( function ( track, i ) {
                            var isSelected = attributes.track_url === track.url;
                            return el( 'div', {
                                    key:     track.url || i,
                                    onClick: function () {
                                        setAttributes( {
                                            track_url:      track.url,
                                            track_title:    track.title,
                                            track_duration: track.duration   || '',
                                            track_artwork:  track.artwork_url || '',
                                        } );
                                        merge( { view: 'selected' } );
                                    },
                                    style: {
                                        display:     'flex',
                                        gap:         '10px',
                                        alignItems:  'center',
                                        padding:     '8px 12px',
                                        cursor:      'pointer',
                                        borderBottom: '1px solid #f0f0f0',
                                        background:  isSelected ? '#e8f0fe' : 'white',
                                    },
                                },
                                track.artwork_url
                                    ? el( 'img', {
                                        src:    track.artwork_url,
                                        width:  40,
                                        height: 40,
                                        style:  { objectFit: 'cover', borderRadius: '2px', flexShrink: 0 },
                                      } )
                                    : el( 'div', {
                                        style: {
                                            width: 40, height: 40, background: '#ff5500',
                                            borderRadius: '2px', flexShrink: 0,
                                        },
                                      } ),
                                el( 'div', { style: { overflow: 'hidden', flex: 1 } },
                                    el( 'div', {
                                        style: {
                                            fontWeight:   isSelected ? 600 : 400,
                                            overflow:     'hidden',
                                            textOverflow: 'ellipsis',
                                            whiteSpace:   'nowrap',
                                        },
                                    }, track.title ),
                                    track.duration
                                        ? el( 'div', { style: { fontSize: '12px', color: '#757575' } }, track.duration )
                                        : null
                                )
                            );
                        } )
                )
            );
        },

        // Dynamic block — server-side rendered via render.php. save() must return null.
        save: function () {
            return null;
        },
    } );

} )(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.apiFetch,
    window.wp.components
);
