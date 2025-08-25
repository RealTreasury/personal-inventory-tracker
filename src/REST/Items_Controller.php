<?php
/**
 * Items REST API Controller.
 *
 * @package PersonalInventoryTracker
 */

namespace RealTreasury\Inventory\REST;

use RealTreasury\Inventory\Container;
use RealTreasury\Inventory\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API Controller for inventory items.
 */
class Items_Controller extends Rest_Api {

    /**
     * Service container.
     *
     * @var Container
     */
    private $container;

    /**
     * Constructor.
     *
     * @param Container $container Service container.
     */
    public function __construct( Container $container ) {
        $this->container = $container;
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // Items endpoints
        register_rest_route( 'pit/v2', '/items', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_items' ],
                'permission_callback' => [ $this, 'permissions_read' ],
                'args'                => $this->get_collection_params(),
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_item' ],
                'permission_callback' => [ $this, 'permissions_write' ],
                'args'                => $this->get_item_schema(),
            ]
        ] );

        register_rest_route( 'pit/v2', '/items/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_item' ],
                'permission_callback' => [ $this, 'permissions_read' ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_item' ],
                'permission_callback' => [ $this, 'permissions_write' ],
                'args'                => $this->get_item_schema(),
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_item' ],
                'permission_callback' => [ $this, 'permissions_write' ],
            ]
        ] );

        // Analytics endpoints
        register_rest_route( 'pit/v2', '/analytics', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_analytics' ],
            'permission_callback' => [ $this, 'permissions_read' ],
        ] );

        register_rest_route( 'pit/v2', '/analytics/trends', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_trends' ],
            'permission_callback' => [ $this, 'permissions_read' ],
        ] );

        // Shopping list endpoint
        register_rest_route( 'pit/v2', '/shopping-list', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_shopping_list' ],
            'permission_callback' => [ $this, 'permissions_read' ],
        ] );

        // Bulk operations
        register_rest_route( 'pit/v2', '/items/batch', [
            'methods'             => \WP_REST_Server::EDITABLE,
            'callback'            => [ $this, 'batch_update_items' ],
            'permission_callback' => [ $this, 'permissions_write' ],
        ] );

        // OCR processing endpoint
        register_rest_route( 'pit/v2', '/ocr/process', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'process_ocr_results' ],
            'permission_callback' => [ $this, 'permissions_write' ],
        ] );

        // Categories endpoint
        register_rest_route( 'pit/v2', '/categories', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_categories' ],
            'permission_callback' => [ $this, 'permissions_read' ],
        ] );
    }

    /**
     * Check read permissions for REST requests.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return true|\WP_Error True if permitted, WP_Error if not.
     */
    public function permissions_read( $request ) {
        $nonce = $this->verify_nonce( $request );
        if ( true !== $nonce ) {
            return $nonce;
        }

        $limit = $this->check_rate_limit( $request );
        if ( true !== $limit ) {
            return $limit;
        }

        // Allow logged-in users or public if enabled
        $settings = Settings::get_settings();
        return ! empty( $settings['public_access'] ) || current_user_can( 'view_inventory' );
    }

    /**
     * Check write permissions for REST requests.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return true|\WP_Error True if permitted, WP_Error if not.
     */
    public function permissions_write( $request ) {
        $nonce = $this->verify_nonce( $request );
        if ( true !== $nonce ) {
            return $nonce;
        }

        $limit = $this->check_rate_limit( $request );
        if ( true !== $limit ) {
            return $limit;
        }

        $settings = Settings::get_settings();
        if ( ! empty( $settings['read_only_mode'] ) ) {
            return new \WP_Error( 'read_only', __( 'Read-only mode enabled', 'personal-inventory-tracker' ), array( 'status' => 403 ) );
        }

        if ( ! current_user_can( 'manage_inventory_items' ) ) {
            return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to manage inventory items.', 'personal-inventory-tracker' ), array( 'status' => rest_authorization_required_code() ) );
        }

        return true;
    }

    /**
     * Get items with enhanced filtering.
     *
     * @param \WP_REST_Request $request The REST request.
     * @return \WP_REST_Response|\WP_Error Response object or error.
     */
    public function get_items( $request ) {
        try {
            $search = $request->get_param( 'search' );
            $category = $request->get_param( 'category' );
            $status = $request->get_param( 'status' );
            $sort = $request->get_param( 'sort' );
            $order = $request->get_param( 'order' );
            $per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
            $page = max( 1, (int) $request->get_param( 'page' ) );

            $args = [
                'post_type'      => 'pit_item',
                'post_status'    => 'publish',
                'posts_per_page' => $per_page,
                'paged'          => $page,
            ];

            // Add search functionality
            if ( ! empty( $search ) ) {
                $args['s'] = sanitize_text_field( $search );
            }

            // Add category filter
            if ( ! empty( $category ) ) {
                $args['tax_query'] = [
                    [
                        'taxonomy' => 'pit_category',
                        'field'    => 'slug',
                        'terms'    => sanitize_title( $category ),
                    ]
                ];
            }

            // Add status filter
            if ( ! empty( $status ) ) {
                $args['meta_query'] = [
                    [
                        'key'   => '_pit_status',
                        'value' => sanitize_text_field( $status ),
                    ]
                ];
            }

            // Add sorting
            if ( ! empty( $sort ) ) {
                switch ( $sort ) {
                    case 'title':
                        $args['orderby'] = 'title';
                        break;
                    case 'date':
                        $args['orderby'] = 'date';
                        break;
                    case 'quantity':
                        $args['orderby'] = 'meta_value_num';
                        $args['meta_key'] = '_pit_qty';
                        break;
                    default:
                        $args['orderby'] = 'date';
                }
                $args['order'] = ( 'desc' === $order ) ? 'DESC' : 'ASC';
            }

            $query = new \WP_Query( $args );
            $items = [];

            foreach ( $query->posts as $post ) {
                $items[] = $this->prepare_item_for_response( $post );
            }

            $response = rest_ensure_response( [
                'success'     => true,
                'data'        => $items,
                'total'       => $query->found_posts,
                'total_pages' => $query->max_num_pages,
                'page'        => $page,
                'per_page'    => $per_page,
            ] );

            return $response;

        } catch ( \Exception $e ) {
            return $this->handle_error( $e );
        }
    }

    /**
     * Prepare item for response.
     *
     * @param \WP_Post $post Post object.
     * @return array Prepared item data.
     */
    private function prepare_item_for_response( $post ) {
        $item = [
            'id'            => $post->ID,
            'title'         => get_the_title( $post ),
            'description'   => $post->post_content,
            'quantity'      => (int) get_post_meta( $post->ID, '_pit_qty', true ),
            'status'        => get_post_meta( $post->ID, '_pit_status', true ),
            'location'      => get_post_meta( $post->ID, '_pit_location', true ),
            'purchase_date' => get_post_meta( $post->ID, '_pit_purchase_date', true ),
            'expiry_date'   => get_post_meta( $post->ID, '_pit_expiry_date', true ),
            'notes'         => get_post_meta( $post->ID, '_pit_notes', true ),
            'created_at'    => $post->post_date,
            'updated_at'    => $post->post_modified,
        ];

        // Get categories
        $categories = wp_get_post_terms( $post->ID, 'pit_category' );
        $item['categories'] = array_map( function( $term ) {
            return [
                'id'   => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            ];
        }, $categories );

        return $item;
    }

    /**
     * Handle API errors with consistent format.
     *
     * @param \Exception $exception The exception.
     * @return \WP_Error Error response.
     */
    private function handle_error( $exception ) {
        // Log error for debugging
        error_log( sprintf( 'PIT API Error: %s in %s:%d', $exception->getMessage(), $exception->getFile(), $exception->getLine() ) );

        return new \WP_Error(
            'pit_api_error',
            __( 'An error occurred while processing your request.', 'personal-inventory-tracker' ),
            [
                'status' => 500,
                'details' => WP_DEBUG ? $exception->getMessage() : null,
            ]
        );
    }

    /**
     * Get collection parameters for items endpoint.
     *
     * @return array Collection parameters.
     */
    private function get_collection_params() {
        return [
            'search'   => [
                'description' => __( 'Search term.', 'personal-inventory-tracker' ),
                'type'        => 'string',
            ],
            'category' => [
                'description' => __( 'Category slug.', 'personal-inventory-tracker' ),
                'type'        => 'string',
            ],
            'status'   => [
                'description' => __( 'Item status.', 'personal-inventory-tracker' ),
                'type'        => 'string',
            ],
            'sort'     => [
                'description' => __( 'Sort field.', 'personal-inventory-tracker' ),
                'type'        => 'string',
                'default'     => 'date',
                'enum'        => [ 'title', 'date', 'quantity' ],
            ],
            'order'    => [
                'description' => __( 'Sort order.', 'personal-inventory-tracker' ),
                'type'        => 'string',
                'default'     => 'desc',
                'enum'        => [ 'asc', 'desc' ],
            ],
            'per_page' => [
                'description' => __( 'Items per page.', 'personal-inventory-tracker' ),
                'type'        => 'integer',
                'default'     => 20,
                'minimum'     => 1,
                'maximum'     => 100,
            ],
            'page'     => [
                'description' => __( 'Page number.', 'personal-inventory-tracker' ),
                'type'        => 'integer',
                'default'     => 1,
                'minimum'     => 1,
            ],
        ];
    }

    /**
     * Placeholder methods for other endpoints (to be implemented).
     */
    public function get_item( $request ) {
        return new \WP_Error( 'not_implemented', __( 'Method not implemented yet.', 'personal-inventory-tracker' ), [ 'status' => 501 ] );
    }

    public function create_item( $request ) {
        return new \WP_Error( 'not_implemented', __( 'Method not implemented yet.', 'personal-inventory-tracker' ), [ 'status' => 501 ] );
    }

    public function update_item( $request ) {
        return new \WP_Error( 'not_implemented', __( 'Method not implemented yet.', 'personal-inventory-tracker' ), [ 'status' => 501 ] );
    }

    public function delete_item( $request ) {
        return new \WP_Error( 'not_implemented', __( 'Method not implemented yet.', 'personal-inventory-tracker' ), [ 'status' => 501 ] );
    }

    public function get_analytics( $request ) {
        return new \WP_Error( 'not_implemented', __( 'Method not implemented yet.', 'personal-inventory-tracker' ), [ 'status' => 501 ] );
    }

    public function get_trends( $request ) {
        return new \WP_Error( 'not_implemented', __( 'Method not implemented yet.', 'personal-inventory-tracker' ), [ 'status' => 501 ] );
    }

    public function get_shopping_list( $request ) {
        return new \WP_Error( 'not_implemented', __( 'Method not implemented yet.', 'personal-inventory-tracker' ), [ 'status' => 501 ] );
    }

    public function batch_update_items( $request ) {
        return new \WP_Error( 'not_implemented', __( 'Method not implemented yet.', 'personal-inventory-tracker' ), [ 'status' => 501 ] );
    }

    public function process_ocr_results( $request ) {
        return new \WP_Error( 'not_implemented', __( 'Method not implemented yet.', 'personal-inventory-tracker' ), [ 'status' => 501 ] );
    }

    public function get_categories( $request ) {
        return new \WP_Error( 'not_implemented', __( 'Method not implemented yet.', 'personal-inventory-tracker' ), [ 'status' => 501 ] );
    }
}