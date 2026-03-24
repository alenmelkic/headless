<?php
/**
 * Image Gallery Block — Render Template (ACF Block v3)
 *
 * Variables injected by ACF Pro 6.3+:
 *   $block      (array) Block attributes.
 *   $is_preview (bool)  True when rendered inside the block editor.
 *
 * @package Headless
 */

$title   = get_field( 'title' );
$images  = get_field( 'images' ) ?: []; // array of attachment IDs
$columns = (int) ( get_field( 'columns' ) ?: 3 );

$image_data = [];
foreach ( $images as $image_id ) {
    $src = wp_get_attachment_image_src( (int) $image_id, 'headless-large' );
    if ( ! $src ) {
        continue;
    }
    $image_data[] = [
        'id'     => (int) $image_id,
        'url'    => $src[0],
        'width'  => (int) $src[1],  // dimensions of the generated file, not original
        'height' => (int) $src[2],
        'alt'    => (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
    ];
}

$props = [
    'title'   => $title ?: null,
    'columns' => $columns,
    'images'  => $image_data,
];

$block_id = ! empty( $block['anchor'] ) ? $block['anchor'] : $block['id'];

$classes = array_filter( [
    'block',
    'block-image-gallery',
    $block['className'] ?? '',
] );
?>
<div
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="image-gallery"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
>
    <?php if ( $is_preview ) : ?>
        <p style="padding:1rem;background:#f0f0f0;margin:0;">
            Image Gallery
            <?php if ( $title ) : ?> &mdash; <strong><?php echo esc_html( $title ); ?></strong><?php endif; ?>
            (<?php echo count( $image_data ); ?> image<?php echo count( $image_data ) !== 1 ? 's' : ''; ?>,
            <?php echo esc_html( $columns ); ?> columns)
        </p>
    <?php endif; ?>
</div>
