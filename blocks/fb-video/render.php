<?php
/**
 * Facebook Video Block — Render Template (Native Gutenberg dynamic block)
 *
 * @package Headless
 */

$video_url   = $attributes['videoUrl']    ?? '';
$cover_image = $attributes['coverImage']  ?? null;
$orientation = $attributes['orientation'] ?? '';

// Validate: must be https Facebook URL.
if ( $video_url ) {
    $scheme  = wp_parse_url( $video_url, PHP_URL_SCHEME );
    $host    = wp_parse_url( $video_url, PHP_URL_HOST );
    $allowed = [ 'facebook.com', 'www.facebook.com', 'fb.watch' ];

    if ( $scheme !== 'https' || ! in_array( $host, $allowed, true ) ) {
        $video_url = '';
    }
}

$props = [
    'videoUrl'    => $video_url   ?: null,
    'coverImage'  => $cover_image ?: null,
    'orientation' => $orientation ?: null,
];

$block_id = ! empty( $attributes['anchor'] ) ? $attributes['anchor'] : '';

$classes = array_filter( [
    'block',
    'block-fb-video',
    $attributes['className'] ?? '',
] );
?>
<div
    <?php if ( $block_id ) : ?>id="<?php echo esc_attr( $block_id ); ?>"<?php endif; ?>
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="fb-video"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
></div>
