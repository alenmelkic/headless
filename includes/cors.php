<?php
/**
 * HTTP Headers — CORS & Cache-Control
 *
 * CORS:
 *   Allows cross-origin REST API requests from known front-end origins.
 *   Exact-match allowlist + optional Vercel preview wildcard (*.vercel.app).
 *   Unrecognised origins receive no Allow-Origin header (browser blocks them).
 *   Disable Vercel wildcard: add_filter('headless_cors_allow_vercel', '__return_false')
 *
 * Cache-Control:
 *   Public GET responses get s-maxage=60 / stale-while-revalidate=300 for CDN caching.
 *   Authenticated requests (Authorization / X-WP-Nonce) get no-store.
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;


// ---------------------------------------------------------------------------
// CORS Headers
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', function () {
    remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );

    add_filter( 'rest_pre_serve_request', function ( $value ) {
        $default_origins = [ 'http://localhost:3000', 'http://localhost:3001', 'http://localhost:5173' ];
        $frontend = headless_get_setting( 'frontend_url' );
        if ( $frontend ) {
            $default_origins[] = rtrim( $frontend, '/' );
        }
        $allowed_origins = apply_filters( 'headless_cors_allowed_origins', $default_origins );

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        $is_allowed = in_array( $origin, $allowed_origins, true );

        // Also allow Vercel preview deployments (https://*.vercel.app).
        // Disable via: add_filter( 'headless_cors_allow_vercel', '__return_false' );
        if ( ! $is_allowed && preg_match( '#^https://[a-zA-Z0-9-]+\.vercel\.app$#', $origin ) ) {
            $is_allowed = (bool) apply_filters( 'headless_cors_allow_vercel', true, $origin );
        }

        if ( $is_allowed ) {
            header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
            header( 'Access-Control-Allow-Credentials: true' );
        }
        // Unrecognised origins receive no Allow-Origin header (request blocked by browser).

        header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );

        return $value;
    } );
}, 15 );


// ---------------------------------------------------------------------------
// Cache-Control Headers
//
//   s-maxage=60                — CDN caches the response for 60 seconds
//   stale-while-revalidate=300 — CDN serves stale while fetching fresh copy
// ---------------------------------------------------------------------------

add_filter( 'rest_post_dispatch', function ( WP_REST_Response $response, WP_REST_Server $server, WP_REST_Request $request ): WP_REST_Response {
    if ( $request->get_method() !== 'GET' ) {
        return $response;
    }

    if ( $request->get_header( 'authorization' ) || $request->get_header( 'x-wp-nonce' ) ) {
        $response->header( 'Cache-Control', 'no-store' );
        return $response;
    }

    $response->header( 'Cache-Control', 'public, s-maxage=60, stale-while-revalidate=300' );

    return $response;
}, 10, 3 );
