<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIT_Admin {

    public function init() {
        // Initialize admin hooks.
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets() {
        wp_enqueue_script(
            'pit-admin',
            PIT_PLUGIN_URL . 'assets/admin.js',
            array( 'jquery' ),
            filemtime( PIT_PLUGIN_DIR . 'assets/admin.js' ),
            true
        );

        wp_enqueue_style(
            'pit-admin',
            PIT_PLUGIN_URL . 'assets/admin.css',
            array(),
            filemtime( PIT_PLUGIN_DIR . 'assets/admin.css' )
        );

        wp_localize_script(
            'pit-admin',
            'pitAdmin',
            array(
                'restUrl'   => esc_url_raw( rest_url() ),
                'restNonce' => wp_create_nonce( 'wp_rest' ),
                'i18n'      => array(
                    'error' => __( 'An error occurred', 'personal-inventory-tracker' ),
                ),
            )
        );
    }
}
