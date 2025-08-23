<?php

namespace RealTreasury\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Taxonomy {

    public static function register() {
        register_taxonomy(
            'pit_category',
            'pit_item',
            array(
                'labels' => array(
                    'name'          => __( 'Item Categories', 'personal-inventory-tracker' ),
                    'singular_name' => __( 'Item Category', 'personal-inventory-tracker' ),
                ),
                'public'       => false,
                'show_ui'      => true,
                'hierarchical' => true,
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

\class_alias( __NAMESPACE__ . '\\Taxonomy', 'PIT_Taxonomy' );
