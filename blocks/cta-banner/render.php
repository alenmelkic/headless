<?php
/**
 * CTA Banner Block — Render Template (ACF Block v3)
 *
 * Variables injected by ACF Pro 6.3+:
 *   $block      (array)  Block attributes.
 *   $content    (string) Inner blocks HTML (unused — leaf block).
 *   $is_preview (bool)   True when rendered inside the block editor.
 *   $post_id    (int)    ID of the post being edited / viewed.
 *   $context    (array)  Block context.
 *
 * @package Headless
 */

$heading              = get_field( 'heading' );
$subheading           = get_field( 'subheading' );
$primary_cta_label    = get_field( 'primary_cta_label' );
$primary_cta_url      = get_field( 'primary_cta_url' );
$primary_cta_new_tab  = get_field( 'primary_cta_new_tab' );
$secondary_cta_label  = get_field( 'secondary_cta_label' );
$secondary_cta_url    = get_field( 'secondary_cta_url' );
$secondary_cta_new_tab = get_field( 'secondary_cta_new_tab' );
$background_color     = get_field( 'background_color' );

$props = [
    'heading'               => $heading,
    'subheading'            => $subheading,
    'primary_cta_label'     => $primary_cta_label,
    'primary_cta_url'       => $primary_cta_url,
    'primary_cta_new_tab'   => (bool) $primary_cta_new_tab,
    'secondary_cta_label'   => $secondary_cta_label,
    'secondary_cta_url'     => $secondary_cta_url,
    'secondary_cta_new_tab' => (bool) $secondary_cta_new_tab,
    'background_color'      => $background_color ?: null,
];

$block_id = ! empty( $block['anchor'] ) ? $block['anchor'] : $block['id'];

$classes = array_filter( [
    'block',
    'block-cta-banner',
    $block['className'] ?? '',
    ! empty( $block['align'] ) ? 'align' . $block['align'] : '',
] );

$style = $background_color ? 'background-color:' . esc_attr( $background_color ) : '';
?>
<div
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    <?php if ( $style ) : ?>style="<?php echo esc_attr( $style ); ?>"<?php endif; ?>
    data-block="cta-banner"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
>
    <div class="block-cta-banner__content">

        <?php if ( $heading ) : ?>
            <h2 class="block-cta-banner__heading">
                <?php echo wp_kses_post( $heading ); ?>
            </h2>
        <?php endif; ?>

        <?php if ( $subheading ) : ?>
            <p class="block-cta-banner__subheading">
                <?php echo wp_kses_post( $subheading ); ?>
            </p>
        <?php endif; ?>

        <?php if ( $primary_cta_label || $secondary_cta_label ) : ?>
            <div class="block-cta-banner__actions">

                <?php if ( $primary_cta_label && $primary_cta_url ) : ?>
                    <a
                        href="<?php echo esc_url( $primary_cta_url ); ?>"
                        class="block-cta-banner__btn block-cta-banner__btn--primary"
                        <?php if ( $primary_cta_new_tab ) : ?>
                            target="_blank" rel="noopener noreferrer"
                        <?php endif; ?>
                    >
                        <?php echo esc_html( $primary_cta_label ); ?>
                    </a>
                <?php endif; ?>

                <?php if ( $secondary_cta_label && $secondary_cta_url ) : ?>
                    <a
                        href="<?php echo esc_url( $secondary_cta_url ); ?>"
                        class="block-cta-banner__btn block-cta-banner__btn--secondary"
                        <?php if ( $secondary_cta_new_tab ) : ?>
                            target="_blank" rel="noopener noreferrer"
                        <?php endif; ?>
                    >
                        <?php echo esc_html( $secondary_cta_label ); ?>
                    </a>
                <?php endif; ?>

            </div>
        <?php endif; ?>

    </div>
</div>
