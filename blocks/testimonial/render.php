<?php
/**
 * Testimonial Block — Render Template (ACF Block v3)
 *
 * Variables injected by ACF Pro 6.3+:
 *   $block      (array)  Block attributes.
 *   $content    (string) Inner blocks HTML (unused — leaf block).
 *   $is_preview (bool)   True when rendered inside the block editor.
 *   $post_id    (int)    ID of the post being edited / viewed.
 *   $context    (array)  Block context.
 *
 * @package Headless
 */

$quote        = get_field( 'quote' );
$author_name  = get_field( 'author_name' );
$author_title = get_field( 'author_title' );
$author_image = get_field( 'author_image' ); // attachment ID
$rating       = (int) ( get_field( 'rating' ) ?: 0 ); // 0–5

$props = [
    'quote'        => $quote,
    'author_name'  => $author_name,
    'author_title' => $author_title,
    'author_image' => $author_image
        ? [
            'url'    => wp_get_attachment_image_url( $author_image, 'thumbnail' ),
            'alt'    => get_post_meta( $author_image, '_wp_attachment_image_alt', true ) ?: $author_name,
        ]
        : null,
    'rating'       => $rating,
];

$block_id = ! empty( $block['anchor'] ) ? $block['anchor'] : $block['id'];

$classes = array_filter( [
    'block',
    'block-testimonial',
    $block['className'] ?? '',
] );
?>
<figure
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="testimonial"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
>
    <?php if ( $rating > 0 ) : ?>
        <div class="block-testimonial__rating" aria-label="<?php echo esc_attr( sprintf( __( '%d out of 5 stars', 'headless' ), $rating ) ); ?>">
            <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                <span class="block-testimonial__star<?php echo $i <= $rating ? ' block-testimonial__star--filled' : ''; ?>">
                    &#9733;
                </span>
            <?php endfor; ?>
        </div>
    <?php endif; ?>

    <?php if ( $quote ) : ?>
        <blockquote class="block-testimonial__quote">
            <?php echo wp_kses_post( $quote ); ?>
        </blockquote>
    <?php endif; ?>

    <?php if ( $author_name || $author_image ) : ?>
        <figcaption class="block-testimonial__author">
            <?php if ( $author_image ) : ?>
                <div class="block-testimonial__author-image">
                    <?php echo wp_get_attachment_image(
                        $author_image,
                        'thumbnail',
                        false,
                        [
                            'class' => 'block-testimonial__author-img',
                            'alt'   => $props['author_image']['alt'],
                        ]
                    ); ?>
                </div>
            <?php endif; ?>

            <div class="block-testimonial__author-info">
                <?php if ( $author_name ) : ?>
                    <span class="block-testimonial__author-name">
                        <?php echo esc_html( $author_name ); ?>
                    </span>
                <?php endif; ?>

                <?php if ( $author_title ) : ?>
                    <span class="block-testimonial__author-title">
                        <?php echo esc_html( $author_title ); ?>
                    </span>
                <?php endif; ?>
            </div>
        </figcaption>
    <?php endif; ?>
</figure>
