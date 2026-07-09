<?php
/**
 * Cron Cleanup — Auto-trash old CPT posts
 *
 * Schedules two daily WordPress cron events:
 *   - headless_servisne_cleanup   — 03:00 site-local time, trashes servisne posts older than 30 days
 *   - headless_obavijesti_cleanup — 04:00 site-local time, trashes obavijest-o-smrti posts older than 41 days
 *
 * Trashed posts can be recovered manually; WordPress auto-purges trash after 30 days.
 *
 * The existing wp_trash_post hook in revalidation.php fires the ISR
 * webhook automatically, so the Next.js cache is purged on cleanup.
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;


// ---------------------------------------------------------------------------
// Helper — next Unix timestamp for a given local HH:MM, anchored to site TZ
// ---------------------------------------------------------------------------

function headless_next_local_time( string $time_str ): int {
	$tz        = wp_timezone();
	$now       = new DateTimeImmutable( 'now', $tz );
	$candidate = new DateTimeImmutable( 'today ' . $time_str, $tz );

	if ( $candidate <= $now ) {
		$candidate = $candidate->modify( '+1 day' );
	}

	return $candidate->getTimestamp();
}


// ---------------------------------------------------------------------------
// Schedule daily events
// ---------------------------------------------------------------------------

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'headless_servisne_cleanup' ) ) {
		wp_schedule_event( headless_next_local_time( '03:00' ), 'daily', 'headless_servisne_cleanup' );
	}

	if ( ! wp_next_scheduled( 'headless_obavijesti_cleanup' ) ) {
		wp_schedule_event( headless_next_local_time( '04:00' ), 'daily', 'headless_obavijesti_cleanup' );
	}
} );


// ---------------------------------------------------------------------------
// Cleanup callbacks
// ---------------------------------------------------------------------------

add_action( 'headless_servisne_cleanup', function () {
	$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

	$query = new WP_Query( [
		'post_type'      => 'servisne',
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'date_query'     => [
			[
				'before'    => $cutoff,
				'inclusive' => false,
			],
		],
		'fields'         => 'ids',
		'no_found_rows'  => true,
	] );

	foreach ( $query->posts as $post_id ) {
		wp_trash_post( $post_id );
	}
} );

add_action( 'headless_obavijesti_cleanup', function () {
	$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-41 days' ) );

	$query = new WP_Query( [
		'post_type'      => 'obavijest-o-smrti',
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'date_query'     => [
			[
				'before'    => $cutoff,
				'inclusive' => false,
			],
		],
		'fields'         => 'ids',
		'no_found_rows'  => true,
	] );

	foreach ( $query->posts as $post_id ) {
		wp_trash_post( $post_id );
	}
} );


// ---------------------------------------------------------------------------
// Deregistration on theme switch
// ---------------------------------------------------------------------------

add_action( 'switch_theme', function () {
	wp_clear_scheduled_hook( 'headless_servisne_cleanup' );
	wp_clear_scheduled_hook( 'headless_obavijesti_cleanup' );
} );
