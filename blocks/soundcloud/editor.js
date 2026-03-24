/**
 * SoundCloud Block — Editor Script
 *
 * Registers the block type and provides the track-picker editor UI.
 * Uses wp.blockEditor.useBlockProps so Gutenberg attaches the standard
 * block toolbar (drag handle, move up/down, more options).
 * Vanilla JS only — no JSX, no build step required.
 *
 * @package Headless
 */
( function ( blocks, element, apiFetch, components, blockEditor ) {
    'use strict';

    var el             = element.createElement;
    var useState       = element.useState;
    var useEffect      = element.useEffect;
    var useBlockProps  = blockEditor.useBlockProps;
    var BlockControls  = blockEditor.BlockControls;
    var Spinner        = components.Spinner;
    var Button         = components.Button;
    var TextControl    = components.TextControl;
    var Placeholder    = components.Placeholder;
    var ToolbarGroup   = components.ToolbarGroup;
    var ToolbarButton  = components.ToolbarButton;

    blocks.registerBlockType( 'headless/soundcloud', {

        edit: function ( props ) {
            var attributes    = props.attributes;
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
            // Intentionally omitting state.tracks.length and state.loading from the dep array:
            // the early-return guard reads them from the render closure, so they don't trigger
            // the effect themselves. Adding them would cause an infinite fetch loop.
            }, [ state.view ] );  // re-run whenever view changes to 'list'

            // useBlockProps() attaches the Gutenberg block wrapper attributes so the
            // editor can render the drag handle, move-up/down arrows, and block toolbar.
            var blockProps = useBlockProps();

            // ---- SELECTED VIEW ----
            if ( state.view === 'selected' && attributes.track_url ) {
                return el( 'div', blockProps,
                    el( BlockControls, null,
                        el( ToolbarGroup, null,
                            el( ToolbarButton, {
                                icon:    'edit',
                                label:   'Change track',
                                onClick: function () { merge( { view: 'list' } ); },
                            } )
                        )
                    ),
                    el( 'div', {
                            style: {
                                display:      'flex',
                                gap:          '12px',
                                alignItems:   'center',
                                padding:      '12px 16px',
                                background:   '#f6f7f7',
                                borderRadius: '4px',
                                border:       '1px solid #e0e0e0',
                            },
                        },
                        attributes.track_artwork
                            ? el( 'img', {
                                src:    attributes.track_artwork,
                                alt:    attributes.track_title || 'SoundCloud track artwork',
                                width:  56,
                                height: 56,
                                style:  { objectFit: 'cover', borderRadius: '4px', flexShrink: 0 },
                              } )
                            : el( 'div', {
                                style: {
                                    width:           56,
                                    height:          56,
                                    background:      '#ff5500',
                                    borderRadius:    '4px',
                                    flexShrink:      0,
                                    display:         'flex',
                                    alignItems:      'center',
                                    justifyContent:  'center',
                                },
                              },
                                el( 'span', {
                                    className: 'dashicons dashicons-format-audio',
                                    style:     { color: '#fff', fontSize: '24px', width: 'auto', height: 'auto' },
                                } )
                              ),
                        el( 'div', { style: { overflow: 'hidden', flex: 1 } },
                            el( 'div', {
                                style: {
                                    fontWeight:   600,
                                    fontSize:     '14px',
                                    overflow:     'hidden',
                                    textOverflow: 'ellipsis',
                                    whiteSpace:   'nowrap',
                                    color:        '#1e1e1e',
                                },
                            }, attributes.track_title || 'SoundCloud Track' ),
                            attributes.track_duration
                                ? el( 'div', {
                                    style: { fontSize: '12px', color: '#757575', marginTop: '2px' },
                                  }, attributes.track_duration )
                                : null,
                            el( 'div', { style: { marginTop: '8px' } },
                                el( Button, {
                                    variant:  'secondary',
                                    isSmall:  true,
                                    onClick:  function () { merge( { view: 'list' } ); },
                                }, 'Change track' )
                            )
                        )
                    )
                );
            }

            // ---- LOADING STATE ----
            if ( state.loading ) {
                return el( 'div', blockProps,
                    el( Placeholder, {
                        icon:  'format-audio',
                        label: 'SoundCloud',
                    },
                        el( 'div', { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
                            el( Spinner ),
                            el( 'span', null, 'Loading tracks\u2026' )
                        )
                    )
                );
            }

            // ---- ERROR STATE ----
            if ( state.error ) {
                return el( 'div', blockProps,
                    el( Placeholder, {
                        icon:  'warning',
                        label: 'SoundCloud',
                    },
                        el( 'p', { style: { color: '#cc0000', margin: '0 0 12px' } }, state.error ),
                        el( Button, {
                            variant: 'primary',
                            onClick: function () { merge( { error: null, loading: false, tracks: [] } ); },
                        }, 'Retry' )
                    )
                );
            }

            // ---- LIST VIEW ----
            var filtered = state.tracks.filter( function ( t ) {
                return ! state.filter ||
                    t.title.toLowerCase().indexOf( state.filter.toLowerCase() ) !== -1;
            } );

            return el( 'div', blockProps,
                el( Placeholder, {
                    icon:         'format-audio',
                    label:        'SoundCloud',
                    instructions: 'Select a track to embed.',
                },
                    el( 'div', { style: { width: '100%' } },
                        el( TextControl, {
                            placeholder: 'Filter tracks\u2026',
                            value:       state.filter,
                            onChange:    function ( val ) { merge( { filter: val } ); },
                        } ),
                        el( 'div', {
                                style: {
                                    maxHeight:    '320px',
                                    overflowY:    'auto',
                                    border:       '1px solid #e0e0e0',
                                    borderRadius: '4px',
                                    background:   '#fff',
                                    marginTop:    '4px',
                                },
                            },
                            filtered.length === 0
                                ? el( 'p', {
                                    style: { padding: '12px', color: '#757575', margin: 0, fontSize: '13px' },
                                  }, 'No tracks found.' )
                                : filtered.map( function ( track, i ) {
                                    var isSelected = attributes.track_url === track.url;
                                    return el( 'div', {
                                            key:     track.url || i,
                                            onClick: function () {
                                                setAttributes( {
                                                    track_url:      track.url,
                                                    track_title:    track.title,
                                                    track_duration: track.duration    || '',
                                                    track_artwork:  track.artwork_url || '',
                                                } );
                                                merge( { view: 'selected' } );
                                            },
                                            style: {
                                                display:      'flex',
                                                gap:          '10px',
                                                alignItems:   'center',
                                                padding:      '8px 12px',
                                                cursor:       'pointer',
                                                borderBottom: '1px solid #f0f0f0',
                                                background:   isSelected ? '#e8f0fe' : 'white',
                                            },
                                        },
                                        track.artwork_url
                                            ? el( 'img', {
                                                src:    track.artwork_url,
                                                alt:    track.title || 'SoundCloud track artwork',
                                                width:  40,
                                                height: 40,
                                                style:  { objectFit: 'cover', borderRadius: '2px', flexShrink: 0 },
                                              } )
                                            : el( 'div', {
                                                style: {
                                                    width:          40,
                                                    height:         40,
                                                    background:     '#ff5500',
                                                    borderRadius:   '2px',
                                                    flexShrink:     0,
                                                },
                                              } ),
                                        el( 'div', { style: { overflow: 'hidden', flex: 1 } },
                                            el( 'div', {
                                                style: {
                                                    fontWeight:   isSelected ? 600 : 400,
                                                    fontSize:     '13px',
                                                    overflow:     'hidden',
                                                    textOverflow: 'ellipsis',
                                                    whiteSpace:   'nowrap',
                                                    color:        '#1e1e1e',
                                                },
                                            }, track.title ),
                                            track.duration
                                                ? el( 'div', {
                                                    style: { fontSize: '12px', color: '#757575', marginTop: '1px' },
                                                  }, track.duration )
                                                : null
                                        ),
                                        isSelected
                                            ? el( 'span', {
                                                className: 'dashicons dashicons-yes-alt',
                                                style:     { color: '#007cba', marginLeft: 'auto', flexShrink: 0 },
                                              } )
                                            : null
                                    );
                                } )
                        )
                    )
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
    window.wp.apiFetch,
    window.wp.components,
    window.wp.blockEditor
);
