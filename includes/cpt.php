<?php
/**
 * Custom Post Types
 *
 * Register custom post types here.
 * Each CPT should be optimised for:
 *  - Headless REST API  (show_in_rest, rest_base)
 *  - WPGraphQL          (show_in_graphql, graphql_single_name, graphql_plural_name)
 *
 * @package Headless
 * @author  Alen Melkic
 */

defined( 'ABSPATH' ) || exit;

/**
 * The servisne CPT is registered without author support — add it so the
 * frontend can show the author on servisne singles (WPGraphQL exposes the
 * `author` field only for post types that support it).
 */
add_action( 'init', function (): void {
    if ( post_type_exists( 'servisne' ) ) {
        add_post_type_support( 'servisne', 'author' );
    }
}, 20 );

/**
 * Ensure the control term "Dodaj na banner" exists on the servisne taxonomy.
 *
 * Servisne posts tagged with this term are merged into the Article Listing
 * block (see blocks/article-listing/render.php). Runs once — guarded by an
 * option flag so no term lookup happens on subsequent requests.
 */
add_action( 'init', function (): void {
    if ( get_option( 'headless_banner_term_created' ) ) {
        return;
    }
    if ( ! taxonomy_exists( 'servisna-informacija' ) ) {
        return;
    }
    if ( ! term_exists( 'dodaj-na-banner', 'servisna-informacija' ) ) {
        wp_insert_term( 'Dodaj na banner', 'servisna-informacija', [ 'slug' => 'dodaj-na-banner' ] );
    }
    update_option( 'headless_banner_term_created', 1 );
}, 20 );
