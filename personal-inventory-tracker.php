<?php
/**
 * Plugin Name: Personal Inventory Tracker
 * Description: Provides a shortcode and block for the Personal Inventory Tracker app.
 * Version: 1.0.0
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Render the Personal Inventory Tracker container.
 *
 * @return string HTML markup for the app container.
 */
function pit_app_render_container() {
    // Enqueue styles only when container is rendered.
    wp_enqueue_style( 'pit-app-style' );

    ob_start();
    ?>
    <div id="pit-app"></div>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode handler to render the Personal Inventory Tracker app.
 *
 * @return string HTML markup for the app container.
 */
function pit_app_shortcode_handler() {
    return pit_app_render_container();
}
add_shortcode( 'pit_app', 'pit_app_shortcode_handler' );

/**
 * Register the Personal Inventory Tracker block on init.
 */
function pit_app_register_block() {
    register_block_type( __DIR__ . '/blocks/pit-app', array(
        'render_callback' => 'pit_app_render_container',
    ) );
}
add_action( 'init', 'pit_app_register_block' );
