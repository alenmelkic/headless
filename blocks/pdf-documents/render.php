<?php
/**
 * PDF Documents Block — Render Template (ACF Block v3)
 *
 * Variables injected by ACF Pro 6.3+:
 *   $block      (array) Block attributes.
 *   $is_preview (bool)  True when rendered inside the block editor.
 *
 * @package Headless
 */

$title     = get_field( 'title' );
$documents = get_field( 'documents' ) ?: [];

$doc_data = [];
foreach ( $documents as $doc ) {
    $file = $doc['file'] ?? null; // ACF file array: url, filename, filesize (formatted string), mime_type, etc.
    if ( ! $file || empty( $file['url'] ) ) {
        continue;
    }
    $doc_data[] = [
        'title'    => (string) ( $doc['doc_title'] ?? '' ),
        'url'      => esc_url_raw( (string) $file['url'] ),
        'filename' => (string) ( $file['filename'] ?? '' ),
        'filesize' => (string) ( $file['filesize'] ?? '' ), // ACF returns formatted string, e.g. "200 kB"
    ];
}

$props = [
    'title'     => $title ?: null,
    'documents' => $doc_data,
];

$block_id = ! empty( $block['anchor'] ) ? $block['anchor'] : $block['id'];

$classes = array_filter( [
    'block',
    'block-pdf-documents',
    $block['className'] ?? '',
] );
?>
<div
    id="<?php echo esc_attr( $block_id ); ?>"
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-block="pdf-documents"
    data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
>
    <?php if ( $is_preview ) : ?>
        <p style="padding:1rem;background:#f0f0f0;margin:0;">
            PDF Documents
            <?php if ( $title ) : ?> &mdash; <strong><?php echo esc_html( $title ); ?></strong><?php endif; ?>
            (<?php echo count( $doc_data ); ?> file<?php echo count( $doc_data ) !== 1 ? 's' : ''; ?>)
        </p>
    <?php endif; ?>
</div>
