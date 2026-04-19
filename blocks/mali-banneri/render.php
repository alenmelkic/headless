<?php
/**
 * Mali Banneri Block — Render Template (Native Gutenberg dynamic block)
 *
 * Pulls small banners from the Marketing settings (wp_options) and outputs
 * them as data-props so the headless frontend can render the carousel.
 *
 * Variables injected by WordPress (NOT ACF):
 *   $attributes (array)    Block attributes as defined in block.json.
 *   $content    (string)   Inner block HTML (empty — leaf block).
 *   $block      (WP_Block) Block instance object.
 *
 * @package Headless
 */

$raw     = get_option( 'headless_mkt_small_banners', '[]' );
$banners = json_decode( is_string( $raw ) ? $raw : '[]', true );
if ( ! is_array( $banners ) ) {
    $banners = [];
}

$mali = [];
foreach ( $banners as $b ) {
    $image_id = (int) ( $b['image_id'] ?? 0 );
    $image    = headless_theme_resolve_image( $image_id );
    if ( ! $image ) {
        continue;
    }
    $mali[] = [
        'image' => $image,
        'alt'   => (string) ( $b['alt'] ?? '' ),
        'link'  => (string) ( $b['link'] ?? '' ),
    ];
}

if ( empty( $mali ) ) {
    return;
}

// Extract background color/gradient from WP block supports
$bg_color    = $attributes['backgroundColor'] ?? '';
$custom_bg   = $attributes['style']['color']['background'] ?? '';
$gradient    = $attributes['gradient'] ?? '';
$custom_grad = $attributes['style']['color']['gradient'] ?? '';

$props = [
    'title'       => (string) ( $attributes['title'] ?? '' ),
    'description' => (string) ( $attributes['description'] ?? '' ),
    'ctaLabel'    => (string) ( $attributes['ctaLabel'] ?? '' ),
    'ctaUrl'      => (string) ( $attributes['ctaUrl'] ?? '' ),
    'ctaNewTab'   => (bool) ( $attributes['ctaNewTab'] ?? false ),
    'bgColor'     => (string) ( $custom_bg ?: $bg_color ),
    'gradient'    => (string) ( $custom_grad ?: $gradient ),
    'banneri'     => $mali,
];

$block_id = ! empty( $attributes['anchor'] ) ? $attributes['anchor'] : '';

$classes = array_filter( [
    'block',
    'block-mali-banneri',
    $attributes['className'] ?? '',
] );
?>
<div
    <?php if ( $block_id ) : ?>id="<?php echo esc_attr( $block_id ); ?>"<?php endif; ?>
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="mali-banneri"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
></div>
