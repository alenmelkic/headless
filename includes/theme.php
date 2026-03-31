<?php
/**
 * Theme Setup
 *
 * - Theme support flags
 * - Navigation menu locations
 * - Image sizes (registered + exposed in REST)
 * - Featured image URL in REST post/page responses
 * - Front-end asset dequeue (headless — no styles needed)
 * - Noindex for the WordPress front-end
 * - Allowed block types in the editor
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;


// ---------------------------------------------------------------------------
// Theme Support, Menus & Image Sizes
// ---------------------------------------------------------------------------

add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'script',
        'style',
    ] );
    add_theme_support( 'align-wide' );

    register_nav_menus( [
        'primary' => __( 'Primary Navigation', 'headless' ),
        'footer'  => __( 'Footer Navigation', 'headless' ),
        'mobile'  => __( 'Mobile Navigation', 'headless' ),
    ] );

    add_image_size( 'headless-thumbnail', 400, 300, true );
    add_image_size( 'headless-medium',    800, 600, false );
    add_image_size( 'headless-large',    1200, 900, false );
} );


// ---------------------------------------------------------------------------
// Disable Front-End Assets  (no stylesheet / scripts needed server-side)
// ---------------------------------------------------------------------------

add_action( 'wp_enqueue_scripts', function () {
    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'wp-block-library-theme' );
    wp_dequeue_style( 'classic-theme-styles' );
    wp_dequeue_style( 'global-styles' );
}, 100 );


// ---------------------------------------------------------------------------
// REST API — Expose Featured Image URL on posts/pages
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', function () {
    foreach ( [ 'post', 'page' ] as $type ) {
        register_rest_field( $type, 'featured_image_url', [
            'get_callback' => function ( $post ) {
                $id = $post['featured_media'] ?? 0;
                return $id ? wp_get_attachment_image_url( $id, 'full' ) : null;
            },
            'schema' => [
                'description' => __( 'Full URL of the featured image.', 'headless' ),
                'type'        => 'string',
                'context'     => [ 'view', 'embed' ],
            ],
        ] );
    }
} );


// ---------------------------------------------------------------------------
// REST API — Expose Custom Image Sizes in Media Responses
// ---------------------------------------------------------------------------

add_filter( 'wp_get_attachment_image_src', '__return_false', 0 );

add_filter( 'rest_prepare_attachment', function ( WP_REST_Response $response ): WP_REST_Response {
    $sizes = wp_get_registered_image_subsizes();
    $data  = $response->get_data();
    $id    = $data['id'] ?? 0;

    if ( ! $id ) {
        return $response;
    }

    $extra = [];
    foreach ( array_keys( $sizes ) as $size ) {
        $src = wp_get_attachment_image_src( $id, $size );
        if ( $src ) {
            $extra[ $size ] = [
                'url'    => $src[0],
                'width'  => $src[1],
                'height' => $src[2],
            ];
        }
    }

    if ( ! empty( $extra ) ) {
        $data['media_details']['sizes'] = array_merge(
            $data['media_details']['sizes'] ?? [],
            $extra
        );
        $response->set_data( $data );
    }

    return $response;
} );

remove_filter( 'wp_get_attachment_image_src', '__return_false', 0 );


// ---------------------------------------------------------------------------
// Noindex — WordPress Front-End
//
// WordPress is a pure headless back-end; suppress it from search engines.
// ---------------------------------------------------------------------------

add_action( 'wp_head', function (): void {
    echo '<meta name="robots" content="noindex, nofollow">' . PHP_EOL;
}, 1 );

add_filter( 'wp_headers', function ( array $headers ): array {
    $headers['X-Robots-Tag'] = 'noindex, nofollow';
    return $headers;
} );


// ---------------------------------------------------------------------------
// Allowed Block Types
//
// Restricts the editor to custom ACF blocks only. Block names are read
// dynamically from block.json files so adding a new block folder
// automatically makes it available — no changes needed here.
// On Posts and Pages, core/paragraph and core/image are also allowed.
// ---------------------------------------------------------------------------

add_filter( 'allowed_block_types_all', function ( array|bool $allowed, WP_Block_Editor_Context $context ): array {
    $acf_blocks = [];
    foreach ( glob( get_template_directory() . '/blocks/*/block.json' ) ?: [] as $manifest ) {
        $data = json_decode( file_get_contents( $manifest ), true );
        if ( ! empty( $data['name'] ) ) {
            $acf_blocks[] = $data['name'];
        }
    }

    $types_with_text = [ 'post', 'page', 'obavijest-o-smrti', 'servisne' ];

    if (
        isset( $context->post ) &&
        $context->post instanceof WP_Post &&
        in_array( $context->post->post_type, $types_with_text, true )
    ) {
        return array_merge( $acf_blocks, [ 'core/paragraph', 'core/image' ] );
    }

    return $acf_blocks;
}, 10, 2 );
