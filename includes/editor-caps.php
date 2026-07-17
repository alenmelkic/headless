<?php
/**
 * Editor Capabilities — grant Editors access to Marketing & Theme Settings.
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// One-time: add custom capability to the Editor role
// ---------------------------------------------------------------------------

add_action( 'init', function () {
	if ( get_option( 'headless_editor_caps_v1' ) ) {
		return;
	}

	$editor = get_role( 'editor' );
	if ( $editor ) {
		$editor->add_cap( 'edit_headless_settings' );
	}

	update_option( 'headless_editor_caps_v1', true, true );
} );

// Administrators implicitly have all capabilities, but make it explicit
// so current_user_can() never fails if the cap isn't in the DB yet.
add_action( 'init', function () {
	if ( get_option( 'headless_admin_caps_v1' ) ) {
		return;
	}

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( 'edit_headless_settings' );
	}

	update_option( 'headless_admin_caps_v1', true, true );
} );

// ---------------------------------------------------------------------------
// Allow options.php to accept our custom capability for these option groups
// ---------------------------------------------------------------------------

$headless_option_groups = [
	'headless_mkt_banner_group',
	'headless_mkt_small_group',
	'headless_theme_logo_group',
	'headless_theme_analytics_group',
	'headless_theme_article_group',
	'headless_theme_banner_group',
];

foreach ( $headless_option_groups as $group ) {
	add_filter( "option_page_capability_{$group}", function () {
		return 'edit_headless_settings';
	} );
}
