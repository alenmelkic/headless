<?php
/**
 * Section Block — Render Template (ACF Block v3)
 *
 * Variables injected by ACF Pro 6.3+:
 *   $block      (array)  Block attributes.
 *   $content    (string) Inner blocks HTML.
 *   $is_preview (bool)   True when rendered inside the block editor.
 *   $post_id    (int)    ID of the post being edited / viewed.
 *   $context    (array)  Block context.
 *
 * This block is a layout wrapper — $content contains any inner blocks
 * the editor has placed inside it.
 *
 * @package Headless
 */

$background_color = get_field( 'background_color' ); // hex string or CSS var
$background_image = get_field( 'background_image' ); // attachment ID
$padding_size     = get_field( 'padding_size' ) ?: 'md'; // sm | md | lg
$full_width       = get_field( 'full_width' );

$props = [
    'background_color' => $background_color ?: null,
    'background_image' => $background_image
        ? wp_get_attachment_image_url( $background_image, 'headless-large' )
        : null,
    'padding_size'     => $padding_size,
    'full_width'       => (bool) $full_width,
];

$block_id = ! empty( $block['anchor'] ) ? $block['anchor'] : $block['id'];

$classes = array_filter( [
    'block',
    'block-section',
    'block-section--padding-' . $padding_size,
    $full_width ? 'block-section--full' : '',
    $block['className'] ?? '',
    ! empty( $block['align'] ) ? 'align' . $block['align'] : '',
] );

$styles = [];
if ( $background_color ) {
    $styles[] = 'background-color:' . esc_attr( $background_color );
}
if ( $background_image ) {
    $img_url  = wp_get_attachment_image_url( $background_image, 'headless-large' );
    $styles[] = 'background-image:url(' . esc_url( $img_url ) . ')';
}
?>
<section
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    <?php if ( $styles ) : ?>
        style="<?php echo esc_attr( implode( ';', $styles ) ); ?>"
    <?php endif; ?>
    data-block="section"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
>
    <div class="block-section__inner">
        <?php echo wp_kses_post( $content ); // inner blocks ?>
    </div>
</section>
