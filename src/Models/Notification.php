<?php
/**
 * Notification Model - Handles user notifications
 */

namespace RealTreasury\Inventory\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Notification {

    /**
     * Create notification
     */
    public static function create( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_notifications';

        $wpdb->insert(
            $table,
            array(
                'user_id'  => absint( $data['user_id'] ),
                'item_id'  => isset( $data['item_id'] ) ? absint( $data['item_id'] ) : null,
                'type'     => sanitize_text_field( $data['type'] ),
                'title'    => sanitize_text_field( $data['title'] ),
                'message'  => sanitize_textarea_field( $data['message'] ?? '' ),
                'is_read'  => absint( $data['is_read'] ?? 0 ),
                'metadata' => wp_json_encode( $data['metadata'] ?? array() ),
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
        );

        return $wpdb->insert_id;
    }

    /**
     * Get notifications for a user
     */
    public static function get_by_user( $user_id, $unread_only = false, $limit = 50 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_notifications';

        $where = $wpdb->prepare( 'user_id = %d', $user_id );

        if ( $unread_only ) {
            $where .= ' AND is_read = 0';
        }

        $sql = "SELECT * FROM {$table}
                WHERE {$where}
                ORDER BY created_at DESC
                LIMIT %d";

        $notifications = $wpdb->get_results(
            $wpdb->prepare( $sql, $limit ),
            ARRAY_A
        );

        foreach ( $notifications as &$notification ) {
            if ( isset( $notification['metadata'] ) ) {
                $notification['metadata'] = json_decode( $notification['metadata'], true );
            }
        }

        return $notifications;
    }

    /**
     * Get unread count for a user
     */
    public static function get_unread_count( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_notifications';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
                $user_id
            )
        );
    }

    /**
     * Mark notification as read
     */
    public static function mark_as_read( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_notifications';

        return $wpdb->update(
            $table,
            array( 'is_read' => 1 ),
            array( 'id' => $id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    /**
     * Mark all notifications as read for a user
     */
    public static function mark_all_as_read( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_notifications';

        return $wpdb->update(
            $table,
            array( 'is_read' => 1 ),
            array( 'user_id' => $user_id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    /**
     * Delete notification
     */
    public static function delete( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_notifications';

        return $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
    }

    /**
     * Delete old notifications
     */
    public static function delete_old( $days = 90 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_notifications';

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table}
                WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
                AND is_read = 1",
                $days
            )
        );
    }

    /**
     * Create low stock notification
     */
    public static function create_low_stock_alert( $item_id, $user_id ) {
        $post = get_post( $item_id );
        if ( ! $post ) {
            return false;
        }

        $qty = absint( get_post_meta( $item_id, 'pit_qty', true ) );
        $threshold = absint( get_post_meta( $item_id, 'pit_threshold', true ) );

        return self::create(
            array(
                'user_id' => $user_id,
                'item_id' => $item_id,
                'type'    => 'low_stock',
                'title'   => sprintf( 'Low stock alert: %s', $post->post_title ),
                'message' => sprintf( 'Current quantity (%d) is at or below threshold (%d)', $qty, $threshold ),
                'metadata' => array(
                    'current_qty' => $qty,
                    'threshold'   => $threshold,
                ),
            )
        );
    }
}
