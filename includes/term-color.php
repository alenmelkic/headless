<?php
/**
 * Category Color — Term Meta + Admin UI + WPGraphQL Field
 *
 * Adds a hex colour picker to the WP category admin pages.
 * Value is stored as term meta `category_color` (sanitized hex).
 * Exposed to WPGraphQL as `color` (String | null) on the Category type.
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;


// ---------------------------------------------------------------------------
// 1. Register term meta
// ---------------------------------------------------------------------------

add_action( 'init', function (): void {
	register_term_meta( 'category', 'category_color', [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'sanitize_hex_color',
	] );
} );


// ---------------------------------------------------------------------------
// 2. Admin UI — colour picker on Add and Edit screens
// ---------------------------------------------------------------------------

// "Add new category" form.
add_action( 'category_add_form_fields', function (): void {
	?>
	<div class="form-field">
		<label for="category_color"><?php esc_html_e( 'Boja kategorije', 'headless' ); ?></label>
		<input
			type="color"
			id="category_color"
			name="category_color"
			value="#1a1a2e"
		>
		<p><?php esc_html_e( 'Prikazuje se na bedžu kategorije u frontendu. Promjena boje zahtijeva revalidaciju keša.', 'headless' ); ?></p>
	</div>
	<?php
} );

// "Edit category" form.
add_action( 'category_edit_form_fields', function ( WP_Term $term ): void {
	$color = (string) get_term_meta( $term->term_id, 'category_color', true );
	?>
	<tr class="form-field">
		<th scope="row">
			<label for="category_color"><?php esc_html_e( 'Boja kategorije', 'headless' ); ?></label>
		</th>
		<td>
			<input
				type="color"
				id="category_color"
				name="category_color"
				value="<?php echo esc_attr( $color ?: '#1a1a2e' ); ?>"
			>
			<p class="description">
				<?php esc_html_e( 'Prikazuje se na bedžu kategorije u frontendu. Promjena boje zahtijeva revalidaciju keša.', 'headless' ); ?>
			</p>
		</td>
	</tr>
	<?php
} );


// ---------------------------------------------------------------------------
// 3. Save — hooks on created_category and edited_category
// ---------------------------------------------------------------------------

/**
 * Saves or deletes the category_color term meta.
 *
 * @param int $term_id Category term ID.
 */
function headless_save_category_color( int $term_id ): void {
	if ( ! isset( $_POST['category_color'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	$color = sanitize_hex_color( wp_unslash( $_POST['category_color'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

	if ( $color ) {
		update_term_meta( $term_id, 'category_color', $color );
	} else {
		delete_term_meta( $term_id, 'category_color' );
	}
}

add_action( 'created_category', 'headless_save_category_color' );
add_action( 'edited_category', 'headless_save_category_color' );


// ---------------------------------------------------------------------------
// 4. Category list table — colour swatch column
// ---------------------------------------------------------------------------

add_filter( 'manage_edit-category_columns', function ( array $columns ): array {
	$columns['category_color'] = __( 'Boja', 'headless' );
	return $columns;
} );

add_filter( 'manage_category_custom_column', function ( string $output, string $column_name, int $term_id ): string {
	if ( 'category_color' !== $column_name ) {
		return $output;
	}

	$color = (string) get_term_meta( $term_id, 'category_color', true );

	if ( ! $color ) {
		return '<span style="color:#999;" aria-label="Boja nije postavljena">—</span>';
	}

	return sprintf(
		'<span style="display:inline-block;width:24px;height:24px;background:%1$s;border-radius:4px;border:1px solid rgba(0,0,0,.2);" title="%1$s" aria-label="%1$s"></span>',
		esc_attr( $color )
	);
}, 10, 3 );


// ---------------------------------------------------------------------------
// 5. WPGraphQL — expose `color` field on Category type
// ---------------------------------------------------------------------------

add_action( 'graphql_register_types', function (): void {
	register_graphql_field( 'Category', 'color', [
		'type'        => 'String',
		'description' => 'Hex boja kategorije (term meta category_color). Null ako boja nije postavljena.',
		'resolve'     => function ( $term ): ?string {
			$color = get_term_meta( $term->term_id, 'category_color', true );
			return $color ?: null;
		},
	] );
} );
