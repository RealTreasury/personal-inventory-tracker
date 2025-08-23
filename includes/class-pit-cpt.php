<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIT_CPT {

    public static function register() {
        register_post_type(
            'pit_item',
            array(
                'labels' => array(
                    'name'          => __( 'Inventory Items', 'personal-inventory-tracker' ),
                    'singular_name' => __( 'Inventory Item', 'personal-inventory-tracker' ),
                ),
                'public'       => false,
                'show_ui'      => true,
                'supports'     => array( 'title' ),
                'taxonomies'   => array( 'pit_category' ),
                'show_in_rest' => false,
            )
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
