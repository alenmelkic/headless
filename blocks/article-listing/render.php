<?php
/**
 * Article Listing Block — Render Template (ACF Block v3)
 *
 * Queries posts based on editor-configured filters (category, tag, author, or latest)
 * and outputs structured JSON in data-props for the headless frontend.
 *
 * Variables injected by ACF Pro 6.3+:
 *   $block      (array)  Block attributes.
 *   $content    (string) Inner blocks HTML (empty for leaf blocks).
 *   $is_preview (bool)   True when rendered inside the block editor preview.
 *   $post_id    (int)    ID of the post being edited / viewed.
 *   $context    (array)  Block context from parent blocks.
 *
 * @package Headless
 */

// Validate layout against allowed values.
$layout_raw      = get_field( 'layout' ) ?: 'izgled_1';
$layout          = in_array( $layout_raw, [ 'izgled_1', 'izgled_2' ], true ) ? $layout_raw : 'izgled_1';
$najnovije       = get_field( 'najnovije' );
$category        = get_field( 'category' );
$tag             = get_field( 'tag' );
$author          = get_field( 'author' );
$number_of_posts = min( 12, max( 1, (int) ( get_field( 'number_of_posts' ) ?: 6 ) ) );

// Build WP_Query args — filters are combinable.
$query_args = [
    'post_type'              => 'post',
    'posts_per_page'         => $number_of_posts,
    'post_status'            => 'publish',
    'orderby'                => 'date',
    'order'                  => 'DESC',
    'post__not_in'           => [ $post_id ],
    'no_found_rows'          => true,   // Skip counting total rows (perf).
    'update_post_term_cache' => true,
    'update_post_meta_cache' => true,
];

if ( $category ) {
    $query_args['cat'] = (int) $category;
}
if ( $tag ) {
    $query_args['tag_id'] = (int) $tag;
}
if ( $author ) {
    $query_args['author'] = (int) $author;
}

$query    = new WP_Query( $query_args );
$articles = [];

while ( $query->have_posts() ) {
    $query->the_post();
    $pid       = get_the_ID();
    $thumb_id  = get_post_thumbnail_id( $pid );
    $author_id = (int) get_the_author_meta( 'ID' );

    // Resolve featured image.
    $featured_image = null;
    if ( $thumb_id ) {
        $src = wp_get_attachment_image_src( $thumb_id, 'full' );
        if ( $src ) {
            $featured_image = [
                'sourceUrl' => esc_url( $src[0] ),
                'altText'   => esc_attr( get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) ?: '' ),
                'width'     => (int) $src[1],
                'height'    => (int) $src[2],
            ];

            // Include amnext_* size metadata so the frontend can build srcsets
            // from real WP-generated dimensions instead of deriving them.
            $meta = wp_get_attachment_metadata( $thumb_id );
            if ( ! empty( $meta['sizes'] ) ) {
                $upload_dir = wp_get_upload_dir();
                $base_dir   = trailingslashit( dirname( $meta['file'] ) );
                $sizes_arr  = [];

                foreach ( $meta['sizes'] as $name => $size_data ) {
                    if ( str_starts_with( $name, 'amnext_' ) && ! empty( $size_data['file'] ) ) {
                        $sizes_arr[] = [
                            'name'      => $name,
                            'sourceUrl' => esc_url( $upload_dir['baseurl'] . '/' . $base_dir . $size_data['file'] ),
                            'width'     => (string) $size_data['width'],
                            'height'    => (string) $size_data['height'],
                        ];
                    }
                }

                if ( $sizes_arr ) {
                    $featured_image['mediaDetails'] = [ 'sizes' => $sizes_arr ];
                }
            }
        }
    }

    // Resolve categories.
    $categories = [];
    $post_cats  = get_the_category( $pid );
    if ( $post_cats ) {
        foreach ( $post_cats as $cat ) {
            $categories[] = [
                'id'   => (string) $cat->term_id,
                'name' => esc_html( $cat->name ),
                'slug' => sanitize_title( $cat->slug ),
            ];
        }
    }

    // Resolve custom author image (replaces Gravatar).
    $author_image_id = (int) get_user_meta( $author_id, 'headless_author_image_id', true );
    $author_avatar   = null;
    if ( $author_image_id ) {
        $avatar_src = wp_get_attachment_image_src( $author_image_id, 'thumbnail' );
        if ( $avatar_src ) {
            $author_avatar = [ 'url' => esc_url( $avatar_src[0] ) ];
        }
    }

    // Compute reading time server-side to avoid sending full content.
    $raw_content   = get_the_content();
    $text_only     = wp_strip_all_tags( $raw_content );
    $word_count    = str_word_count( $text_only );
    $reading_time  = max( 1, (int) ceil( $word_count / 200 ) );

    // Sanitize excerpt — strip tags, send plain text.
    $excerpt_raw = get_the_excerpt();
    $excerpt     = wp_strip_all_tags( $excerpt_raw );

    $articles[] = [
        'id'            => (string) $pid,
        'slug'          => sanitize_title( get_post_field( 'post_name', $pid ) ),
        'title'         => esc_html( get_the_title() ),
        'date'          => get_the_date( 'c' ),
        'excerpt'       => $excerpt,
        'readingTime'   => $reading_time,
        'featuredImage' => $featured_image,
        'categories'    => $categories,
        'author'        => [
            'name'      => esc_html( get_the_author() ),
            'firstName' => esc_html( get_the_author_meta( 'first_name' ) ),
            'lastName'  => esc_html( get_the_author_meta( 'last_name' ) ),
            'slug'      => sanitize_title( get_the_author_meta( 'user_nicename' ) ),
            'avatar'    => $author_avatar,
        ],
    ];
}
wp_reset_postdata();

$props = [
    'layout'   => $layout,
    'articles' => $articles,
];

$block_id = ! empty( $block['anchor'] ) ? esc_attr( $block['anchor'] ) : $block['id'];
$classes  = array_filter( [
    'block',
    'block-article-listing',
    $block['className'] ?? '',
    ! empty( $block['align'] ) ? 'align' . $block['align'] : '',
] );

// Build filter description for editor preview.
$filter_parts = [];
if ( $najnovije ) {
    $filter_parts[] = 'Najnovije';
}
if ( $category ) {
    $cat_obj = get_category( (int) $category );
    if ( $cat_obj ) {
        $filter_parts[] = 'Kategorija: ' . $cat_obj->name;
    }
}
if ( $tag ) {
    $tag_obj = get_tag( (int) $tag );
    if ( $tag_obj ) {
        $filter_parts[] = 'Oznaka: ' . $tag_obj->name;
    }
}
if ( $author ) {
    $user_obj = get_userdata( (int) $author );
    if ( $user_obj ) {
        $filter_parts[] = 'Autor: ' . $user_obj->display_name;
    }
}
$filter_label = $filter_parts ? implode( ' + ', $filter_parts ) : 'Svi članci';
?>
<section
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="article-listing"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
>
    <?php if ( $is_preview ) : ?>
        <div style="padding: 16px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px;">
            <p style="margin: 0 0 8px; font-weight: 600; font-size: 14px;">
                📰 Article Listing — <?php echo esc_html( $layout === 'izgled_1' ? 'Izgled 1' : 'Izgled 2' ); ?>
            </p>
            <p style="margin: 0 0 8px; font-size: 12px; color: #666;">
                <?php echo esc_html( $filter_label ); ?> • <?php echo count( $articles ); ?> članaka
            </p>
            <?php if ( $articles ) : ?>
                <ol style="margin: 0; padding-left: 20px; font-size: 13px;">
                    <?php foreach ( $articles as $article ) : ?>
                        <li><?php echo esc_html( $article['title'] ); ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php else : ?>
                <p style="margin: 0; font-size: 13px; color: #999;">Nema pronađenih članaka.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
