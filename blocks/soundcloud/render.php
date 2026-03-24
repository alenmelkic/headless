<?php
/**
 * SoundCloud Block — Render Template (Native Gutenberg dynamic block)
 *
 * Variables injected by WordPress (NOT ACF):
 *   $attributes (array)    Block attributes as defined in block.json.
 *   $content    (string)   Inner block HTML (empty — leaf block).
 *   $block      (WP_Block) Block instance object.
 *
 * Unlike ACF blocks, fields are NOT accessed via get_field().
 * Use $attributes['key'] directly.
 *
 * @package Headless
 */

$track_url      = $attributes['track_url']      ?? '';
$track_title    = $attributes['track_title']    ?? '';
$track_duration = $attributes['track_duration'] ?? '';
$track_artwork  = $attributes['track_artwork']  ?? '';

$props = [
    'track_url'      => $track_url      ?: null,
    'track_title'    => $track_title    ?: null,
    'track_duration' => $track_duration ?: null,
    'track_artwork'  => $track_artwork  ?: null,
];

// For native blocks, anchor comes from $attributes (not $block['anchor']).
$block_id = ! empty( $attributes['anchor'] ) ? $attributes['anchor'] : '';

$classes = array_filter( [
    'block',
    'block-soundcloud',
    $attributes['className'] ?? '',
] );
?>
<div
    <?php if ( $block_id ) : ?>id="<?php echo esc_attr( $block_id ); ?>"<?php endif; ?>
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="soundcloud"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
></div>
