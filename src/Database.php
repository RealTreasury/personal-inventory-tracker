<?php
/**
 * Handles database migrations for Personal Inventory Tracker.
 */

namespace RealTreasury\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Database {

    const SCHEMA_VERSION = '1.1';

    /**
     * Run database migrations.
     */
    public static function migrate() {
        $installed_version = get_option( 'pit_db_version', '0' );
        if ( version_compare( $installed_version, self::SCHEMA_VERSION, '>=' ) ) {
            return;
        }

        self::migrate_schema();
        self::migrate_data( $installed_version );
        self::cleanup_deprecated_meta_keys();

        update_option( 'pit_db_version', self::SCHEMA_VERSION );
    }

    /**
     * Create or update database schema items.
     */
    private static function migrate_schema() {
        global $wpdb;
        $postmeta = $wpdb->postmeta;

        $indexes = array(
            'pit_status_idx' => "CREATE INDEX pit_status_idx ON {$postmeta} (meta_key(191), meta_value(191))",
            'pit_qty_idx'    => "CREATE INDEX pit_qty_idx ON {$postmeta} (meta_key(191), meta_value(191))",
        );

        foreach ( $indexes as $name => $sql ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$postmeta} WHERE Key_name = %s", $name ) );
            if ( ! $exists ) {
                // Note: CREATE INDEX statements cannot use prepare() as they don't support placeholders
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->query( $sql );
            }
        }
    }

    /**
     * Run data migrations based on a previously installed version.
     *
     * @param string $from_version Installed schema version.
     */
    private static function migrate_data( $from_version ) {
        global $wpdb;

        if ( version_compare( $from_version, '1.1', '<' ) ) {
            $postmeta = $wpdb->postmeta;

            // Rename legacy meta keys to new prefixed versions.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query( $wpdb->prepare( "UPDATE {$postmeta} SET meta_key = %s WHERE meta_key = %s", 'pit_qty', 'qty' ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query( $wpdb->prepare( "UPDATE {$postmeta} SET meta_key = %s WHERE meta_key = %s", 'pit_threshold', 'reorder_threshold' ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query( $wpdb->prepare( "UPDATE {$postmeta} SET meta_key = %s WHERE meta_key = %s", 'pit_interval', 'reorder_interval' ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query( $wpdb->prepare( "UPDATE {$postmeta} SET meta_key = %s WHERE meta_key = %s", 'pit_last_purchased', 'last_reordered' ) );

            // Rename deprecated prefixed keys.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query( $wpdb->prepare( "UPDATE {$postmeta} SET meta_key = %s WHERE meta_key = %s", 'pit_threshold', 'pit_reorder_threshold' ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query( $wpdb->prepare( "UPDATE {$postmeta} SET meta_key = %s WHERE meta_key = %s", 'pit_interval', 'pit_reorder_interval' ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query( $wpdb->prepare( "UPDATE {$postmeta} SET meta_key = %s WHERE meta_key = %s", 'pit_last_purchased', 'pit_last_reordered' ) );
        }
    }

    /**
     * Remove deprecated meta keys from the database.
     */
    public static function cleanup_deprecated_meta_keys() {
        global $wpdb;
        $deprecated = array(
            'qty',
            'reorder_threshold',
            'reorder_interval',
            'last_reordered',
            'pit_reorder_threshold',
            'pit_reorder_interval',
            'pit_last_reordered',
        );

        foreach ( $deprecated as $key ) {
            $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $key ) );
        }
    }

    /**
     * Roll back database changes.
     */
    public static function rollback() {
        global $wpdb;
        $postmeta = $wpdb->postmeta;

        $indexes = array( 'pit_status_idx', 'pit_qty_idx' );
        foreach ( $indexes as $name ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$postmeta} WHERE Key_name = %s", $name ) );
            if ( $exists ) {
                // Note: DROP INDEX statements cannot use prepare() as they don't support placeholders
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->query( "DROP INDEX {$name} ON {$postmeta}" );
            }
        }

        delete_option( 'pit_db_version' );
    }
}

\class_alias( __NAMESPACE__ . '\\Database', 'PIT_Database' );
