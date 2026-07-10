<?php
/**
 * Admin Subdomain Redirect
 *
 * Redirects the root path to the WordPress admin dashboard (or login page
 * if not authenticated) when the request host matches the WordPress Address
 * configured in Settings → General (site_url). Works for any environment —
 * production, staging, localhost — with no hardcoded domains.
 * Any sub-path is passed through unchanged so wp-admin, wp-login, wp-json,
 * and media uploads continue to work normally.
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	if ( ! isset( $_SERVER['HTTP_HOST'] ) ) {
		return;
	}

	$request_host = strtolower( $_SERVER['HTTP_HOST'] );
	$uri          = $_SERVER['REQUEST_URI'] ?? '/';
	$wp_host      = strtolower( (string) wp_parse_url( site_url(), PHP_URL_HOST ) );

	// Only redirect when the request host matches the WordPress Address (site_url).
	if ( $request_host !== $wp_host ) {
		return;
	}

	// Only redirect the bare root — let all other paths (wp-admin, wp-login,
	// wp-json, wp-content, etc.) pass through normally.
	if ( rtrim( $uri, '/' ) !== '' ) {
		return;
	}

	wp_redirect( admin_url(), 302 );
	exit;
} );
