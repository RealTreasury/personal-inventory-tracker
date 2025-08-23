<?php
/**
 * Personal Inventory Tracker core functions.
 *
 * @package PersonalInventoryTracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
    define( 'DAY_IN_SECONDS', 86400 );
}

/**
 * Retrieve all meta data for an inventory item.
 *
 * @param int $post_id Inventory item post ID.
 * @return array Array of meta key => value pairs.
 */
function pit_get_item( $post_id ) {
    $post_id = absint( $post_id );
    if ( ! $post_id ) {
        return array();
    }

    $meta = get_post_meta( $post_id );
    $item = array();

    foreach ( $meta as $key => $values ) {
        // get_post_meta returns array of values; take the first.
        $item[ $key ] = maybe_unserialize( $values[0] );
    }

    return $item;
}

/**
 * Update an inventory item and its meta fields.
 *
 * @param int   $post_id Post ID.
 * @param array $data    Data to update.
 * @return array|WP_Error Updated item array on success or WP_Error on failure.
 */
function pit_update_item( $post_id, $data ) {
    $post_id = absint( $post_id );
    if ( ! $post_id || ! is_array( $data ) ) {
        return new WP_Error( 'pit_invalid_args', __( 'Invalid item data.', 'personal-inventory-tracker' ) );
    }

    $sanitized = array();

    // Known fields and their sanitization callbacks.
    $schema = array(
        'post_title'        => 'sanitize_text_field',
        'qty'               => 'pit_sanitize_int',
        'reorder_threshold' => 'pit_sanitize_int',
        'reorder_interval'  => 'pit_sanitize_int',
        'last_reordered'    => 'pit_sanitize_int',
    );

    foreach ( $data as $field => $value ) {
        if ( isset( $schema[ $field ] ) ) {
            $sanitize = $schema[ $field ];
            $sanitized[ $field ] = call_user_func( $sanitize, $value );
        } else {
            $sanitized[ $field ] = pit_sanitize_text( $value );
        }
    }

    if ( isset( $sanitized['post_title'] ) ) {
        wp_update_post(
            array(
                'ID'         => $post_id,
                'post_title' => $sanitized['post_title'],
            )
        );
        unset( $sanitized['post_title'] );
    }

    foreach ( $sanitized as $key => $value ) {
        update_post_meta( $post_id, $key, $value );
    }

    return pit_get_item( $post_id );
}

/**
 * Determine whether an item needs to be reordered.
 *
 * @param array $item Item data.
 * @return array {
 *     @type bool   $needed Whether reorder is needed.
 *     @type string $reason Reason for reorder ('quantity', 'interval', or '').
 * }
 */
function pit_calculate_reorder_needed( $item ) {
    $qty               = isset( $item['qty'] ) ? absint( $item['qty'] ) : 0;
    $threshold         = isset( $item['reorder_threshold'] ) ? absint( $item['reorder_threshold'] ) : 0;
    $interval          = isset( $item['reorder_interval'] ) ? absint( $item['reorder_interval'] ) : 0; // Days.
    $last_reordered    = isset( $item['last_reordered'] ) ? absint( $item['last_reordered'] ) : 0; // Timestamp.
    $current_timestamp = function_exists( 'current_time' ) ? current_time( 'timestamp' ) : time();

    $needed = false;
    $reason = '';

    if ( $qty <= $threshold ) {
        $needed = true;
        $reason = 'quantity';
    } elseif ( $interval > 0 && $last_reordered > 0 ) {
        $next_due = $last_reordered + ( $interval * DAY_IN_SECONDS );
        if ( $current_timestamp >= $next_due ) {
            $needed = true;
            $reason = 'interval';
        }
    }

    return array(
        'needed' => $needed,
        'reason' => $reason,
    );
}

/**
 * Sanitize a text value.
 *
 * @param mixed $value Value to sanitize.
 * @return string
 */
function pit_sanitize_text( $value ) {
    return sanitize_text_field( $value );
}

/**
 * Sanitize an integer value.
 *
 * @param mixed $value Value to sanitize.
 * @return int
 */
function pit_sanitize_int( $value ) {
    return absint( $value );
}

/**
 * Escape a string for safe HTML output.
 *
 * @param string $text Text to escape.
 * @return string
 */
function pit_esc_html( $text ) {
    return esc_html( $text );
}

/**
 * Clear cached report summaries when inventory items change.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function pit_clear_summary_cache( $post_id ) {
    if ( 'pit_item' === get_post_type( $post_id ) ) {
        \RealTreasury\Inventory\Cache::delete( 'pit_reco_summary' );
    }
}

add_action( 'save_post_pit_item', 'pit_clear_summary_cache' );
add_action( 'delete_post', 'pit_clear_summary_cache' );

