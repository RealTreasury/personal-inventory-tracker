<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIT_Taxonomy {

    public static function register() {
        // Register custom taxonomy.
    }

    public static function activate() {
        self::register();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }
}
