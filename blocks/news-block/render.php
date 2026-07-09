<?php
/**
 * News Block (3 kolone) — Render Template (ACF Block v3)
 *
 * Queries posts based on editor-configured category/tag filters and outputs
 * structured JSON in data-props for the headless frontend.
 *
 * Layout: 2 card columns + 1 list column, with category title & description header.
 *
 * @package Headless
 */

$category        = get_field( 'category' );
$tag             = get_field( 'tag' );
$number_of_posts = min( 12, max( 1, (int) ( get_field( 'number_of_posts' ) ?: 6 ) ) );

// Resolve category metadata for the header.
$category_name = '';
$category_desc = '';
$category_slug = '';
if ( $category ) {
    $cat_obj = get_category( (int) $category );
    if ( $cat_obj && ! is_wp_error( $cat_obj ) ) {
        $category_name = esc_html( $cat_obj->name );
        $category_desc = esc_html( wp_strip_all_tags( $cat_obj->description ) );
        $category_slug = sanitize_title( $cat_obj->slug );
    }
}

// Build WP_Query args.
$query_args = [
    'post_type'              => 'post',
    'posts_per_page'         => $number_of_posts,
    'post_status'            => 'publish',
    'orderby'                => 'date',
    'order'                  => 'DESC',
    'post__not_in'           => [ $post_id ],
    'no_found_rows'          => true,
    'update_post_term_cache' => true,
    'update_post_meta_cache' => true,
];

if ( $category ) {
    $query_args['cat'] = (int) $category;
}
if ( $tag ) {
    $query_args['tag_id'] = (int) $tag;
}

$query    = new WP_Query( $query_args );
$articles = [];

while ( $query->have_posts() ) {
    $query->the_post();
    $pid      = get_the_ID();
    $thumb_id = get_post_thumbnail_id( $pid );

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
                'id'    => (string) $cat->term_id,
                'name'  => esc_html( $cat->name ),
                'slug'  => sanitize_title( $cat->slug ),
                'color' => get_term_meta( $cat->term_id, 'category_color', true ) ?: null,
            ];
        }
    }

    // Compute reading time.
    $raw_content  = get_the_content();
    $text_only    = wp_strip_all_tags( $raw_content );
    $word_count   = str_word_count( $text_only );
    $reading_time = max( 1, (int) ceil( $word_count / 200 ) );

    // Sanitize excerpt.
    $excerpt = wp_strip_all_tags( get_the_excerpt() );

    $articles[] = [
        'id'            => (string) $pid,
        'slug'          => sanitize_title( get_post_field( 'post_name', $pid ) ),
        'title'         => esc_html( get_the_title() ),
        'date'          => get_the_date( 'c' ),
        'excerpt'       => $excerpt,
        'readingTime'   => $reading_time,
        'featuredImage' => $featured_image,
        'categories'    => $categories,
    ];
}
wp_reset_postdata();

$category_color = null;
if ( $category && isset( $cat_obj ) && $cat_obj && ! is_wp_error( $cat_obj ) ) {
    $category_color = get_term_meta( $cat_obj->term_id, 'category_color', true ) ?: null;
}

$props = [
    'categoryName'  => $category_name,
    'categoryDesc'  => $category_desc,
    'categorySlug'  => $category_slug,
    'categoryColor' => $category_color,
    'articles'      => $articles,
];

$block_id = ! empty( $block['anchor'] ) ? esc_attr( $block['anchor'] ) : $block['id'];
$classes  = array_filter( [
    'block',
    'block-news-block',
    $block['className'] ?? '',
    ! empty( $block['align'] ) ? 'align' . $block['align'] : '',
] );

// Build filter description for editor preview.
$filter_parts = [];
if ( $category_name ) {
    $filter_parts[] = 'Kategorija: ' . $category_name;
}
if ( $tag ) {
    $tag_obj = get_tag( (int) $tag );
    if ( $tag_obj ) {
        $filter_parts[] = 'Oznaka: ' . $tag_obj->name;
    }
}
$filter_label = $filter_parts ? implode( ' + ', $filter_parts ) : 'Svi članci';
?>
<section
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="news-block"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
>
    <?php if ( $is_preview ) : ?>
        <div style="padding: 16px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px;">
            <p style="margin: 0 0 8px; font-weight: 600; font-size: 14px;">
                📰 News Block (3 kolone)
            </p>
            <p style="margin: 0 0 4px; font-size: 13px; color: #333;">
                <?php if ( $category_name ) : ?>
                    <strong><?php echo esc_html( $category_name ); ?></strong>
                    <?php if ( $category_desc ) : ?>
                        — <?php echo esc_html( $category_desc ); ?>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
            <p style="margin: 0 0 8px; font-size: 12px; color: #666;">
                <?php echo esc_html( $filter_label ); ?> &bull; <?php echo count( $articles ); ?> članaka
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
