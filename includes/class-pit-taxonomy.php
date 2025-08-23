<?php

class PIT_Taxonomy {
    public static function register() {
        register_taxonomy( 'pit_category', 'pit_item', [
            'hierarchical' => true,
            'label'        => 'Category',
        ] );
    }
}

