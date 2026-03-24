<?php
/**
 * Block Patterns
 *
 * Pre-built block combinations editors can insert in one click.
 * Patterns appear in the editor under Inserter → Patterns → Theme.
 *
 * To add a new pattern:
 *  1. Register a category if needed (register_block_pattern_category).
 *  2. Call register_block_pattern() with a unique name, title, and content.
 *  3. The content is standard block comment markup — copy it from the WP
 *     editor's Code Editor view after building your layout visually.
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function (): void {

    // Register a custom category for theme patterns.
    register_block_pattern_category( 'headless', [
        'label' => __( 'Headless Theme', 'headless' ),
    ] );

    // -----------------------------------------------------------------------
    // Pattern: Hero + Card Grid
    // A full-width hero block followed by a three-column card row.
    // -----------------------------------------------------------------------
    register_block_pattern( 'headless/hero-card-grid', [
        'title'       => __( 'Hero + Card Grid', 'headless' ),
        'description' => __( 'Full-width hero banner followed by a three-column card row.', 'headless' ),
        'categories'  => [ 'headless' ],
        'keywords'    => [ 'hero', 'cards', 'grid', 'landing' ],
        'content'     => <<<PATTERN
<!-- wp:acf/hero {"name":"acf/hero","data":{},"align":"full","mode":"preview"} /-->

<!-- wp:acf/section {"name":"acf/section","data":{"padding_size":"md"},"mode":"preview"} -->
<!-- wp:columns -->
<div class="wp-block-columns">
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:acf/card {"name":"acf/card","data":{},"mode":"preview"} /--></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:acf/card {"name":"acf/card","data":{},"mode":"preview"} /--></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:acf/card {"name":"acf/card","data":{},"mode":"preview"} /--></div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->
<!-- /wp:acf/section -->
PATTERN,
    ] );

    // -----------------------------------------------------------------------
    // Pattern: CTA Banner + Testimonials Row
    // -----------------------------------------------------------------------
    register_block_pattern( 'headless/cta-testimonials', [
        'title'       => __( 'CTA Banner + Testimonials', 'headless' ),
        'description' => __( 'A call-to-action banner above a two-column testimonials row.', 'headless' ),
        'categories'  => [ 'headless' ],
        'keywords'    => [ 'cta', 'testimonial', 'social proof' ],
        'content'     => <<<PATTERN
<!-- wp:acf/cta-banner {"name":"acf/cta-banner","data":{},"align":"full","mode":"preview"} /-->

<!-- wp:acf/section {"name":"acf/section","data":{"padding_size":"lg"},"mode":"preview"} -->
<!-- wp:columns -->
<div class="wp-block-columns">
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:acf/testimonial {"name":"acf/testimonial","data":{},"mode":"preview"} /--></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:acf/testimonial {"name":"acf/testimonial","data":{},"mode":"preview"} /--></div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->
<!-- /wp:acf/section -->
PATTERN,
    ] );

    // -----------------------------------------------------------------------
    // Pattern: FAQ Accordion Section
    // -----------------------------------------------------------------------
    register_block_pattern( 'headless/faq-section', [
        'title'       => __( 'FAQ Accordion Section', 'headless' ),
        'description' => __( 'A section wrapper containing an accordion block for FAQs.', 'headless' ),
        'categories'  => [ 'headless' ],
        'keywords'    => [ 'faq', 'accordion', 'questions' ],
        'content'     => <<<PATTERN
<!-- wp:acf/section {"name":"acf/section","data":{"padding_size":"lg"},"mode":"preview"} -->
<!-- wp:acf/accordion {"name":"acf/accordion","data":{},"mode":"preview"} /-->
<!-- /wp:acf/section -->
PATTERN,
    ] );

} );
