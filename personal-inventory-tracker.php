<?php
/**
 * Plugin Name:       Personal Inventory Tracker
 * Description:       Manage personal inventory via custom post types and APIs.
 * Version:           1.0.0
 * Author:            OpenAI
 * License:           GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PIT_PLUGIN_FILE', __FILE__ );
define( 'PIT_PLUGIN_DIR', plugin_dir_path( PIT_PLUGIN_FILE ) );
define( 'PIT_PLUGIN_URL', plugin_dir_url( PIT_PLUGIN_FILE ) );
define( 'PIT_PLUGIN_BASENAME', plugin_basename( PIT_PLUGIN_FILE ) );

require_once PIT_PLUGIN_DIR . 'includes/class-pit-cpt.php';
require_once PIT_PLUGIN_DIR . 'includes/class-pit-taxonomy.php';
require_once PIT_PLUGIN_DIR . 'includes/class-pit-rest.php';
require_once PIT_PLUGIN_DIR . 'includes/class-pit-admin.php';
require_once PIT_PLUGIN_DIR . 'includes/class-pit-cron.php';
require_once PIT_PLUGIN_DIR . 'includes/class-pit-settings.php';

function pit_activate() {
    PIT_CPT::activate();
    PIT_Taxonomy::activate();
    PIT_Cron::activate();
    PIT_Settings::activate();
}
register_activation_hook( PIT_PLUGIN_FILE, 'pit_activate' );

function pit_deactivate() {
    PIT_CPT::deactivate();
    PIT_Taxonomy::deactivate();
    PIT_Cron::deactivate();
}
register_deactivation_hook( PIT_PLUGIN_FILE, 'pit_deactivate' );

add_action( 'init', [ 'PIT_CPT', 'register' ] );
add_action( 'init', [ 'PIT_Taxonomy', 'register' ] );
add_action( 'rest_api_init', function() {
    $rest = new PIT_REST();
    $rest->register_routes();
} );
add_action( 'plugins_loaded', function() {
    ( new PIT_Admin() )->init();
    PIT_Cron::init();
} );

