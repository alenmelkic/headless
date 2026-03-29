<?php
/**
 * Custom Post Types
 *
 * Each CPT is optimised for:
 *  - Headless REST API  (show_in_rest, rest_base)
 *  - WPGraphQL          (show_in_graphql, graphql_single_name, graphql_plural_name)
 *  - Taxonomies         (kategorija / hierarchical, oznaka / flat — shared across CPTs)
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

        'taxonomies'         => [ 'kategorija', 'oznaka' ],

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

        'taxonomies'         => [ 'kategorija', 'oznaka' ],

        'supports'           => [ 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ],
        'has_archive'        => true,
        'rewrite'            => [ 'slug' => 'servisne-info', 'with_front' => false ],
        'menu_icon'          => 'dashicons-info',
        'capability_type'    => 'post',
        'map_meta_cap'       => true,
    ] );
}

add_action( 'init', function (): void {
    headless_register_taxonomies();
} );

/**
 * Registers shared taxonomies for all CPTs.
 *
 * kategorija — hierarchical (category-style)
 *   REST:    GET /wp-json/wp/v2/kategorije
 *   GraphQL: query { categories { nodes { ... } } }
 *
 * oznaka — flat (tag-style)
 *   REST:    GET /wp-json/wp/v2/oznake
 *   GraphQL: query { tags { nodes { ... } } }
 */
function headless_register_taxonomies(): void {

    // -----------------------------------------------------------------------
    // Kategorija — hierarchical, shared across all CPTs
    // -----------------------------------------------------------------------
    register_taxonomy( 'kategorija', [ 'obavijest', 'servisna_info' ], [
        'labels' => [
            'name'              => 'Kategorije',
            'singular_name'     => 'Kategorija',
            'search_items'      => 'Pretraži kategorije',
            'all_items'         => 'Sve kategorije',
            'parent_item'       => 'Nadređena kategorija',
            'parent_item_colon' => 'Nadređena kategorija:',
            'edit_item'         => 'Uredi kategoriju',
            'update_item'       => 'Ažuriraj kategoriju',
            'add_new_item'      => 'Dodaj novu kategoriju',
            'new_item_name'     => 'Naziv nove kategorije',
            'menu_name'         => 'Kategorije',
        ],
        'hierarchical'        => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_nav_menus'   => false,
        'show_in_rest'        => true,
        'rest_base'           => 'kategorije',
        'show_in_graphql'     => true,
        'graphql_single_name' => 'category',
        'graphql_plural_name' => 'categories',
        'rewrite'             => [ 'slug' => 'kategorije', 'with_front' => false ],
        'show_admin_column'   => true,
    ] );

    // -----------------------------------------------------------------------
    // Oznaka — flat (tag-style), shared across all CPTs
    // -----------------------------------------------------------------------
    register_taxonomy( 'oznaka', [ 'obavijest', 'servisna_info' ], [
        'labels' => [
            'name'                       => 'Oznake',
            'singular_name'              => 'Oznaka',
            'search_items'               => 'Pretraži oznake',
            'popular_items'              => 'Popularne oznake',
            'all_items'                  => 'Sve oznake',
            'edit_item'                  => 'Uredi oznaku',
            'update_item'                => 'Ažuriraj oznaku',
            'add_new_item'               => 'Dodaj novu oznaku',
            'new_item_name'              => 'Naziv nove oznake',
            'separate_items_with_commas' => 'Odvojite oznake zarezima',
            'add_or_remove_items'        => 'Dodaj ili ukloni oznake',
            'choose_from_most_used'      => 'Odaberi iz najkorištenijih',
            'menu_name'                  => 'Oznake',
        ],
        'hierarchical'        => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_nav_menus'   => false,
        'show_in_rest'        => true,
        'rest_base'           => 'oznake',
        'show_in_graphql'     => true,
        'graphql_single_name' => 'tag',
        'graphql_plural_name' => 'tags',
        'rewrite'             => [ 'slug' => 'oznake', 'with_front' => false ],
        'show_admin_column'   => true,
    ] );
}
