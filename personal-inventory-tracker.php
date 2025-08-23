<?php
/**
 * Plugin Name: Personal Inventory Tracker
 * Description: Simple inventory tracker dashboard.
 * Version: 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PIT_PATH', plugin_dir_path( __FILE__ ) );

require_once PIT_PATH . 'includes/class-pit-reports.php';

function pit_register_admin_page() {
    add_menu_page(
        __( 'Inventory Dashboard', 'personal-inventory-tracker' ),
        __( 'Inventory', 'personal-inventory-tracker' ),
        'manage_options',
        'pit-dashboard',
        'pit_render_dashboard'
    );
}
add_action( 'admin_menu', 'pit_register_admin_page' );

function pit_render_dashboard() {
    PIT_Reports::render_dashboard();
}
