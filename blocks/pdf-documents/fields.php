<?php
/**
 * PDF Documents Block — ACF Field Group
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key'    => 'group_block_pdf_documents',
        'title'  => 'PDF Documents',
        'fields' => [
            [
                'key'         => 'field_pdf_title',
                'label'       => 'Title',
                'name'        => 'title',
                'type'        => 'text',
                'placeholder' => 'Optional section heading',
            ],
            [
                'key'          => 'field_pdf_documents',
                'label'        => 'Documents',
                'name'         => 'documents',
                'type'         => 'repeater',
                'required'     => 1,
                'min'          => 1,
                'layout'       => 'block',
                'button_label' => 'Add Document',
                'sub_fields'   => [
                    [
                        'key'      => 'field_pdf_doc_title',
                        'label'    => 'Document Title',
                        'name'     => 'doc_title',
                        'type'     => 'text',
                        'required' => 1,
                        'placeholder' => 'e.g. Annual Report 2025',
                    ],
                    [
                        'key'           => 'field_pdf_doc_file',
                        'label'         => 'PDF File',
                        'name'          => 'file',
                        'type'          => 'file',
                        'required'      => 1,
                        'return_format' => 'array',
                        'library'       => 'all',
                        'mime_types'    => 'pdf',
                        'instructions'  => 'Upload a PDF file.',
                    ],
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'acf/pdf-documents',
                ],
            ],
        ],
    ] );
} );
