<?php
/**
 * Accordion Block — Render Template (ACF Block v3)
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

$title = get_field( 'title' );
$items = get_field( 'items' ) ?: []; // repeater: [ 'question', 'answer' ]

$props = [
    'title' => $title,
    'items' => array_map( function ( array $item, int $index ): array {
        return [
            'id'       => 'accordion-item-' . $index,
            'question' => $item['question'] ?? '',
            'answer'   => $item['answer']   ?? '',
        ];
    }, $items, array_keys( $items ) ),
];

$block_id = ! empty( $block['anchor'] ) ? $block['anchor'] : $block['id'];

$classes = array_filter( [
    'block',
    'block-accordion',
    $block['className'] ?? '',
] );
?>
<div
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="accordion"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
>
    <?php if ( $title ) : ?>
        <h2 class="block-accordion__title">
            <?php echo wp_kses_post( $title ); ?>
        </h2>
    <?php endif; ?>

    <?php if ( $items ) : ?>
        <dl class="block-accordion__list">
            <?php foreach ( $props['items'] as $item ) : ?>
                <div class="block-accordion__item" id="<?php echo esc_attr( $item['id'] ); ?>">

                    <dt class="block-accordion__question">
                        <button
                            type="button"
                            class="block-accordion__trigger"
                            aria-expanded="false"
                            aria-controls="<?php echo esc_attr( $item['id'] . '-answer' ); ?>"
                        >
                            <?php echo esc_html( $item['question'] ); ?>
                        </button>
                    </dt>

                    <dd
                        class="block-accordion__answer"
                        id="<?php echo esc_attr( $item['id'] . '-answer' ); ?>"
                        hidden
                    >
                        <?php echo wp_kses_post( $item['answer'] ); ?>
                    </dd>

                </div>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>
</div>
