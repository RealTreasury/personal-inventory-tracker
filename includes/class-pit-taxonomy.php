<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIT_Taxonomy {

    public static function register() {
        register_taxonomy(
            'pit_category',
            'pit_item',
            [
                'labels' => [
                    'name'          => __( 'Categories', 'personal-inventory-tracker' ),
                    'singular_name' => __( 'Category', 'personal-inventory-tracker' ),
                ],
                'hierarchical' => true,
                'show_ui'      => true,
                'show_in_rest' => true,
            ]
        );
    }

    public static function activate() {
        self::register();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }
}
