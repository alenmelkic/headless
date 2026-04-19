/**
 * Mali Banneri Block — Editor Script
 *
 * Block with optional title & description. Banner data comes from Marketing
 * settings (wp_options).
 * Vanilla JS only — no JSX, no build step required.
 *
 * @package Headless
 */
( function ( blocks, element, components, blockEditor, apiFetch ) {
    'use strict';

    var el             = element.createElement;
    var useState       = element.useState;
    var useEffect      = element.useEffect;
    var useBlockProps  = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var RichText       = blockEditor.RichText;
    var Placeholder    = components.Placeholder;
    var Spinner        = components.Spinner;
    var PanelBody      = components.PanelBody;
    var TextControl    = components.TextControl;
    var TextareaControl = components.TextareaControl;
    var ToggleControl  = components.ToggleControl;

    blocks.registerBlockType( 'headless/mali-banneri', {

        edit: function ( props ) {
            var attributes    = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps    = useBlockProps();

            var stateHook = useState( { banners: [], loading: true, error: null } );
            var state     = stateHook[0];
            var setState  = stateHook[1];

            useEffect( function () {
                apiFetch( { path: '/headless/v1/marketing' } )
                    .then( function ( data ) {
                        var mali = data && data.maliBanneri ? data.maliBanneri : [];
                        setState( { banners: mali, loading: false, error: null } );
                    } )
                    .catch( function ( err ) {
                        setState( {
                            banners: [],
                            loading: false,
                            error: ( err && err.message ) ? err.message : 'Greška pri učitavanju.',
                        } );
                    } );
            }, [] );

            // Sidebar settings panel
            var inspector = el( InspectorControls, null,
                el( PanelBody, { title: 'Postavke', initialOpen: true },
                    el( TextControl, {
                        label:    'Naslov',
                        value:    attributes.title || '',
                        onChange: function ( val ) { setAttributes( { title: val } ); },
                        placeholder: 'npr. Naši partneri',
                    } ),
                    el( TextareaControl, {
                        label:    'Opis',
                        value:    attributes.description || '',
                        onChange: function ( val ) { setAttributes( { description: val } ); },
                        placeholder: 'Kratki opis ispod naslova',
                        rows:     2,
                    } )
                ),
                el( PanelBody, { title: 'CTA dugme', initialOpen: false },
                    el( TextControl, {
                        label:    'Tekst dugmeta',
                        value:    attributes.ctaLabel || '',
                        onChange: function ( val ) { setAttributes( { ctaLabel: val } ); },
                        placeholder: 'npr. Pogledaj sve',
                    } ),
                    el( TextControl, {
                        label:    'URL',
                        value:    attributes.ctaUrl || '',
                        onChange: function ( val ) { setAttributes( { ctaUrl: val } ); },
                        placeholder: 'https://',
                        type:     'url',
                    } ),
                    el( ToggleControl, {
                        label:    'Otvori u novom tabu',
                        checked:  !! attributes.ctaNewTab,
                        onChange: function ( val ) { setAttributes( { ctaNewTab: val } ); },
                    } )
                )
            );

            if ( state.loading ) {
                return el( 'div', blockProps,
                    inspector,
                    el( Placeholder, {
                        icon:  'images-alt2',
                        label: 'Mali Banneri',
                    },
                        el( 'div', { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
                            el( Spinner ),
                            el( 'span', null, 'Učitavanje bannera\u2026' )
                        )
                    )
                );
            }

            if ( state.error ) {
                return el( 'div', blockProps,
                    inspector,
                    el( Placeholder, {
                        icon:  'warning',
                        label: 'Mali Banneri',
                    },
                        el( 'p', { style: { color: '#cc0000', margin: 0 } }, state.error )
                    )
                );
            }

            if ( state.banners.length === 0 ) {
                return el( 'div', blockProps,
                    inspector,
                    el( Placeholder, {
                        icon:         'images-alt2',
                        label:        'Mali Banneri',
                        instructions: 'Nema dodanih malih bannera. Dodajte ih u Marketing → Mali banneri.',
                    } )
                );
            }

            // Show preview with inline-editable title & description
            return el( 'div', blockProps,
                inspector,
                el( 'div', {
                        style: {
                            background:   '#f6f7f7',
                            border:       '1px solid #e0e0e0',
                            borderRadius: '4px',
                            padding:      '16px 20px',
                        },
                    },
                    // Inline editable title
                    el( RichText, {
                        tagName:     'h3',
                        value:       attributes.title || '',
                        onChange:     function ( val ) { setAttributes( { title: val } ); },
                        placeholder: 'Naslov (opcionalno)',
                        style: {
                            margin:     '0 0 4px',
                            fontSize:   '18px',
                            fontWeight: 700,
                            color:      '#1e1e1e',
                        },
                    } ),
                    // Inline editable description
                    el( RichText, {
                        tagName:     'p',
                        value:       attributes.description || '',
                        onChange:     function ( val ) { setAttributes( { description: val } ); },
                        placeholder: 'Opis (opcionalno)',
                        style: {
                            margin:    '0 0 12px',
                            fontSize:  '14px',
                            color:     '#757575',
                        },
                    } ),
                    // Banner count label
                    el( 'div', {
                            style: {
                                display:      'flex',
                                alignItems:   'center',
                                gap:          '8px',
                                marginBottom: '8px',
                                fontWeight:   600,
                                fontSize:     '13px',
                                color:        '#1e1e1e',
                            },
                        },
                        el( 'span', { className: 'dashicons dashicons-images-alt2', style: { color: '#007cba' } } ),
                        state.banners.length + ' bannera'
                    ),
                    // Banner thumbnails
                    el( 'div', {
                            style: {
                                display:       'flex',
                                gap:           '8px',
                                overflowX:     'auto',
                                paddingBottom: '4px',
                            },
                        },
                        state.banners.map( function ( banner, i ) {
                            return el( 'img', {
                                key:    i,
                                src:    banner.image.url,
                                alt:    banner.alt || '',
                                width:  130,
                                height: 72,
                                style: {
                                    objectFit:    'cover',
                                    borderRadius: '4px',
                                    flexShrink:   0,
                                    border:       '1px solid #ddd',
                                },
                            } );
                        } )
                    ),
                    el( 'p', {
                        style: { margin: '8px 0 0', fontSize: '12px', color: '#757575' },
                    }, 'Upravljajte bannerima u Marketing → Mali banneri.' ),

                    // CTA fields inline
                    el( 'div', {
                            style: {
                                marginTop:    '16px',
                                paddingTop:   '12px',
                                borderTop:    '1px solid #e0e0e0',
                            },
                        },
                        el( 'div', {
                                style: {
                                    display:      'flex',
                                    alignItems:   'center',
                                    gap:          '6px',
                                    marginBottom: '8px',
                                    fontWeight:   600,
                                    fontSize:     '13px',
                                    color:        '#1e1e1e',
                                },
                            },
                            el( 'span', { className: 'dashicons dashicons-admin-links', style: { color: '#007cba', fontSize: '16px', width: '16px', height: '16px' } } ),
                            'CTA dugme'
                        ),
                        el( 'div', { style: { display: 'flex', gap: '8px', flexWrap: 'wrap' } },
                            el( TextControl, {
                                value:       attributes.ctaLabel || '',
                                onChange:     function ( val ) { setAttributes( { ctaLabel: val } ); },
                                placeholder: 'Tekst dugmeta',
                                __nextHasNoMarginBottom: true,
                                style: { flex: '1', minWidth: '120px' },
                            } ),
                            el( TextControl, {
                                value:       attributes.ctaUrl || '',
                                onChange:     function ( val ) { setAttributes( { ctaUrl: val } ); },
                                placeholder: 'https://',
                                type:        'url',
                                __nextHasNoMarginBottom: true,
                                style: { flex: '2', minWidth: '200px' },
                            } )
                        ),
                        el( ToggleControl, {
                            label:   'Otvori u novom tabu',
                            checked: !! attributes.ctaNewTab,
                            onChange: function ( val ) { setAttributes( { ctaNewTab: val } ); },
                            __nextHasNoMarginBottom: true,
                        } )
                    )
                )
            );
        },

        save: function () {
            return null;
        },
    } );

} )(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
    window.wp.blockEditor,
    window.wp.apiFetch
);
