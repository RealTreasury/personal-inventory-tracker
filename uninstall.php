<?php
/**
 * Uninstall actions for Personal Inventory Tracker.
 *
 * @package PersonalInventoryTracker
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

// Remove custom posts.
$posts = get_posts(
    array(
        'post_type'      => 'pit_item',
        'post_status'    => 'any',
        'numberposts'    => -1,
        'fields'         => 'ids',
        'nopaging'       => true,
    )
);

foreach ( $posts as $post_id ) {
    wp_delete_post( $post_id, true );
}

// Remove taxonomy terms.
$terms = get_terms(
    array(
        'taxonomy'   => 'pit_category',
        'hide_empty' => false,
        'fields'     => 'ids',
    )
);

if ( ! is_wp_error( $terms ) ) {
    foreach ( $terms as $term_id ) {
        wp_delete_term( $term_id, 'pit_category' );
    }
}

// Delete options.
$options = array(
    'pit_settings',
    'pit_intro_dismissed',
    'pit_public_access',
    'pit_read_only_mode',
    'pit_ocr_confidence',
    'pit_currency',
    'pit_version',
    'pit_reco_summary',
);

foreach ( $options as $option ) {
    delete_option( $option );
    delete_site_option( $option );
}

// Delete transients.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pit_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_pit_%'" );
if ( is_multisite() ) {
    $wpdb->query( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_pit_%'" );
    $wpdb->query( "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_timeout_pit_%'" );
}

// Remove scheduled hooks.
$timestamp = wp_next_scheduled( 'pit_refresh_recommendations_daily' );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, 'pit_refresh_recommendations_daily' );
}

// Remove custom capabilities.
if ( class_exists( 'PIT_Capabilities' ) ) {
    PIT_Capabilities::remove_capabilities();
}
