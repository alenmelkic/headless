<?php
/**
 * Draft Preview
 *
 * Intercepts the WordPress "Preview" button and redirects editors to the
 * front-end preview route instead of the WP front-end.
 *
 *   Front-end route: /api/preview?id=X&post_type=Y&iat=Z&token=T
 *
 * The token is a time-limited HMAC (HEADLESS_PREVIEW_SECRET required).
 * Tokens expire after 15 minutes. If the secret is not configured, the
 * original WordPress preview link is returned unchanged.
 *
 * REST verification endpoint:
 *   GET /wp-json/headless/v1/preview?id=X&iat=Z&token=T
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;


// ---------------------------------------------------------------------------
// Redirect "Preview" button to front-end preview route
// ---------------------------------------------------------------------------

add_filter( 'preview_post_link', function ( string $link, WP_Post $post ): string {
    $frontend = headless_get_setting( 'frontend_url' );

    if ( ! $frontend ) {
        return $link;
    }

    $secret = headless_get_setting( 'preview_secret' );
    if ( ! $secret ) {
        return $link; // Cannot generate a secure preview URL without a preview secret.
    }

    $issued_at = time();
    $token     = hash_hmac( 'sha256', $post->ID . '|' . $post->post_type . '|' . $issued_at, $secret );

    return add_query_arg(
        [
            'preview'   => 'true',
            'id'        => $post->ID,
            'post_type' => $post->post_type,
            'iat'       => $issued_at,
            'token'     => $token,
        ],
        trailingslashit( $frontend ) . 'api/preview'
    );
}, 10, 2 );


// ---------------------------------------------------------------------------
// Redirect "View Post" links to the headless frontend for published content
// ---------------------------------------------------------------------------

/**
 * Rewrites a published post permalink to the headless frontend URL.
 * All single items use flat URLs on the frontend: /{slug}
 */
function headless_rewrite_permalink( string $url, WP_Post|int $post ): string {
    $frontend = headless_get_setting( 'frontend_url' );
    if ( ! $frontend ) {
        return $url;
    }

    $post = get_post( $post );
    if ( ! $post || $post->post_status !== 'publish' ) {
        return $url;
    }

    return trailingslashit( $frontend ) . $post->post_name;
}

add_filter( 'post_link',      'headless_rewrite_permalink', 10, 2 );
add_filter( 'page_link',      function ( string $url, int $id ) {
    return headless_rewrite_permalink( $url, $id );
}, 10, 2 );
add_filter( 'post_type_link', 'headless_rewrite_permalink', 10, 2 );


// ---------------------------------------------------------------------------
// Admin "View" links → activate admin mode so editors see the edit button
// ---------------------------------------------------------------------------

/**
 * Builds a frontend admin-mode URL that sets the admin_mode cookie and
 * redirects to the post. Used only for admin-context view links.
 */
function headless_admin_view_url( WP_Post $post ): string {
    $frontend = headless_get_setting( 'frontend_url' );
    $secret   = headless_get_setting( 'preview_secret' );

    if ( ! $frontend || ! $secret ) {
        return '';
    }

    return add_query_arg(
        [
            'token'  => $secret,
            'return' => '/' . $post->post_name,
        ],
        trailingslashit( $frontend ) . 'api/admin-mode'
    );
}

/**
 * Replaces the "View" link in admin post list rows with an admin-mode URL.
 */
function headless_admin_row_view_link( array $actions, WP_Post $post ): array {
    if ( ! isset( $actions['view'] ) || $post->post_status !== 'publish' ) {
        return $actions;
    }

    $url = headless_admin_view_url( $post );
    if ( ! $url ) {
        return $actions;
    }

    $actions['view'] = sprintf(
        '<a href="%s" rel="bookmark" target="_blank">%s</a>',
        esc_url( $url ),
        __( 'Pregledaj' )
    );

    return $actions;
}

add_filter( 'post_row_actions', 'headless_admin_row_view_link', 10, 2 );
add_filter( 'page_row_actions', 'headless_admin_row_view_link', 10, 2 );

/**
 * Adds a "View on frontend" link in the admin bar that activates admin mode.
 */
add_action( 'admin_bar_menu', function ( WP_Admin_Bar $bar ) {
    $node = $bar->get_node( 'view' );
    if ( ! $node ) {
        return;
    }

    $post = get_post();
    if ( ! $post || $post->post_status !== 'publish' ) {
        return;
    }

    $url = headless_admin_view_url( $post );
    if ( ! $url ) {
        return;
    }

    $bar->add_node( [
        'id'   => 'view',
        'href' => $url,
    ] );
}, 999 );


// ---------------------------------------------------------------------------
// REST Endpoint — Token Verification & Draft Content
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', function () {
    register_rest_route( 'headless/v1', '/preview', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'headless_verify_preview',
        'permission_callback' => '__return_true',
        'args'                => [
            'id'    => [ 'required' => true, 'validate_callback' => fn( $v ) => is_numeric( $v ) ],
            'iat'   => [ 'required' => true, 'validate_callback' => fn( $v ) => is_numeric( $v ) ],
            'token' => [ 'required' => true ],
        ],
    ] );
} );

/**
 * Verifies the preview token and returns the latest draft content for a post.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function headless_verify_preview( WP_REST_Request $request ): WP_REST_Response|WP_Error {
    $id        = (int) $request->get_param( 'id' );
    $token     = (string) $request->get_param( 'token' );
    $issued_at = (int) $request->get_param( 'iat' );

    $secret = headless_get_setting( 'preview_secret' );
    if ( ! $secret ) {
        return new WP_Error( 'preview_not_configured', __( 'Preview secret is not configured.', 'headless' ), [ 'status' => 503 ] );
    }

    // Reject tokens older than 15 minutes.
    if ( ( time() - $issued_at ) > 15 * MINUTE_IN_SECONDS ) {
        return new WP_Error( 'token_expired', __( 'Preview token has expired.', 'headless' ), [ 'status' => 403 ] );
    }

    $expected = hash_hmac( 'sha256', $id . '|' . get_post_type( $id ) . '|' . $issued_at, $secret );
    if ( ! hash_equals( $expected, $token ) ) {
        return new WP_Error( 'invalid_token', __( 'Invalid preview token.', 'headless' ), [ 'status' => 403 ] );
    }

    $post = get_post( $id );
    if ( ! $post ) {
        return new WP_Error( 'not_found', __( 'Post not found.', 'headless' ), [ 'status' => 404 ] );
    }

    // Use the latest revision if one exists, otherwise use the post itself.
    $revisions = wp_get_post_revisions( $id, [ 'posts_per_page' => 1 ] );
    $source    = $revisions ? reset( $revisions ) : $post;

    return rest_ensure_response( [
        'id'      => $id,
        'type'    => $post->post_type,
        'slug'    => $post->post_name,
        'status'  => $post->post_status,
        'title'   => get_the_title( $source ),
        'content' => apply_filters( 'the_content', $source->post_content ),
        'excerpt' => get_the_excerpt( $source ),
        'acf'     => function_exists( 'get_fields' ) ? ( get_fields( $id ) ?: (object) [] ) : (object) [],
        'blocks'  => headless_parse_blocks_recursive( parse_blocks( $source->post_content ) ),
    ] );
}
