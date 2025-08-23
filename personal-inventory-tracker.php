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
require_once PIT_PLUGIN_DIR . 'includes/class-pit-reports.php';
require_once PIT_PLUGIN_DIR . 'includes/class-pit-cron.php';
require_once PIT_PLUGIN_DIR . 'includes/class-pit-settings.php';
require_once PIT_PLUGIN_DIR . 'pit-functions.php';

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

function pit_enqueue_frontend() {
    $app_js  = PIT_PLUGIN_DIR . 'assets/app.js';
    $app_css = PIT_PLUGIN_DIR . 'assets/app.css';
    $ver_js  = file_exists( $app_js ) ? filemtime( $app_js ) : false;
    $ver_css = file_exists( $app_css ) ? filemtime( $app_css ) : false;

    wp_enqueue_style( 'pit-app', PIT_PLUGIN_URL . 'assets/app.css', array(), $ver_css );
    wp_enqueue_script( 'tesseract', 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js', array(), '5.0.0', true );
    wp_enqueue_script( 'pit-app', PIT_PLUGIN_URL . 'assets/app.js', array(), $ver_js, true );

    wp_localize_script( 'pit-app', 'pitApp', array(
        'restUrl' => esc_url_raw( rest_url( 'pit/v1/' ) ),
        'nonce'   => wp_create_nonce( 'wp_rest' ),
        'i18n'    => array(
            'search'         => __( 'Search…', 'personal-inventory-tracker' ),
            'filterAll'      => __( 'All', 'personal-inventory-tracker' ),
            'filterPurchased'=> __( 'Purchased', 'personal-inventory-tracker' ),
            'filterNeeded'   => __( 'Needed', 'personal-inventory-tracker' ),
            'filterLabel'    => __( 'Filter items', 'personal-inventory-tracker' ),
            'addItem'        => __( 'Add Item', 'personal-inventory-tracker' ),
            'addName'        => __( 'Item name', 'personal-inventory-tracker' ),
            'item'           => __( 'Item', 'personal-inventory-tracker' ),
            'qty'            => __( 'Qty', 'personal-inventory-tracker' ),
            'purchased'      => __( 'Purchased', 'personal-inventory-tracker' ),
            'actions'        => __( 'Actions', 'personal-inventory-tracker' ),
            'exportCsv'      => __( 'Export CSV', 'personal-inventory-tracker' ),
            'scanReceipt'    => __( 'Scan Receipt', 'personal-inventory-tracker' ),
            'confirmDelete'  => __( 'Are you sure you want to delete this item?', 'personal-inventory-tracker' ),
            'noItems'        => __( 'No items found. Use the form above to add items.', 'personal-inventory-tracker' ),
            'increaseQty'    => __( 'Increase quantity', 'personal-inventory-tracker' ),
            'decreaseQty'    => __( 'Decrease quantity', 'personal-inventory-tracker' ),
            'delete'         => __( 'Delete item', 'personal-inventory-tracker' ),
            'togglePurchased'=> __( 'Toggle purchased state', 'personal-inventory-tracker' ),
            'addHelp'        => __( 'Provide an item name and quantity, then click Add Item.', 'personal-inventory-tracker' ),
        ),
    ) );
}

function pit_enqueue_admin( $hook ) {
    if ( false === strpos( $hook, 'pit_' ) ) {
        return;
    }

    $admin_js  = PIT_PLUGIN_DIR . 'assets/admin.js';
    $admin_css = PIT_PLUGIN_DIR . 'assets/admin.css';
    $ver_js    = file_exists( $admin_js ) ? filemtime( $admin_js ) : false;
    $ver_css   = file_exists( $admin_css ) ? filemtime( $admin_css ) : false;

    wp_enqueue_style( 'pit-admin', PIT_PLUGIN_URL . 'assets/admin.css', array(), $ver_css );
    wp_enqueue_script( 'pit-admin', PIT_PLUGIN_URL . 'assets/admin.js', array(), $ver_js, true );

    wp_localize_script( 'pit-admin', 'pitAdmin', array(
        'restUrl' => esc_url_raw( rest_url( 'pit/v1/' ) ),
        'nonce'   => wp_create_nonce( 'wp_rest' ),
        'i18n'    => array(
            'scanReceipt' => __( 'Scan Receipt', 'personal-inventory-tracker' ),
        ),
    ) );
}
add_action( 'admin_enqueue_scripts', 'pit_enqueue_admin' );

function pit_app_shortcode() {
    pit_enqueue_frontend();
    ob_start();
    include PIT_PLUGIN_DIR . 'templates/frontend-app.php';
    return ob_get_clean();
}
add_shortcode( 'pit_app', 'pit_app_shortcode' );


function pit_render_app_block( $attributes, $content ) {
    pit_enqueue_frontend();
    ob_start();
    include PIT_PLUGIN_DIR . 'templates/frontend-app.php';
    return ob_get_clean();
}

function pit_register_app_block() {
    register_block_type( PIT_PLUGIN_DIR . 'blocks/pit-app', array(
        'render_callback' => 'pit_render_app_block',
    ) );
}
add_action( 'init', 'pit_register_app_block' );

