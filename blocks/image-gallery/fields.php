<?php
/**
 * Image Gallery Block — ACF Field Group
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key'    => 'group_block_image_gallery',
        'title'  => 'Image Gallery',
        'fields' => [
            [
                'key'         => 'field_gallery_title',
                'label'       => 'Title',
                'name'        => 'title',
                'type'        => 'text',
                'placeholder' => 'Optional gallery heading',
            ],
            [
                'key'           => 'field_gallery_images',
                'label'         => 'Images',
                'name'          => 'images',
                'type'          => 'gallery',
                'required'      => 1,
                'return_format' => 'id',
                'preview_size'  => 'medium',
                'library'       => 'all',
                'instructions'  => 'Select or upload images for the gallery.',
            ],
            [
                'key'           => 'field_gallery_columns',
                'label'         => 'Columns',
                'name'          => 'columns',
                'type'          => 'select',
                'choices'       => [
                    '2' => '2 Columns',
                    '3' => '3 Columns',
                    '4' => '4 Columns',
                ],
                'default_value' => '3',
                'return_format' => 'value',
                'ui'            => 0,
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'acf/image-gallery',
                ],
            ],
        ],
    ] );
} );
