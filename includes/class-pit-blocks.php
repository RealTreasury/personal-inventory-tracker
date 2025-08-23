<?php
/**
 * Block registrations for Personal Inventory Tracker.
 *
 * @package PersonalInventoryTracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class PIT_Blocks
 */
class PIT_Blocks {

    /**
     * Initialize hooks.
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_blocks' ) );
    }

    /**
     * Register plugin blocks.
     */
    public static function register_blocks() {
        $dir = PIT_PLUGIN_DIR . 'blocks/';

        $blocks = array( 'pit-app', 'inventory-table' );

        foreach ( $blocks as $block ) {
            $block_type = register_block_type( $dir . $block );
            if ( $block_type ) {
                if ( ! empty( $block_type->script ) ) {
                    wp_set_script_translations( $block_type->script, 'personal-inventory-tracker' );
                }
                if ( ! empty( $block_type->editor_script ) ) {
                    wp_set_script_translations( $block_type->editor_script, 'personal-inventory-tracker' );
                }
            }
        }
    }
}

PIT_Blocks::init();
