<?php
/**
 * Location Model - Handles multi-location inventory management
 */

namespace RealTreasury\Inventory\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Location {

    /**
     * Create a new location
     */
    public static function create( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_locations';

        $wpdb->insert(
            $table,
            array(
                'parent_id'   => isset( $data['parent_id'] ) ? absint( $data['parent_id'] ) : null,
                'name'        => sanitize_text_field( $data['name'] ),
                'type'        => sanitize_text_field( $data['type'] ?? 'room' ),
                'description' => sanitize_textarea_field( $data['description'] ?? '' ),
                'metadata'    => wp_json_encode( $data['metadata'] ?? array() ),
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );

        return $wpdb->insert_id;
    }

    /**
     * Get location by ID
     */
    public static function get( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_locations';

        $location = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
            ARRAY_A
        );

        if ( $location && isset( $location['metadata'] ) ) {
            $location['metadata'] = json_decode( $location['metadata'], true );
        }

        return $location;
    }

    /**
     * Get all locations with hierarchy
     */
    public static function get_all( $type = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_locations';

        if ( $type ) {
            $locations = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE type = %s ORDER BY name ASC", $type ),
                ARRAY_A
            );
        } else {
            $locations = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A );
        }

        foreach ( $locations as &$location ) {
            if ( isset( $location['metadata'] ) ) {
                $location['metadata'] = json_decode( $location['metadata'], true );
            }
        }

        return $locations;
    }

    /**
     * Get location hierarchy tree
     */
    public static function get_tree() {
        $all_locations = self::get_all();
        return self::build_tree( $all_locations );
    }

    /**
     * Build hierarchical tree from flat array
     */
    private static function build_tree( $elements, $parent_id = null ) {
        $branch = array();

        foreach ( $elements as $element ) {
            if ( $element['parent_id'] == $parent_id ) {
                $children = self::build_tree( $elements, $element['id'] );
                if ( $children ) {
                    $element['children'] = $children;
                }
                $branch[] = $element;
            }
        }

        return $branch;
    }

    /**
     * Update location
     */
    public static function update( $id, $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_locations';

        $update_data = array();
        $format = array();

        if ( isset( $data['parent_id'] ) ) {
            $update_data['parent_id'] = absint( $data['parent_id'] );
            $format[] = '%d';
        }
        if ( isset( $data['name'] ) ) {
            $update_data['name'] = sanitize_text_field( $data['name'] );
            $format[] = '%s';
        }
        if ( isset( $data['type'] ) ) {
            $update_data['type'] = sanitize_text_field( $data['type'] );
            $format[] = '%s';
        }
        if ( isset( $data['description'] ) ) {
            $update_data['description'] = sanitize_textarea_field( $data['description'] );
            $format[] = '%s';
        }
        if ( isset( $data['metadata'] ) ) {
            $update_data['metadata'] = wp_json_encode( $data['metadata'] );
            $format[] = '%s';
        }

        return $wpdb->update( $table, $update_data, array( 'id' => $id ), $format, array( '%d' ) );
    }

    /**
     * Delete location
     */
    public static function delete( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_locations';

        // Update items to remove this location
        $items_in_location = get_posts(
            array(
                'post_type'      => 'pit_item',
                'meta_key'       => 'pit_location_id',
                'meta_value'     => $id,
                'posts_per_page' => -1,
            )
        );

        foreach ( $items_in_location as $item ) {
            delete_post_meta( $item->ID, 'pit_location_id' );
        }

        // Update child locations to have no parent
        $wpdb->update(
            $table,
            array( 'parent_id' => null ),
            array( 'parent_id' => $id ),
            array( '%d' ),
            array( '%d' )
        );

        return $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
    }

    /**
     * Get items in location
     */
    public static function get_items( $location_id, $include_children = false ) {
        $location_ids = array( $location_id );

        if ( $include_children ) {
            $location_ids = array_merge( $location_ids, self::get_descendant_ids( $location_id ) );
        }

        return get_posts(
            array(
                'post_type'      => 'pit_item',
                'meta_query'     => array(
                    array(
                        'key'     => 'pit_location_id',
                        'value'   => $location_ids,
                        'compare' => 'IN',
                    ),
                ),
                'posts_per_page' => -1,
            )
        );
    }

    /**
     * Get all descendant location IDs
     */
    private static function get_descendant_ids( $parent_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'pit_locations';

        $children = $wpdb->get_col(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE parent_id = %d", $parent_id )
        );

        $descendants = $children;
        foreach ( $children as $child_id ) {
            $descendants = array_merge( $descendants, self::get_descendant_ids( $child_id ) );
        }

        return $descendants;
    }
}
