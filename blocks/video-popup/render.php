<?php
/**
 * YouTube Video Block — Render Template (Native Gutenberg dynamic block)
 *
 * @package Headless
 */

$video_url   = $attributes['videoUrl']  ?? '';
$cover_image = $attributes['coverImage'] ?? null;

// Validate: must be https YouTube URL.
if ( $video_url ) {
    $scheme  = wp_parse_url( $video_url, PHP_URL_SCHEME );
    $host    = wp_parse_url( $video_url, PHP_URL_HOST );
    $allowed = [ 'youtube.com', 'www.youtube.com', 'youtu.be' ];

    if ( $scheme !== 'https' || ! in_array( $host, $allowed, true ) ) {
        $video_url = '';
    }
}

$props = [
    'videoUrl'   => $video_url   ?: null,
    'coverImage' => $cover_image ?: null,
];

$block_id = ! empty( $attributes['anchor'] ) ? $attributes['anchor'] : '';

$classes = array_filter( [
    'block',
    'block-video-popup',
    $attributes['className'] ?? '',
] );
?>
<div
    <?php if ( $block_id ) : ?>id="<?php echo esc_attr( $block_id ); ?>"<?php endif; ?>
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="video-popup"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
></div>
