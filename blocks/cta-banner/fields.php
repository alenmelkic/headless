<?php
/**
 * CTA Banner Block — ACF Field Group (programmatic registration)
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key'    => 'group_block_cta_banner',
        'title'  => 'CTA Banner',
        'fields' => [
            [
                'key'         => 'field_cta_banner_heading',
                'label'       => 'Heading',
                'name'        => 'heading',
                'type'        => 'text',
                'required'    => 1,
                'placeholder' => 'Enter banner heading…',
            ],
            [
                'key'         => 'field_cta_banner_subheading',
                'label'       => 'Subheading',
                'name'        => 'subheading',
                'type'        => 'textarea',
                'rows'        => 2,
                'placeholder' => 'Enter supporting text…',
            ],
            [
                'key'   => 'field_cta_banner_background_color',
                'label' => 'Background Color',
                'name'  => 'background_color',
                'type'  => 'color_picker',
                'enable_opacity' => 0,
                'return_format'  => 'string',
            ],
            // --- Primary CTA ---
            [
                'key'     => 'field_cta_banner_primary_tab',
                'label'   => 'Primary Button',
                'name'    => '',
                'type'    => 'tab',
            ],
            [
                'key'         => 'field_cta_banner_primary_label',
                'label'       => 'Label',
                'name'        => 'primary_cta_label',
                'type'        => 'text',
                'placeholder' => 'e.g. Get Started',
            ],
            [
                'key'         => 'field_cta_banner_primary_url',
                'label'       => 'URL',
                'name'        => 'primary_cta_url',
                'type'        => 'url',
                'placeholder' => 'https://',
            ],
            [
                'key'   => 'field_cta_banner_primary_new_tab',
                'label' => 'Open in new tab',
                'name'  => 'primary_cta_new_tab',
                'type'  => 'true_false',
                'ui'    => 1,
            ],
            // --- Secondary CTA ---
            [
                'key'   => 'field_cta_banner_secondary_tab',
                'label' => 'Secondary Button',
                'name'  => '',
                'type'  => 'tab',
            ],
            [
                'key'         => 'field_cta_banner_secondary_label',
                'label'       => 'Label',
                'name'        => 'secondary_cta_label',
                'type'        => 'text',
                'placeholder' => 'e.g. Learn More',
            ],
            [
                'key'         => 'field_cta_banner_secondary_url',
                'label'       => 'URL',
                'name'        => 'secondary_cta_url',
                'type'        => 'url',
                'placeholder' => 'https://',
            ],
            [
                'key'   => 'field_cta_banner_secondary_new_tab',
                'label' => 'Open in new tab',
                'name'  => 'secondary_cta_new_tab',
                'type'  => 'true_false',
                'ui'    => 1,
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'acf/cta-banner',
                ],
            ],
        ],
    ] );
} );
