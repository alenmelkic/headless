<?php
/**
 * Accordion Block — ACF Field Group (programmatic registration)
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key'    => 'group_block_accordion',
        'title'  => 'Accordion',
        'fields' => [
            [
                'key'         => 'field_accordion_title',
                'label'       => 'Section Title',
                'name'        => 'title',
                'type'        => 'text',
                'placeholder' => 'e.g. Frequently Asked Questions',
                'instructions' => 'Optional heading displayed above the accordion.',
            ],
            [
                'key'        => 'field_accordion_items',
                'label'      => 'Items',
                'name'       => 'items',
                'type'       => 'repeater',
                'required'   => 1,
                'min'        => 1,
                'layout'     => 'block',
                'button_label' => 'Add Item',
                'sub_fields' => [
                    [
                        'key'         => 'field_accordion_item_question',
                        'label'       => 'Question',
                        'name'        => 'question',
                        'type'        => 'text',
                        'required'    => 1,
                        'placeholder' => 'Enter question…',
                        'column_width' => '',
                    ],
                    [
                        'key'         => 'field_accordion_item_answer',
                        'label'       => 'Answer',
                        'name'        => 'answer',
                        'type'        => 'wysiwyg',
                        'required'    => 1,
                        'tabs'        => 'all',
                        'toolbar'     => 'basic',
                        'media_upload' => 0,
                    ],
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'acf/accordion',
                ],
            ],
        ],
    ] );
} );
