<?php
/**
 * Hero Block — ACF Field Group (programmatic registration)
 *
 * Keeping fields in PHP puts them in version control and avoids
 * relying on database-stored field groups.
 *
 * Remove this file if you prefer to manage fields via the ACF UI.
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key'    => 'group_block_hero',
        'title'  => 'Hero',
        'fields' => [
            [
                'key'         => 'field_hero_heading',
                'label'       => 'Heading',
                'name'        => 'heading',
                'type'        => 'text',
                'required'    => 1,
                'placeholder' => 'Enter heading…',
            ],
            [
                'key'         => 'field_hero_subheading',
                'label'       => 'Subheading',
                'name'        => 'subheading',
                'type'        => 'textarea',
                'rows'        => 3,
                'placeholder' => 'Enter subheading…',
            ],
            [
                'key'           => 'field_hero_background_image',
                'label'         => 'Background Image',
                'name'          => 'background_image',
                'type'          => 'image',
                'return_format' => 'id',
                'preview_size'  => 'medium',
                'library'       => 'all',
            ],
            [
                'key'         => 'field_hero_cta_label',
                'label'       => 'CTA Label',
                'name'        => 'cta_label',
                'type'        => 'text',
                'placeholder' => 'e.g. Learn more',
            ],
            [
                'key'         => 'field_hero_cta_url',
                'label'       => 'CTA URL',
                'name'        => 'cta_url',
                'type'        => 'url',
                'placeholder' => 'https://',
            ],
            [
                'key'   => 'field_hero_cta_new_tab',
                'label' => 'Open link in new tab',
                'name'  => 'cta_new_tab',
                'type'  => 'true_false',
                'ui'    => 1,
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'acf/hero',
                ],
            ],
        ],
    ] );
} );
