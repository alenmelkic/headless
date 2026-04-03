/**
 * Facebook Video Block — Editor Script
 *
 * Vanilla JS only — no JSX, no build step required.
 *
 * @package Headless
 */
( function ( blocks, element, components, blockEditor ) {
    'use strict';

    var el              = element.createElement;
    var useBlockProps   = blockEditor.useBlockProps;
    var BlockControls   = blockEditor.BlockControls;
    var MediaUpload     = blockEditor.MediaUpload;
    var MediaUploadCheck = blockEditor.MediaUploadCheck;
    var TextControl     = components.TextControl;
    var Button          = components.Button;
    var Placeholder     = components.Placeholder;
    var ToolbarGroup    = components.ToolbarGroup;
    var ToolbarButton   = components.ToolbarButton;

    function isFacebookUrl( url ) {
        return /(?:facebook\.com|fb\.watch)/.test( url || '' );
    }

    blocks.registerBlockType( 'headless/fb-video', {

        edit: function ( props ) {
            var attributes    = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps    = useBlockProps();

            var videoUrl    = attributes.videoUrl    || '';
            var coverImage  = attributes.coverImage  || null;
            var orientation = attributes.orientation || '';
            var valid       = ! videoUrl || isFacebookUrl( videoUrl );

            var onSelectImage = function ( media ) {
                setAttributes( {
                    coverImage: {
                        id:     media.id,
                        url:    media.url,
                        alt:    media.alt || '',
                        width:  media.width,
                        height: media.height,
                    },
                } );
            };

            var onRemoveImage = function () {
                setAttributes( { coverImage: null } );
            };

            // ---- EMPTY STATE ----
            if ( ! videoUrl && ! coverImage ) {
                return el( 'div', blockProps,
                    el( Placeholder, {
                        icon:         'facebook',
                        label:        'Facebook Video',
                        instructions: 'Dodajte Facebook video link, odaberite orijentaciju i naslovnu sliku.',
                    },
                        el( 'div', { style: { width: '100%' } },
                            el( TextControl, {
                                label:       'Facebook URL',
                                value:       videoUrl,
                                onChange:     function ( val ) { setAttributes( { videoUrl: val } ); },
                                placeholder: 'https://facebook.com/watch?v=... ili https://facebook.com/reel/...',
                                __nextHasNoMarginBottom: true,
                            } ),
                            el( 'div', { style: { marginTop: '8px' } },
                                el( MediaUploadCheck, null,
                                    el( MediaUpload, {
                                        onSelect:     onSelectImage,
                                        allowedTypes: [ 'image' ],
                                        render:       function ( renderProps ) {
                                            return el( Button, {
                                                variant: 'secondary',
                                                onClick: renderProps.open,
                                            }, 'Dodaj naslovnu sliku' );
                                        },
                                    } )
                                )
                            )
                        )
                    )
                );
            }

            // ---- CONFIGURED STATE ----
            return el( 'div', blockProps,
                el( BlockControls, null,
                    el( ToolbarGroup, null,
                        el( ToolbarButton, {
                            icon:    'edit',
                            label:   'Edit video',
                            onClick: function () {
                                setAttributes( { videoUrl: '', coverImage: null, orientation: '' } );
                            },
                        } )
                    )
                ),

                el( 'div', {
                    style: { border: '1px solid #ddd', borderRadius: '8px', overflow: 'hidden' },
                },
                    // Cover image preview
                    el( 'div', {
                        style: {
                            position: 'relative', aspectRatio: '16/9',
                            background: coverImage ? '#000' : '#1877f2',
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                        },
                    },
                        coverImage
                            ? el( 'img', { src: coverImage.url, alt: coverImage.alt || '', style: { width: '100%', height: '100%', objectFit: 'cover' } } )
                            : el( 'div', { style: { color: '#fff', fontSize: '14px', opacity: 0.7 } }, 'Nema naslovne slike' ),
                        el( 'div', { style: { position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', pointerEvents: 'none' } },
                            el( 'div', { style: { width: '64px', height: '64px', borderRadius: '50%', background: 'rgba(255,255,255,0.9)', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 2px 8px rgba(0,0,0,0.2)' } },
                                el( 'svg', { width: 28, height: 28, viewBox: '0 0 24 24', fill: '#1877f2' },
                                    el( 'path', { d: 'M8 5v14l11-7z' } )
                                )
                            )
                        )
                    ),

                    // Controls
                    el( 'div', { style: { padding: '12px', display: 'flex', flexDirection: 'column', gap: '10px' } },
                        el( TextControl, {
                            label:       'Facebook URL',
                            value:       videoUrl,
                            onChange:     function ( val ) { setAttributes( { videoUrl: val } ); },
                            placeholder: 'https://facebook.com/watch?v=... ili https://facebook.com/reel/...',
                            __nextHasNoMarginBottom: true,
                        } ),

                        ! valid
                            ? el( 'span', { style: { fontSize: '12px', color: '#cc0000' } }, 'Unesite ispravan Facebook link.' )
                            : el( 'span', { style: { display: 'inline-block', fontSize: '11px', fontWeight: 600, padding: '2px 8px', borderRadius: '3px', background: '#1877f2', color: '#fff' } }, 'Facebook' ),

                        // Orientation picker (mandatory)
                        el( 'div', { style: { display: 'flex', flexDirection: 'column', gap: '6px' } },
                            el( 'label', {
                                style: { fontSize: '11px', fontWeight: 600, textTransform: 'uppercase', color: '#1e1e1e' },
                            }, 'Orijentacija videa *' ),
                            el( 'div', { style: { display: 'flex', gap: '8px' } },
                                el( Button, {
                                    variant: orientation === 'horizontal' ? 'primary' : 'secondary',
                                    isSmall: true,
                                    onClick: function () { setAttributes( { orientation: 'horizontal' } ); },
                                    style:   orientation === 'horizontal' ? {} : { opacity: 0.7 },
                                }, 'Horizontalno' ),
                                el( Button, {
                                    variant: orientation === 'vertical' ? 'primary' : 'secondary',
                                    isSmall: true,
                                    onClick: function () { setAttributes( { orientation: 'vertical' } ); },
                                    style:   orientation === 'vertical' ? {} : { opacity: 0.7 },
                                }, 'Vertikalno' )
                            ),
                            ! orientation
                                ? el( 'span', { style: { fontSize: '12px', color: '#cc0000' } }, 'Obavezno odaberite orijentaciju.' )
                                : null
                        ),

                        // Cover image controls
                        el( MediaUploadCheck, null,
                            el( MediaUpload, {
                                onSelect:     onSelectImage,
                                allowedTypes: [ 'image' ],
                                value:        coverImage ? coverImage.id : undefined,
                                render:       function ( renderProps ) {
                                    return el( 'div', { style: { display: 'flex', gap: '8px' } },
                                        el( Button, { variant: 'secondary', onClick: renderProps.open }, coverImage ? 'Zamijeni sliku' : 'Dodaj naslovnu sliku' ),
                                        coverImage
                                            ? el( Button, { variant: 'tertiary', isDestructive: true, onClick: onRemoveImage }, 'Ukloni sliku' )
                                            : null
                                    );
                                },
                            } )
                        )
                    )
                )
            );
        },

        save: function () { return null; },
    } );

} )(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
    window.wp.blockEditor
);
