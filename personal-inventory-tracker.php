<?php
/**
 * Plugin Name: Personal Inventory Tracker
 * Description: Manage household inventory with receipts OCR.
 * Version: 0.1.0
 * Author: ChatGPT
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'PIT_Admin' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-pit-admin.php';
}

register_activation_hook( __FILE__, ['PIT_Admin','activate'] );
function pit_init() {
    PIT_Admin::get_instance();
}
add_action( 'plugins_loaded', 'pit_init' );
