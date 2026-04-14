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
    $id  = (int) $image_id;
    $src = wp_get_attachment_image_src( $id, 'headless-large' );
    if ( ! $src ) {
        continue;
    }

    // Full-size for lightbox zoom
    $full = wp_get_attachment_image_src( $id, 'full' );
    // Desktop thumbnail for grid (800x600)
    $thumb = wp_get_attachment_image_src( $id, 'headless-medium' );
    // Mobile thumbnail for grid (400x300, cropped)
    $mobile = wp_get_attachment_image_src( $id, 'headless-thumbnail' );

    $image_data[] = [
        'id'         => $id,
        'url'        => $src[0],
        'width'      => (int) $src[1],
        'height'     => (int) $src[2],
        'full_url'   => $full ? $full[0] : $src[0],
        'full_w'     => $full ? (int) $full[1] : (int) $src[1],
        'full_h'     => $full ? (int) $full[2] : (int) $src[2],
        'thumb_url'  => $thumb ? $thumb[0] : $src[0],
        'thumb_w'    => $thumb ? (int) $thumb[1] : (int) $src[1],
        'thumb_h'    => $thumb ? (int) $thumb[2] : (int) $src[2],
        'mobile_url' => $mobile ? $mobile[0] : ( $thumb ? $thumb[0] : $src[0] ),
        'mobile_w'   => $mobile ? (int) $mobile[1] : ( $thumb ? (int) $thumb[1] : (int) $src[1] ),
        'mobile_h'   => $mobile ? (int) $mobile[2] : ( $thumb ? (int) $thumb[2] : (int) $src[2] ),
        'alt'        => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
        'caption'    => (string) get_post( $id )?->post_excerpt,
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
        <?php if ( $title ) : ?>
            <p style="margin:0 0 8px;font-weight:600;font-size:14px;">
                <?php echo esc_html( $title ); ?>
            </p>
        <?php endif; ?>
        <?php if ( ! empty( $image_data ) ) : ?>
            <div style="display:grid;grid-template-columns:repeat(<?php echo esc_attr( $columns ); ?>,1fr);gap:4px;">
                <?php foreach ( $image_data as $img ) : ?>
                    <img
                        src="<?php echo esc_url( $img['thumb_url'] ); ?>"
                        alt="<?php echo esc_attr( $img['alt'] ); ?>"
                        style="width:100%;height:auto;display:block;border-radius:6px;aspect-ratio:1/1;object-fit:cover;"
                    />
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p style="padding:1rem;background:#f0f0f0;margin:0;color:#666;">
                No images selected. Use the sidebar to add images.
            </p>
        <?php endif; ?>
    <?php endif; ?>
</div>
