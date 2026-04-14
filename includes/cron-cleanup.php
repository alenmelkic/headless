<?php
/**
 * Cron Cleanup — Auto-trash Servisne Posts
 *
 * Schedules a daily WordPress cron event that moves published servisne
 * posts older than 15 days to the trash. Trashed posts can be recovered
 * manually; WordPress auto-purges trash after 30 days.
 *
 * The existing wp_trash_post hook in revalidation.php fires the ISR
 * webhook automatically, so the Next.js cache is purged on cleanup.
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;


// ---------------------------------------------------------------------------
// Schedule the daily event
// ---------------------------------------------------------------------------

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'headless_servisne_cleanup' ) ) {
		wp_schedule_event( time(), 'daily', 'headless_servisne_cleanup' );
	}
} );


// ---------------------------------------------------------------------------
// Cleanup callback
// ---------------------------------------------------------------------------

add_action( 'headless_servisne_cleanup', function () {
	$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-15 days' ) );

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

	if ( empty( $query->posts ) ) {
		return;
	}

	foreach ( $query->posts as $post_id ) {
		wp_trash_post( $post_id );
	}
} );


// ---------------------------------------------------------------------------
// Deregistration on theme switch
// ---------------------------------------------------------------------------

add_action( 'switch_theme', function () {
	wp_clear_scheduled_hook( 'headless_servisne_cleanup' );
} );
