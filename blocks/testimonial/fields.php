<?php
/**
 * Testimonial Block — ACF Field Group (programmatic registration)
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key'    => 'group_block_testimonial',
        'title'  => 'Testimonial',
        'fields' => [
            [
                'key'         => 'field_testimonial_quote',
                'label'       => 'Quote',
                'name'        => 'quote',
                'type'        => 'textarea',
                'required'    => 1,
                'rows'        => 4,
                'placeholder' => 'Enter the customer quote…',
            ],
            [
                'key'         => 'field_testimonial_author_name',
                'label'       => 'Author Name',
                'name'        => 'author_name',
                'type'        => 'text',
                'required'    => 1,
                'placeholder' => 'e.g. Jane Doe',
            ],
            [
                'key'         => 'field_testimonial_author_title',
                'label'       => 'Author Title / Company',
                'name'        => 'author_title',
                'type'        => 'text',
                'placeholder' => 'e.g. CEO at Acme Inc.',
            ],
            [
                'key'           => 'field_testimonial_author_image',
                'label'         => 'Author Photo',
                'name'          => 'author_image',
                'type'          => 'image',
                'return_format' => 'id',
                'preview_size'  => 'thumbnail',
                'library'       => 'all',
            ],
            [
                'key'         => 'field_testimonial_rating',
                'label'       => 'Star Rating (0 = hidden)',
                'name'        => 'rating',
                'type'        => 'number',
                'min'         => 0,
                'max'         => 5,
                'step'        => 1,
                'default_value' => 5,
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'acf/testimonial',
                ],
            ],
        ],
    ] );
} );
