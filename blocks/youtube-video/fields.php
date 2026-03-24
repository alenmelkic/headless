<?php
/**
 * YouTube Video Block — ACF Field Group
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key'    => 'group_block_youtube_video',
        'title'  => 'YouTube Video',
        'fields' => [
            [
                'key'          => 'field_youtube_video_url',
                'label'        => 'Video URL',
                'name'         => 'video_url',
                'type'         => 'url',
                'required'     => 1,
                'placeholder'  => 'https://www.youtube.com/watch?v=...',
                'instructions' => 'Paste the YouTube video URL. Supports youtube.com and youtu.be formats.',
            ],
            [
                'key'         => 'field_youtube_title',
                'label'       => 'Title',
                'name'        => 'title',
                'type'        => 'text',
                'placeholder' => 'Optional title',
            ],
            [
                'key'         => 'field_youtube_caption',
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
                    'value'    => 'acf/youtube-video',
                ],
            ],
        ],
    ] );
} );
