<?php
/**
 * REST Endpoint — SEO Meta
 *
 *   GET /wp-json/headless/v1/seo/{id}
 *
 * Returns SEO meta for a post with automatic plugin detection:
 *   1. Yoast SEO  (if active)
 *   2. RankMath   (if active)
 *   3. Fallback — basic WordPress data
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;


add_action( 'rest_api_init', function () {
    register_rest_route( 'headless/v1', '/seo/(?P<id>\d+)', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'headless_get_seo_meta',
        'permission_callback' => '__return_true',
        'args'                => [
            'id' => [
                'required'          => true,
                'validate_callback' => fn( $v ) => is_numeric( $v ),
            ],
        ],
    ] );
} );

/**
 * Returns SEO meta for a post, with Yoast / RankMath passthrough.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function headless_get_seo_meta( WP_REST_Request $request ): WP_REST_Response|WP_Error {
    $post_id = (int) $request->get_param( 'id' );
    $post    = get_post( $post_id );

    if ( ! $post || ! is_post_publicly_viewable( $post ) ) {
        return new WP_Error( 'not_found', __( 'Post not found.', 'headless' ), [ 'status' => 404 ] );
    }

    // --- Yoast SEO ---
    if ( defined( 'WPSEO_VERSION' ) && function_exists( 'YoastSEO' ) ) {
        $meta = YoastSEO()->meta->for_post( $post_id );
        return rest_ensure_response( [
            'title'               => $meta->title,
            'description'         => $meta->description,
            'robots'              => $meta->robots,
            'canonical'           => $meta->canonical,
            'og_title'            => $meta->open_graph_title,
            'og_description'      => $meta->open_graph_description,
            'og_image'            => $meta->open_graph_images[0]['url'] ?? null,
            'twitter_title'       => $meta->twitter_title,
            'twitter_description' => $meta->twitter_description,
            'source'              => 'yoast',
        ] );
    }

    // --- RankMath ---
    if ( class_exists( 'RankMath' ) ) {
        $thumbnail_url = null;
        $rm_image_id   = get_post_meta( $post_id, 'rank_math_facebook_image_id', true );
        if ( $rm_image_id ) {
            $thumbnail_url = wp_get_attachment_image_url( (int) $rm_image_id, 'headless-large' );
        }

        return rest_ensure_response( [
            'title'               => get_post_meta( $post_id, 'rank_math_title', true )       ?: get_the_title( $post ),
            'description'         => get_post_meta( $post_id, 'rank_math_description', true )  ?: '',
            'robots'              => get_post_meta( $post_id, 'rank_math_robots', true )        ?: 'index, follow',
            'canonical'           => get_post_meta( $post_id, 'rank_math_canonical_url', true ) ?: get_permalink( $post ),
            'og_title'            => get_post_meta( $post_id, 'rank_math_facebook_title', true )       ?: get_the_title( $post ),
            'og_description'      => get_post_meta( $post_id, 'rank_math_facebook_description', true ) ?: '',
            'og_image'            => $thumbnail_url,
            'twitter_title'       => get_post_meta( $post_id, 'rank_math_twitter_title', true )       ?: get_the_title( $post ),
            'twitter_description' => get_post_meta( $post_id, 'rank_math_twitter_description', true ) ?: '',
            'source'              => 'rankmath',
        ] );
    }

    // --- Fallback: basic WordPress data ---
    $thumbnail_id  = get_post_thumbnail_id( $post_id );
    $thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'headless-large' ) : null;
    $description   = has_excerpt( $post )
        ? get_the_excerpt( $post )
        : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );

    return rest_ensure_response( [
        'title'               => get_the_title( $post ),
        'description'         => $description,
        'robots'              => 'index, follow',
        'canonical'           => get_permalink( $post ),
        'og_title'            => get_the_title( $post ),
        'og_description'      => $description,
        'og_image'            => $thumbnail_url,
        'twitter_title'       => get_the_title( $post ),
        'twitter_description' => $description,
        'source'              => 'fallback',
    ] );
}
