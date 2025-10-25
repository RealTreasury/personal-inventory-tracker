<?php
/**
 * Enterprise REST API - Handles all enterprise inventory features
 */

namespace RealTreasury\Inventory\REST;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RealTreasury\Inventory\Models\Location;
use RealTreasury\Inventory\Models\PurchaseHistory;
use RealTreasury\Inventory\Models\Warranty;
use RealTreasury\Inventory\Models\Maintenance;
use RealTreasury\Inventory\Models\Notification;
use RealTreasury\Inventory\Models\AuditLog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EnterpriseApi {

    public function register_routes() {
        // Location endpoints
        register_rest_route(
            'pit/v2',
            '/locations',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_locations' ),
                    'permission_callback' => array( $this, 'permissions_read' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_location' ),
                    'permission_callback' => array( $this, 'permissions_write' ),
                ),
            )
        );

        register_rest_route(
            'pit/v2',
            '/locations/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_location' ),
                    'permission_callback' => array( $this, 'permissions_write' ),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_location' ),
                    'permission_callback' => array( $this, 'permissions_write' ),
                ),
            )
        );

        register_rest_route(
            'pit/v2',
            '/locations/tree',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_location_tree' ),
                'permission_callback' => array( $this, 'permissions_read' ),
            )
        );

        // Purchase history endpoints
        register_rest_route(
            'pit/v2',
            '/items/(?P<item_id>\d+)/purchases',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_purchase_history' ),
                    'permission_callback' => array( $this, 'permissions_read' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'add_purchase' ),
                    'permission_callback' => array( $this, 'permissions_write' ),
                ),
            )
        );

        register_rest_route(
            'pit/v2',
            '/purchases/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_purchase' ),
                    'permission_callback' => array( $this, 'permissions_write' ),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_purchase' ),
                    'permission_callback' => array( $this, 'permissions_write' ),
                ),
            )
        );

        register_rest_route(
            'pit/v2',
            '/analytics/spending',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_spending_analytics' ),
                'permission_callback' => array( $this, 'permissions_read' ),
            )
        );

        // Warranty endpoints
        register_rest_route(
            'pit/v2',
            '/items/(?P<item_id>\d+)/warranties',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_warranties' ),
                    'permission_callback' => array( $this, 'permissions_read' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'add_warranty' ),
                    'permission_callback' => array( $this, 'permissions_write' ),
                ),
            )
        );

        register_rest_route(
            'pit/v2',
            '/warranties/expiring',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_expiring_warranties' ),
                'permission_callback' => array( $this, 'permissions_read' ),
            )
        );

        // Maintenance endpoints
        register_rest_route(
            'pit/v2',
            '/items/(?P<item_id>\d+)/maintenance',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_maintenance' ),
                    'permission_callback' => array( $this, 'permissions_read' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'add_maintenance' ),
                    'permission_callback' => array( $this, 'permissions_write' ),
                ),
            )
        );

        register_rest_route(
            'pit/v2',
            '/maintenance/upcoming',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_upcoming_maintenance' ),
                'permission_callback' => array( $this, 'permissions_read' ),
            )
        );

        register_rest_route(
            'pit/v2',
            '/maintenance/(?P<id>\d+)/complete',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'complete_maintenance' ),
                'permission_callback' => array( $this, 'permissions_write' ),
            )
        );

        // Notification endpoints
        register_rest_route(
            'pit/v2',
            '/notifications',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_notifications' ),
                'permission_callback' => array( $this, 'permissions_read' ),
            )
        );

        register_rest_route(
            'pit/v2',
            '/notifications/(?P<id>\d+)/read',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'mark_notification_read' ),
                'permission_callback' => array( $this, 'permissions_write' ),
            )
        );

        register_rest_route(
            'pit/v2',
            '/notifications/mark-all-read',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'mark_all_notifications_read' ),
                'permission_callback' => array( $this, 'permissions_write' ),
            )
        );

        // Audit log endpoints
        register_rest_route(
            'pit/v2',
            '/audit-log',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_audit_log' ),
                'permission_callback' => array( $this, 'permissions_manage' ),
            )
        );

        register_rest_route(
            'pit/v2',
            '/items/(?P<item_id>\d+)/history',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_item_history' ),
                'permission_callback' => array( $this, 'permissions_read' ),
            )
        );

        // Bulk operations
        register_rest_route(
            'pit/v2',
            '/items/bulk-update',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'bulk_update_items' ),
                'permission_callback' => array( $this, 'permissions_write' ),
            )
        );

        register_rest_route(
            'pit/v2',
            '/import',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'import_data' ),
                'permission_callback' => array( $this, 'permissions_write' ),
            )
        );

        register_rest_route(
            'pit/v2',
            '/export',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'export_data' ),
                'permission_callback' => array( $this, 'permissions_read' ),
            )
        );

        // Advanced search
        register_rest_route(
            'pit/v2',
            '/search',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'advanced_search' ),
                'permission_callback' => array( $this, 'permissions_read' ),
            )
        );
    }

    // Permission callbacks
    public function permissions_read( $request ) {
        $settings = \RealTreasury\Inventory\Settings::get_settings();
        return ! empty( $settings['public_access'] ) || current_user_can( 'view_inventory' );
    }

    public function permissions_write( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', 'Invalid nonce', array( 'status' => 403 ) );
        }

        return current_user_can( 'manage_inventory_items' );
    }

    public function permissions_manage( $request ) {
        return current_user_can( 'manage_inventory_settings' );
    }

    // Location methods
    public function get_locations( $request ) {
        $type = $request->get_param( 'type' );
        $locations = Location::get_all( $type );

        return rest_ensure_response( $locations );
    }

    public function get_location_tree( $request ) {
        $tree = Location::get_tree();
        return rest_ensure_response( $tree );
    }

    public function create_location( $request ) {
        $data = array(
            'name'        => $request->get_param( 'name' ),
            'type'        => $request->get_param( 'type' ),
            'parent_id'   => $request->get_param( 'parent_id' ),
            'description' => $request->get_param( 'description' ),
            'metadata'    => $request->get_param( 'metadata' ),
        );

        $id = Location::create( $data );

        if ( $id ) {
            AuditLog::log( 'create', 'location', $id, null, $data );
            return rest_ensure_response( Location::get( $id ) );
        }

        return new WP_Error( 'creation_failed', 'Failed to create location', array( 'status' => 500 ) );
    }

    public function update_location( $request ) {
        $id = $request->get_param( 'id' );
        $old_location = Location::get( $id );

        $data = array(
            'name'        => $request->get_param( 'name' ),
            'type'        => $request->get_param( 'type' ),
            'parent_id'   => $request->get_param( 'parent_id' ),
            'description' => $request->get_param( 'description' ),
            'metadata'    => $request->get_param( 'metadata' ),
        );

        $updated = Location::update( $id, $data );

        if ( $updated !== false ) {
            AuditLog::log( 'update', 'location', $id, $old_location, $data );
            return rest_ensure_response( Location::get( $id ) );
        }

        return new WP_Error( 'update_failed', 'Failed to update location', array( 'status' => 500 ) );
    }

    public function delete_location( $request ) {
        $id = $request->get_param( 'id' );
        $location = Location::get( $id );

        $deleted = Location::delete( $id );

        if ( $deleted ) {
            AuditLog::log( 'delete', 'location', $id, $location, null );
            return rest_ensure_response( array( 'deleted' => true ) );
        }

        return new WP_Error( 'delete_failed', 'Failed to delete location', array( 'status' => 500 ) );
    }

    // Purchase history methods
    public function get_purchase_history( $request ) {
        $item_id = $request->get_param( 'item_id' );
        $limit = $request->get_param( 'limit' );

        $history = PurchaseHistory::get_by_item( $item_id, $limit );

        return rest_ensure_response( $history );
    }

    public function add_purchase( $request ) {
        $item_id = $request->get_param( 'item_id' );

        $data = array(
            'item_id'       => $item_id,
            'vendor'        => $request->get_param( 'vendor' ),
            'purchase_date' => $request->get_param( 'purchase_date' ),
            'quantity'      => $request->get_param( 'quantity' ),
            'unit_price'    => $request->get_param( 'unit_price' ),
            'total_price'   => $request->get_param( 'total_price' ),
            'currency'      => $request->get_param( 'currency' ),
            'receipt_url'   => $request->get_param( 'receipt_url' ),
            'notes'         => $request->get_param( 'notes' ),
            'metadata'      => $request->get_param( 'metadata' ),
        );

        $id = PurchaseHistory::add( $data );

        if ( $id ) {
            AuditLog::log( 'add_purchase', 'item', $item_id, null, $data );
            return rest_ensure_response( array( 'id' => $id, 'success' => true ) );
        }

        return new WP_Error( 'creation_failed', 'Failed to add purchase', array( 'status' => 500 ) );
    }

    public function get_spending_analytics( $request ) {
        $start_date = $request->get_param( 'start_date' );
        $end_date = $request->get_param( 'end_date' );
        $period = $request->get_param( 'period' ) ?? 'month';

        $by_vendor = PurchaseHistory::get_spending_by_vendor( $start_date, $end_date );
        $trends = PurchaseHistory::get_spending_trends( $period );

        return rest_ensure_response(
            array(
                'by_vendor' => $by_vendor,
                'trends'    => $trends,
            )
        );
    }

    // Warranty methods
    public function get_warranties( $request ) {
        $item_id = $request->get_param( 'item_id' );
        $warranties = Warranty::get_by_item( $item_id );

        return rest_ensure_response( $warranties );
    }

    public function add_warranty( $request ) {
        $item_id = $request->get_param( 'item_id' );

        $data = array(
            'item_id'          => $item_id,
            'warranty_type'    => $request->get_param( 'warranty_type' ),
            'provider'         => $request->get_param( 'provider' ),
            'start_date'       => $request->get_param( 'start_date' ),
            'end_date'         => $request->get_param( 'end_date' ),
            'coverage_details' => $request->get_param( 'coverage_details' ),
            'document_url'     => $request->get_param( 'document_url' ),
            'reminder_days'    => $request->get_param( 'reminder_days' ),
            'metadata'         => $request->get_param( 'metadata' ),
        );

        $id = Warranty::add( $data );

        if ( $id ) {
            AuditLog::log( 'add_warranty', 'item', $item_id, null, $data );
            return rest_ensure_response( array( 'id' => $id, 'success' => true ) );
        }

        return new WP_Error( 'creation_failed', 'Failed to add warranty', array( 'status' => 500 ) );
    }

    public function get_expiring_warranties( $request ) {
        $days = $request->get_param( 'days' ) ?? 30;
        $warranties = Warranty::get_expiring( $days );

        return rest_ensure_response( $warranties );
    }

    // Maintenance methods
    public function get_maintenance( $request ) {
        $item_id = $request->get_param( 'item_id' );
        $schedules = Maintenance::get_by_item( $item_id );

        return rest_ensure_response( $schedules );
    }

    public function add_maintenance( $request ) {
        $item_id = $request->get_param( 'item_id' );

        $data = array(
            'item_id'          => $item_id,
            'maintenance_type' => $request->get_param( 'maintenance_type' ),
            'frequency'        => $request->get_param( 'frequency' ),
            'last_performed'   => $request->get_param( 'last_performed' ),
            'next_due'         => $request->get_param( 'next_due' ),
            'cost'             => $request->get_param( 'cost' ),
            'notes'            => $request->get_param( 'notes' ),
            'metadata'         => $request->get_param( 'metadata' ),
        );

        $id = Maintenance::add( $data );

        if ( $id ) {
            AuditLog::log( 'add_maintenance', 'item', $item_id, null, $data );
            return rest_ensure_response( array( 'id' => $id, 'success' => true ) );
        }

        return new WP_Error( 'creation_failed', 'Failed to add maintenance', array( 'status' => 500 ) );
    }

    public function get_upcoming_maintenance( $request ) {
        $days = $request->get_param( 'days' ) ?? 30;
        $schedules = Maintenance::get_upcoming( $days );

        return rest_ensure_response( $schedules );
    }

    public function complete_maintenance( $request ) {
        $id = $request->get_param( 'id' );

        $data = array(
            'performed_at' => $request->get_param( 'performed_at' ),
            'cost'         => $request->get_param( 'cost' ),
            'notes'        => $request->get_param( 'notes' ),
        );

        $updated = Maintenance::mark_completed( $id, $data );

        if ( $updated !== false ) {
            AuditLog::log( 'complete_maintenance', 'maintenance', $id, null, $data );
            return rest_ensure_response( array( 'success' => true ) );
        }

        return new WP_Error( 'update_failed', 'Failed to mark maintenance as completed', array( 'status' => 500 ) );
    }

    // Notification methods
    public function get_notifications( $request ) {
        $user_id = get_current_user_id();
        $unread_only = $request->get_param( 'unread_only' ) === 'true';
        $limit = $request->get_param( 'limit' ) ?? 50;

        $notifications = Notification::get_by_user( $user_id, $unread_only, $limit );
        $unread_count = Notification::get_unread_count( $user_id );

        return rest_ensure_response(
            array(
                'notifications' => $notifications,
                'unread_count'  => $unread_count,
            )
        );
    }

    public function mark_notification_read( $request ) {
        $id = $request->get_param( 'id' );
        $updated = Notification::mark_as_read( $id );

        if ( $updated !== false ) {
            return rest_ensure_response( array( 'success' => true ) );
        }

        return new WP_Error( 'update_failed', 'Failed to mark notification as read', array( 'status' => 500 ) );
    }

    public function mark_all_notifications_read( $request ) {
        $user_id = get_current_user_id();
        $updated = Notification::mark_all_as_read( $user_id );

        if ( $updated !== false ) {
            return rest_ensure_response( array( 'success' => true ) );
        }

        return new WP_Error( 'update_failed', 'Failed to mark all notifications as read', array( 'status' => 500 ) );
    }

    // Audit log methods
    public function get_audit_log( $request ) {
        $entity_type = $request->get_param( 'entity_type' );
        $entity_id = $request->get_param( 'entity_id' );
        $user_id = $request->get_param( 'user_id' );
        $limit = $request->get_param( 'limit' ) ?? 100;

        if ( $entity_type && $entity_id ) {
            $logs = AuditLog::get_by_entity( $entity_type, $entity_id, $limit );
        } elseif ( $user_id ) {
            $logs = AuditLog::get_by_user( $user_id, $limit );
        } else {
            $logs = AuditLog::get_recent( $limit );
        }

        return rest_ensure_response( $logs );
    }

    public function get_item_history( $request ) {
        $item_id = $request->get_param( 'item_id' );
        $history = AuditLog::get_by_entity( 'item', $item_id );

        return rest_ensure_response( $history );
    }

    // Advanced search
    public function advanced_search( $request ) {
        $query = $request->get_param( 'query' );
        $filters = $request->get_param( 'filters' ) ?? array();

        // Build WP_Query args
        $args = array(
            'post_type'      => 'pit_item',
            'post_status'    => 'publish',
            'posts_per_page' => $request->get_param( 'per_page' ) ?? 50,
            'paged'          => $request->get_param( 'page' ) ?? 1,
        );

        if ( $query ) {
            $args['s'] = sanitize_text_field( $query );
        }

        // Apply filters
        if ( ! empty( $filters ) ) {
            $meta_query = array( 'relation' => 'AND' );
            $tax_query = array( 'relation' => 'AND' );

            foreach ( $filters as $filter ) {
                $field = sanitize_text_field( $filter['field'] );
                $operator = sanitize_text_field( $filter['operator'] ?? '=' );
                $value = sanitize_text_field( $filter['value'] );

                if ( strpos( $field, 'pit_' ) === 0 ) {
                    // Meta query
                    $meta_query[] = array(
                        'key'     => $field,
                        'value'   => $value,
                        'compare' => $operator,
                    );
                } elseif ( $field === 'category' ) {
                    // Taxonomy query
                    $tax_query[] = array(
                        'taxonomy' => 'pit_category',
                        'field'    => 'slug',
                        'terms'    => $value,
                    );
                }
            }

            if ( count( $meta_query ) > 1 ) {
                $args['meta_query'] = $meta_query;
            }
            if ( count( $tax_query ) > 1 ) {
                $args['tax_query'] = $tax_query;
            }
        }

        $query_result = new \WP_Query( $args );
        $items = array();

        foreach ( $query_result->posts as $post ) {
            $items[] = $this->prepare_item( $post );
        }

        return rest_ensure_response(
            array(
                'items'       => $items,
                'total'       => $query_result->found_posts,
                'total_pages' => $query_result->max_num_pages,
            )
        );
    }

    // Helper methods
    private function prepare_item( $post ) {
        $categories = wp_get_post_terms( $post->ID, 'pit_category', array( 'fields' => 'names' ) );

        return array(
            'id'            => $post->ID,
            'title'         => $post->post_title,
            'qty'           => absint( get_post_meta( $post->ID, 'pit_qty', true ) ),
            'unit'          => get_post_meta( $post->ID, 'pit_unit', true ),
            'threshold'     => absint( get_post_meta( $post->ID, 'pit_threshold', true ) ),
            'location_id'   => absint( get_post_meta( $post->ID, 'pit_location_id', true ) ),
            'barcode'       => get_post_meta( $post->ID, 'pit_barcode', true ),
            'sku'           => get_post_meta( $post->ID, 'pit_sku', true ),
            'brand'         => get_post_meta( $post->ID, 'pit_brand', true ),
            'model'         => get_post_meta( $post->ID, 'pit_model', true ),
            'serial_number' => get_post_meta( $post->ID, 'pit_serial_number', true ),
            'purchase_price'=> get_post_meta( $post->ID, 'pit_purchase_price', true ),
            'expiration'    => get_post_meta( $post->ID, 'pit_expiration', true ),
            'notes'         => get_post_meta( $post->ID, 'pit_notes', true ),
            'category'      => $categories ? $categories[0] : null,
            'created_at'    => $post->post_date,
            'updated_at'    => $post->post_modified,
        );
    }

    // Bulk operations
    public function bulk_update_items( $request ) {
        $items = $request->get_param( 'items' );
        $action = $request->get_param( 'action' );

        if ( ! is_array( $items ) || empty( $items ) ) {
            return new WP_Error( 'invalid_data', 'Invalid items data', array( 'status' => 400 ) );
        }

        $results = array(
            'success' => 0,
            'failed'  => 0,
            'errors'  => array(),
        );

        foreach ( $items as $item_data ) {
            $item_id = absint( $item_data['id'] );

            if ( ! $item_id ) {
                $results['failed']++;
                continue;
            }

            if ( $action === 'delete' ) {
                $deleted = wp_trash_post( $item_id );
                if ( $deleted ) {
                    AuditLog::log( 'bulk_delete', 'item', $item_id );
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
            } else {
                // Update item
                foreach ( $item_data as $key => $value ) {
                    if ( $key !== 'id' && strpos( $key, 'pit_' ) === 0 ) {
                        update_post_meta( $item_id, $key, $value );
                    }
                }

                AuditLog::log( 'bulk_update', 'item', $item_id, null, $item_data );
                $results['success']++;
            }
        }

        return rest_ensure_response( $results );
    }

    // Import/Export
    public function import_data( $request ) {
        $data = $request->get_param( 'data' );
        $format = $request->get_param( 'format' ) ?? 'json';

        if ( ! $data ) {
            return new WP_Error( 'invalid_data', 'No data provided', array( 'status' => 400 ) );
        }

        $items = array();

        if ( $format === 'json' ) {
            $items = json_decode( $data, true );
        } elseif ( $format === 'csv' ) {
            // Parse CSV
            $lines = str_getcsv( $data, "\n" );
            $headers = str_getcsv( array_shift( $lines ) );

            foreach ( $lines as $line ) {
                $row = str_getcsv( $line );
                if ( count( $row ) === count( $headers ) ) {
                    $items[] = array_combine( $headers, $row );
                }
            }
        }

        $results = array(
            'imported' => 0,
            'failed'   => 0,
            'errors'   => array(),
        );

        foreach ( $items as $item_data ) {
            $post_id = wp_insert_post(
                array(
                    'post_type'   => 'pit_item',
                    'post_title'  => sanitize_text_field( $item_data['title'] ?? $item_data['name'] ?? '' ),
                    'post_status' => 'publish',
                )
            );

            if ( ! is_wp_error( $post_id ) ) {
                foreach ( $item_data as $key => $value ) {
                    if ( $key !== 'title' && $key !== 'name' ) {
                        update_post_meta( $post_id, 'pit_' . $key, sanitize_text_field( $value ) );
                    }
                }

                AuditLog::log( 'import', 'item', $post_id, null, $item_data );
                $results['imported']++;
            } else {
                $results['failed']++;
                $results['errors'][] = $post_id->get_error_message();
            }
        }

        return rest_ensure_response( $results );
    }

    public function export_data( $request ) {
        $format = $request->get_param( 'format' ) ?? 'json';
        $filters = $request->get_param( 'filters' ) ?? array();

        $args = array(
            'post_type'      => 'pit_item',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        );

        $query = new \WP_Query( $args );
        $items = array();

        foreach ( $query->posts as $post ) {
            $items[] = $this->prepare_item( $post );
        }

        if ( $format === 'json' ) {
            return rest_ensure_response(
                array(
                    'data'   => $items,
                    'format' => 'json',
                )
            );
        } elseif ( $format === 'csv' ) {
            // Generate CSV
            $csv = '';
            if ( ! empty( $items ) ) {
                $headers = array_keys( $items[0] );
                $csv .= implode( ',', $headers ) . "\n";

                foreach ( $items as $item ) {
                    $csv .= implode( ',', array_map( array( $this, 'escape_csv_value' ), array_values( $item ) ) ) . "\n";
                }
            }

            return rest_ensure_response(
                array(
                    'data'   => $csv,
                    'format' => 'csv',
                )
            );
        }

        return new WP_Error( 'invalid_format', 'Unsupported export format', array( 'status' => 400 ) );
    }

    private function escape_csv_value( $value ) {
        if ( is_array( $value ) ) {
            $value = implode( ';', $value );
        }

        if ( strpos( $value, ',' ) !== false || strpos( $value, '"' ) !== false || strpos( $value, "\n" ) !== false ) {
            $value = '"' . str_replace( '"', '""', $value ) . '"';
        }

        return $value;
    }
}
