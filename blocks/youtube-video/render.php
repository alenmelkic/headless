<?php
/**
 * YouTube Video Block — Render Template (ACF Block v3)
 *
 * Variables injected by ACF Pro 6.3+:
 *   $block      (array) Block attributes.
 *   $is_preview (bool)  True when rendered inside the block editor.
 *
 * @package Headless
 */

$video_url = get_field( 'video_url' );
$title     = get_field( 'title' );
$caption   = get_field( 'caption' );

// Validate host and extract video ID.
$video_id = null;
if ( $video_url ) {
    $scheme  = wp_parse_url( $video_url, PHP_URL_SCHEME );
    $host    = wp_parse_url( $video_url, PHP_URL_HOST );
    $allowed = [ 'youtube.com', 'www.youtube.com', 'youtu.be' ];

    if ( $scheme === 'https' && in_array( $host, $allowed, true ) ) {
        if ( $host === 'youtu.be' ) {
            // https://youtu.be/VIDEO_ID or https://youtu.be/VIDEO_ID?si=TOKEN
            $path     = trim( wp_parse_url( $video_url, PHP_URL_PATH ), '/' );
            $video_id = strtok( $path, '/' );
        } else {
            $path = wp_parse_url( $video_url, PHP_URL_PATH );
            if ( str_starts_with( $path ?? '', '/embed/' ) ) {
                // https://www.youtube.com/embed/VIDEO_ID
                $video_id = trim( substr( $path, strlen( '/embed/' ) ), '/' );
            } else {
                // https://www.youtube.com/watch?v=VIDEO_ID&si=TOKEN
                parse_str( wp_parse_url( $video_url, PHP_URL_QUERY ) ?? '', $query );
                $video_id = $query['v'] ?? null;
            }
        }
        // Video IDs are alphanumeric + hyphen + underscore only.
        if ( $video_id && ! preg_match( '/^[a-zA-Z0-9_-]+$/', $video_id ) ) {
            $video_id = null;
        }
    } else {
        $video_url = null; // invalid host / scheme — omit from props
    }
}

$props = [
    'video_id'  => $video_id,
    'video_url' => $video_url,
    'title'     => $title   ?: null,
    'caption'   => $caption ?: null,
];

$block_id = ! empty( $block['anchor'] ) ? $block['anchor'] : $block['id'];

$classes = array_filter( [
    'block',
    'block-youtube-video',
    $block['className'] ?? '',
] );
?>
<div
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="youtube-video"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
>
    <?php if ( $is_preview ) : ?>
        <p style="padding:1rem;background:#f0f0f0;margin:0;">
            <?php if ( $video_id ) : ?>
                YouTube: <code><?php echo esc_html( $video_id ); ?></code>
                <?php if ( $title ) : ?> &mdash; <strong><?php echo esc_html( $title ); ?></strong><?php endif; ?>
            <?php else : ?>
                Add a YouTube URL above to preview.
            <?php endif; ?>
        </p>
    <?php endif; ?>
</div>
