<?php
/**
 * Revalidation Webhook
 *
 * Fires a non-blocking HTTP POST to the front-end revalidation endpoint on
 * every content change so Next.js ISR caches are purged automatically.
 *
 * Covered events:
 *  - Post save / publish / status change
 *  - Post trash / untrash / permanent delete
 *  - Nav menu update
 *  - ACF Options page save
 *  - Term (taxonomy) create / update / delete
 *  - Attachment (media) add / update / delete
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
    $endpoint = headless_get_setting( 'revalidate_url' );

    if ( ! $endpoint ) {
        return;
    }

    $secret  = headless_get_setting( 'revalidate_secret' );
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


// ---------------------------------------------------------------------------
// Posts
// ---------------------------------------------------------------------------

// Save / publish / status change.
add_action( 'save_post', function ( int $post_id, WP_Post $post ): void {
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }

    headless_send_revalidation( [
        'type'      => 'post',
        'action'    => 'saved',
        'post_id'   => $post_id,
        'post_type' => $post->post_type,
        'slug'      => $post->post_name,
        'status'    => $post->post_status,
        'permalink' => get_permalink( $post_id ),
    ] );
}, 10, 2 );

// Moved to trash.
add_action( 'wp_trash_post', function ( int $post_id, string $previous_status ): void {
    $post = get_post( $post_id );
    if ( ! $post ) {
        return;
    }

    headless_send_revalidation( [
        'type'            => 'post',
        'action'          => 'trashed',
        'post_id'         => $post_id,
        'post_type'       => $post->post_type,
        'slug'            => $post->post_name,
        'previous_status' => $previous_status,
        'permalink'       => get_permalink( $post_id ),
    ] );
}, 10, 2 );

// Restored from trash.
add_action( 'untrash_post', function ( int $post_id, string $previous_status ): void {
    $post = get_post( $post_id );
    if ( ! $post ) {
        return;
    }

    headless_send_revalidation( [
        'type'            => 'post',
        'action'          => 'untrashed',
        'post_id'         => $post_id,
        'post_type'       => $post->post_type,
        'slug'            => $post->post_name,
        'previous_status' => $previous_status,
    ] );
}, 10, 2 );

// Permanently deleted.
add_action( 'before_delete_post', function ( int $post_id, WP_Post $post ): void {
    // Skip revisions and auto-drafts — no frontend page exists for them.
    if ( in_array( $post->post_status, [ 'inherit', 'auto-draft' ], true ) ) {
        return;
    }

    headless_send_revalidation( [
        'type'      => 'post',
        'action'    => 'deleted',
        'post_id'   => $post_id,
        'post_type' => $post->post_type,
        'slug'      => $post->post_name,
        'permalink' => get_permalink( $post_id ),
    ] );
}, 10, 2 );


// ---------------------------------------------------------------------------
// Nav Menus
// ---------------------------------------------------------------------------

// Item add / remove / reorder and menu rename.
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


// ---------------------------------------------------------------------------
// ACF Options
// ---------------------------------------------------------------------------

add_action( 'acf/save_post', function ( $post_id ): void {
    if ( $post_id !== 'options' ) {
        return;
    }

    headless_send_revalidation( [ 'type' => 'options' ] );
} );


// ---------------------------------------------------------------------------
// Theme Settings (native wp_options, not ACF)
// ---------------------------------------------------------------------------

// Revalidate when any headless_theme_* option changes.
add_action( 'updated_option', function ( string $option ): void {
    if ( str_starts_with( $option, 'headless_theme_' ) ) {
        headless_send_revalidation( [ 'type' => 'options', 'option' => $option ] );
    }
} );

// Fallback: also revalidate on Theme Settings page save-redirect (covers
// unchanged-value saves where updated_option does not fire).
add_action( 'admin_init', function (): void {
    if (
        ! isset( $_GET['settings-updated'] ) ||
        ! isset( $_GET['page'] ) ||
        $_GET['page'] !== 'theme-settings'
    ) {
        return;
    }
    headless_send_revalidation( [ 'type' => 'options' ] );
} );

// Revalidate marketing data whenever the Marketing admin page reloads after
// a successful save.  The Settings API redirects back with ?settings-updated=true
// so we detect that on the marketing page and fire the webhook once.
// This is more reliable than updated_option which skips unchanged values.
add_action( 'admin_init', function (): void {
    if (
        ! isset( $_GET['settings-updated'] ) ||
        ! isset( $_GET['page'] ) ||
        $_GET['page'] !== 'headless-marketing'
    ) {
        return;
    }
    headless_send_revalidation( [ 'type' => 'marketing' ] );
} );


// ---------------------------------------------------------------------------
// Terms (Categories / Tags / Custom Taxonomies)
// ---------------------------------------------------------------------------

// Term created.
add_action( 'created_term', function ( int $term_id, int $tt_id, string $taxonomy ): void {
    $term = get_term( $term_id, $taxonomy );

    headless_send_revalidation( [
        'type'     => 'term',
        'action'   => 'created',
        'term_id'  => $term_id,
        'taxonomy' => $taxonomy,
        'slug'     => $term instanceof WP_Term ? $term->slug : '',
        'name'     => $term instanceof WP_Term ? $term->name : '',
    ] );
}, 10, 3 );

// Term updated.
add_action( 'edited_term', function ( int $term_id, int $tt_id, string $taxonomy ): void {
    $term = get_term( $term_id, $taxonomy );

    headless_send_revalidation( [
        'type'     => 'term',
        'action'   => 'updated',
        'term_id'  => $term_id,
        'taxonomy' => $taxonomy,
        'slug'     => $term instanceof WP_Term ? $term->slug : '',
        'name'     => $term instanceof WP_Term ? $term->name : '',
    ] );
}, 10, 3 );

// Term deleted.
add_action( 'delete_term', function ( int $term_id, int $tt_id, string $taxonomy, $deleted_term ): void {
    headless_send_revalidation( [
        'type'     => 'term',
        'action'   => 'deleted',
        'term_id'  => $term_id,
        'taxonomy' => $taxonomy,
        'slug'     => $deleted_term instanceof WP_Term ? $deleted_term->slug : '',
        'name'     => $deleted_term instanceof WP_Term ? $deleted_term->name : '',
    ] );
}, 10, 4 );


// ---------------------------------------------------------------------------
// Attachments (Media Library)
// ---------------------------------------------------------------------------

// Attachment uploaded.
add_action( 'add_attachment', function ( int $post_id ): void {
    headless_send_revalidation( [
        'type'    => 'attachment',
        'action'  => 'added',
        'post_id' => $post_id,
        'url'     => wp_get_attachment_url( $post_id ),
    ] );
} );

// Attachment metadata updated (alt text, caption, replace, etc.).
add_action( 'edit_attachment', function ( int $post_id ): void {
    headless_send_revalidation( [
        'type'    => 'attachment',
        'action'  => 'updated',
        'post_id' => $post_id,
        'url'     => wp_get_attachment_url( $post_id ),
    ] );
} );

// Attachment deleted.
add_action( 'delete_attachment', function ( int $post_id, WP_Post $post ): void {
    headless_send_revalidation( [
        'type'    => 'attachment',
        'action'  => 'deleted',
        'post_id' => $post_id,
        'url'     => wp_get_attachment_url( $post_id ),
    ] );
}, 10, 2 );
