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
 * Returns theme settings stored in wp_options (logo, favicon, analytics IDs).
 *
 * Data is managed via WP Admin → Theme Settings and exposed here for
 * frontends that prefer REST over GraphQL.
 *
 * @return WP_REST_Response
 */
function headless_get_global_options(): WP_REST_Response {
    return rest_ensure_response( [
        'logo'       => headless_theme_resolve_image( (int) get_option( 'headless_theme_logo_id',      0 ) ),
        'logo_dark'  => headless_theme_resolve_image( (int) get_option( 'headless_theme_logo_dark_id', 0 ) ),
        'logo_alt'   => (string) get_option( 'headless_theme_logo_alt',   '' ),
        'logo_width' => (int) get_option( 'headless_theme_logo_width', 120 ),
        'favicon'    => headless_theme_resolve_image( (int) get_option( 'headless_theme_favicon_id', 0 ) ),
        'clarity_id' => (string) get_option( 'headless_theme_clarity_id', '' ),
        'ga_id'          => (string) get_option( 'headless_theme_ga_id',           '' ),
        'related_source'    => (string) get_option( 'headless_theme_related_source', 'category' ),
        'banner_visibility' => (array) get_option( 'headless_theme_banner_visibility', [ 'homepage', 'categories', 'single_posts', 'single_pages', 'servisne_listing', 'obavijesti_listing' ] ),
    ] );
}
