<?php
/**
 * Handles database migrations for Personal Inventory Tracker.
 */

namespace RealTreasury\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Database {

    const SCHEMA_VERSION = '2.0';

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
        self::create_custom_tables();

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
     * Create custom tables for enterprise features.
     */
    private static function create_custom_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Locations table
        $locations_table = $wpdb->prefix . 'pit_locations';
        $sql = "CREATE TABLE {$locations_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            parent_id bigint(20) unsigned DEFAULT NULL,
            name varchar(255) NOT NULL,
            type varchar(50) DEFAULT 'room',
            description text,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY parent_id (parent_id),
            KEY type (type)
        ) $charset_collate;";
        dbDelta( $sql );

        // Purchase history table
        $purchase_history_table = $wpdb->prefix . 'pit_purchase_history';
        $sql = "CREATE TABLE {$purchase_history_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            item_id bigint(20) unsigned NOT NULL,
            vendor varchar(255),
            purchase_date datetime,
            quantity int(11) DEFAULT 0,
            unit_price decimal(10,2),
            total_price decimal(10,2),
            currency varchar(10) DEFAULT 'USD',
            receipt_url varchar(500),
            notes text,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY item_id (item_id),
            KEY purchase_date (purchase_date)
        ) $charset_collate;";
        dbDelta( $sql );

        // Warranties table
        $warranties_table = $wpdb->prefix . 'pit_warranties';
        $sql = "CREATE TABLE {$warranties_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            item_id bigint(20) unsigned NOT NULL,
            warranty_type varchar(100),
            provider varchar(255),
            start_date date,
            end_date date,
            coverage_details text,
            document_url varchar(500),
            reminder_days int(11) DEFAULT 30,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY item_id (item_id),
            KEY end_date (end_date)
        ) $charset_collate;";
        dbDelta( $sql );

        // Maintenance schedule table
        $maintenance_table = $wpdb->prefix . 'pit_maintenance';
        $sql = "CREATE TABLE {$maintenance_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            item_id bigint(20) unsigned NOT NULL,
            maintenance_type varchar(100),
            frequency varchar(50),
            last_performed datetime,
            next_due datetime,
            cost decimal(10,2),
            notes text,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY item_id (item_id),
            KEY next_due (next_due)
        ) $charset_collate;";
        dbDelta( $sql );

        // Audit log table
        $audit_table = $wpdb->prefix . 'pit_audit_log';
        $sql = "CREATE TABLE {$audit_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned,
            action varchar(50) NOT NULL,
            entity_type varchar(50) NOT NULL,
            entity_id bigint(20) unsigned,
            old_value longtext,
            new_value longtext,
            ip_address varchar(100),
            user_agent varchar(500),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY entity (entity_type, entity_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta( $sql );

        // Custom fields table
        $custom_fields_table = $wpdb->prefix . 'pit_custom_fields';
        $sql = "CREATE TABLE {$custom_fields_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            item_id bigint(20) unsigned NOT NULL,
            field_name varchar(255) NOT NULL,
            field_value longtext,
            field_type varchar(50) DEFAULT 'text',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY item_id (item_id),
            KEY field_name (field_name(191))
        ) $charset_collate;";
        dbDelta( $sql );

        // Notifications table
        $notifications_table = $wpdb->prefix . 'pit_notifications';
        $sql = "CREATE TABLE {$notifications_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            item_id bigint(20) unsigned,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text,
            is_read tinyint(1) DEFAULT 0,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY item_id (item_id),
            KEY is_read (is_read),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta( $sql );

        // Attachments table
        $attachments_table = $wpdb->prefix . 'pit_attachments';
        $sql = "CREATE TABLE {$attachments_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            item_id bigint(20) unsigned NOT NULL,
            attachment_id bigint(20) unsigned NOT NULL,
            type varchar(50) DEFAULT 'image',
            title varchar(255),
            description text,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY item_id (item_id),
            KEY attachment_id (attachment_id)
        ) $charset_collate;";
        dbDelta( $sql );
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
