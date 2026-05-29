<?php
/**
 * Column Block News — Render Template (ACF Block v3)
 *
 * Queries posts for 2-4 columns, each filtered by a category or CPT taxonomy.
 * Outputs structured JSON in data-props for the headless frontend.
 *
 * @package Headless
 */

$num_columns = max( 2, min( 4, (int) ( get_field( 'columns' ) ?: 3 ) ) );

$columns_data = [];

for ( $n = 1; $n <= $num_columns; $n++ ) {
	$source         = get_field( "column_{$n}_source" ) ?: 'category';
	$number_of_posts = min( 12, max( 1, (int) ( get_field( "column_{$n}_number_of_posts" ) ?: 5 ) ) );

	// Resolve the selected term and build query args.
	$term_name = '';
	$term_desc = '';
	$term_slug = '';
	$href      = '';
	$query_args = [
		'posts_per_page'         => $number_of_posts,
		'post_status'            => 'publish',
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
	];

	switch ( $source ) {
		case 'category':
			$term_id = (int) get_field( "column_{$n}_category" );
			if ( $term_id ) {
				$cat_obj = get_category( $term_id );
				if ( $cat_obj && ! is_wp_error( $cat_obj ) ) {
					$term_name = esc_html( $cat_obj->name );
					$term_desc = esc_html( wp_strip_all_tags( $cat_obj->description ) );
					$term_slug = sanitize_title( $cat_obj->slug );
					$href      = '/kategorija/' . $term_slug;
				}
			}
			$query_args['post_type'] = 'post';
			if ( $term_id ) {
				$query_args['cat'] = $term_id;
			}
			break;

		case 'obavijesti_o_smrti':
			$term_id = (int) get_field( "column_{$n}_obavijesti_taxonomy" );
			$query_args['post_type'] = 'obavijest-o-smrti';

			// Discover the registered taxonomy for this CPT.
			$taxonomies = get_object_taxonomies( 'obavijest-o-smrti', 'names' );
			$taxonomy   = ! empty( $taxonomies ) ? reset( $taxonomies ) : 'obavijest';

			if ( $term_id ) {
				$term_obj = get_term( $term_id, $taxonomy );
				if ( $term_obj && ! is_wp_error( $term_obj ) ) {
					$term_name = esc_html( $term_obj->name );
					$term_desc = esc_html( wp_strip_all_tags( $term_obj->description ) );
					$term_slug = sanitize_title( $term_obj->slug );
				}
				$query_args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					[
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $term_id,
					],
				];
			} else {
				// No specific term — show all, use CPT label.
				$term_name = 'Obavijesti o smrti';
				$term_desc = 'Obavijesti o smrti i posljednji isprati';
			}
			$href = '/kategorija/obavijesti-o-smrti';
			break;

		case 'servisne_informacije':
			$term_id = (int) get_field( "column_{$n}_servisne_taxonomy" );
			$query_args['post_type'] = 'servisne';

			// Discover the registered taxonomy for this CPT.
			$taxonomies = get_object_taxonomies( 'servisne', 'names' );
			$taxonomy   = ! empty( $taxonomies ) ? reset( $taxonomies ) : 'servisna-informacija';

			if ( $term_id ) {
				$term_obj = get_term( $term_id, $taxonomy );
				if ( $term_obj && ! is_wp_error( $term_obj ) ) {
					$term_name = esc_html( $term_obj->name );
					$term_desc = esc_html( wp_strip_all_tags( $term_obj->description ) );
					$term_slug = sanitize_title( $term_obj->slug );
				}
				$query_args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					[
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $term_id,
					],
				];
			} else {
				// No specific term — show all, use CPT label.
				$term_name = 'Servisne informacije';
				$term_desc = 'Servisne i komunalne informacije';
			}
			$href = '/kategorija/servisne-informacije';
			break;
	}

	// Run the query.
	$query    = new WP_Query( $query_args );
	$articles = [];

	while ( $query->have_posts() ) {
		$query->the_post();
		$pid = get_the_ID();

		$articles[] = [
			'id'    => (string) $pid,
			'slug'  => sanitize_title( get_post_field( 'post_name', $pid ) ),
			'title' => esc_html( get_the_title() ),
			'date'  => get_the_date( 'c' ),
		];
	}
	wp_reset_postdata();

	$columns_data[] = [
		'title'       => $term_name,
		'description' => $term_desc,
		'href'        => $href,
		'articles'    => $articles,
	];
}

$props = [
	'columns' => $columns_data,
];

$block_id = ! empty( $block['anchor'] ) ? esc_attr( $block['anchor'] ) : $block['id'];
$classes  = array_filter( [
	'block',
	'block-column-block-news',
	$block['className'] ?? '',
	! empty( $block['align'] ) ? 'align' . $block['align'] : '',
] );
?>
<section
	id="<?php echo esc_attr( $block_id ); ?>"
	class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
	data-block="column-block-news"
	data-props="<?php echo esc_attr( wp_json_encode( $props ) ); ?>"
>
	<?php if ( $is_preview ) : ?>
		<div style="padding: 16px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px;">
			<p style="margin: 0 0 8px; font-weight: 600; font-size: 14px;">
				&#x1f4f0; Column Block News (<?php echo esc_html( $num_columns ); ?> kolone)
			</p>
			<div style="display: flex; gap: 16px; flex-wrap: wrap;">
				<?php foreach ( $columns_data as $i => $col ) : ?>
					<div style="flex: 1; min-width: 150px; padding: 8px; background: #fff; border: 1px solid #e0e0e0; border-radius: 6px;">
						<p style="margin: 0 0 4px; font-weight: 600; font-size: 13px;">
							<?php echo esc_html( $col['title'] ?: 'Kolona ' . ( $i + 1 ) ); ?>
						</p>
						<p style="margin: 0; font-size: 12px; color: #666;">
							<?php echo count( $col['articles'] ); ?> članaka
						</p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
</section>
