<?php
/**
 * Cache helper for Personal Inventory Tracker.
 *
 * @package PersonalInventoryTracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

/**
 * Class PIT_Cache
 *
 * Provides wrapper methods for WordPress transients used throughout the plugin.
 */
class PIT_Cache {

    /**
     * Retrieve a transient or set it using a callback.
     *
     * @param string   $key        Cache key.
     * @param callable $callback   Callback to generate the value.
     * @param int      $expiration Expiration in seconds. Defaults to one hour.
     *
     * @return mixed Cached value.
     */
    public static function get_or_set( string $key, callable $callback, int $expiration = HOUR_IN_SECONDS ) {
        $value = get_transient( $key );
        if ( false === $value ) {
            $value = call_user_func( $callback );
            set_transient( $key, $value, $expiration );
        }

        return $value;
    }

    /**
     * Clear transients matching a pattern.
     *
     * @param string $pattern Partial transient key to match.
     *
     * @return void
     */
    public static function clear_by_pattern( string $pattern ): void {
        global $wpdb;

        $like     = '%' . $wpdb->esc_like( $pattern ) . '%';
        $options  = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . $like
            )
        );

        foreach ( $options as $option ) {
            $key = str_replace( '_transient_', '', $option );
            delete_transient( $key );
        }
    }

    /**
     * Clear all inventory-related caches.
     *
     * @return void
     */
    public static function clear_inventory_caches(): void {
        self::clear_by_pattern( 'pit_' );
    }
}
