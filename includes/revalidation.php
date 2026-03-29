<?php
/**
 * Revalidation Webhook
 *
 * On every post save/publish, nav menu update, or ACF Options save, fires a
 * non-blocking HTTP POST to the front-end revalidation endpoint so Next.js
 * ISR caches are purged automatically.
 *
 * Configure via wp-config.php:
 *   define( 'HEADLESS_REVALIDATE_URL',    'https://your-frontend.com/api/revalidate' );
 *   define( 'HEADLESS_REVALIDATE_SECRET', 'your-secret' );
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;


/**
 * Sends a non-blocking revalidation webhook to the front-end.
 *
 * @param array $payload JSON-encodable data to include in the request body.
 */
function headless_send_revalidation( array $payload ): void {
    $endpoint = defined( 'HEADLESS_REVALIDATE_URL' ) && HEADLESS_REVALIDATE_URL
        ? HEADLESS_REVALIDATE_URL
        : ( function (): string {
            $frontend = defined( 'HEADLESS_FRONTEND_URL' ) ? HEADLESS_FRONTEND_URL : get_option( 'headless_frontend_url', '' );
            return $frontend ? trailingslashit( $frontend ) . 'api/revalidate' : '';
        } )();

    if ( ! $endpoint ) {
        return;
    }

    $secret  = defined( 'HEADLESS_REVALIDATE_SECRET' ) ? HEADLESS_REVALIDATE_SECRET : '';
    $headers = [ 'Content-Type' => 'application/json' ];

    if ( $secret ) {
        $headers['x-revalidate-secret'] = $secret;
    }

    wp_remote_post( $endpoint, [
        'method'   => 'POST',
        'headers'  => $headers,
        'body'     => wp_json_encode( $payload ),
        'timeout'  => 5,
        'blocking' => false, // fire-and-forget — don't slow down the editor save
    ] );
}

// Post save / publish.
add_action( 'save_post', function ( int $post_id, WP_Post $post ): void {
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }

    headless_send_revalidation( [
        'type'      => 'post',
        'post_id'   => $post_id,
        'post_type' => $post->post_type,
        'slug'      => $post->post_name,
        'status'    => $post->post_status,
        'permalink' => get_permalink( $post_id ),
    ] );
}, 10, 2 );

// Nav menu update — covers item add/remove/reorder and menu rename.
add_action( 'wp_update_nav_menu', function ( int $menu_id ): void {
    $menu      = wp_get_nav_menu_object( $menu_id );
    $locations = array_filter( get_nav_menu_locations(), fn( int $id ): bool => $id === $menu_id );

    headless_send_revalidation( [
        'type'      => 'menu',
        'menu_id'   => $menu_id,
        'menu_name' => $menu ? $menu->name : '',
        'locations' => array_keys( $locations ),
    ] );
} );

// ACF Options page save.
add_action( 'acf/save_post', function ( $post_id ): void {
    if ( $post_id !== 'options' ) {
        return;
    }

    headless_send_revalidation( [ 'type' => 'options' ] );
} );
