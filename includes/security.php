<?php
/**
 * Security hardening
 *
 * - Hides the user list from unauthenticated REST requests
 * - Blocks root WPGraphQL `users` / `user` fields for unauthenticated requests
 *   (Post.author is untouched — the frontend reads authors through it)
 * - Blocks author enumeration (/?author=N, /author/{slug}/)
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;


// ---------------------------------------------------------------------------
// REST — remove /wp/v2/users* routes for logged-out visitors
// ---------------------------------------------------------------------------

add_filter( 'rest_endpoints', function ( array $endpoints ): array {
    if ( is_user_logged_in() ) {
        return $endpoints;
    }

    foreach ( array_keys( $endpoints ) as $route ) {
        if ( str_starts_with( $route, '/wp/v2/users' ) ) {
            unset( $endpoints[ $route ] );
        }
    }

    return $endpoints;
} );


// ---------------------------------------------------------------------------
// GraphQL — root users / user fields return null for logged-out requests
// ---------------------------------------------------------------------------

add_filter(
    'graphql_pre_resolve_field',
    function ( $nil, $source, $args, $context, $info, $type_name, $field_key ) {
        if (
            'RootQuery' === $type_name
            && in_array( $field_key, [ 'users', 'user' ], true )
            && ! is_user_logged_in()
        ) {
            return null;
        }

        return $nil;
    },
    10,
    7
);


// ---------------------------------------------------------------------------
// Author enumeration — empty 404 for logged-out visitors
//
// Priority 0 so it runs before redirect_canonical() turns ?author=N into
// /author/{slug}/.
// ---------------------------------------------------------------------------

add_action( 'template_redirect', function (): void {
    if ( is_user_logged_in() ) {
        return;
    }

    if ( ! is_author() && ! isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
        return;
    }

    global $wp_query;
    $wp_query->set_404();
    status_header( 404 );
    nocache_headers();
    exit;
}, 0 );
