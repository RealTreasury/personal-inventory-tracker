<?php
/**
 * Enhanced REST API functionality for Personal Inventory Tracker.
 *
 * Provides enhanced REST API endpoints with advanced filtering, analytics,
 * OCR processing, and shopping list functionality.
 *
 * @package PersonalInventoryTracker
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enhanced REST API class for inventory management.
 *
 * Extends the base REST API functionality with enhanced features including:
 * - Advanced item filtering and pagination
 * - Analytics and reporting endpoints
 * - OCR receipt processing
 * - Shopping list management
 * - Batch operations
 *
 * @since 2.0.0
 */
class PIT_Enhanced_REST {

    /**
     * Register all REST API routes.
     *
     * @since 2.0.0
     * @return void
     */
    public function register_routes() {
        // Items endpoints
        register_rest_route(
            'pit/v2',
            '/items',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_items' ],
                    'permission_callback' => [ $this, 'permissions_read' ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'create_item' ],
                    'permission_callback' => [ $this, 'permissions_write' ],
                ],
            ]
        );

        register_rest_route(
            'pit/v2',
            '/items/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'update_item' ],
                    'permission_callback' => [ $this, 'permissions_write' ],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_item' ],
                    'permission_callback' => [ $this, 'permissions_write' ],
                ],
            ]
        );

        // Analytics endpoints
        register_rest_route(
            'pit/v2',
            '/analytics',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_analytics' ],
                'permission_callback' => [ $this, 'permissions_read' ],
            ]
        );

        register_rest_route(
            'pit/v2',
            '/analytics/trends',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_trends' ],
                'permission_callback' => [ $this, 'permissions_read' ],
            ]
        );

        // Shopping list endpoint
        register_rest_route(
            'pit/v2',
            '/shopping-list',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_shopping_list' ],
                'permission_callback' => [ $this, 'permissions_read' ],
            ]
        );

        // Bulk operations
        register_rest_route(
            'pit/v2',
            '/items/batch',
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'batch_update_items' ],
                'permission_callback' => [ $this, 'permissions_write' ],
            ]
        );

        // OCR processing endpoint
        register_rest_route(
            'pit/v2',
            '/ocr/process',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'process_ocr_results' ],
                'permission_callback' => [ $this, 'permissions_write' ],
            ]
        );

        // Categories endpoint
        register_rest_route(
            'pit/v2',
            '/categories',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_categories' ],
                'permission_callback' => [ $this, 'permissions_read' ],
            ]
        );
    }

    /**
     * Check read permissions for REST requests.
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request The REST request object.
     * @return true|WP_Error True if permitted, WP_Error if not.
     */
    public function permissions_read( $request ) {
        // Allow logged-in users or public if enabled
        $settings = \RealTreasury\Inventory\Settings::get_settings();
        return ! empty( $settings['public_access'] ) || current_user_can( 'view_inventory' );
    }

    /**
     * Verify nonce for REST request security.
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request The REST request object.
     * @return true|WP_Error True if valid, WP_Error if invalid.
     */
    private function verify_nonce( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 
                'invalid_nonce', 
                __( 'Invalid or missing nonce.', 'personal-inventory-tracker' ), 
                array( 'status' => 403 ) 
            );
        }

        return true;
    }

    /**
     * Check write permissions for REST requests.
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request The REST request object.
     * @return true|WP_Error True if permitted, WP_Error if not.
     */
    public function permissions_write( $request ) {
        $nonce_check = $this->verify_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) {
            return $nonce_check;
        }

        $settings = \RealTreasury\Inventory\Settings::get_settings();
        if ( ! empty( $settings['read_only_mode'] ) ) {
            return new WP_Error( 
                'read_only', 
                __( 'Read-only mode enabled', 'personal-inventory-tracker' ), 
                array( 'status' => 403 ) 
            );
        }

        if ( ! current_user_can( 'manage_inventory_items' ) ) {
            return new WP_Error( 
                'rest_forbidden', 
                __( 'Sorry, you are not allowed to manage inventory items.', 'personal-inventory-tracker' ), 
                array( 'status' => rest_authorization_required_code() ) 
            );
        }

        return true;
    }

    /**
     * Get inventory items with enhanced filtering and pagination.
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response containing filtered items.
     */
    public function get_items( $request ) {
        $search    = $request->get_param( 'search' );
        $category  = $request->get_param( 'category' );
        $status    = $request->get_param( 'status' );
        $sort      = $request->get_param( 'sort' );
        $order     = $request->get_param( 'order' );
        $per_page  = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
        $page      = max( 1, (int) $request->get_param( 'page' ) );

        $args = [
            'post_type'      => 'pit_item',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
        ];

        // Search functionality
        if ( $search ) {
            $args['s'] = sanitize_text_field( $search );
        }

        // Category filter
        if ( $category ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'pit_category',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field( $category ),
                ],
            ];
        }

        // Status filter
        if ( $status ) {
            switch ( $status ) {
                case 'low-stock':
                    $args['meta_query'] = [
                        [
                            'key'     => 'pit_qty',
                            'value'   => 5,
                            'compare' => '<=',
                        ],
                    ];
                    break;
                case 'out-of-stock':
                    $args['meta_query'] = [
                        [
                            'key'     => 'pit_qty',
                            'value'   => 0,
                            'compare' => '=',
                        ],
                    ];
                    break;
            }
        }

        // Sorting
        if ( $sort ) {
            switch ( $sort ) {
                case 'title':
                    $args['orderby'] = 'title';
                    break;
                case 'date':
                    $args['orderby'] = 'date';
                    break;
                case 'quantity':
                    $args['orderby']  = 'meta_value_num';
                    $args['meta_key'] = 'pit_qty';
                    break;
            }
            $args['order'] = ( 'desc' === $order ) ? 'DESC' : 'ASC';
        }

        $query = new WP_Query( $args );
        $items = [];

        foreach ( $query->posts as $post ) {
            $items[] = $this->prepare_item( $post );
        }

        $response = rest_ensure_response( $items );

        // Add pagination headers
        $response->header( 'X-WP-Total', $query->found_posts );
        $response->header( 'X-WP-TotalPages', $query->max_num_pages );

        return $response;
    }

    /**
     * Create a new inventory item.
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response with created item or error.
     */
    public function create_item( $request ) {
        $title     = sanitize_text_field( $request->get_param( 'title' ) );
        $qty       = absint( $request->get_param( 'qty' ) );
        $category  = sanitize_text_field( $request->get_param( 'category' ) );
        $unit      = sanitize_text_field( $request->get_param( 'unit' ) );
        $threshold = absint( $request->get_param( 'threshold' ) );
        $notes     = sanitize_textarea_field( $request->get_param( 'notes' ) );

        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', 'Item title is required', [ 'status' => 400 ] );
        }

        $post_id = wp_insert_post(
            [
                'post_type'   => 'pit_item',
                'post_title'  => $title,
                'post_status' => 'publish',
            ]
        );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Update metadata
        update_post_meta( $post_id, 'pit_qty', $qty );
        update_post_meta( $post_id, 'pit_unit', $unit );
        update_post_meta( $post_id, 'pit_threshold', $threshold );
        update_post_meta( $post_id, 'pit_notes', $notes );
        update_post_meta( $post_id, 'pit_created_via', 'frontend' );

        // Set category
        if ( $category ) {
            $term = get_term_by( 'slug', $category, 'pit_category' );
            if ( $term ) {
                wp_set_post_terms( $post_id, [ $term->term_id ], 'pit_category' );
            }
        }

        return rest_ensure_response( $this->prepare_item( get_post( $post_id ) ) );
    }

    /**
     * Update an existing inventory item.
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response with updated item or error.
     */
    public function update_item( $request ) {
        $post_id = absint( $request->get_param( 'id' ) );

        if ( ! get_post( $post_id ) || 'pit_item' !== get_post_type( $post_id ) ) {
            return new WP_Error( 'item_not_found', 'Item not found', [ 'status' => 404 ] );
        }

        if ( $request->has_param( 'title' ) ) {
            wp_update_post(
                [
                    'ID'         => $post_id,
                    'post_title' => sanitize_text_field( $request->get_param( 'title' ) ),
                ]
            );
        }

        $meta_fields = [ 'qty', 'unit', 'threshold', 'notes', 'last_purchased' ];
        foreach ( $meta_fields as $field ) {
            if ( $request->has_param( $field ) ) {
                $value = $request->get_param( $field );
                if ( in_array( $field, [ 'qty', 'threshold' ], true ) ) {
                    $value = absint( $value );
                } else {
                    $value = sanitize_text_field( $value );
                }
                update_post_meta( $post_id, "pit_{$field}", $value );
            }
        }

        // Handle purchased status
        if ( $request->has_param( 'purchased' ) ) {
            $purchased = (bool) $request->get_param( 'purchased' );
            update_post_meta( $post_id, 'pit_purchased', $purchased );
            if ( $purchased ) {
                update_post_meta( $post_id, 'pit_last_purchased', current_time( 'Y-m-d' ) );
            }
        }

        return rest_ensure_response( $this->prepare_item( get_post( $post_id ) ) );
    }

    /**
     * Delete an inventory item.
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error Response indicating success or error.
     */
    public function delete_item( $request ) {
        $post_id = absint( $request->get_param( 'id' ) );

        if ( ! get_post( $post_id ) || 'pit_item' !== get_post_type( $post_id ) ) {
            return new WP_Error( 'item_not_found', 'Item not found', [ 'status' => 404 ] );
        }

        $deleted = wp_trash_post( $post_id );

        if ( ! $deleted ) {
            return new WP_Error( 'delete_failed', 'Failed to delete item', [ 'status' => 500 ] );
        }

        return rest_ensure_response( [ 'deleted' => true ] );
    }

    /**
     * Get comprehensive analytics data.
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response containing analytics data.
     */
    public function get_analytics( $request ) {
        $range     = absint( $request->get_param( 'range' ) ) ?: 30;
        $cache_key = 'pit_analytics_' . $range;

        $analytics = PIT_Cache::get_or_set(
            $cache_key,
            function () use ( $range ) {
                $cutoff = strtotime( '-' . $range . ' days' );
                $items  = get_posts(
                    [
                        'post_type'      => 'pit_item',
                        'posts_per_page' => -1,
                        'post_status'    => 'publish',
                    ]
                );

                $analytics = [
                    'items'               => [],
                    'purchase_trends'     => [],
                    'total_items'         => count( $items ),
                    'total_quantity'      => 0,
                    'low_stock_count'     => 0,
                    'out_of_stock_count'  => 0,
                    'categories'          => [],
                    'stock_levels'        => [
                        'out_of_stock'  => 0,
                        'low_stock'     => 0,
                        'medium_stock'  => 0,
                        'high_stock'    => 0,
                    ],
                    'recent_purchases'    => [],
                    'top_categories'      => [],
                ];

                $trends = [];

                foreach ( $items as $item ) {
                    $qty                          = absint( get_post_meta( $item->ID, 'pit_qty', true ) );
                    $analytics['total_quantity'] += $qty;
                    $analytics['items'][]         = $this->prepare_item( $item );

                    // Stock level categorization
                    if ( 0 === $qty ) {
                        $analytics['out_of_stock_count']++;
                        $analytics['stock_levels']['out_of_stock']++;
                    } elseif ( $qty <= 5 ) {
                        $analytics['low_stock_count']++;
                        $analytics['stock_levels']['low_stock']++;
                    } elseif ( $qty <= 20 ) {
                        $analytics['stock_levels']['medium_stock']++;
                    } else {
                        $analytics['stock_levels']['high_stock']++;
                    }

                    // Category breakdown
                    $categories = wp_get_post_terms( $item->ID, 'pit_category', [ 'fields' => 'names' ] );
                    foreach ( $categories as $category ) {
                        if ( ! isset( $analytics['categories'][ $category ] ) ) {
                            $analytics['categories'][ $category ] = [ 'count' => 0, 'quantity' => 0 ];
                        }
                        $analytics['categories'][ $category ]['count']++;
                        $analytics['categories'][ $category ]['quantity'] += $qty;
                    }

                    // Recent purchases
                    $purchased      = get_post_meta( $item->ID, 'pit_purchased', true );
                    $last_purchased = get_post_meta( $item->ID, 'pit_last_purchased', true );

                    if ( $purchased && $last_purchased && strtotime( $last_purchased ) >= $cutoff ) {
                        $analytics['recent_purchases'][] = [
                            'id'       => $item->ID,
                            'title'    => $item->post_title,
                            'date'     => $last_purchased,
                            'quantity' => $qty,
                        ];

                        $date_key = gmdate( 'Y-m-d', strtotime( $last_purchased ) );
                        if ( ! isset( $trends[ $date_key ] ) ) {
                            $trends[ $date_key ] = 0;
                        }
                        $trends[ $date_key ] += $qty;
                    }
                }

                foreach ( $trends as $date => $quantity ) {
                    $analytics['purchase_trends'][] = [
                        'date'     => $date,
                        'quantity' => $quantity,
                    ];
                }

                // Sort recent purchases by date
                usort(
                    $analytics['recent_purchases'],
                    function ( $a, $b ) {
                        return strtotime( $b['date'] ) - strtotime( $a['date'] );
                    }
                );
                $analytics['recent_purchases'] = array_slice( $analytics['recent_purchases'], 0, 10 );

                // Top categories
                arsort( $analytics['categories'] );
                $analytics['top_categories'] = array_slice( $analytics['categories'], 0, 5, true );

                return $analytics;
            }
        );

        return rest_ensure_response( $analytics );
    }

    /**
     * Get shopping list based on low stock and thresholds.
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response containing shopping list data.
     */
    public function get_shopping_list( $request ) {
        $data = PIT_Cache::get_or_set(
            'pit_shopping_list',
            function () {
                $items = get_posts(
                    [
                        'post_type'      => 'pit_item',
                        'posts_per_page' => -1,
                        'post_status'    => 'publish',
                    ]
                );

                $shopping_list = [];

                foreach ( $items as $item ) {
                    $qty       = absint( get_post_meta( $item->ID, 'pit_qty', true ) );
                    $threshold = absint( get_post_meta( $item->ID, 'pit_threshold', true ) );
                    $purchased = get_post_meta( $item->ID, 'pit_purchased', true );

                    // Add to shopping list if below threshold or marked as needed
                    if ( ( $threshold > 0 && $qty <= $threshold ) || ( ! $purchased && 0 === $qty ) ) {
                        $categories = wp_get_post_terms( $item->ID, 'pit_category', [ 'fields' => 'names' ] );

                        $shopping_list[] = [
                            'id'             => $item->ID,
                            'title'          => $item->post_title,
                            'current_qty'    => $qty,
                            'threshold'      => $threshold,
                            'category'       => $categories ? $categories[0] : 'Uncategorized',
                            'priority'       => 0 === $qty ? 'high' : 'medium',
                            'estimated_cost' => get_post_meta( $item->ID, 'pit_estimated_cost', true ) ?: 0,
                        ];
                    }
                }

                // Sort by priority and category
                usort(
                    $shopping_list,
                    function ( $a, $b ) {
                        if ( $a['priority'] !== $b['priority'] ) {
                            return 'high' === $a['priority'] ? -1 : 1;
                        }
                        return strcmp( $a['category'], $b['category'] );
                    }
                );

                return [
                    'items'           => $shopping_list,
                    'total_items'     => count( $shopping_list ),
                    'estimated_total' => array_sum( array_column( $shopping_list, 'estimated_cost' ) ),
                ];
            }
        );

        return rest_ensure_response( $data );
    }

    /**
     * Process OCR receipt scanning results.
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response containing processed OCR results.
     */
    public function process_ocr_results( $request ) {
        $results  = $request->get_param( 'results' );
        $auto_add = $request->get_param( 'auto_add' );

        if ( ! is_array( $results ) ) {
            return new WP_Error( 'invalid_results', 'Invalid OCR results', [ 'status' => 400 ] );
        }

        $processed = [];
        $added     = [];

        foreach ( $results as $result ) {
            $text       = sanitize_text_field( $result['text'] );
            $confidence = absint( $result['confidence'] );
            $quantity   = absint( $result['quantity'] ?? 1 );

            // Skip low confidence or empty results
            if ( $confidence < 30 || empty( $text ) || strlen( $text ) < 2 ) {
                continue;
            }

            // Try to match with existing items
            $existing = $this->find_similar_item( $text );

            $processed[] = [
                'text'             => $text,
                'confidence'       => $confidence,
                'quantity'         => $quantity,
                'existing_match'   => $existing,
                'suggested_action' => $existing ? 'update' : 'create',
            ];

            // Auto-add if enabled
            if ( $auto_add && $confidence >= 60 ) {
                if ( $existing ) {
                    // Update existing item quantity
                    $current_qty = absint( get_post_meta( $existing['id'], 'pit_qty', true ) );
                    update_post_meta( $existing['id'], 'pit_qty', $current_qty + $quantity );
                    $added[] = [ 'action' => 'updated', 'item' => $existing ];
                } else {
                    // Create new item
                    $post_id = wp_insert_post(
                        [
                            'post_type'   => 'pit_item',
                            'post_title'  => $text,
                            'post_status' => 'publish',
                        ]
                    );

                    if ( ! is_wp_error( $post_id ) ) {
                        update_post_meta( $post_id, 'pit_qty', $quantity );
                        update_post_meta( $post_id, 'pit_purchased', true );
                        update_post_meta( $post_id, 'pit_last_purchased', current_time( 'Y-m-d' ) );
                        update_post_meta( $post_id, 'pit_created_via', 'ocr' );

                        $added[] = [
                            'action' => 'created',
                            'item'   => $this->prepare_item( get_post( $post_id ) ),
                        ];
                    }
                }
            }
        }

        return rest_ensure_response(
            [
                'processed'       => $processed,
                'auto_added'      => $added,
                'total_processed' => count( $processed ),
            ]
        );
    }

    /**
     * Get available inventory categories.
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response containing categories data.
     */
    public function get_categories( $request ) {
        $categories = get_terms(
            [
                'taxonomy'   => 'pit_category',
                'hide_empty' => false,
            ]
        );

        $formatted = [];
        foreach ( $categories as $category ) {
            $formatted[] = [
                'id'    => $category->term_id,
                'name'  => $category->name,
                'slug'  => $category->slug,
                'count' => $category->count,
            ];
        }

        return rest_ensure_response( $formatted );
    }

    /**
     * Prepare item data for API response.
     *
     * @since 2.0.0
     *
     * @param WP_Post $post The post object.
     * @return array|null Formatted item data or null if invalid post.
     */
    private function prepare_item( $post ) {
        if ( ! $post ) {
            return null;
        }

        $categories = wp_get_post_terms( $post->ID, 'pit_category', [ 'fields' => 'names' ] );

        return [
            'id'             => $post->ID,
            'title'          => $post->post_title,
            'qty'            => absint( get_post_meta( $post->ID, 'pit_qty', true ) ),
            'unit'           => get_post_meta( $post->ID, 'pit_unit', true ),
            'threshold'      => absint( get_post_meta( $post->ID, 'pit_threshold', true ) ),
            'purchased'      => (bool) get_post_meta( $post->ID, 'pit_purchased', true ),
            'last_purchased' => get_post_meta( $post->ID, 'pit_last_purchased', true ),
            'notes'          => get_post_meta( $post->ID, 'pit_notes', true ),
            'category'       => $categories ? $categories[0] : null,
            'created_at'     => $post->post_date,
            'updated_at'     => $post->post_modified,
            'status'         => $this->get_item_status( $post->ID ),
        ];
    }

    /**
     * Get item status based on quantity and threshold.
     *
     * @since 2.0.0
     *
     * @param int $post_id The post ID.
     * @return string The item status.
     */
    private function get_item_status( $post_id ) {
        $qty       = absint( get_post_meta( $post_id, 'pit_qty', true ) );
        $threshold = absint( get_post_meta( $post_id, 'pit_threshold', true ) );

        if ( 0 === $qty ) {
            return 'out-of-stock';
        }
        if ( $threshold > 0 && $qty <= $threshold ) {
            return 'low-stock';
        }
        return 'in-stock';
    }

    /**
     * Find similar items for OCR matching.
     *
     * @since 2.0.0
     *
     * @param string $text The text to match against.
     * @return array|null Matched item data or null if no match.
     */
    private function find_similar_item( $text ) {
        $existing_items = get_posts(
            [
                'post_type'      => 'pit_item',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
            ]
        );

        $normalized_text = strtolower( trim( $text ) );

        foreach ( $existing_items as $item ) {
            $item_title = strtolower( trim( $item->post_title ) );

            // Check for exact match or contains
            if ( $item_title === $normalized_text ||
                 false !== strpos( $item_title, $normalized_text ) ||
                 false !== strpos( $normalized_text, $item_title ) ) {

                return $this->prepare_item( $item );
            }
        }

        return null;
    }

    /**
     * Get purchase trends for analytics.
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response containing trends data.
     */
    public function get_trends( $request ) {
        // This could be expanded with more specific trend analysis
        $analytics = $this->get_analytics( $request );
        return rest_ensure_response( $analytics->data['purchase_trends'] ?? [] );
    }

    /**
     * Batch update multiple inventory items.
     *
     * @since 2.0.0
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response Response containing batch operation results.
     */
    public function batch_update_items( $request ) {
        $updates = $request->get_param( 'items' );
        $results = [];
        $errors  = [];

        if ( is_array( $updates ) ) {
            foreach ( $updates as $update ) {
                $id = isset( $update['id'] ) ? (int) $update['id'] : 0;
                if ( ! $id ) {
                    $errors[] = __( 'Invalid item ID provided.', 'personal-inventory-tracker' );
                    continue;
                }

                // Verify the post exists and user can edit it
                $post = get_post( $id );
                if ( ! $post || 'pit_item' !== $post->post_type ) {
                    $errors[] = sprintf( 
                        __( 'Item with ID %d not found.', 'personal-inventory-tracker' ), 
                        $id 
                    );
                    continue;
                }

                // Update item fields
                if ( isset( $update['qty'] ) ) {
                    update_post_meta( $id, 'pit_qty', absint( $update['qty'] ) );
                }

                if ( isset( $update['purchased'] ) ) {
                    update_post_meta( $id, 'pit_purchased', ! empty( $update['purchased'] ) );
                }

                $results[] = $this->prepare_item( get_post( $id ) );
            }
        }

        return rest_ensure_response(
            [
                'items'  => $results,
                'errors' => $errors,
            ]
        );
    }
}