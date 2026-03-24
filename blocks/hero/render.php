<?php
/**
 * Hero Block — Render Template (ACF Block v3)
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
$heading          = get_field( 'heading' );
$subheading       = get_field( 'subheading' );
$background_image = get_field( 'background_image' ); // returns attachment ID
$cta_label        = get_field( 'cta_label' );
$cta_url          = get_field( 'cta_url' );
$cta_new_tab      = get_field( 'cta_new_tab' );

// Props array passed to data-props for front-end component hydration.
$props = [
    'heading'          => $heading,
    'subheading'       => $subheading,
    'background_image' => $background_image
        ? wp_get_attachment_image_url( $background_image, 'headless-large' )
        : null,
    'cta_label'        => $cta_label,
    'cta_url'          => $cta_url,
    'cta_new_tab'      => (bool) $cta_new_tab,
];

// Block ID: prefer user-defined anchor, fall back to the v3 stable block hash.
$block_id = ! empty( $block['anchor'] ) ? $block['anchor'] : $block['id'];

$classes = array_filter( [
    'block',
    'block-hero',
    $block['className'] ?? '',
    ! empty( $block['align'] ) ? 'align' . $block['align'] : '',
] );
?>
<section
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="hero"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
>
    <?php if ( $background_image ) : ?>
        <div class="block-hero__bg">
            <?php echo wp_get_attachment_image(
                $background_image,
                'headless-large',
                false,
                [ 'class' => 'block-hero__bg-img', 'alt' => '' ]
            ); ?>
        </div>
    <?php endif; ?>

    <div class="block-hero__content">

        <?php if ( $heading ) : ?>
            <h1 class="block-hero__heading">
                <?php echo wp_kses_post( $heading ); ?>
            </h1>
        <?php endif; ?>

        <?php if ( $subheading ) : ?>
            <p class="block-hero__subheading">
                <?php echo wp_kses_post( $subheading ); ?>
            </p>
        <?php endif; ?>

        <?php if ( $cta_label && $cta_url ) : ?>
            <a
                href="<?php echo esc_url( $cta_url ); ?>"
                class="block-hero__cta"
                <?php if ( $cta_new_tab ) : ?>
                    target="_blank" rel="noopener noreferrer"
                <?php endif; ?>
            >
                <?php echo esc_html( $cta_label ); ?>
            </a>
        <?php endif; ?>

    </div>
</section>
