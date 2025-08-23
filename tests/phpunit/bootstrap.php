<?php
/**
 * PHPUnit bootstrap for Personal Inventory Tracker.
 */

define( 'ABSPATH', __DIR__ );

if ( ! function_exists( 'get_transient' ) ) {
    $GLOBALS['pit_transients'] = [];

    function get_transient( $key ) {
        return $GLOBALS['pit_transients'][ $key ] ?? false;
    }

    function set_transient( $key, $value, $expiration ) {
        $GLOBALS['pit_transients'][ $key ] = $value;
        return true;
    }

    function delete_transient( $key ) {
        unset( $GLOBALS['pit_transients'][ $key ] );
        return true;
    }
}

require dirname( __DIR__, 2 ) . '/includes/class-pit-cache.php';
