<?php
/**
 * Photo credit ("Foto: …") stored on the attachment.
 *
 * Fallback chain: manual field (rvk_photo_credit) → IPTC credit → IPTC copyright.
 * The "Foto:" prefix is added by the frontend, so the stored value is just the
 * name/source.
 */

defined( 'ABSPATH' ) || exit;

register_post_meta( 'attachment', 'rvk_photo_credit', [
    'type'              => 'string',
    'single'            => true,
    'show_in_rest'      => true,
    'sanitize_callback' => 'sanitize_text_field',
    'auth_callback'     => fn() => current_user_can( 'upload_files' ),
] );

/**
 * Normalise a raw credit value: trim, sanitize, limit length, strip a leading
 * "©", "(c)", "Foto:" or "Photo:" so the frontend does not render "Foto: Foto: X".
 */
function headless_normalize_photo_credit( $value ): string {
    if ( ! is_string( $value ) ) {
        return '';
    }

    $value = sanitize_text_field( trim( $value ) );
    $value = mb_substr( $value, 0, 120 );
    $value = trim( preg_replace( '/^(?:©|\(c\)|foto\s*:|photo\s*:)\s*/iu', '', $value ) );

    return $value;
}

/**
 * Returns the first non-empty credit: manual meta → IPTC credit → IPTC copyright.
 */
function headless_get_photo_credit( int $attachment_id ): string {
    $image_meta = wp_get_attachment_metadata( $attachment_id );
    $image_meta = is_array( $image_meta ) && isset( $image_meta['image_meta'] ) && is_array( $image_meta['image_meta'] )
        ? $image_meta['image_meta']
        : [];

    $sources = [
        get_post_meta( $attachment_id, 'rvk_photo_credit', true ),
        $image_meta['credit'] ?? '',
        $image_meta['copyright'] ?? '',
    ];

    foreach ( $sources as $source ) {
        $credit = headless_normalize_photo_credit( $source );
        if ( $credit !== '' ) {
            return $credit;
        }
    }

    return '';
}

// ---------------------------------------------------------------------------
// Media Library field (modal + Edit Media screen)
// ---------------------------------------------------------------------------

add_filter( 'attachment_fields_to_edit', function ( array $fields, WP_Post $post ): array {
    if ( ! wp_attachment_is_image( $post->ID ) ) {
        return $fields;
    }

    $fields['rvk_photo_credit'] = [
        'label' => 'Autor fotografije / izvor',
        'input' => 'text',
        'value' => (string) get_post_meta( $post->ID, 'rvk_photo_credit', true ),
        'helps' => 'Prikazuje se ispod slike kao „Foto: …“. Ako je prazno, koristi se IPTC podatak iz fajla (ako postoji).',
    ];

    return $fields;
}, 10, 2 );

add_filter( 'attachment_fields_to_save', function ( array $post, array $attachment ): array {
    if ( ! isset( $attachment['rvk_photo_credit'] ) ) {
        return $post;
    }

    $value = sanitize_text_field( wp_unslash( $attachment['rvk_photo_credit'] ) );

    if ( $value === '' ) {
        delete_post_meta( $post['ID'], 'rvk_photo_credit' );
    } else {
        update_post_meta( $post['ID'], 'rvk_photo_credit', $value );
    }

    return $post;
}, 10, 2 );

// ---------------------------------------------------------------------------
// WPGraphQL
// ---------------------------------------------------------------------------

add_action( 'graphql_register_types', function (): void {
    register_graphql_field( 'MediaItem', 'photoCredit', [
        'type'        => 'String',
        'description' => 'Photo credit (manual field → IPTC credit → IPTC copyright).',
        'resolve'     => function ( $media ) {
            return headless_get_photo_credit( (int) $media->databaseId ) ?: null;
        },
    ] );
} );

// ---------------------------------------------------------------------------
// Revalidation — the attachment webhook only revalidates the `media` tag, so
// re-send revalidation for posts that use the attachment.
// ---------------------------------------------------------------------------

function headless_revalidate_posts_using_attachment( int $attachment_id ): void {
    global $wpdb;

    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_thumbnail_id'
         WHERE p.post_status = 'publish'
           AND p.post_type != 'attachment'
           AND ( pm.meta_value = %d OR p.post_content LIKE %s )
         LIMIT 20",
        $attachment_id,
        '%' . $wpdb->esc_like( '"id":' . $attachment_id ) . '%'
    ) );

    $public_types = get_post_types( [ 'public' => true ] );

    foreach ( $ids as $post_id ) {
        $post = get_post( (int) $post_id );
        if ( ! $post || ! isset( $public_types[ $post->post_type ] ) ) {
            continue;
        }

        headless_send_revalidation( [
            'type'      => 'post',
            'action'    => 'saved',
            'post_id'   => $post->ID,
            'post_type' => $post->post_type,
            'slug'      => $post->post_name,
            'status'    => $post->post_status,
            'permalink' => get_permalink( $post->ID ),
        ] );
    }
}

function headless_photo_credit_meta_changed( $meta_id, $object_id, $meta_key ): void {
    if ( $meta_key !== 'rvk_photo_credit' ) {
        return;
    }

    static $fired = [];
    if ( isset( $fired[ $object_id ] ) ) {
        return;
    }
    $fired[ $object_id ] = true;

    // Run once the request is done so multiple meta writes collapse into one.
    add_action( 'shutdown', function () use ( $object_id ): void {
        headless_revalidate_posts_using_attachment( (int) $object_id );
    } );
}

add_action( 'updated_post_meta', 'headless_photo_credit_meta_changed', 10, 3 );
add_action( 'added_post_meta', 'headless_photo_credit_meta_changed', 10, 3 );
add_action( 'deleted_post_meta', 'headless_photo_credit_meta_changed', 10, 3 );
