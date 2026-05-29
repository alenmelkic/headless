<?php
/**
 * Column Block News — Programmatic ACF Field Group
 *
 * Registers fields for the column-block-news block with conditional logic
 * to show/hide column slots (3, 4) and taxonomy pickers based on source.
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build fields for a single column slot.
 *
 * @param int   $n              Column number (1-4).
 * @param array $column_conds   Conditional logic for column visibility (empty = always visible).
 * @return array ACF field definitions.
 */
function headless_cbn_column_fields( int $n, array $column_conds = [] ): array {
	$prefix = "field_cbn_column_{$n}";

	// Source field — always visible when the column is visible.
	$source_field = [
		'key'               => "{$prefix}_source",
		'label'             => "Kolona {$n} — Izvor",
		'name'              => "column_{$n}_source",
		'type'              => 'select',
		'choices'           => [
			'category'              => 'Kategorija (članci)',
			'obavijesti_o_smrti'    => 'Obavijesti o smrti',
			'servisne_informacije'  => 'Servisne informacije',
		],
		'default_value'     => 'category',
		'return_format'     => 'value',
		'allow_null'        => 0,
		'ui'                => 1,
		'conditional_logic' => $column_conds ?: 0,
	];

	// Taxonomy picker — WP categories.
	$source_is_category = [ [ 'field' => "{$prefix}_source", 'operator' => '==', 'value' => 'category' ] ];
	$category_field = [
		'key'               => "{$prefix}_category",
		'label'             => "Kolona {$n} — Kategorija",
		'name'              => "column_{$n}_category",
		'type'              => 'taxonomy',
		'taxonomy'          => 'category',
		'field_type'        => 'select',
		'return_format'     => 'id',
		'allow_null'        => 0,
		'multiple'          => 0,
		'add_term'          => 0,
		'conditional_logic' => headless_cbn_merge_conditions( $column_conds, $source_is_category ),
	];

	// Taxonomy picker — Obavijesti o smrti.
	$source_is_obavijesti = [ [ 'field' => "{$prefix}_source", 'operator' => '==', 'value' => 'obavijesti_o_smrti' ] ];
	$obavijesti_field = [
		'key'               => "{$prefix}_obavijesti_taxonomy",
		'label'             => "Kolona {$n} — Obavijesti taksonomija",
		'name'              => "column_{$n}_obavijesti_taxonomy",
		'type'              => 'taxonomy',
		'taxonomy'          => 'obavijest',
		'field_type'        => 'select',
		'return_format'     => 'id',
		'allow_null'        => 1,
		'multiple'          => 0,
		'add_term'          => 0,
		'conditional_logic' => headless_cbn_merge_conditions( $column_conds, $source_is_obavijesti ),
	];

	// Taxonomy picker — Servisne informacije.
	$source_is_servisne = [ [ 'field' => "{$prefix}_source", 'operator' => '==', 'value' => 'servisne_informacije' ] ];
	$servisne_field = [
		'key'               => "{$prefix}_servisne_taxonomy",
		'label'             => "Kolona {$n} — Servisne taksonomija",
		'name'              => "column_{$n}_servisne_taxonomy",
		'type'              => 'taxonomy',
		'taxonomy'          => 'servisna-informacija',
		'field_type'        => 'select',
		'return_format'     => 'id',
		'allow_null'        => 1,
		'multiple'          => 0,
		'add_term'          => 0,
		'conditional_logic' => headless_cbn_merge_conditions( $column_conds, $source_is_servisne ),
	];

	// Number of posts.
	$number_field = [
		'key'               => "{$prefix}_number_of_posts",
		'label'             => "Kolona {$n} — Broj članaka",
		'name'              => "column_{$n}_number_of_posts",
		'type'              => 'number',
		'default_value'     => 5,
		'min'               => 1,
		'max'               => 12,
		'step'              => 1,
		'conditional_logic' => $column_conds ?: 0,
	];

	return [ $source_field, $category_field, $obavijesti_field, $servisne_field, $number_field ];
}

/**
 * Merge column-visibility conditions with field-specific conditions.
 *
 * ACF conditional logic uses OR between groups and AND within each group.
 * If column_conds is empty (columns 1 & 2), return just the field condition.
 * Otherwise, combine each column-visibility group with the field condition.
 *
 * @param array $column_conds Column visibility OR-groups (e.g. [[columns==3], [columns==4]]).
 * @param array $field_cond   Single AND-group for the field (e.g. [[source==category]]).
 * @return array Merged conditional logic.
 */
function headless_cbn_merge_conditions( array $column_conds, array $field_cond ): array {
	if ( empty( $column_conds ) ) {
		// Columns 1 & 2: always visible, just need the source condition.
		return [ $field_cond[0] ];
	}

	// For columns 3/4: combine each visibility group with the field condition.
	$merged = [];
	foreach ( $column_conds as $group ) {
		$merged[] = array_merge( $group, $field_cond[0] );
	}
	return $merged;
}

// ---------------------------------------------------------------------------
// Register the field group.
// ---------------------------------------------------------------------------

acf_add_local_field_group( [
	'key'      => 'group_block_column_block_news',
	'title'    => 'Column Block News',
	'fields'   => array_merge(
		// Columns count selector.
		[
			[
				'key'           => 'field_cbn_columns',
				'label'         => 'Broj kolona',
				'name'          => 'columns',
				'type'          => 'select',
				'choices'       => [
					'2' => '2 kolone',
					'3' => '3 kolone',
					'4' => '4 kolone',
				],
				'default_value' => '3',
				'return_format' => 'value',
				'allow_null'    => 0,
				'ui'            => 1,
			],
		],
		// Column 1 — always visible.
		headless_cbn_column_fields( 1 ),
		// Column 2 — always visible.
		headless_cbn_column_fields( 2 ),
		// Column 3 — visible when columns >= 3.
		headless_cbn_column_fields( 3, [
			[ [ 'field' => 'field_cbn_columns', 'operator' => '==', 'value' => '3' ] ],
			[ [ 'field' => 'field_cbn_columns', 'operator' => '==', 'value' => '4' ] ],
		] ),
		// Column 4 — visible when columns == 4.
		headless_cbn_column_fields( 4, [
			[ [ 'field' => 'field_cbn_columns', 'operator' => '==', 'value' => '4' ] ],
		] )
	),
	'location' => [
		[
			[
				'param'    => 'block',
				'operator' => '==',
				'value'    => 'acf/column-block-news',
			],
		],
	],
	'active'   => true,
] );
