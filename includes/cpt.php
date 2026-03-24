<?php
/**
 * Custom Post Types
 *
 * Each CPT is optimised for:
 *  - Headless REST API  (show_in_rest, rest_base)
 *  - WPGraphQL          (show_in_graphql, graphql_single_name, graphql_plural_name)
 *  - No taxonomies      (no categories or tags attached)
 *
 * To add a new CPT, duplicate one of the register_post_type() blocks,
 * update the slug, labels, and GraphQL names.
 *
 * @package Headless
 * @author  Alen Melkic
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function (): void {
    headless_register_cpts();
} );

/**
 * Registers all custom post types.
 */
function headless_register_cpts(): void {

    // -----------------------------------------------------------------------
    // Obavijesti
    //   REST:    GET /wp-json/wp/v2/obavijesti
    //   GraphQL: query { obavijesti { nodes { ... } } }
    // -----------------------------------------------------------------------
    register_post_type( 'obavijest', [
        'labels'  => [
            'name'               => 'Obavijesti',
            'singular_name'      => 'Obavijest',
            'add_new'            => 'Dodaj novu',
            'add_new_item'       => 'Dodaj novu obavijest',
            'edit_item'          => 'Uredi obavijest',
            'new_item'           => 'Nova obavijest',
            'view_item'          => 'Pogledaj obavijest',
            'search_items'       => 'Pretraži obavijesti',
            'not_found'          => 'Nema pronađenih obavijesti.',
            'not_found_in_trash' => 'Nema obavijesti u košu.',
            'all_items'          => 'Sve obavijesti',
            'menu_name'          => 'Obavijesti',
        ],

        // Visibility
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_nav_menus'  => false,
        'show_in_admin_bar'  => true,

        // REST API (headless)
        'show_in_rest'       => true,
        'rest_base'          => 'obavijesti',
        'rest_controller_class' => 'WP_REST_Posts_Controller',

        // WPGraphQL
        'show_in_graphql'    => true,
        'graphql_single_name' => 'obavijest',
        'graphql_plural_name' => 'obavijesti',

        // No taxonomies — supports only core post fields
        'taxonomies'         => [],

        'supports'           => [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ],
        'has_archive'        => true,
        'rewrite'            => [ 'slug' => 'obavijesti', 'with_front' => false ],
        'menu_icon'          => 'dashicons-bell',
        'capability_type'    => 'post',
        'map_meta_cap'       => true,
    ] );

    // -----------------------------------------------------------------------
    // Servisne info
    //   REST:    GET /wp-json/wp/v2/servisne-info
    //   GraphQL: query { servisneInfo { nodes { ... } } }
    // -----------------------------------------------------------------------
    register_post_type( 'servisna_info', [
        'labels'  => [
            'name'               => 'Servisne info',
            'singular_name'      => 'Servisna info',
            'add_new'            => 'Dodaj novu',
            'add_new_item'       => 'Dodaj novu servisnu info',
            'edit_item'          => 'Uredi servisnu info',
            'new_item'           => 'Nova servisna info',
            'view_item'          => 'Pogledaj servisnu info',
            'search_items'       => 'Pretraži servisne info',
            'not_found'          => 'Nema pronađenih servisnih info.',
            'not_found_in_trash' => 'Nema servisnih info u košu.',
            'all_items'          => 'Sve servisne info',
            'menu_name'          => 'Servisne info',
        ],

        // Visibility
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_nav_menus'  => false,
        'show_in_admin_bar'  => true,

        // REST API (headless)
        'show_in_rest'       => true,
        'rest_base'          => 'servisne-info',
        'rest_controller_class' => 'WP_REST_Posts_Controller',

        // WPGraphQL
        'show_in_graphql'    => true,
        'graphql_single_name' => 'servisnaInfo',
        'graphql_plural_name' => 'servisneInfo',

        // No taxonomies — supports only core post fields
        'taxonomies'         => [],

        'supports'           => [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ],
        'has_archive'        => true,
        'rewrite'            => [ 'slug' => 'servisne-info', 'with_front' => false ],
        'menu_icon'          => 'dashicons-info',
        'capability_type'    => 'post',
        'map_meta_cap'       => true,
    ] );
}
