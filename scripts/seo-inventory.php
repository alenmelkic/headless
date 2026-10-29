<?php
/**
 * SEO Inventory — read-only report.
 *
 * Faza 0 iz SEO brief-a. Ne mijenja bazu.
 *
 * Pokretanje:
 *   wp eval-file scripts/seo-inventory.php
 * ili (bez wp-cli), s PHP-om koji koristi sajt:
 *   php.exe scripts/seo-inventory.php
 *
 * @package Headless
 */

// Samo za WP-CLI / php CLI — nikad preko HTTP-a.
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

// Bootstrap kada se ne pokreće kroz wp-cli.
if ( ! defined( 'ABSPATH' ) ) {
	$root = dirname( __DIR__, 4 ); // themes/headless/scripts -> public/
	require_once $root . '/wp-load.php';
}

/** @var wpdb $wpdb */
global $wpdb;

$types = [ 'post', 'obavijest-o-smrti', 'servisne', 'page' ];

echo "=== 0.2 — META DESCRIPTION / EXCERPT ===\n\n";
printf( "%-20s %10s %14s %14s\n", 'tip', 'objavljeno', 'prazan desc', 'prazan excerpt' );
echo str_repeat( '-', 62 ) . "\n";

foreach ( $types as $type ) {
	$total = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = %s AND post_status = 'publish'",
			$type
		)
	);

	// Prazan metadesc = meta ne postoji ILI je prazan string nakon trim-a.
	$empty_desc = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} m
			   ON m.post_id = p.ID AND m.meta_key = '_yoast_wpseo_metadesc'
			 WHERE p.post_type = %s AND p.post_status = 'publish'
			   AND ( m.meta_id IS NULL OR TRIM(m.meta_value) = '' )",
			$type
		)
	);

	$empty_excerpt = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = %s AND post_status = 'publish'
			   AND TRIM(post_excerpt) = ''",
			$type
		)
	);

	printf( "%-20s %10d %14d %14d\n", $type, $total, $empty_desc, $empty_excerpt );
}

// Kontrola: postoje li uopšte ti tipovi u bazi (hvata pogrešan slug).
echo "\nSvi post_type sa objavljenim zapisima u bazi:\n";
$rows = $wpdb->get_results(
	"SELECT post_type, COUNT(*) AS n FROM {$wpdb->posts}
	 WHERE post_status = 'publish' GROUP BY post_type ORDER BY n DESC"
);
foreach ( $rows as $row ) {
	printf( "  %-24s %6d\n", $row->post_type, (int) $row->n );
}

echo "\n=== 0.3 — RASPODJELA ŠIRINA SLIKA ===\n\n";

$ids = $wpdb->get_col(
	"SELECT ID FROM {$wpdb->posts}
	 WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
);

$buckets = [
	'<800'      => 0,
	'800-1199'  => 0,
	'1200-1439' => 0,
	'>=1440'    => 0,
];
$no_width = 0;

foreach ( $ids as $id ) {
	$meta  = wp_get_attachment_metadata( (int) $id );
	$width = ( is_array( $meta ) && ! empty( $meta['width'] ) ) ? (int) $meta['width'] : 0;

	if ( $width <= 0 ) {
		++$no_width;
		continue;
	}

	if ( $width < 800 ) {
		++$buckets['<800'];
	} elseif ( $width < 1200 ) {
		++$buckets['800-1199'];
	} elseif ( $width < 1440 ) {
		++$buckets['1200-1439'];
	} else {
		++$buckets['>=1440'];
	}
}

$counted = array_sum( $buckets );

printf( "%-14s %8s %8s\n", 'opseg', 'broj', '%' );
echo str_repeat( '-', 32 ) . "\n";
foreach ( $buckets as $label => $n ) {
	$pct = $counted > 0 ? ( $n / $counted * 100 ) : 0;
	printf( "%-14s %8d %7.1f%%\n", $label, $n, $pct );
}
echo str_repeat( '-', 32 ) . "\n";
printf( "%-14s %8d\n", 'sa širinom', $counted );
printf( "%-14s %8d\n", 'bez metapod.', $no_width );
printf( "%-14s %8d\n", 'ukupno slika', count( $ids ) );

echo "\n";
