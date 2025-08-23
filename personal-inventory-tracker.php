<?php
/**
 * Plugin Name: Personal Inventory Tracker
 */

require_once __DIR__ . '/includes/class-pit-cpt.php';
require_once __DIR__ . '/includes/class-pit-taxonomy.php';

add_action( 'init', [ 'PIT_CPT', 'register' ] );
add_action( 'init', [ 'PIT_Taxonomy', 'register' ] );

function pit_activate() {
    PIT_CPT::register();
    PIT_Taxonomy::register();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'pit_activate' );

