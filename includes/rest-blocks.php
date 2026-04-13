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

        // Surface ACF field data on the response object.
        // Prefer the processed data-props from render.php (which resolves
        // attachment IDs, runs WP_Query, etc.) over the raw ACF storage.
        if ( str_starts_with( $block['blockName'], 'acf/' ) ) {
            if ( $entry['html'] && preg_match( '/data-props="([^"]*)"/', $entry['html'], $m ) ) {
                $decoded = json_decode( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ), true );
                if ( is_array( $decoded ) ) {
                    $entry['fields'] = $decoded;
                }
            }
            // Fallback to raw ACF data if no data-props found.
            if ( empty( $entry['fields'] ) && ! empty( $block['attrs']['data'] ) ) {
                $entry['fields'] = $block['attrs']['data'];
            }
        }

        // Enrich core/image with actual image data from the attachment.
        // parse_blocks() only stores id/sizeSlug/align/className/style in
        // attrs — url and alt live in the HTML. Resolve them so the headless
        // frontend can use next/image instead of raw HTML.
        if ( $block['blockName'] === 'core/image' && ! empty( $block['attrs']['id'] ) ) {
            $att_id   = (int) $block['attrs']['id'];
            $size     = $block['attrs']['sizeSlug'] ?? 'full';
            $img_src  = wp_get_attachment_image_src( $att_id, $size );

            if ( $img_src ) {
                $entry['attrs']['url'] = $img_src[0];

                // Only set width/height from attachment if the editor didn't
                // set custom dimensions (user may have resized the image).
                if ( empty( $entry['attrs']['width'] ) ) {
                    $entry['attrs']['width'] = $img_src[1];
                }
                if ( empty( $entry['attrs']['height'] ) ) {
                    $entry['attrs']['height'] = $img_src[2];
                }
            }

            $entry['attrs']['alt'] = get_post_meta( $att_id, '_wp_attachment_image_alt', true ) ?: '';

            // Caption, href, and linkTarget are only in the rendered HTML.
            if ( preg_match( '/<figcaption[^>]*>(.*?)<\/figcaption>/s', $entry['html'], $m ) ) {
                $entry['attrs']['caption'] = $m[1];
            }
            if ( preg_match( '/<a[^>]*href=["\']([^"\']+)["\']/', $entry['html'], $m ) ) {
                $entry['attrs']['href'] = $m[1];
            }
            if ( preg_match( '/target=["\']([^"\']+)["\']/', $entry['html'], $m ) ) {
                $entry['attrs']['linkTarget'] = $m[1];
            }
        }

        $result[] = $entry;
    }

    return $result;
}
