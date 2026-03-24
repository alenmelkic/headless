<?php
/**
 * Headless v3 — Theme Functions
 *
 * Configures WordPress as a pure headless / decoupled back-end:
 *  - REST API with CORS support
 *  - Featured images exposed in REST responses
 *  - Navigation menus registered
 *  - Custom post types ready to be consumed by the front-end
 *  - No unnecessary front-end assets enqueued
 *
 * @package Headless
 * @author  Alen Melkic
 * @version 3.0.0
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Includes
// ---------------------------------------------------------------------------

require_once get_template_directory() . '/includes/cpt.php';
require_once get_template_directory() . '/includes/patterns.php';

// ---------------------------------------------------------------------------
// 1. Theme Support
// ---------------------------------------------------------------------------

add_action( 'after_setup_theme', function () {
    // Allow WordPress to manage the document <title>.
    add_theme_support( 'title-tag' );

    // Featured images in posts / pages.
    add_theme_support( 'post-thumbnails' );

    // HTML5 markup for built-in WordPress output.
    add_theme_support( 'html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'script',
        'style',
    ] );

    // Wide / full alignment support for blocks.
    add_theme_support( 'align-wide' );

    // Register navigation menus (consumed via REST API /wp/v2/menus).
    register_nav_menus( [
        'primary'   => __( 'Primary Navigation', 'headless' ),
        'footer'    => __( 'Footer Navigation', 'headless' ),
        'mobile'    => __( 'Mobile Navigation', 'headless' ),
    ] );
} );


// ---------------------------------------------------------------------------
// 2. REST API — Expose Featured Image URL directly on posts/pages
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', function () {

    $post_types = [ 'post', 'page' ];

    foreach ( $post_types as $type ) {
        register_rest_field( $type, 'featured_image_url', [
            'get_callback' => function ( $post ) {
                $id = $post['featured_media'] ?? 0;
                return $id ? wp_get_attachment_image_url( $id, 'full' ) : null;
            },
            'schema' => [
                'description' => __( 'Full URL of the featured image.', 'headless' ),
                'type'        => 'string',
                'context'     => [ 'view', 'embed' ],
            ],
        ] );
    }
} );


// ---------------------------------------------------------------------------
// 3. CORS Headers for REST API
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', function () {
    remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );

    add_filter( 'rest_pre_serve_request', function ( $value ) {
        $allowed_origins = apply_filters( 'headless_cors_allowed_origins', [
            'http://localhost:3000',
            'http://localhost:3001',
            'http://localhost:5173',
        ] );

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ( in_array( $origin, $allowed_origins, true ) ) {
            header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
        } elseif ( empty( $allowed_origins ) ) {
            header( 'Access-Control-Allow-Origin: *' );
        }

        header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
        header( 'Access-Control-Allow-Credentials: true' );
        header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );

        return $value;
    } );
}, 15 );


// ---------------------------------------------------------------------------
// 4. Disable Front-End Assets (no stylesheet / scripts needed server-side)
// ---------------------------------------------------------------------------

add_action( 'wp_enqueue_scripts', function () {
    // Remove the default block library CSS — not needed in headless mode.
    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'wp-block-library-theme' );
    wp_dequeue_style( 'classic-theme-styles' );
    wp_dequeue_style( 'global-styles' );
}, 100 );


// ---------------------------------------------------------------------------
// 5. Expose Menus via REST API  (/wp-json/headless/v1/menus/<location>)
// ---------------------------------------------------------------------------

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
    $location = $request->get_param( 'location' );
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


// ---------------------------------------------------------------------------
// 6. Site Options Endpoint  (/wp-json/headless/v1/options)
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', function () {
    register_rest_route( 'headless/v1', '/options', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'headless_get_site_options',
        'permission_callback' => '__return_true',
    ] );
} );

/**
 * Returns whitelisted site options useful for a front-end application.
 *
 * @return WP_REST_Response
 */
function headless_get_site_options() {
    return rest_ensure_response( [
        'name'          => get_bloginfo( 'name' ),
        'description'   => get_bloginfo( 'description' ),
        'url'           => get_bloginfo( 'url' ),
        'admin_email'   => get_bloginfo( 'admin_email' ),
        'language'      => get_bloginfo( 'language' ),
        'charset'       => get_bloginfo( 'charset' ),
        'timezone'      => wp_timezone_string(),
        'date_format'   => get_option( 'date_format' ),
        'time_format'   => get_option( 'time_format' ),
        'posts_per_page' => (int) get_option( 'posts_per_page' ),
    ] );
}


// ---------------------------------------------------------------------------
// 7. Add Image Sizes
// ---------------------------------------------------------------------------

add_action( 'after_setup_theme', function () {
    add_image_size( 'headless-thumbnail', 400, 300, true );
    add_image_size( 'headless-medium',    800, 600, false );
    add_image_size( 'headless-large',    1200, 900, false );
} );


// ---------------------------------------------------------------------------
// 8. Expose Custom Image Sizes in REST Media Responses
// ---------------------------------------------------------------------------

add_filter( 'wp_get_attachment_image_src', '__return_false', 0 ); // no-op placeholder

add_filter( 'rest_prepare_attachment', function ( WP_REST_Response $response ) {
    $sizes = wp_get_registered_image_subsizes();
    $data  = $response->get_data();
    $id    = $data['id'] ?? 0;

    if ( ! $id ) {
        return $response;
    }

    $extra = [];
    foreach ( array_keys( $sizes ) as $size ) {
        $src = wp_get_attachment_image_src( $id, $size );
        if ( $src ) {
            $extra[ $size ] = [
                'url'    => $src[0],
                'width'  => $src[1],
                'height' => $src[2],
            ];
        }
    }

    if ( ! empty( $extra ) ) {
        $data['media_details']['sizes'] = array_merge(
            $data['media_details']['sizes'] ?? [],
            $extra
        );
        $response->set_data( $data );
    }

    return $response;
}, 10, 1 );

// Remove the no-op filter we added above.
remove_filter( 'wp_get_attachment_image_src', '__return_false', 0 );


// ---------------------------------------------------------------------------
// 9. ACF Blocks — Auto-Registration
//
// Place each block in its own sub-folder inside /blocks/:
//   blocks/
//     my-block/
//       block.json   ← required (ACF Pro 6.0+ block manifest)
//       render.php   ← required (server-side render template)
//       fields.php   ← optional (programmatic ACF field group)
//
// To add a new block, duplicate an existing block folder and update
// block.json + render.php. Fields can also be created in the ACF UI
// and synced to fields.php later.
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

        // Register via block.json (requires ACF Pro 6.0+).
        register_block_type( $block_dir );

        // Auto-load programmatic field registration if present.
        $fields_file = $block_dir . '/fields.php';
        if ( file_exists( $fields_file ) ) {
            require_once $fields_file;
        }
    }
} );


// ---------------------------------------------------------------------------
// 10. Blocks REST Endpoint  GET /wp-json/headless/v1/posts/<id>/blocks
//
// Returns every block in a post as structured data:
//   - name   : block name  (e.g. "acf/hero")
//   - attrs  : raw block attributes
//   - html   : server-rendered HTML (use as fallback or for SSR)
//   - fields : ACF field data  (only present on acf/* blocks)
// ---------------------------------------------------------------------------

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
function headless_get_post_blocks( WP_REST_Request $request ) {
    $post_id = (int) $request->get_param( 'id' );
    $post    = get_post( $post_id );

    if ( ! $post || ! is_post_publicly_viewable( $post ) ) {
        return new WP_Error(
            'not_found',
            __( 'Post not found.', 'headless' ),
            [ 'status' => 404 ]
        );
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


// ---------------------------------------------------------------------------
// 11. ACF Options Page
//
// Adds a top-level "Global Options" admin page (requires ACF Pro).
// Fields added here (logo, social links, contact info, etc.) are returned by
// the /wp-json/headless/v1/options/global endpoint below.
//
// Define constants in wp-config.php to configure the front-end URL:
//   define( 'HEADLESS_FRONTEND_URL',     'https://your-frontend.com' );
//   define( 'HEADLESS_PREVIEW_SECRET',   'a-strong-random-secret' );
//   define( 'HEADLESS_REVALIDATE_URL',   'https://your-frontend.com/api/revalidate' );
//   define( 'HEADLESS_REVALIDATE_SECRET','another-strong-secret' );
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

add_action( 'rest_api_init', function () {
    register_rest_route( 'headless/v1', '/options/global', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'headless_get_global_options',
        'permission_callback' => '__return_true',
    ] );
} );

/**
 * Returns all ACF fields from the global options page.
 *
 * @return WP_REST_Response
 */
function headless_get_global_options(): WP_REST_Response {
    $fields = function_exists( 'get_fields' ) ? ( get_fields( 'options' ) ?: [] ) : [];
    return rest_ensure_response( $fields );
}


// ---------------------------------------------------------------------------
// 12. ACF Fields on All REST Post-Type Responses
//
// Adds an "acf" key to every publicly queryable post type's REST response so
// the front-end never needs a second round-trip to fetch custom fields.
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', function () {
    if ( ! function_exists( 'get_fields' ) ) {
        return;
    }

    $types = get_post_types( [ 'show_in_rest' => true ], 'names' );

    foreach ( $types as $type ) {
        register_rest_field( $type, 'acf', [
            'get_callback' => function ( array $post ): mixed {
                $fields = get_fields( $post['id'] );
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


// ---------------------------------------------------------------------------
// 13. Draft Preview Redirect
//
// Intercepts the WP "Preview" button and sends editors to the front-end
// preview route instead of the WordPress front-end.
//
// Expected front-end route: /api/preview?id=X&type=Y&token=Z
// The token is an HMAC (HEADLESS_PREVIEW_SECRET) or a WP nonce as fallback.
// ---------------------------------------------------------------------------

add_filter( 'preview_post_link', function ( string $link, WP_Post $post ): string {
    $frontend = defined( 'HEADLESS_FRONTEND_URL' )
        ? HEADLESS_FRONTEND_URL
        : get_option( 'headless_frontend_url', '' );

    if ( ! $frontend ) {
        return $link;
    }

    $secret = defined( 'HEADLESS_PREVIEW_SECRET' ) ? HEADLESS_PREVIEW_SECRET : '';
    $token  = $secret
        ? hash_hmac( 'sha256', $post->ID . '|' . $post->post_type, $secret )
        : wp_create_nonce( 'headless_preview_' . $post->ID );

    return add_query_arg(
        [
            'preview'   => 'true',
            'id'        => $post->ID,
            'post_type' => $post->post_type,
            'token'     => $token,
        ],
        trailingslashit( $frontend ) . 'api/preview'
    );
}, 10, 2 );

/**
 * REST endpoint the front-end preview route uses to verify the token and
 * fetch draft content.  GET /wp-json/headless/v1/preview?id=X&token=Y
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'headless/v1', '/preview', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'headless_verify_preview',
        'permission_callback' => '__return_true',
        'args'                => [
            'id'    => [ 'required' => true,  'validate_callback' => 'is_numeric' ],
            'token' => [ 'required' => true ],
        ],
    ] );
} );

/**
 * Verifies preview token and returns the latest draft content for a post.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function headless_verify_preview( WP_REST_Request $request ): WP_REST_Response|WP_Error {
    $id    = (int) $request->get_param( 'id' );
    $token = $request->get_param( 'token' );

    $secret = defined( 'HEADLESS_PREVIEW_SECRET' ) ? HEADLESS_PREVIEW_SECRET : '';

    if ( $secret ) {
        $expected = hash_hmac( 'sha256', $id . '|' . get_post_type( $id ), $secret );
        $valid     = hash_equals( $expected, $token );
    } else {
        $valid = (bool) wp_verify_nonce( $token, 'headless_preview_' . $id );
    }

    if ( ! $valid ) {
        return new WP_Error( 'invalid_token', __( 'Invalid or expired preview token.', 'headless' ), [ 'status' => 403 ] );
    }

    $post = get_post( $id );
    if ( ! $post ) {
        return new WP_Error( 'not_found', __( 'Post not found.', 'headless' ), [ 'status' => 404 ] );
    }

    // Use the latest revision if one exists, otherwise use the post itself.
    $revisions = wp_get_post_revisions( $id, [ 'posts_per_page' => 1 ] );
    $source    = $revisions ? reset( $revisions ) : $post;

    return rest_ensure_response( [
        'id'        => $id,
        'type'      => $post->post_type,
        'slug'      => $post->post_name,
        'status'    => $post->post_status,
        'title'     => get_the_title( $source ),
        'content'   => apply_filters( 'the_content', $source->post_content ),
        'excerpt'   => get_the_excerpt( $source ),
        'acf'       => function_exists( 'get_fields' ) ? ( get_fields( $id ) ?: (object) [] ) : (object) [],
        'blocks'    => headless_parse_blocks_recursive( parse_blocks( $source->post_content ) ),
    ] );
}


// ---------------------------------------------------------------------------
// 14. SEO Meta Endpoint  GET /wp-json/headless/v1/seo/<id>
//
// Returns SEO data with automatic plugin detection:
//   1. Yoast SEO (if active)
//   2. RankMath (if active)
//   3. Fallback — basic WordPress data
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', function () {
    register_rest_route( 'headless/v1', '/seo/(?P<id>\d+)', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'headless_get_seo_meta',
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
 * Returns SEO meta for a post, with Yoast / RankMath passthrough.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function headless_get_seo_meta( WP_REST_Request $request ): WP_REST_Response|WP_Error {
    $post_id = (int) $request->get_param( 'id' );
    $post    = get_post( $post_id );

    if ( ! $post || ! is_post_publicly_viewable( $post ) ) {
        return new WP_Error( 'not_found', __( 'Post not found.', 'headless' ), [ 'status' => 404 ] );
    }

    // --- Yoast SEO ---
    if ( defined( 'WPSEO_VERSION' ) && function_exists( 'YoastSEO' ) ) {
        $meta = YoastSEO()->meta->for_post( $post_id );
        return rest_ensure_response( [
            'title'               => $meta->title,
            'description'         => $meta->description,
            'robots'              => $meta->robots,
            'canonical'           => $meta->canonical,
            'og_title'            => $meta->open_graph_title,
            'og_description'      => $meta->open_graph_description,
            'og_image'            => $meta->open_graph_images[0]['url'] ?? null,
            'twitter_title'       => $meta->twitter_title,
            'twitter_description' => $meta->twitter_description,
            'source'              => 'yoast',
        ] );
    }

    // --- RankMath SEO ---
    if ( class_exists( 'RankMath' ) ) {
        $thumbnail_url = null;
        $rm_image_id   = get_post_meta( $post_id, 'rank_math_facebook_image_id', true );
        if ( $rm_image_id ) {
            $thumbnail_url = wp_get_attachment_image_url( (int) $rm_image_id, 'headless-large' );
        }

        return rest_ensure_response( [
            'title'               => get_post_meta( $post_id, 'rank_math_title', true )       ?: get_the_title( $post ),
            'description'         => get_post_meta( $post_id, 'rank_math_description', true )  ?: '',
            'robots'              => get_post_meta( $post_id, 'rank_math_robots', true )        ?: 'index, follow',
            'canonical'           => get_post_meta( $post_id, 'rank_math_canonical_url', true ) ?: get_permalink( $post ),
            'og_title'            => get_post_meta( $post_id, 'rank_math_facebook_title', true )       ?: get_the_title( $post ),
            'og_description'      => get_post_meta( $post_id, 'rank_math_facebook_description', true ) ?: '',
            'og_image'            => $thumbnail_url,
            'twitter_title'       => get_post_meta( $post_id, 'rank_math_twitter_title', true )       ?: get_the_title( $post ),
            'twitter_description' => get_post_meta( $post_id, 'rank_math_twitter_description', true ) ?: '',
            'source'              => 'rankmath',
        ] );
    }

    // --- Fallback: basic WordPress data ---
    $thumbnail_id  = get_post_thumbnail_id( $post_id );
    $thumbnail_url = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'headless-large' ) : null;
    $description   = has_excerpt( $post )
        ? get_the_excerpt( $post )
        : wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );

    return rest_ensure_response( [
        'title'               => get_the_title( $post ),
        'description'         => $description,
        'robots'              => 'index, follow',
        'canonical'           => get_permalink( $post ),
        'og_title'            => get_the_title( $post ),
        'og_description'      => $description,
        'og_image'            => $thumbnail_url,
        'twitter_title'       => get_the_title( $post ),
        'twitter_description' => $description,
        'source'              => 'fallback',
    ] );
}


// ---------------------------------------------------------------------------
// 15. Revalidation Webhook
//
// On every post save/publish, fires a non-blocking HTTP POST to the front-end
// revalidation endpoint so Next.js ISR / Nuxt caches are purged automatically.
//
// Configure via constants in wp-config.php:
//   define( 'HEADLESS_REVALIDATE_URL',    'https://your-frontend.com/api/revalidate' );
//   define( 'HEADLESS_REVALIDATE_SECRET', 'your-secret' );
// ---------------------------------------------------------------------------

add_action( 'save_post', function ( int $post_id, WP_Post $post ): void {
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }

    $endpoint = defined( 'HEADLESS_REVALIDATE_URL' )
        ? HEADLESS_REVALIDATE_URL
        : ( function () {
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
        'body'     => wp_json_encode( [
            'post_id'   => $post_id,
            'post_type' => $post->post_type,
            'slug'      => $post->post_name,
            'status'    => $post->post_status,
            'permalink' => get_permalink( $post_id ),
        ] ),
        'timeout'  => 5,
        'blocking' => false, // fire-and-forget — don't slow down the editor save
    ] );
}, 10, 2 );


// ---------------------------------------------------------------------------
// 16. Noindex WP Front-End
//
// Since WordPress is used purely as a headless back-end, we tell search
// engines to ignore all front-end pages served by WordPress itself.
// ---------------------------------------------------------------------------

add_action( 'wp_head', function (): void {
    echo '<meta name="robots" content="noindex, nofollow">' . PHP_EOL;
}, 1 );

add_filter( 'wp_headers', function ( array $headers ): array {
    $headers['X-Robots-Tag'] = 'noindex, nofollow';
    return $headers;
} );


// ---------------------------------------------------------------------------
// 17. Allowed Blocks — Custom ACF Blocks Only
//
// Disables every default WordPress / Gutenberg block and only exposes
// the ACF blocks registered in /blocks/*/block.json.
//
// Block names are read dynamically from block.json files so adding a new
// block folder automatically makes it available — no changes needed here.
// ---------------------------------------------------------------------------

add_filter( 'allowed_block_types_all', function ( array|bool $allowed, WP_Block_Editor_Context $context ): array {
    // Build the list of custom ACF blocks from block.json files.
    $acf_blocks = [];
    foreach ( glob( get_template_directory() . '/blocks/*/block.json' ) ?: [] as $manifest ) {
        $data = json_decode( file_get_contents( $manifest ), true );
        if ( ! empty( $data['name'] ) ) {
            $acf_blocks[] = $data['name'];
        }
    }

    // On standard Posts also allow core Text and Image blocks.
    if (
        isset( $context->post ) &&
        $context->post instanceof WP_Post &&
        $context->post->post_type === 'post'
    ) {
        return array_merge( $acf_blocks, [ 'core/paragraph', 'core/image' ] );
    }

    // Every other post type: custom ACF blocks only.
    return $acf_blocks;
}, 10, 2 );

// ---------------------------------------------------------------------------
// 18. SoundCloud Tracks Endpoint  GET /wp-json/headless/v1/soundcloud/tracks
//
// Fetches the public RSS feed for the SoundCloud channel and returns a
// structured track list. Results are cached in a WP transient for 1 hour.
//
// Bootstrap: on first call the channel page is fetched to discover the RSS
// feed URL (embedded as <link rel="alternate" type="application/rss+xml">
// in the page <head>), which is then stored in wp_options for reuse.
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', function () {
    register_rest_route( 'headless/v1', '/soundcloud/tracks', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'headless_get_soundcloud_tracks',
        'permission_callback' => '__return_true',
    ] );
} );

/**
 * REST callback — wraps headless_soundcloud_fetch_tracks() for the REST layer.
 */
function headless_get_soundcloud_tracks(): WP_REST_Response|WP_Error {
    $tracks = headless_soundcloud_fetch_tracks();
    if ( is_wp_error( $tracks ) ) {
        return $tracks;
    }
    return rest_ensure_response( $tracks );
}

/**
 * Fetches, parses, and caches tracks from the SoundCloud channel RSS feed.
 *
 * @return array|WP_Error Array of track objects on success, WP_Error on failure.
 */
function headless_soundcloud_fetch_tracks(): array|WP_Error {

    // 1. Return cached result if available.
    $cached = get_transient( 'headless_soundcloud_tracks' );
    if ( false !== $cached ) {
        return $cached;
    }

    // 2. Respect negative-cache: bootstrap recently failed — don't retry for 5 min.
    if ( get_transient( 'headless_soundcloud_bootstrap_failed' ) ) {
        return new WP_Error(
            'soundcloud_bootstrap_failed',
            __( 'SoundCloud channel is temporarily unavailable. Please try again shortly.', 'headless' ),
            [ 'status' => 503 ]
        );
    }

    // 3. Get stored RSS URL, or bootstrap by fetching the channel page.
    $rss_url = get_option( 'headless_soundcloud_rss_url', '' );

    if ( ! $rss_url ) {
        $channel_url = 'https://soundcloud.com/radio-velika-kladu-a';
        $response    = wp_remote_get( $channel_url, [ 'timeout' => 10 ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            set_transient( 'headless_soundcloud_bootstrap_failed', true, 300 );
            return new WP_Error(
                'soundcloud_bootstrap_failed',
                __( 'Failed to reach the SoundCloud channel page.', 'headless' ),
                [ 'status' => 503 ]
            );
        }

        $body = wp_remote_retrieve_body( $response );

        // Match <link ... type="application/rss+xml" ... href="..."> in either attribute order.
        if ( ! preg_match( '/<link[^>]+type=["\']application\/rss\+xml["\'][^>]+href=["\']([^"\']+)["\']/', $body, $m ) &&
             ! preg_match( '/<link[^>]+href=["\']([^"\']+)["\'][^>]+type=["\']application\/rss\+xml["\']/', $body, $m ) ) {
            set_transient( 'headless_soundcloud_bootstrap_failed', true, 300 );
            return new WP_Error(
                'soundcloud_bootstrap_failed',
                __( 'Could not find RSS feed link on the SoundCloud channel page.', 'headless' ),
                [ 'status' => 503 ]
            );
        }

        $rss_url = esc_url_raw( $m[1] );
        update_option( 'headless_soundcloud_rss_url', $rss_url );
    }

    // 4. Fetch the RSS feed.
    $rss_response = wp_remote_get( $rss_url, [ 'timeout' => 10 ] );

    if ( is_wp_error( $rss_response ) || wp_remote_retrieve_response_code( $rss_response ) !== 200 ) {
        return new WP_Error(
            'soundcloud_rss_failed',
            __( 'Failed to fetch the SoundCloud RSS feed.', 'headless' ),
            [ 'status' => 502 ]
        );
    }

    // 5. Parse the RSS XML.
    $xml_body = wp_remote_retrieve_body( $rss_response );
    $prev_libxml = libxml_use_internal_errors( true );
    $xml         = simplexml_load_string( $xml_body );
    libxml_clear_errors();
    libxml_use_internal_errors( $prev_libxml );

    if ( ! $xml ) {
        return new WP_Error(
            'soundcloud_rss_failed',
            __( 'Failed to parse the SoundCloud RSS feed.', 'headless' ),
            [ 'status' => 502 ]
        );
    }

    if ( ! isset( $xml->channel ) ) {
        return new WP_Error(
            'soundcloud_rss_failed',
            __( 'SoundCloud RSS feed is not a valid RSS document.', 'headless' ),
            [ 'status' => 502 ]
        );
    }

    // 6. Map items to track objects.
    $xml->registerXPathNamespace( 'itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd' );

    $tracks = [];

    foreach ( $xml->channel->item as $item ) {
        $item->registerXPathNamespace( 'itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd' );

        $url = (string) $item->link;

        // Skip items whose URL is not on soundcloud.com (e.g. redirect or mobile URLs).
        if ( ! preg_match( '#^https://soundcloud\.com/#', $url ) ) {
            continue;
        }

        $duration_nodes = $item->xpath( 'itunes:duration' );
        $image_nodes    = $item->xpath( 'itunes:image' );

        $tracks[] = [
            'title'       => (string) $item->title,
            'url'         => esc_url_raw( $url ),
            'duration'    => $duration_nodes ? (string) $duration_nodes[0] : '',
            'artwork_url' => $image_nodes    ? esc_url_raw( (string) $image_nodes[0]['href'] ) : '',
        ];
    }

    // 7. Cache for 1 hour and return.
    set_transient( 'headless_soundcloud_tracks', $tracks, HOUR_IN_SECONDS );

    return $tracks;
}
