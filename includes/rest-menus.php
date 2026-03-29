<?php
/**
 * REST Endpoint — Navigation Menus
 *
 *   GET /wp-json/headless/v1/menus/{location}
 *
 * Returns a flat array of menu items for a registered nav menu location.
 * Valid locations: primary, footer, mobile.
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;


add_action( 'rest_api_init', function () {
    register_rest_route( 'headless/v1', '/menus/(?P<location>[a-zA-Z0-9_-]+)', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'headless_get_menu_by_location',
        'permission_callback' => '__return_true',
        'args'                => [
            'location' => [
                'required'          => true,
                'sanitize_callback' => 'sanitize_key',
            ],
        ],
    ] );
} );

/**
 * Returns a flat array of menu items for a registered nav menu location.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function headless_get_menu_by_location( WP_REST_Request $request ) {
    $location  = $request->get_param( 'location' );
    $locations = get_nav_menu_locations();

    if ( empty( $locations[ $location ] ) ) {
        return new WP_Error(
            'no_menu',
            sprintf( __( 'No menu assigned to location "%s".', 'headless' ), $location ),
            [ 'status' => 404 ]
        );
    }

    $menu  = wp_get_nav_menu_object( $locations[ $location ] );
    $items = wp_get_nav_menu_items( $menu->term_id );

    if ( ! $items ) {
        return rest_ensure_response( [] );
    }

    $data = array_map( function ( $item ) {
        return [
            'id'        => (int) $item->ID,
            'parent'    => (int) $item->menu_item_parent,
            'order'     => (int) $item->menu_order,
            'title'     => $item->title,
            'url'       => $item->url,
            'target'    => $item->target,
            'classes'   => array_filter( $item->classes ),
            'object'    => $item->object,
            'object_id' => (int) $item->object_id,
            'type'      => $item->type,
        ];
    }, $items );

    return rest_ensure_response( $data );
}
