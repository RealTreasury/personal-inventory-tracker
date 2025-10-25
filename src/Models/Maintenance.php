<?php
/**
 * Maintenance Model - Handles maintenance scheduling and tracking
 */

namespace RealTreasury\Inventory\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Maintenance {

    /**
     * Add maintenance schedule
     */
    public static function add( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_maintenance';

        $next_due = isset( $data['next_due'] ) ? $data['next_due'] : self::calculate_next_due( $data['frequency'] );

        $wpdb->insert(
            $table,
            array(
                'item_id'          => absint( $data['item_id'] ),
                'maintenance_type' => sanitize_text_field( $data['maintenance_type'] ),
                'frequency'        => sanitize_text_field( $data['frequency'] ),
                'last_performed'   => isset( $data['last_performed'] ) ? sanitize_text_field( $data['last_performed'] ) : null,
                'next_due'         => $next_due,
                'cost'             => isset( $data['cost'] ) ? floatval( $data['cost'] ) : 0,
                'notes'            => sanitize_textarea_field( $data['notes'] ?? '' ),
                'metadata'         => wp_json_encode( $data['metadata'] ?? array() ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s' )
        );

        return $wpdb->insert_id;
    }

    /**
     * Get maintenance schedules for an item
     */
    public static function get_by_item( $item_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_maintenance';

        $schedules = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE item_id = %d ORDER BY next_due ASC", $item_id ),
            ARRAY_A
        );

        foreach ( $schedules as &$schedule ) {
            if ( isset( $schedule['metadata'] ) ) {
                $schedule['metadata'] = json_decode( $schedule['metadata'], true );
            }
            $schedule['status'] = self::get_maintenance_status( $schedule['next_due'] );
            $schedule['days_until_due'] = self::get_days_until_due( $schedule['next_due'] );
        }

        return $schedules;
    }

    /**
     * Get upcoming maintenance tasks
     */
    public static function get_upcoming( $days = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_maintenance';

        $schedules = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.*, p.post_title as item_name
                FROM {$table} m
                INNER JOIN {$wpdb->posts} p ON m.item_id = p.ID
                WHERE m.next_due BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL %d DAY)
                ORDER BY m.next_due ASC",
                $days
            ),
            ARRAY_A
        );

        foreach ( $schedules as &$schedule ) {
            if ( isset( $schedule['metadata'] ) ) {
                $schedule['metadata'] = json_decode( $schedule['metadata'], true );
            }
            $schedule['days_until_due'] = self::get_days_until_due( $schedule['next_due'] );
        }

        return $schedules;
    }

    /**
     * Get overdue maintenance tasks
     */
    public static function get_overdue() {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_maintenance';

        $schedules = $wpdb->get_results(
            "SELECT m.*, p.post_title as item_name
            FROM {$table} m
            INNER JOIN {$wpdb->posts} p ON m.item_id = p.ID
            WHERE m.next_due < NOW()
            ORDER BY m.next_due ASC",
            ARRAY_A
        );

        foreach ( $schedules as &$schedule ) {
            if ( isset( $schedule['metadata'] ) ) {
                $schedule['metadata'] = json_decode( $schedule['metadata'], true );
            }
            $schedule['days_overdue'] = abs( self::get_days_until_due( $schedule['next_due'] ) );
        }

        return $schedules;
    }

    /**
     * Mark maintenance as completed
     */
    public static function mark_completed( $id, $data = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_maintenance';

        $schedule = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
            ARRAY_A
        );

        if ( ! $schedule ) {
            return false;
        }

        $last_performed = isset( $data['performed_at'] ) ? $data['performed_at'] : current_time( 'mysql' );
        $next_due = self::calculate_next_due( $schedule['frequency'], $last_performed );

        $update_data = array(
            'last_performed' => $last_performed,
            'next_due'       => $next_due,
        );

        if ( isset( $data['cost'] ) ) {
            $update_data['cost'] = floatval( $data['cost'] );
        }

        if ( isset( $data['notes'] ) ) {
            $update_data['notes'] = sanitize_textarea_field( $data['notes'] );
        }

        return $wpdb->update(
            $table,
            $update_data,
            array( 'id' => $id ),
            array( '%s', '%s', '%f', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Update maintenance schedule
     */
    public static function update( $id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_maintenance';

        $update_data = array();
        $format = array();

        $allowed_fields = array(
            'maintenance_type' => '%s',
            'frequency'        => '%s',
            'last_performed'   => '%s',
            'next_due'         => '%s',
            'cost'             => '%f',
            'notes'            => '%s',
        );

        foreach ( $allowed_fields as $field => $field_format ) {
            if ( isset( $data[ $field ] ) ) {
                $update_data[ $field ] = $data[ $field ];
                $format[] = $field_format;
            }
        }

        if ( isset( $data['metadata'] ) ) {
            $update_data['metadata'] = wp_json_encode( $data['metadata'] );
            $format[] = '%s';
        }

        return $wpdb->update( $table, $update_data, array( 'id' => $id ), $format, array( '%d' ) );
    }

    /**
     * Delete maintenance schedule
     */
    public static function delete( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_maintenance';

        return $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
    }

    /**
     * Calculate next due date based on frequency
     */
    private static function calculate_next_due( $frequency, $from_date = null ) {
        $base_date = $from_date ? $from_date : current_time( 'mysql' );

        $intervals = array(
            'daily'       => '+1 day',
            'weekly'      => '+1 week',
            'biweekly'    => '+2 weeks',
            'monthly'     => '+1 month',
            'quarterly'   => '+3 months',
            'semiannual'  => '+6 months',
            'annual'      => '+1 year',
        );

        if ( isset( $intervals[ $frequency ] ) ) {
            return date( 'Y-m-d H:i:s', strtotime( $intervals[ $frequency ], strtotime( $base_date ) ) );
        }

        // Default to 1 month if frequency not recognized
        return date( 'Y-m-d H:i:s', strtotime( '+1 month', strtotime( $base_date ) ) );
    }

    /**
     * Get maintenance status
     */
    private static function get_maintenance_status( $next_due ) {
        $today = strtotime( current_time( 'mysql' ) );
        $due = strtotime( $next_due );

        if ( $due < $today ) {
            return 'overdue';
        }

        $days_until = floor( ( $due - $today ) / DAY_IN_SECONDS );

        if ( $days_until <= 7 ) {
            return 'due_soon';
        }

        return 'scheduled';
    }

    /**
     * Get days until due
     */
    private static function get_days_until_due( $next_due ) {
        $today = strtotime( current_time( 'mysql' ) );
        $due = strtotime( $next_due );

        return floor( ( $due - $today ) / DAY_IN_SECONDS );
    }
}
