<?php
/**
 * Facebook Video Block — Render Template (ACF Block v3)
 *
 * @package Headless
 */

$video_url = get_field( 'video_url' );
$title     = get_field( 'title' );
$caption   = get_field( 'caption' );

// Validate: must be https and from facebook.com.
if ( $video_url ) {
    $scheme  = wp_parse_url( $video_url, PHP_URL_SCHEME );
    $host    = wp_parse_url( $video_url, PHP_URL_HOST );
    $allowed = [ 'facebook.com', 'www.facebook.com' ];

    if ( $scheme !== 'https' || ! in_array( $host, $allowed, true ) ) {
        $video_url = null;
    }
}

$props = [
    'video_url' => $video_url,
    'title'     => $title   ?: null,
    'caption'   => $caption ?: null,
];

$block_id = ! empty( $block['anchor'] ) ? $block['anchor'] : $block['id'];

$classes = array_filter( [
    'block',
    'block-facebook-video',
    $block['className'] ?? '',
] );
?>
<div
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="facebook-video"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
>
    <?php if ( $is_preview ) : ?>
        <p style="padding:1rem;background:#f0f0f0;margin:0;">
            <?php if ( $video_url ) : ?>
                Facebook Video: <code><?php echo esc_html( $video_url ); ?></code>
                <?php if ( $title ) : ?> &mdash; <strong><?php echo esc_html( $title ); ?></strong><?php endif; ?>
            <?php else : ?>
                Add a Facebook video URL above to preview.
            <?php endif; ?>
        </p>
    <?php endif; ?>
</div>
