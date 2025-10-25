<?php
/**
 * Audit Log Model - Tracks all changes to inventory items
 */

namespace RealTreasury\Inventory\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AuditLog {

    /**
     * Log an action
     */
    public static function log( $action, $entity_type, $entity_id, $old_value = null, $new_value = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_audit_log';

        $user_id = get_current_user_id();
        $ip_address = self::get_client_ip();
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

        $wpdb->insert(
            $table,
            array(
                'user_id'     => $user_id,
                'action'      => sanitize_text_field( $action ),
                'entity_type' => sanitize_text_field( $entity_type ),
                'entity_id'   => absint( $entity_id ),
                'old_value'   => $old_value ? wp_json_encode( $old_value ) : null,
                'new_value'   => $new_value ? wp_json_encode( $new_value ) : null,
                'ip_address'  => $ip_address,
                'user_agent'  => $user_agent,
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
        );

        return $wpdb->insert_id;
    }

    /**
     * Get audit log for an entity
     */
    public static function get_by_entity( $entity_type, $entity_id, $limit = 50 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_audit_log';

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, u.display_name as user_name
                FROM {$table} a
                LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                WHERE a.entity_type = %s AND a.entity_id = %d
                ORDER BY a.created_at DESC
                LIMIT %d",
                $entity_type,
                $entity_id,
                $limit
            ),
            ARRAY_A
        );

        foreach ( $logs as &$log ) {
            if ( isset( $log['old_value'] ) ) {
                $log['old_value'] = json_decode( $log['old_value'], true );
            }
            if ( isset( $log['new_value'] ) ) {
                $log['new_value'] = json_decode( $log['new_value'], true );
            }
        }

        return $logs;
    }

    /**
     * Get audit log for a user
     */
    public static function get_by_user( $user_id, $limit = 100 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_audit_log';

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE user_id = %d
                ORDER BY created_at DESC
                LIMIT %d",
                $user_id,
                $limit
            ),
            ARRAY_A
        );

        foreach ( $logs as &$log ) {
            if ( isset( $log['old_value'] ) ) {
                $log['old_value'] = json_decode( $log['old_value'], true );
            }
            if ( isset( $log['new_value'] ) ) {
                $log['new_value'] = json_decode( $log['new_value'], true );
            }
        }

        return $logs;
    }

    /**
     * Get recent activity across all entities
     */
    public static function get_recent( $limit = 100 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_audit_log';

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, u.display_name as user_name
                FROM {$table} a
                LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                ORDER BY a.created_at DESC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        foreach ( $logs as &$log ) {
            if ( isset( $log['old_value'] ) ) {
                $log['old_value'] = json_decode( $log['old_value'], true );
            }
            if ( isset( $log['new_value'] ) ) {
                $log['new_value'] = json_decode( $log['new_value'], true );
            }
        }

        return $logs;
    }

    /**
     * Get activity statistics
     */
    public static function get_stats( $days = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_audit_log';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT entity_id) as affected_entities,
                    DATE(MIN(created_at)) as first_action,
                    DATE(MAX(created_at)) as last_action
                FROM {$table}
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            ),
            ARRAY_A
        );
    }

    /**
     * Get activity by action type
     */
    public static function get_by_action( $action, $limit = 100 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_audit_log';

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, u.display_name as user_name
                FROM {$table} a
                LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                WHERE a.action = %s
                ORDER BY a.created_at DESC
                LIMIT %d",
                $action,
                $limit
            ),
            ARRAY_A
        );

        foreach ( $logs as &$log ) {
            if ( isset( $log['old_value'] ) ) {
                $log['old_value'] = json_decode( $log['old_value'], true );
            }
            if ( isset( $log['new_value'] ) ) {
                $log['new_value'] = json_decode( $log['new_value'], true );
            }
        }

        return $logs;
    }

    /**
     * Delete old audit logs
     */
    public static function cleanup( $days = 365 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_audit_log';

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table}
                WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip = '';

        if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        // Validate IP address
        if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return $ip;
        }

        return '';
    }
}
