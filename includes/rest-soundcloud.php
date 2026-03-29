<?php
/**
 * REST Endpoint — SoundCloud Tracks
 *
 *   GET /wp-json/headless/v1/soundcloud/tracks
 *
 * Fetches the public RSS feed for the SoundCloud channel and returns a
 * structured track list. Results are cached in a WP transient for 1 hour.
 *
 * @package Headless
 */

defined( 'ABSPATH' ) || exit;


add_action( 'rest_api_init', function () {
    register_rest_route( 'headless/v1', '/soundcloud/tracks', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'headless_get_soundcloud_tracks',
        'permission_callback' => '__return_true',
    ] );
} );

/**
 * REST callback — wraps headless_soundcloud_fetch_tracks() for the REST layer.
 *
 * @return WP_REST_Response|WP_Error
 */
function headless_get_soundcloud_tracks(): WP_REST_Response|WP_Error {
    $tracks = headless_soundcloud_fetch_tracks();
    if ( is_wp_error( $tracks ) ) {
        return $tracks;
    }
    return rest_ensure_response( $tracks );
}

/**
 * Fetches, parses, and caches tracks from the SoundCloud channel RSS feed.
 *
 * @return array|WP_Error Array of track objects on success, WP_Error on failure.
 */
function headless_soundcloud_fetch_tracks(): array|WP_Error {

    // 1. Return cached result if available.
    $cached = get_transient( 'headless_soundcloud_tracks' );
    if ( false !== $cached ) {
        return $cached;
    }

    // 2. RSS feed URL for Radio Velika Kladuša (user ID: 61105252).
    $rss_url = 'https://feeds.soundcloud.com/users/soundcloud:users:61105252/sounds.rss';

    // 3. Fetch the RSS feed.
    $rss_response = wp_remote_get( $rss_url, [ 'timeout' => 10 ] );

    if ( is_wp_error( $rss_response ) || wp_remote_retrieve_response_code( $rss_response ) !== 200 ) {
        return new WP_Error(
            'soundcloud_rss_failed',
            __( 'Failed to fetch the SoundCloud RSS feed.', 'headless' ),
            [ 'status' => 502 ]
        );
    }

    // 4. Parse the RSS XML.
    $xml_body    = wp_remote_retrieve_body( $rss_response );
    $prev_libxml = libxml_use_internal_errors( true );
    $xml         = simplexml_load_string( $xml_body );
    libxml_clear_errors();
    libxml_use_internal_errors( $prev_libxml );

    if ( ! $xml ) {
        return new WP_Error(
            'soundcloud_rss_failed',
            __( 'Failed to parse the SoundCloud RSS feed.', 'headless' ),
            [ 'status' => 502 ]
        );
    }

    if ( ! isset( $xml->channel ) ) {
        return new WP_Error(
            'soundcloud_rss_failed',
            __( 'SoundCloud RSS feed is not a valid RSS document.', 'headless' ),
            [ 'status' => 502 ]
        );
    }

    // 5. Map items to track objects.
    $xml->registerXPathNamespace( 'itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd' );

    $tracks = [];

    foreach ( $xml->channel->item as $item ) {
        $item->registerXPathNamespace( 'itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd' );

        $url = (string) $item->link;

        // Skip items whose URL is not on soundcloud.com.
        if ( ! preg_match( '#^https://soundcloud\.com/#', $url ) ) {
            continue;
        }

        $duration_nodes = $item->xpath( 'itunes:duration' );
        $image_nodes    = $item->xpath( 'itunes:image' );

        $tracks[] = [
            'title'       => sanitize_text_field( (string) $item->title ),
            'url'         => esc_url_raw( $url ),
            'duration'    => $duration_nodes ? sanitize_text_field( (string) $duration_nodes[0] ) : '',
            'artwork_url' => $image_nodes    ? esc_url_raw( (string) $image_nodes[0]['href'] ) : '',
        ];
    }

    // 6. Cache for 1 hour and return.
    set_transient( 'headless_soundcloud_tracks', $tracks, HOUR_IN_SECONDS );

    return $tracks;
}
