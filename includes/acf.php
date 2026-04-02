<?php
/**
 * ACF Integration
 *
 * - JSON field-group sync (save/load paths → /acf-json/)
 * - Block auto-registration from /blocks/{name}/block.json
 * - Global Options page registration
 * - ACF fields embedded on all public REST post-type responses
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;


// ---------------------------------------------------------------------------
// ACF JSON — Field Group Sync
//
// Field groups are stored as JSON in /acf-json/ and committed to version
// control. Edit fields via the ACF UI → changes auto-save back to these files.
// ---------------------------------------------------------------------------

add_filter( 'acf/settings/save_json', function (): string {
    return get_template_directory() . '/acf-json';
} );

add_filter( 'acf/settings/load_json', function ( array $paths ): array {
    $paths[] = get_template_directory() . '/acf-json';
    return $paths;
} );


// ---------------------------------------------------------------------------
// Block Auto-Registration
//
// Place each block in its own sub-folder inside /blocks/:
//   blocks/{name}/block.json   ← required
//   blocks/{name}/render.php   ← required
//   blocks/{name}/fields.php   ← optional (programmatic field group)
//
// Adding a new block folder is sufficient — no changes needed here.
// ---------------------------------------------------------------------------

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_register_block_type' ) ) {
        return;
    }

    $manifests = glob( get_template_directory() . '/blocks/*/block.json' );
    if ( ! $manifests ) {
        return;
    }

    foreach ( $manifests as $manifest ) {
        $block_dir = dirname( $manifest );
        register_block_type( $block_dir );

        $fields_file = $block_dir . '/fields.php';
        if ( file_exists( $fields_file ) ) {
            require_once $fields_file;
        }
    }
} );


// ---------------------------------------------------------------------------
// ACF Options Page
//
// Registers the legacy "Global Options" admin page (ACF Pro required).
// Theme Settings (logo, favicon, analytics) live in includes/theme-settings.php
// and use native wp_options + WPGraphQL — no ACF involved.
// ---------------------------------------------------------------------------

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_options_page' ) ) {
        return;
    }

    acf_add_options_page( [
        'page_title' => __( 'Global Options', 'headless' ),
        'menu_title' => __( 'Global Options', 'headless' ),
        'menu_slug'  => 'headless-global-options',
        'capability' => 'manage_options',
        'icon_url'   => 'dashicons-admin-generic',
        'redirect'   => false,
    ] );
} );


// ---------------------------------------------------------------------------
// Remove Stale ACF Options Pages
//
// The "Podešavanja" parent and "Logo & Favicon" child pages were removed
// but may still be registered from ACF database records. Remove them here.
// ---------------------------------------------------------------------------

add_action( 'admin_menu', function () {
    remove_menu_page( 'podesavanja' );
    remove_submenu_page( 'podesavanja', 'logo-and-favicon' );
}, 999 );


// ---------------------------------------------------------------------------
// ACF Fields on All REST Post-Type Responses
//
// Adds an "acf" key to every publicly queryable post type's REST response
// so the front-end never needs a second round-trip to fetch custom fields.
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', function () {
    if ( ! function_exists( 'get_fields' ) ) {
        return;
    }

    $types = get_post_types( [ 'show_in_rest' => true ], 'names' );

    foreach ( $types as $type ) {
        register_rest_field( $type, 'acf', [
            'get_callback' => function ( array $post ): mixed {
                $fields = get_fields( $post['id'] ?? 0 );
                return $fields ?: (object) [];
            },
            'schema' => [
                'description' => __( 'ACF custom fields.', 'headless' ),
                'type'        => 'object',
                'context'     => [ 'view', 'embed' ],
            ],
        ] );
    }
} );
