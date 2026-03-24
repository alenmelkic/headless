<?php
/**
 * Card Block — ACF Field Group (programmatic registration)
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
        'key'    => 'group_block_card',
        'title'  => 'Card',
        'fields' => [
            [
                'key'           => 'field_card_image',
                'label'         => 'Image',
                'name'          => 'image',
                'type'          => 'image',
                'return_format' => 'id',
                'preview_size'  => 'medium',
                'library'       => 'all',
            ],
            [
                'key'         => 'field_card_title',
                'label'       => 'Title',
                'name'        => 'title',
                'type'        => 'text',
                'required'    => 1,
                'placeholder' => 'Card title…',
            ],
            [
                'key'          => 'field_card_description',
                'label'        => 'Description',
                'name'         => 'description',
                'type'         => 'wysiwyg',
                'toolbar'      => 'basic',
                'media_upload' => 0,
            ],
            [
                'key'        => 'field_card_link',
                'label'      => 'Link',
                'name'       => 'link',
                'type'       => 'link',
                'return_format' => 'array',
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'acf/card',
                ],
            ],
        ],
    ] );
} );
