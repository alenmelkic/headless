<?php
/**
 * Headless v3 — Theme Functions
 *
 * WordPress is used as a pure headless CMS / API backend.
 * All logic is split into focused files under /includes/.
 *
 * @package Headless
 * @author  Alen Melkic
 * @version 3.0.0
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Core
// ---------------------------------------------------------------------------

require_once get_template_directory() . '/includes/cpt.php';       // Custom post types
require_once get_template_directory() . '/includes/patterns.php';   // Block patterns
require_once get_template_directory() . '/includes/theme.php';      // Theme setup, image sizes, editor restrictions
require_once get_template_directory() . '/includes/acf.php';        // ACF JSON sync, block auto-registration, options page

// ---------------------------------------------------------------------------
// REST API
// ---------------------------------------------------------------------------

require_once get_template_directory() . '/includes/cors.php';            // CORS + Cache-Control headers
require_once get_template_directory() . '/includes/rest-menus.php';      // GET /headless/v1/menus/{location}
require_once get_template_directory() . '/includes/rest-options.php';    // GET /headless/v1/options[/global]
require_once get_template_directory() . '/includes/rest-blocks.php';     // GET /headless/v1/posts/{id}/blocks
require_once get_template_directory() . '/includes/rest-seo.php';        // GET /headless/v1/seo/{id}
require_once get_template_directory() . '/includes/rest-preview.php';    // GET /headless/v1/preview
require_once get_template_directory() . '/includes/rest-soundcloud.php'; // GET /headless/v1/soundcloud/tracks

// ---------------------------------------------------------------------------
// Integrations
// ---------------------------------------------------------------------------

require_once get_template_directory() . '/includes/settings.php';        // Admin settings page + headless_get_setting() helper
require_once get_template_directory() . '/includes/theme-settings.php'; // Theme Settings: logo, favicon, analytics (WPGraphQL)
require_once get_template_directory() . '/includes/marketing.php';      // Marketing: glavni banner + mali banneri
require_once get_template_directory() . '/includes/author-image.php';  // Author profile image field + WPGraphQL
require_once get_template_directory() . '/includes/revalidation.php';   // Next.js ISR revalidation webhook
require_once get_template_directory() . '/includes/cron-cleanup.php';  // Auto-trash servisne posts older than 15 days
require_once get_template_directory() . '/includes/cookie-banner-settings.php'; // Cookie Banner admin page + WPGraphQL
