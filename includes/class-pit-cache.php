<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

class PIT_Cache {

    /**
     * Retrieve a transient or set it using a callback.
     *
     * @param string   $key        Cache key.
     * @param callable $callback   Callback to generate the value.
     * @param int      $expiration Expiration in seconds.
     * @return mixed Cached value.
     */
    public static function get_or_set( $key, $callback, $expiration = HOUR_IN_SECONDS ) {
        $value = get_transient( $key );
        if ( false === $value ) {
            $value = call_user_func( $callback );
            set_transient( $key, $value, $expiration );
        }
        return $value;
    }

    /**
     * Delete a transient cache entry.
     *
     * @param string $key Cache key.
     * @return void
     */
    public static function delete( $key ) {
        delete_transient( $key );
    }
}
