<?php
/**
 * Plugin Name: Personal Inventory Tracker
 * Description: Track personal inventory items.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: personal-inventory-tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Personal_Inventory_Tracker {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_app_assets' ] );
    }

    /**
     * Enqueue scripts and styles for admin pages.
     */
    public function enqueue_admin_assets() {
        $asset_path = plugin_dir_path( __FILE__ ) . 'assets/';

        $admin_js  = $asset_path . 'admin.js';
        $admin_css = $asset_path . 'admin.css';

        $ver_js  = file_exists( $admin_js ) ? filemtime( $admin_js ) : false;
        $ver_css = file_exists( $admin_css ) ? filemtime( $admin_css ) : false;

        wp_enqueue_script(
            'pit-admin-js',
            plugin_dir_url( __FILE__ ) . 'assets/admin.js',
            [ 'jquery' ],
            $ver_js,
            true
        );

        wp_enqueue_style(
            'pit-admin-css',
            plugin_dir_url( __FILE__ ) . 'assets/admin.css',
            [],
            $ver_css
        );

        wp_localize_script( 'pit-admin-js', 'pitAdminData', [
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'i18n'  => [
                'greeting' => __( 'Hello, admin!', 'personal-inventory-tracker' ),
            ],
        ] );
    }

    /**
     * Enqueue scripts and styles for the front-end.
     */
    public function enqueue_app_assets() {
        $asset_path = plugin_dir_path( __FILE__ ) . 'assets/';

        $app_js  = $asset_path . 'app.js';
        $app_css = $asset_path . 'app.css';

        $ver_js  = file_exists( $app_js ) ? filemtime( $app_js ) : false;
        $ver_css = file_exists( $app_css ) ? filemtime( $app_css ) : false;

        wp_enqueue_script(
            'pit-app-js',
            plugin_dir_url( __FILE__ ) . 'assets/app.js',
            [ 'jquery' ],
            $ver_js,
            true
        );

        wp_enqueue_style(
            'pit-app-css',
            plugin_dir_url( __FILE__ ) . 'assets/app.css',
            [],
            $ver_css
        );

        wp_localize_script( 'pit-app-js', 'pitAppData', [
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'i18n'  => [
                'greeting' => __( 'Hello, user!', 'personal-inventory-tracker' ),
            ],
        ] );
    }
}

new Personal_Inventory_Tracker();
