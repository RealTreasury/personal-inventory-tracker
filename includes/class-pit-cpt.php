<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIT_CPT {

    public static function register() {
        register_post_type(
            'pit_item',
            [
                'labels' => [
                    'name'          => __( 'Items', 'personal-inventory-tracker' ),
                    'singular_name' => __( 'Item', 'personal-inventory-tracker' ),
                ],
                'public'      => false,
                'show_ui'     => true,
                'supports'    => [ 'title', 'editor' ],
                'show_in_rest'=> true,
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
