<?php
/**
 * Plugin Name:       Personal Inventory Tracker Pro
 * Description:       Advanced personal inventory management with modern dashboard, analytics, OCR scanning, and more.
 * Version:           2.0.0
 * Author:            Enhanced by Claude
 * License:           GPL-2.0+
 * Text Domain:       personal-inventory-tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PIT_PLUGIN_FILE', __FILE__ );
define( 'PIT_PLUGIN_DIR', plugin_dir_path( PIT_PLUGIN_FILE ) );
define( 'PIT_PLUGIN_URL', plugin_dir_url( PIT_PLUGIN_FILE ) );
define( 'PIT_PLUGIN_BASENAME', plugin_basename( PIT_PLUGIN_FILE ) );
define( 'PIT_VERSION', '2.0.0' );

// Autoloader
require_once PIT_PLUGIN_DIR . 'vendor/autoload.php';
require_once PIT_PLUGIN_DIR . 'pit-functions.php';
require_once PIT_PLUGIN_DIR . 'includes/class-pit-cache.php';
require_once PIT_PLUGIN_DIR . 'includes/class-pit-blocks.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'pit inventory', 'RealTreasury\Inventory\CLI\Inventory_Command' );
    WP_CLI::add_command( 'pit cache', 'RealTreasury\Inventory\CLI\Cache_Command' );
}

// Enhanced REST API Class
class PIT_Enhanced_REST {
    
    public function register_routes() {
        // Items endpoints
        register_rest_route('pit/v2', '/items', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'permissions_read'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'permissions_write'],
            ]
        ]);

        register_rest_route('pit/v2', '/items/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'permissions_write'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'permissions_write'],
            ]
        ]);

        // Analytics endpoints
        register_rest_route('pit/v2', '/analytics', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_analytics'],
            'permission_callback' => [$this, 'permissions_read'],
        ]);

        register_rest_route('pit/v2', '/analytics/trends', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_trends'],
            'permission_callback' => [$this, 'permissions_read'],
        ]);

        // Shopping list endpoint
        register_rest_route('pit/v2', '/shopping-list', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_shopping_list'],
            'permission_callback' => [$this, 'permissions_read'],
        ]);

        // Bulk operations
        register_rest_route('pit/v2', '/items/batch', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'batch_update_items'],
            'permission_callback' => [$this, 'permissions_write'],
        ]);

        // Import/Export endpoints
        register_rest_route('pit/v2', '/export', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'export_data'],
            'permission_callback' => [$this, 'permissions_read'],
        ]);

        register_rest_route('pit/v2', '/import', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'import_data'],
            'permission_callback' => [$this, 'permissions_write'],
        ]);

        // OCR processing endpoint
        register_rest_route('pit/v2', '/ocr/process', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'process_ocr_results'],
            'permission_callback' => [$this, 'permissions_write'],
        ]);

        // Categories endpoint
        register_rest_route('pit/v2', '/categories', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_categories'],
            'permission_callback' => [$this, 'permissions_read'],
        ]);
    }

    // Permission callbacks
    public function permissions_read($request) {
        // Allow logged-in users or public if enabled
        $public_access = get_option('pit_public_access', false);
        return $public_access || current_user_can('view_inventory');
    }

    private function verify_nonce( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid or missing nonce.', 'personal-inventory-tracker' ), array( 'status' => 403 ) );
        }

        return true;
    }

    public function permissions_write( $request ) {
        $nonce_check = $this->verify_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) {
            return $nonce_check;
        }

        if ( get_option( 'pit_read_only_mode', false ) ) {
            return new WP_Error( 'read_only', __( 'Read-only mode enabled', 'personal-inventory-tracker' ), array( 'status' => 403 ) );
        }

        if ( ! current_user_can( 'manage_inventory_items' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to manage inventory items.', 'personal-inventory-tracker' ), array( 'status' => rest_authorization_required_code() ) );
        }

        return true;
    }

    // Get items with enhanced filtering
    public function get_items($request) {
        $search = $request->get_param('search');
        $category = $request->get_param('category');
        $status = $request->get_param('status');
        $sort = $request->get_param('sort');
        $order = $request->get_param('order');
        $per_page = min(100, max(1, (int) $request->get_param('per_page')));
        $page = max(1, (int) $request->get_param('page'));

        $args = [
            'post_type' => 'pit_item',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
        ];

        // Search functionality
        if ($search) {
            $args['s'] = sanitize_text_field($search);
        }

        // Category filter
        if ($category) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'pit_category',
                    'field' => 'slug',
                    'terms' => sanitize_text_field($category),
                ]
            ];
        }

        // Status filter
        if ($status) {
            switch ($status) {
                case 'low-stock':
                    $args['meta_query'] = [
                        [
                            'key' => 'pit_qty',
                            'value' => 5,
                            'compare' => '<='
                        ]
                    ];
                    break;
                case 'out-of-stock':
                    $args['meta_query'] = [
                        [
                            'key' => 'pit_qty',
                            'value' => 0,
                            'compare' => '='
                        ]
                    ];
                    break;
            }
        }

        // Sorting
        if ($sort) {
            switch ($sort) {
                case 'title':
                    $args['orderby'] = 'title';
                    break;
                case 'date':
                    $args['orderby'] = 'date';
                    break;
                case 'quantity':
                    $args['orderby'] = 'meta_value_num';
                    $args['meta_key'] = 'pit_qty';
                    break;
            }
            $args['order'] = ($order === 'desc') ? 'DESC' : 'ASC';
        }

        $query = new WP_Query($args);
        $items = [];

        foreach ($query->posts as $post) {
            $items[] = $this->prepare_item($post);
        }

        $response = rest_ensure_response($items);
        
        // Add pagination headers
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);

        return $response;
    }

    // Create item
    public function create_item($request) {
        $title = sanitize_text_field($request->get_param('title'));
        $qty = absint($request->get_param('qty'));
        $category = sanitize_text_field($request->get_param('category'));
        $unit = sanitize_text_field($request->get_param('unit'));
        $threshold = absint($request->get_param('threshold'));
        $notes = sanitize_textarea_field($request->get_param('notes'));

        if (empty($title)) {
            return new WP_Error('missing_title', 'Item title is required', ['status' => 400]);
        }

        $post_id = wp_insert_post([
            'post_type' => 'pit_item',
            'post_title' => $title,
            'post_status' => 'publish',
        ]);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Update metadata
        update_post_meta($post_id, 'pit_qty', $qty);
        update_post_meta($post_id, 'pit_unit', $unit);
        update_post_meta($post_id, 'pit_threshold', $threshold);
        update_post_meta($post_id, 'pit_notes', $notes);
        update_post_meta($post_id, 'pit_created_via', 'frontend');

        // Set category
        if ($category) {
            $term = get_term_by('slug', $category, 'pit_category');
            if ($term) {
                wp_set_post_terms($post_id, [$term->term_id], 'pit_category');
            }
        }

        return rest_ensure_response($this->prepare_item(get_post($post_id)));
    }

    // Update item
    public function update_item($request) {
        $post_id = absint($request->get_param('id'));
        
        if (!get_post($post_id) || get_post_type($post_id) !== 'pit_item') {
            return new WP_Error('item_not_found', 'Item not found', ['status' => 404]);
        }

        $updates = [];
        
        if ($request->has_param('title')) {
            wp_update_post([
                'ID' => $post_id,
                'post_title' => sanitize_text_field($request->get_param('title'))
            ]);
        }

        $meta_fields = ['qty', 'unit', 'threshold', 'notes', 'last_purchased'];
        foreach ($meta_fields as $field) {
            if ($request->has_param($field)) {
                $value = $request->get_param($field);
                if (in_array($field, ['qty', 'threshold'])) {
                    $value = absint($value);
                } else {
                    $value = sanitize_text_field($value);
                }
                update_post_meta($post_id, "pit_{$field}", $value);
            }
        }

        // Handle purchased status
        if ($request->has_param('purchased')) {
            $purchased = (bool) $request->get_param('purchased');
            update_post_meta($post_id, 'pit_purchased', $purchased);
            if ($purchased) {
                update_post_meta($post_id, 'pit_last_purchased', current_time('Y-m-d'));
            }
        }

        return rest_ensure_response($this->prepare_item(get_post($post_id)));
    }

    // Delete item
    public function delete_item($request) {
        $post_id = absint($request->get_param('id'));
        
        if (!get_post($post_id) || get_post_type($post_id) !== 'pit_item') {
            return new WP_Error('item_not_found', 'Item not found', ['status' => 404]);
        }

        $deleted = wp_trash_post($post_id);
        
        if (!$deleted) {
            return new WP_Error('delete_failed', 'Failed to delete item', ['status' => 500]);
        }

        return rest_ensure_response(['deleted' => true]);
    }

    // Get analytics data
    public function get_analytics( $request ) {
        $range     = absint( $request->get_param( 'range' ) ) ?: 30;
        $cache_key = 'pit_analytics_' . $range;

        $analytics = PIT_Cache::get_or_set(
            $cache_key,
            function() use ( $range ) {
                $cutoff = strtotime( '-' . $range . ' days' );
                $items  = get_posts(
                    [
                        'post_type'      => 'pit_item',
                        'posts_per_page' => -1,
                        'post_status'    => 'publish',
                    ]
                );

                $analytics = [
                    'items'             => [],
                    'purchase_trends'   => [],
                    'total_items'       => count( $items ),
                    'total_quantity'    => 0,
                    'low_stock_count'   => 0,
                    'out_of_stock_count'=> 0,
                    'categories'        => [],
                    'stock_levels'      => [
                        'out_of_stock' => 0,
                        'low_stock'    => 0,
                        'medium_stock' => 0,
                        'high_stock'   => 0,
                    ],
                    'recent_purchases'  => [],
                    'top_categories'    => [],
                ];

                $trends = [];

                foreach ( $items as $item ) {
                    $qty                          = absint( get_post_meta( $item->ID, 'pit_qty', true ) );
                    $analytics['total_quantity']  += $qty;
                    $analytics['items'][]         = $this->prepare_item( $item );

                    // Stock level categorization.
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

                    // Category breakdown.
                    $categories = wp_get_post_terms( $item->ID, 'pit_category', [ 'fields' => 'names' ] );
                    foreach ( $categories as $category ) {
                        if ( ! isset( $analytics['categories'][ $category ] ) ) {
                            $analytics['categories'][ $category ] = [ 'count' => 0, 'quantity' => 0 ];
                        }
                        $analytics['categories'][ $category ]['count']++;
                        $analytics['categories'][ $category ]['quantity'] += $qty;
                    }

                    // Recent purchases.
                    $purchased      = get_post_meta( $item->ID, 'pit_purchased', true );
                    $last_purchased = get_post_meta( $item->ID, 'pit_last_purchased', true );

                    if ( $purchased && $last_purchased && strtotime( $last_purchased ) >= $cutoff ) {
                        $analytics['recent_purchases'][] = [
                            'id'       => $item->ID,
                            'title'    => $item->post_title,
                            'date'     => $last_purchased,
                            'quantity' => $qty,
                        ];

                        $date_key = date( 'Y-m-d', strtotime( $last_purchased ) );
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

                // Sort recent purchases by date.
                usort(
                    $analytics['recent_purchases'],
                    function( $a, $b ) {
                        return strtotime( $b['date'] ) - strtotime( $a['date'] );
                    }
                );
                $analytics['recent_purchases'] = array_slice( $analytics['recent_purchases'], 0, 10 );

                // Top categories.
                arsort( $analytics['categories'] );
                $analytics['top_categories'] = array_slice( $analytics['categories'], 0, 5, true );

                return $analytics;
            }
        );

        return rest_ensure_response( $analytics );
    }

    // Get shopping list
    public function get_shopping_list( $request ) {
        $data = PIT_Cache::get_or_set(
            'pit_shopping_list',
            function() {
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

                    // Add to shopping list if below threshold or marked as needed.
                    if ( ( $threshold > 0 && $qty <= $threshold ) || ( ! $purchased && 0 === $qty ) ) {
                        $categories = wp_get_post_terms( $item->ID, 'pit_category', [ 'fields' => 'names' ] );

                        $shopping_list[] = [
                            'id'            => $item->ID,
                            'title'         => $item->post_title,
                            'current_qty'   => $qty,
                            'threshold'     => $threshold,
                            'category'      => $categories ? $categories[0] : 'Uncategorized',
                            'priority'      => 0 === $qty ? 'high' : 'medium',
                            'estimated_cost'=> get_post_meta( $item->ID, 'pit_estimated_cost', true ) ?: 0,
                        ];
                    }
                }

                // Sort by priority and category.
                usort(
                    $shopping_list,
                    function( $a, $b ) {
                        if ( $a['priority'] !== $b['priority'] ) {
                            return 'high' === $a['priority'] ? -1 : 1;
                        }
                        return strcmp( $a['category'], $b['category'] );
                    }
                );

                return [
                    'items'          => $shopping_list,
                    'total_items'    => count( $shopping_list ),
                    'estimated_total'=> array_sum( array_column( $shopping_list, 'estimated_cost' ) ),
                ];
            }
        );

        return rest_ensure_response( $data );
    }

    // Process OCR results
    public function process_ocr_results($request) {
        $results = $request->get_param('results');
        $auto_add = $request->get_param('auto_add');

        if (!is_array($results)) {
            return new WP_Error('invalid_results', 'Invalid OCR results', ['status' => 400]);
        }

        $processed = [];
        $added = [];

        foreach ($results as $result) {
            $text = sanitize_text_field($result['text']);
            $confidence = absint($result['confidence']);
            $quantity = absint($result['quantity'] ?? 1);

            // Skip low confidence or empty results
            if ($confidence < 30 || empty($text) || strlen($text) < 2) {
                continue;
            }

            // Try to match with existing items
            $existing = $this->find_similar_item($text);
            
            $processed[] = [
                'text' => $text,
                'confidence' => $confidence,
                'quantity' => $quantity,
                'existing_match' => $existing,
                'suggested_action' => $existing ? 'update' : 'create'
            ];

            // Auto-add if enabled
            if ($auto_add && $confidence >= 60) {
                if ($existing) {
                    // Update existing item quantity
                    $current_qty = absint(get_post_meta($existing['id'], 'pit_qty', true));
                    update_post_meta($existing['id'], 'pit_qty', $current_qty + $quantity);
                    $added[] = ['action' => 'updated', 'item' => $existing];
                } else {
                    // Create new item
                    $post_id = wp_insert_post([
                        'post_type' => 'pit_item',
                        'post_title' => $text,
                        'post_status' => 'publish',
                    ]);

                    if (!is_wp_error($post_id)) {
                        update_post_meta($post_id, 'pit_qty', $quantity);
                        update_post_meta($post_id, 'pit_purchased', true);
                        update_post_meta($post_id, 'pit_last_purchased', current_time('Y-m-d'));
                        update_post_meta($post_id, 'pit_created_via', 'ocr');

                        $added[] = [
                            'action' => 'created',
                            'item' => $this->prepare_item(get_post($post_id))
                        ];
                    }
                }
            }
        }

        return rest_ensure_response([
            'processed' => $processed,
            'auto_added' => $added,
            'total_processed' => count($processed)
        ]);
    }

    // Get categories
    public function get_categories($request) {
        $categories = get_terms([
            'taxonomy' => 'pit_category',
            'hide_empty' => false,
        ]);

        $formatted = [];
        foreach ($categories as $category) {
            $formatted[] = [
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'count' => $category->count
            ];
        }

        return rest_ensure_response($formatted);
    }

    // Helper function to prepare item data
    private function prepare_item($post) {
        if (!$post) return null;

        $categories = wp_get_post_terms($post->ID, 'pit_category', ['fields' => 'names']);
        
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'qty' => absint(get_post_meta($post->ID, 'pit_qty', true)),
            'unit' => get_post_meta($post->ID, 'pit_unit', true),
            'threshold' => absint(get_post_meta($post->ID, 'pit_threshold', true)),
            'purchased' => (bool) get_post_meta($post->ID, 'pit_purchased', true),
            'last_purchased' => get_post_meta($post->ID, 'pit_last_purchased', true),
            'notes' => get_post_meta($post->ID, 'pit_notes', true),
            'category' => $categories ? $categories[0] : null,
            'created_at' => $post->post_date,
            'updated_at' => $post->post_modified,
            'status' => $this->get_item_status($post->ID)
        ];
    }

    // Helper to get item status
    private function get_item_status($post_id) {
        $qty = absint(get_post_meta($post_id, 'pit_qty', true));
        $threshold = absint(get_post_meta($post_id, 'pit_threshold', true));

        if ($qty === 0) return 'out-of-stock';
        if ($threshold > 0 && $qty <= $threshold) return 'low-stock';
        return 'in-stock';
    }

    // Helper to find similar items
    private function find_similar_item($text) {
        $existing_items = get_posts([
            'post_type' => 'pit_item',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        $normalized_text = strtolower(trim($text));
        
        foreach ($existing_items as $item) {
            $item_title = strtolower(trim($item->post_title));
            
            // Check for exact match or contains
            if ($item_title === $normalized_text || 
                strpos($item_title, $normalized_text) !== false ||
                strpos($normalized_text, $item_title) !== false) {
                
                return $this->prepare_item($item);
            }
        }

        return null;
    }

    // Export data
    public function export_data($request) {
        $format = $request->get_param('format') ?: 'csv';
        $items = get_posts([
            'post_type' => 'pit_item',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        $data = [];
        foreach ($items as $item) {
            $data[] = $this->prepare_item($item);
        }

        if ($format === 'json') {
            return rest_ensure_response($data);
        }

        // CSV format
        $csv_data = $this->array_to_csv($data);
        
        return new WP_REST_Response($csv_data, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="inventory-export-' . date('Y-m-d') . '.csv"'
        ]);
    }

    // Helper to convert array to CSV
    private function array_to_csv($data) {
        if (empty($data)) return '';

        $output = fopen('php://temp', 'w+');
        
        // Header row
        fputcsv($output, array_keys($data[0]));
        
        // Data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}

// Enhanced frontend functionality
function pit_enqueue_enhanced_frontend() {
    if ( ! is_singular() ) {
        return;
    }

    $post = get_post();
    if ( ! $post ) {
        return;
    }

    $content   = $post->post_content;
    $has_app   = has_shortcode( $content, 'pit_enhanced' ) || has_shortcode( $content, 'pit_dashboard' ) || has_shortcode( $content, 'pit_app' );
    $has_ocr   = has_shortcode( $content, 'pit_ocr_scanner' );

    if ( ! $has_app && ! $has_ocr ) {
        return;
    }

    $app_js  = PIT_PLUGIN_DIR . 'assets/app.js';
    $app_css = PIT_PLUGIN_DIR . 'assets/app.css';
    $ocr_js  = PIT_PLUGIN_DIR . 'assets/ocr-scanner.js';

    $script_args = [
        'in_footer' => true,
        'strategy'  => 'defer',
    ];


    if ( file_exists( $app_css ) ) {
        wp_register_style( 'pit-enhanced', PIT_PLUGIN_URL . 'assets/app.css', [], PIT_VERSION );
        wp_enqueue_style( 'pit-enhanced' );
    }

    if ( file_exists( $app_js ) ) {
        wp_register_script( 'pit-enhanced', PIT_PLUGIN_URL . 'assets/app.js', array(), PIT_VERSION, $script_args );
        wp_enqueue_script( 'pit-enhanced' );
        wp_script_add_data( 'pit-enhanced', 'type', 'module' );

        wp_localize_script( 'pit-enhanced', 'pitApp', [
            'restUrl'   => rest_url( 'pit/v2/' ),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'currentUser' => get_current_user_id(),
            'userCan'   => [
                'edit'   => current_user_can( 'manage_inventory_items' ),
                'delete' => current_user_can( 'manage_inventory_items' ),
                'manage' => current_user_can( 'manage_inventory_settings' ),
            ],
            'settings' => [
                'publicAccess'    => get_option( 'pit_public_access', false ),
                'readOnlyMode'    => get_option( 'pit_read_only_mode', false ),
                'defaultConfidence' => get_option( 'pit_ocr_confidence', 60 ),
                'currency'        => get_option( 'pit_currency', '$' ),
            ],
            'i18n' => [
                'dashboard'     => __( 'Dashboard', 'personal-inventory-tracker' ),
                'inventory'     => __( 'Inventory', 'personal-inventory-tracker' ),
                'analytics'     => __( 'Analytics', 'personal-inventory-tracker' ),
                'scanner'       => __( 'Scanner', 'personal-inventory-tracker' ),
                'shoppingList'  => __( 'Shopping List', 'personal-inventory-tracker' ),
                'search'        => __( 'Search items...', 'personal-inventory-tracker' ),
                'addItem'       => __( 'Add Item', 'personal-inventory-tracker' ),
                'totalItems'    => __( 'Total Items', 'personal-inventory-tracker' ),
                'lowStock'      => __( 'Low Stock', 'personal-inventory-tracker' ),
                'outOfStock'    => __( 'Out of Stock', 'personal-inventory-tracker' ),
                'recentPurchases' => __( 'Recent Purchases', 'personal-inventory-tracker' ),
                'exportData'    => __( 'Export Data', 'personal-inventory-tracker' ),
                'importData'    => __( 'Import Data', 'personal-inventory-tracker' ),
                'scanReceipt'   => __( 'Scan Receipt', 'personal-inventory-tracker' ),
                'categories'    => __( 'Categories', 'personal-inventory-tracker' ),
                'confirmDelete' => __( 'Are you sure you want to delete this item?', 'personal-inventory-tracker' ),
                'loading'       => __( 'Loading...', 'personal-inventory-tracker' ),
                'error'         => __( 'An error occurred', 'personal-inventory-tracker' ),
                'success'       => __( 'Operation completed successfully', 'personal-inventory-tracker' ),
            ],
        ] );
    }

    if ( $has_ocr && file_exists( $ocr_js ) ) {
        wp_enqueue_script( 'pit-ocr-scanner', PIT_PLUGIN_URL . 'assets/ocr-scanner.js', [], PIT_VERSION, $script_args );
        wp_script_add_data( 'pit-ocr-scanner', 'type', 'module' );

        $items   = get_posts(
            [
                'post_type'      => 'pit_item',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
            ]
        );
        $choices = [];

        foreach ( $items as $item ) {
            $choices[] = [
                'id'    => $item->ID,
                'title' => $item->post_title,
            ];
        }

        wp_localize_script(
            'pit-ocr-scanner',
            'pitApp',
            [
                'restUrl' => rest_url( 'pit/v2/' ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
                'items'   => $choices,
            ]
        );
    }
}
add_action( 'wp_enqueue_scripts', 'pit_enqueue_enhanced_frontend' );

// OCR scanner shortcode
function pit_ocr_scanner_shortcode() {
    ob_start();
    include PIT_PLUGIN_DIR . 'templates/ocr-scanner.php';
    return ob_get_clean();
}

// Enhanced shortcode
function pit_enhanced_shortcode( $atts = [] ) {
    $atts = shortcode_atts(
        [
            'view'     => 'dashboard',
            'public'   => 'false',
            'readonly' => 'false',
        ],
        $atts
    );

    $quick_add_notice = '';
    $read_only        = get_option( 'pit_read_only_mode', false );

    if (
        isset( $_POST['action'], $_POST['pit_nonce'] ) &&
        'pit_quick_add' === $_POST['action'] &&
        wp_verify_nonce( $_POST['pit_nonce'], 'pit_quick_add' ) &&
        current_user_can( 'manage_inventory_items' ) &&
        ! $read_only
    ) {
        $item_name = sanitize_text_field( wp_unslash( $_POST['item_name'] ) );
        $quantity  = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 0;

        if ( $item_name ) {
            $post_id = wp_insert_post(
                [
                    'post_type'   => 'pit_item',
                    'post_title'  => $item_name,
                    'post_status' => 'publish',
                ]
            );

            if ( ! is_wp_error( $post_id ) ) {
                update_post_meta( $post_id, 'pit_qty', $quantity );
                update_post_meta( $post_id, 'pit_created_via', 'fallback' );

                $quick_add_notice = '<div style="background: #d1fae5; border: 1px solid #a7f3d0; color: #065f46; padding: 1rem; border-radius: 8px; margin: 1rem 0;">' .
                    esc_html(
                        sprintf(
                            __( 'Successfully added "%s" with quantity %d', 'personal-inventory-tracker' ),
                            $item_name,
                            $quantity
                        )
                    ) .
                    '</div>';
            }
        }
    }

    $can_edit   = current_user_can( 'manage_inventory_items' );
    $can_manage = current_user_can( 'manage_inventory_settings' );
    $settings   = [
        'publicAccess'      => get_option( 'pit_public_access', false ),
        'readOnlyMode'      => $read_only,
        'defaultConfidence' => get_option( 'pit_ocr_confidence', 60 ),
        'currency'          => get_option( 'pit_currency', '$' ),
        'dateFormat'        => get_option( 'date_format' ),
        'timeFormat'        => get_option( 'time_format' ),
    ];
    $has_access = $settings['publicAccess'] || is_user_logged_in();

    $recent_items = get_posts(
        [
            'post_type'      => 'pit_item',
            'posts_per_page' => 10,
            'post_status'    => 'publish',
        ]
    );

    $view = sanitize_key( $atts['view'] );
    $inventory_app_props = [
        'view'      => $view,
        'canEdit'   => $can_edit,
        'canManage' => $can_manage,
        'readOnly'  => $read_only,
        'settings'  => $settings,
    ];

    ob_start();
    include PIT_PLUGIN_DIR . 'templates/frontend-app.php';
    return ob_get_clean();
}

// Initialize enhanced functionality
function pit_init_enhanced() {
    // Register enhanced REST API
    $rest_api = new PIT_Enhanced_REST();
    add_action('rest_api_init', [$rest_api, 'register_routes']);
    
    // Register shortcode
    add_shortcode('pit_enhanced', 'pit_enhanced_shortcode');
    add_shortcode('pit_dashboard', 'pit_enhanced_shortcode');
    add_shortcode('pit_ocr_scanner', 'pit_ocr_scanner_shortcode');

    // Backward compatibility
    add_shortcode('pit_app', 'pit_enhanced_shortcode');
}

// Activation/Deactivation hooks
function pit_activate() {
    \RealTreasury\Inventory\CPT::activate();
    \RealTreasury\Inventory\Taxonomy::activate();
    \RealTreasury\Inventory\Cron::activate();
    \RealTreasury\Inventory\Settings::activate();
    \RealTreasury\Inventory\Capabilities::add_capabilities();
    
    // Add enhanced options
    add_option('pit_public_access', false);
    add_option('pit_read_only_mode', false);
    add_option('pit_ocr_confidence', 60);
    add_option('pit_currency', '$');
    add_option('pit_version', PIT_VERSION);

    flush_rewrite_rules();
}

function pit_deactivate() {
    \RealTreasury\Inventory\CPT::deactivate();
    \RealTreasury\Inventory\Taxonomy::deactivate();
    \RealTreasury\Inventory\Cron::deactivate();
    \RealTreasury\Inventory\Capabilities::remove_capabilities();
    flush_rewrite_rules();
}

// Register hooks
register_activation_hook(PIT_PLUGIN_FILE, 'pit_activate');
register_deactivation_hook(PIT_PLUGIN_FILE, 'pit_deactivate');
register_activation_hook(PIT_PLUGIN_FILE, array( \RealTreasury\Inventory\Database::class, 'migrate' ) );
register_deactivation_hook(PIT_PLUGIN_FILE, array( \RealTreasury\Inventory\Database::class, 'rollback' ) );

// Initialize
add_action('init', [\RealTreasury\Inventory\CPT::class, 'register']);
add_action('init', [\RealTreasury\Inventory\Taxonomy::class, 'register']);
add_action('plugins_loaded', [\RealTreasury\Inventory\Database::class, 'migrate']);
add_action('plugins_loaded', function() {
    pit_init_enhanced();
    ( new \RealTreasury\Inventory\Admin\Admin() )->init();
    \RealTreasury\Inventory\Cron::init();
    \RealTreasury\Inventory\Settings::init();
});

// Admin enqueue (keep existing admin functionality)
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'pit_') !== false) {
        wp_enqueue_style('pit-admin', PIT_PLUGIN_URL . 'assets/admin.css', [], PIT_VERSION);
        wp_enqueue_script('pit-admin', PIT_PLUGIN_URL . 'assets/admin.js', [], PIT_VERSION, true);
    }
});

// Add settings page for enhanced options
add_action( 'admin_init', 'pit_register_enhanced_settings' );
add_action( 'admin_menu', function() {
    add_submenu_page(
        'pit_dashboard',
        __( 'Enhanced Settings', 'personal-inventory-tracker' ),
        __( 'Enhanced Settings', 'personal-inventory-tracker' ),
        'manage_inventory_settings',
        'pit_enhanced_settings',
        'pit_enhanced_settings_page'
    );
} );

function pit_enhanced_settings_page() {
    if ( ! current_user_can( 'manage_inventory_settings' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Enhanced Settings', 'personal-inventory-tracker' ); ?></h1>
        <form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
            <?php
            settings_fields( 'pit_enhanced_settings' );
            do_settings_sections( 'pit_enhanced_settings' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function pit_register_enhanced_settings() {
    register_setting(
        'pit_enhanced_settings',
        'pit_public_access',
        array(
            'type'              => 'boolean',
            'sanitize_callback' => 'pit_sanitize_public_access',
            'default'           => false,
        )
    );

    register_setting(
        'pit_enhanced_settings',
        'pit_read_only_mode',
        array(
            'type'              => 'boolean',
            'sanitize_callback' => 'pit_sanitize_read_only_mode',
            'default'           => false,
        )
    );

    register_setting(
        'pit_enhanced_settings',
        'pit_ocr_confidence',
        array(
            'type'              => 'integer',
            'sanitize_callback' => 'pit_sanitize_ocr_confidence',
            'default'           => 60,
        )
    );

    register_setting(
        'pit_enhanced_settings',
        'pit_currency',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'pit_sanitize_currency',
            'default'           => '$',
        )
    );

    add_settings_section(
        'pit_enhanced_main',
        __( 'Enhanced Settings', 'personal-inventory-tracker' ),
        '__return_null',
        'pit_enhanced_settings'
    );

    add_settings_field(
        'pit_public_access',
        __( 'Public Access', 'personal-inventory-tracker' ),
        'pit_field_public_access',
        'pit_enhanced_settings',
        'pit_enhanced_main'
    );

    add_settings_field(
        'pit_read_only_mode',
        __( 'Read-Only Mode', 'personal-inventory-tracker' ),
        'pit_field_read_only_mode',
        'pit_enhanced_settings',
        'pit_enhanced_main'
    );

    add_settings_field(
        'pit_ocr_confidence',
        __( 'OCR Confidence', 'personal-inventory-tracker' ),
        'pit_field_ocr_confidence',
        'pit_enhanced_settings',
        'pit_enhanced_main'
    );

    add_settings_field(
        'pit_currency',
        __( 'Currency Symbol', 'personal-inventory-tracker' ),
        'pit_field_currency',
        'pit_enhanced_settings',
        'pit_enhanced_main'
    );
}

function pit_field_public_access() {
    $value = get_option( 'pit_public_access', false );
    printf(
        '<label><input type="checkbox" name="pit_public_access" value="1" %s /> %s</label>',
        checked( $value, 1, false ),
        esc_html__( 'Allow non-logged-in users to view inventory', 'personal-inventory-tracker' )
    );
}

function pit_field_read_only_mode() {
    $value = get_option( 'pit_read_only_mode', false );
    printf(
        '<label><input type="checkbox" name="pit_read_only_mode" value="1" %s /> %s</label>',
        checked( $value, 1, false ),
        esc_html__( 'Disable editing for all users', 'personal-inventory-tracker' )
    );
}

function pit_field_ocr_confidence() {
    $value = get_option( 'pit_ocr_confidence', 60 );
    printf(
        '<input type="range" name="pit_ocr_confidence" min="30" max="95" value="%1$d" class="regular-text" oninput="document.getElementById(\'pit-ocr-confidence-value\').textContent = this.value + \'%\';" /> <span id="pit-ocr-confidence-value">%1$d%%</span>',
        intval( $value )
    );
}

function pit_field_currency() {
    $value = get_option( 'pit_currency', '$' );
    printf(
        '<input type="text" name="pit_currency" value="%s" class="regular-text" />',
        esc_attr( $value )
    );
}

function pit_sanitize_public_access( $value ) {
    return current_user_can( 'manage_inventory_settings' ) ? ( $value ? 1 : 0 ) : get_option( 'pit_public_access', false );
}

function pit_sanitize_read_only_mode( $value ) {
    return current_user_can( 'manage_inventory_settings' ) ? ( $value ? 1 : 0 ) : get_option( 'pit_read_only_mode', false );
}

function pit_sanitize_ocr_confidence( $value ) {
    if ( ! current_user_can( 'manage_inventory_settings' ) ) {
        return get_option( 'pit_ocr_confidence', 60 );
    }
    $value = absint( $value );
    if ( $value < 30 ) {
        $value = 30;
    }
    if ( $value > 95 ) {
        $value = 95;
    }
    return $value;
}

function pit_sanitize_currency( $value ) {
    if ( ! current_user_can( 'manage_inventory_settings' ) ) {
        return get_option( 'pit_currency', '$' );
    }
    $value = sanitize_text_field( $value );
    return '' === $value ? '$' : $value;
}
