<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIT_Settings {

    public static function activate() {
        // Set default options.
        add_option( 'pit_default_settings', array() );
    }
}
