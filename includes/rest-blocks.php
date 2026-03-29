<?php
/**
 * REST Endpoint — Post Blocks
 *
 *   GET /wp-json/headless/v1/posts/{id}/blocks
 *
 * Returns every block in a post as structured JSON:
 *   name        — block name  (e.g. "acf/hero")
 *   attrs       — raw block attributes
 *   html        — server-rendered HTML (SSR / fallback)
 *   fields      — ACF field data (acf/* blocks only)
 *   innerBlocks — nested inner block tree
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;


add_action( 'rest_api_init', function () {
    register_rest_route( 'headless/v1', '/posts/(?P<id>\d+)/blocks', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'headless_get_post_blocks',
        'permission_callback' => '__return_true',
        'args'                => [
            'id' => [
                'required'          => true,
                'validate_callback' => fn( $v ) => is_numeric( $v ),
            ],
        ],
    ] );
} );

/**
 * Returns all blocks for a post as structured JSON, including nested inner blocks.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function headless_get_post_blocks( WP_REST_Request $request ): WP_REST_Response|WP_Error {
    $post_id = (int) $request->get_param( 'id' );
    $post    = get_post( $post_id );

    if ( ! $post || ! is_post_publicly_viewable( $post ) ) {
        return new WP_Error( 'not_found', __( 'Post not found.', 'headless' ), [ 'status' => 404 ] );
    }

    return rest_ensure_response(
        headless_parse_blocks_recursive( parse_blocks( $post->post_content ) )
    );
}

/**
 * Recursively converts a blocks array into structured response data.
 * Preserves the full inner block tree so the front-end can render nested layouts.
 *
 * @param array $blocks Raw blocks from parse_blocks().
 * @return array
 */
function headless_parse_blocks_recursive( array $blocks ): array {
    $result = [];

    foreach ( $blocks as $block ) {
        if ( empty( $block['blockName'] ) ) {
            continue;
        }

        $entry = [
            'name'        => $block['blockName'],
            'attrs'       => $block['attrs'],
            'html'        => trim( render_block( $block ) ),
            'innerBlocks' => headless_parse_blocks_recursive( $block['innerBlocks'] ?? [] ),
        ];

        // Surface ACF field data directly on the response object.
        if ( str_starts_with( $block['blockName'], 'acf/' ) && ! empty( $block['attrs']['data'] ) ) {
            $entry['fields'] = $block['attrs']['data'];
        }

        $result[] = $entry;
    }

    return $result;
}
