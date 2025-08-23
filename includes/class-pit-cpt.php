<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIT_CPT {

    public static function register() {
        // Register custom post type.
    }

    public static function activate() {
        self::register();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }
}
