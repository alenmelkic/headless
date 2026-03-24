<?php
/**
 * Section Block — ACF Field Group (programmatic registration)
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key'    => 'group_block_section',
        'title'  => 'Section',
        'fields' => [
            [
                'key'         => 'field_section_background_color',
                'label'       => 'Background Color',
                'name'        => 'background_color',
                'type'        => 'color_picker',
                'enable_opacity' => 0,
                'return_format' => 'string',
            ],
            [
                'key'           => 'field_section_background_image',
                'label'         => 'Background Image',
                'name'          => 'background_image',
                'type'          => 'image',
                'return_format' => 'id',
                'preview_size'  => 'medium',
                'library'       => 'all',
            ],
            [
                'key'     => 'field_section_padding_size',
                'label'   => 'Padding Size',
                'name'    => 'padding_size',
                'type'    => 'select',
                'choices' => [
                    'sm' => 'Small',
                    'md' => 'Medium',
                    'lg' => 'Large',
                ],
                'default_value' => 'md',
                'return_format' => 'value',
                'ui'            => 1,
            ],
            [
                'key'   => 'field_section_full_width',
                'label' => 'Full Width Container',
                'name'  => 'full_width',
                'type'  => 'true_false',
                'ui'    => 1,
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'acf/section',
                ],
            ],
        ],
    ] );
} );
