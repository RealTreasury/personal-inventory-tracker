<?php
/**
 * Handles database migrations for Personal Inventory Tracker.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIT_Database {

    const SCHEMA_VERSION = '1.0';

    /**
     * Run database migrations.
     */
    public static function migrate() {
        $installed_version = get_option( 'pit_db_version', '0' );
        if ( version_compare( $installed_version, self::SCHEMA_VERSION, '>=' ) ) {
            return;
        }

        global $wpdb;
        $postmeta = $wpdb->postmeta;

        $indexes = array(
            'pit_status_idx' => "CREATE INDEX pit_status_idx ON {$postmeta} (meta_key(191), meta_value(191))",
            'pit_qty_idx'    => "CREATE INDEX pit_qty_idx ON {$postmeta} (meta_key(191), meta_value(191))",
        );

        foreach ( $indexes as $name => $sql ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$postmeta} WHERE Key_name = %s", $name ) );
            if ( ! $exists ) {
                $wpdb->query( $sql );
            }
        }

        update_option( 'pit_db_version', self::SCHEMA_VERSION );
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
                $wpdb->query( "DROP INDEX {$name} ON {$postmeta}" );
            }
        }

        delete_option( 'pit_db_version' );
    }
}
