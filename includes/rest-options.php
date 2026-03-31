<?php
/**
 * REST Endpoints — Site Options & Global ACF Options
 *
 *   GET /wp-json/headless/v1/options
 *     Basic site info (name, URL, language, timezone, etc.)
 *
 *   GET /wp-json/headless/v1/options/global
 *     ACF options-page fields safe for public consumption.
 *     Extend the allowlist via the 'headless_public_option_keys' filter —
 *     never add secrets or private credentials to that list.
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;


// ---------------------------------------------------------------------------
// /options — Basic Site Info
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', function () {
    register_rest_route( 'headless/v1', '/options', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'headless_get_site_options',
        'permission_callback' => '__return_true',
    ] );
} );

/**
 * Returns whitelisted site options useful for a front-end application.
 *
 * @return WP_REST_Response
 */
function headless_get_site_options(): WP_REST_Response {
    return rest_ensure_response( [
        'name'           => get_bloginfo( 'name' ),
        'description'    => get_bloginfo( 'description' ),
        'url'            => get_bloginfo( 'url' ),
        'language'       => get_bloginfo( 'language' ),
        'charset'        => get_bloginfo( 'charset' ),
        'timezone'       => wp_timezone_string(),
        'date_format'    => get_option( 'date_format' ),
        'time_format'    => get_option( 'time_format' ),
        'posts_per_page' => (int) get_option( 'posts_per_page' ),
    ] );
}


// ---------------------------------------------------------------------------
// /options/global — ACF Global Options (public fields only)
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', function () {
    register_rest_route( 'headless/v1', '/options/global', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'headless_get_global_options',
        'permission_callback' => '__return_true',
    ] );
} );

/**
 * Returns ACF options-page fields that are safe for public consumption.
 *
 * Add new keys to the allowlist when you add options pages that contain
 * frontend-safe values (e.g. GA4 measurement IDs).
 * Use the 'headless_public_option_keys' filter to extend from a plugin/mu-plugin.
 *
 * @return WP_REST_Response
 */
function headless_get_global_options(): WP_REST_Response {
    if ( ! function_exists( 'get_fields' ) ) {
        return rest_ensure_response( [] );
    }

    $all = get_fields( 'options' ) ?: [];

    $public_keys = apply_filters( 'headless_public_option_keys', [
        'logo',
        'logo_opis',
        'favicon',
        'clarity_id',
        'ga_id',
    ] );

    return rest_ensure_response( array_intersect_key( $all, array_flip( (array) $public_keys ) ) );
}
