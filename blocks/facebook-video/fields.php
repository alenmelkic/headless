<?php
/**
 * Facebook Video Block — ACF Field Group
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key'    => 'group_block_facebook_video',
        'title'  => 'Facebook Video',
        'fields' => [
            [
                'key'          => 'field_facebook_video_url',
                'label'        => 'Video URL',
                'name'         => 'video_url',
                'type'         => 'url',
                'required'     => 1,
                'placeholder'  => 'https://www.facebook.com/watch/?v=...',
                'instructions' => 'Paste the Facebook video URL from the address bar or share dialog.',
            ],
            [
                'key'         => 'field_facebook_title',
                'label'       => 'Title',
                'name'        => 'title',
                'type'        => 'text',
                'placeholder' => 'Optional title',
            ],
            [
                'key'         => 'field_facebook_caption',
                'label'       => 'Caption',
                'name'        => 'caption',
                'type'        => 'textarea',
                'rows'        => 3,
                'placeholder' => 'Optional caption',
            ],
        ],
        'location' => [
            [
                [
                    'param'    => 'block',
                    'operator' => '==',
                    'value'    => 'acf/facebook-video',
                ],
            ],
        ],
    ] );
} );
