<?php
/**
 * Purchase History Model - Tracks purchase history and price trends
 */

namespace RealTreasury\Inventory\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PurchaseHistory {

    /**
     * Add purchase record
     */
    public static function add( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_purchase_history';

        $wpdb->insert(
            $table,
            array(
                'item_id'       => absint( $data['item_id'] ),
                'vendor'        => sanitize_text_field( $data['vendor'] ?? '' ),
                'purchase_date' => sanitize_text_field( $data['purchase_date'] ?? current_time( 'mysql' ) ),
                'quantity'      => absint( $data['quantity'] ?? 1 ),
                'unit_price'    => floatval( $data['unit_price'] ?? 0 ),
                'total_price'   => floatval( $data['total_price'] ?? 0 ),
                'currency'      => sanitize_text_field( $data['currency'] ?? 'USD' ),
                'receipt_url'   => esc_url_raw( $data['receipt_url'] ?? '' ),
                'notes'         => sanitize_textarea_field( $data['notes'] ?? '' ),
                'metadata'      => wp_json_encode( $data['metadata'] ?? array() ),
            ),
            array( '%d', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s' )
        );

        // Update item last purchased date
        update_post_meta( $data['item_id'], 'pit_last_purchased', $data['purchase_date'] ?? current_time( 'Y-m-d' ) );

        return $wpdb->insert_id;
    }

    /**
     * Get purchase history for an item
     */
    public static function get_by_item( $item_id, $limit = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_purchase_history';

        $sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE item_id = %d ORDER BY purchase_date DESC", $item_id );

        if ( $limit ) {
            $sql .= $wpdb->prepare( " LIMIT %d", $limit );
        }

        $history = $wpdb->get_results( $sql, ARRAY_A );

        foreach ( $history as &$record ) {
            if ( isset( $record['metadata'] ) ) {
                $record['metadata'] = json_decode( $record['metadata'], true );
            }
        }

        return $history;
    }

    /**
     * Get price trends for an item
     */
    public static function get_price_trends( $item_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_purchase_history';

        $trends = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT purchase_date as date, unit_price, vendor
                FROM {$table}
                WHERE item_id = %d AND unit_price > 0
                ORDER BY purchase_date ASC",
                $item_id
            ),
            ARRAY_A
        );

        $stats = self::calculate_price_stats( $item_id );

        return array(
            'trends' => $trends,
            'stats'  => $stats,
        );
    }

    /**
     * Calculate price statistics
     */
    public static function calculate_price_stats( $item_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_purchase_history';

        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    MIN(unit_price) as lowest_price,
                    MAX(unit_price) as highest_price,
                    AVG(unit_price) as average_price,
                    COUNT(*) as purchase_count,
                    SUM(total_price) as total_spent
                FROM {$table}
                WHERE item_id = %d AND unit_price > 0",
                $item_id
            ),
            ARRAY_A
        );

        return $stats;
    }

    /**
     * Get spending by vendor
     */
    public static function get_spending_by_vendor( $start_date = null, $end_date = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_purchase_history';

        $where = '1=1';
        $params = array();

        if ( $start_date ) {
            $where .= ' AND purchase_date >= %s';
            $params[] = $start_date;
        }

        if ( $end_date ) {
            $where .= ' AND purchase_date <= %s';
            $params[] = $end_date;
        }

        $sql = "SELECT vendor, SUM(total_price) as total_spent, COUNT(*) as purchase_count
                FROM {$table}
                WHERE {$where} AND vendor != ''
                GROUP BY vendor
                ORDER BY total_spent DESC";

        if ( ! empty( $params ) ) {
            $sql = $wpdb->prepare( $sql, ...$params );
        }

        return $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * Get spending trends over time
     */
    public static function get_spending_trends( $period = 'month', $limit = 12 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_purchase_history';

        $date_format = $period === 'day' ? '%Y-%m-%d' : ( $period === 'week' ? '%Y-%u' : '%Y-%m' );

        $sql = $wpdb->prepare(
            "SELECT DATE_FORMAT(purchase_date, %s) as period,
                    SUM(total_price) as total_spent,
                    COUNT(*) as purchase_count
            FROM {$table}
            WHERE purchase_date >= DATE_SUB(NOW(), INTERVAL %d " . strtoupper( $period ) . ")
            GROUP BY period
            ORDER BY period ASC",
            $date_format,
            $limit
        );

        return $wpdb->get_results( $sql, ARRAY_A );
    }

    /**
     * Update purchase record
     */
    public static function update( $id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_purchase_history';

        $update_data = array();
        $format = array();

        $allowed_fields = array(
            'vendor'        => '%s',
            'purchase_date' => '%s',
            'quantity'      => '%d',
            'unit_price'    => '%f',
            'total_price'   => '%f',
            'currency'      => '%s',
            'receipt_url'   => '%s',
            'notes'         => '%s',
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
     * Delete purchase record
     */
    public static function delete( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_purchase_history';

        return $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
    }
}
