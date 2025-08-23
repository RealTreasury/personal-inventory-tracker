<?php
/**
 * Plugin Name: Personal Inventory Tracker
 * Description: Manage a personal inventory with import and export tools.
 * Version: 0.1.0
 * Author: OpenAI Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/includes/class-pit-import-export.php';

/**
 * Initialize plugin components.
 */
function pit_init_plugin() {
    PIT_Import_Export::get_instance();
}
add_action( 'plugins_loaded', 'pit_init_plugin' );
