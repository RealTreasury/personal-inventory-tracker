<?php
/**
 * Plugin Name: Personal Inventory Tracker
 * Description: Manage personal inventory with reorder recommendations.
 * Version: 1.0.0
 * Author: OpenAI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/includes/class-pit-cron.php';

PIT_Cron::init();

register_activation_hook( __FILE__, array( 'PIT_Cron', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PIT_Cron', 'deactivate' ) );
