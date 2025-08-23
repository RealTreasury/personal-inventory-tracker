<?php

class PIT_CPT {
    public static function register() {
        register_post_type( 'pit_item', [
            'public'  => false,
            'show_ui' => true,
            'label'   => 'Item',
        ] );
    }
}

