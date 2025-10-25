<?php
/**
 * Warranty Model - Handles warranty tracking and notifications
 */

namespace RealTreasury\Inventory\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Warranty {

    /**
     * Add warranty
     */
    public static function add( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_warranties';

        $wpdb->insert(
            $table,
            array(
                'item_id'          => absint( $data['item_id'] ),
                'warranty_type'    => sanitize_text_field( $data['warranty_type'] ?? 'manufacturer' ),
                'provider'         => sanitize_text_field( $data['provider'] ?? '' ),
                'start_date'       => sanitize_text_field( $data['start_date'] ?? current_time( 'Y-m-d' ) ),
                'end_date'         => sanitize_text_field( $data['end_date'] ),
                'coverage_details' => sanitize_textarea_field( $data['coverage_details'] ?? '' ),
                'document_url'     => esc_url_raw( $data['document_url'] ?? '' ),
                'reminder_days'    => absint( $data['reminder_days'] ?? 30 ),
                'metadata'         => wp_json_encode( $data['metadata'] ?? array() ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
        );

        $warranty_id = $wpdb->insert_id;

        // Schedule expiration reminder
        if ( $warranty_id ) {
            self::schedule_reminder( $warranty_id, $data['item_id'], $data['end_date'], $data['reminder_days'] ?? 30 );
        }

        return $warranty_id;
    }

    /**
     * Get warranties for an item
     */
    public static function get_by_item( $item_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_warranties';

        $warranties = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE item_id = %d ORDER BY end_date DESC", $item_id ),
            ARRAY_A
        );

        foreach ( $warranties as &$warranty ) {
            if ( isset( $warranty['metadata'] ) ) {
                $warranty['metadata'] = json_decode( $warranty['metadata'], true );
            }
            $warranty['status'] = self::get_warranty_status( $warranty['end_date'] );
            $warranty['days_remaining'] = self::get_days_remaining( $warranty['end_date'] );
        }

        return $warranties;
    }

    /**
     * Get expiring warranties
     */
    public static function get_expiring( $days = 30 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_warranties';

        $warranties = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT w.*, p.post_title as item_name
                FROM {$table} w
                INNER JOIN {$wpdb->posts} p ON w.item_id = p.ID
                WHERE w.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL %d DAY)
                ORDER BY w.end_date ASC",
                $days
            ),
            ARRAY_A
        );

        foreach ( $warranties as &$warranty ) {
            if ( isset( $warranty['metadata'] ) ) {
                $warranty['metadata'] = json_decode( $warranty['metadata'], true );
            }
            $warranty['days_remaining'] = self::get_days_remaining( $warranty['end_date'] );
        }

        return $warranties;
    }

    /**
     * Get all active warranties
     */
    public static function get_active() {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_warranties';

        $warranties = $wpdb->get_results(
            "SELECT w.*, p.post_title as item_name
            FROM {$table} w
            INNER JOIN {$wpdb->posts} p ON w.item_id = p.ID
            WHERE w.end_date >= CURDATE()
            ORDER BY w.end_date ASC",
            ARRAY_A
        );

        foreach ( $warranties as &$warranty ) {
            if ( isset( $warranty['metadata'] ) ) {
                $warranty['metadata'] = json_decode( $warranty['metadata'], true );
            }
            $warranty['days_remaining'] = self::get_days_remaining( $warranty['end_date'] );
        }

        return $warranties;
    }

    /**
     * Update warranty
     */
    public static function update( $id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_warranties';

        $update_data = array();
        $format = array();

        $allowed_fields = array(
            'warranty_type'    => '%s',
            'provider'         => '%s',
            'start_date'       => '%s',
            'end_date'         => '%s',
            'coverage_details' => '%s',
            'document_url'     => '%s',
            'reminder_days'    => '%d',
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
     * Delete warranty
     */
    public static function delete( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_warranties';

        return $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
    }

    /**
     * Get warranty status
     */
    private static function get_warranty_status( $end_date ) {
        $today = strtotime( current_time( 'Y-m-d' ) );
        $end = strtotime( $end_date );

        if ( $end < $today ) {
            return 'expired';
        }

        $days_remaining = floor( ( $end - $today ) / DAY_IN_SECONDS );

        if ( $days_remaining <= 30 ) {
            return 'expiring_soon';
        }

        return 'active';
    }

    /**
     * Get days remaining
     */
    private static function get_days_remaining( $end_date ) {
        $today = strtotime( current_time( 'Y-m-d' ) );
        $end = strtotime( $end_date );

        return max( 0, floor( ( $end - $today ) / DAY_IN_SECONDS ) );
    }

    /**
     * Schedule warranty expiration reminder
     */
    private static function schedule_reminder( $warranty_id, $item_id, $end_date, $reminder_days ) {
        $reminder_date = date( 'Y-m-d', strtotime( $end_date . " -{$reminder_days} days" ) );
        $today = current_time( 'Y-m-d' );

        if ( $reminder_date >= $today ) {
            // Create notification for warranty expiration
            $post = get_post( $item_id );
            if ( $post ) {
                \RealTreasury\Inventory\Models\Notification::create(
                    array(
                        'user_id' => get_current_user_id(),
                        'item_id' => $item_id,
                        'type'    => 'warranty_expiring',
                        'title'   => sprintf( 'Warranty expiring for %s', $post->post_title ),
                        'message' => sprintf( 'The warranty for %s will expire on %s', $post->post_title, $end_date ),
                        'metadata' => array(
                            'warranty_id' => $warranty_id,
                            'end_date'    => $end_date,
                        ),
                    )
                );
            }
        }
    }
}
