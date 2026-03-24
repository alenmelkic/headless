<?php
/**
 * Card Block — Render Template (ACF Block v3)
 *
 * Variables injected by ACF Pro 6.3+ (block version 3):
 *   $block      (array)  Block attributes — id, name, align, className, anchor, mode, data.
 *   $content    (string) Inner blocks HTML (empty for leaf blocks).
 *   $is_preview (bool)   True when rendered inside the block editor preview.
 *   $post_id    (int)    ID of the post being edited / viewed.
 *   $context    (array)  Block context values passed down from parent blocks.
 *
 * In v3, $block['id'] is a stable unique hash (e.g. "block_5f4a3b2c").
 * Fields are accessed via get_field() — ACF sets up the block meta context
 * automatically before this template runs.
 *
 * @package Headless
 */

// Per-field calls are the v3-recommended approach — avoids loading unused fields.
$image       = get_field( 'image' );       // returns attachment ID
$title       = get_field( 'title' );
$description = get_field( 'description' ); // wysiwyg — already HTML
$link        = get_field( 'link' );        // returns ['url', 'title', 'target']

// Props array passed to data-props for front-end component hydration.
$props = [
    'image'       => $image
        ? [
            'url'    => wp_get_attachment_image_url( $image, 'headless-medium' ),
            'alt'    => get_post_meta( $image, '_wp_attachment_image_alt', true ),
            'width'  => (int) ( wp_get_attachment_metadata( $image )['width'] ?? 0 ),
            'height' => (int) ( wp_get_attachment_metadata( $image )['height'] ?? 0 ),
        ]
        : null,
    'title'       => $title,
    'description' => $description,
    'link'        => $link ?: null,
];

// Block ID: prefer user-defined anchor, fall back to the v3 stable block hash.
$block_id = ! empty( $block['anchor'] ) ? $block['anchor'] : $block['id'];

$classes = array_filter( [
    'block',
    'block-card',
    $block['className'] ?? '',
] );
?>
<article
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="card"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
>
    <?php if ( $image ) : ?>
        <div class="block-card__image">
            <?php echo wp_get_attachment_image(
                $image,
                'headless-medium',
                false,
                [ 'class' => 'block-card__img', 'alt' => $props['image']['alt'] ]
            ); ?>
        </div>
    <?php endif; ?>

    <div class="block-card__body">

        <?php if ( $title ) : ?>
            <h3 class="block-card__title">
                <?php echo wp_kses_post( $title ); ?>
            </h3>
        <?php endif; ?>

        <?php if ( $description ) : ?>
            <div class="block-card__description">
                <?php echo wp_kses_post( $description ); ?>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $link['url'] ) ) : ?>
            <a
                href="<?php echo esc_url( $link['url'] ); ?>"
                class="block-card__link"
                <?php if ( ! empty( $link['target'] ) ) : ?>
                    target="<?php echo esc_attr( $link['target'] ); ?>"
                    rel="noopener noreferrer"
                <?php endif; ?>
            >
                <?php echo esc_html( $link['title'] ?: __( 'Read more', 'headless' ) ); ?>
            </a>
        <?php endif; ?>

    </div>
</article>
