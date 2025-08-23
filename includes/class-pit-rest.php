<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIT_REST {

    /**
     * Register REST API routes for the plugin.
     */
    public function register_routes() {
        // Items collection routes.
        register_rest_route(
            'pit/v1',
            '/items',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_items' ),
                    'permission_callback' => array( $this, 'permissions_read' ),
                    'args'                => array(),
                    'schema'              => array( $this, 'get_item_schema' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_item' ),
                    'permission_callback' => array( $this, 'permissions_write' ),
                    'args'                => $this->get_item_args(),
                    'schema'              => array( $this, 'get_item_schema' ),
                ),
            )
        );

        // Single item routes.
        register_rest_route(
            'pit/v1',
            '/items/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_item' ),
                    'permission_callback' => array( $this, 'permissions_write' ),
                    'args'                => array_merge(
                        array(
                            'id' => array(
                                'description' => __( 'Item ID', 'personal-inventory-tracker' ),
                                'type'        => 'integer',
                                'required'    => true,
                            ),
                        ),
                        $this->get_item_args( true )
                    ),
                    'schema'              => array( $this, 'get_item_schema' ),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_item' ),
                    'permission_callback' => array( $this, 'permissions_write' ),
                    'args'                => array(
                        'id' => array(
                            'description' => __( 'Item ID', 'personal-inventory-tracker' ),
                            'type'        => 'integer',
                            'required'    => true,
                        ),
                    ),
                ),
            )
        );

        // Batch update route.
        register_rest_route(
            'pit/v1',
            '/items/batch',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'batch_update' ),
                    'permission_callback' => array( $this, 'permissions_write' ),
                    'args'                => array(
                        'items' => array(
                            'description' => __( 'Array of items to update', 'personal-inventory-tracker' ),
                            'type'        => 'array',
                            'required'    => true,
                            'items'       => array(
                                'type'       => 'object',
                                'properties' => $this->get_item_args( true ),
                            ),
                        ),
                    ),
                ),
            )
        );

        // Export route.
        register_rest_route(
            'pit/v1',
            '/export',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'export_items' ),
                'permission_callback' => array( $this, 'permissions_read' ),
            )
        );

        // Import route.
        register_rest_route(
            'pit/v1',
            '/import',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'import_items' ),
                'permission_callback' => array( $this, 'permissions_write' ),
                'args'                => array(
                    'items' => array(
                        'description' => __( 'Array of items to import', 'personal-inventory-tracker' ),
                        'type'        => 'array',
                        'required'    => true,
                        'items'       => array(
                            'type'       => 'object',
                            'properties' => $this->get_item_args(),
                        ),
                    ),
                ),
            )
        );

        // Recommendation refresh route.
        register_rest_route(
            'pit/v1',
            '/recommendations/refresh',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'refresh_recommendations' ),
                'permission_callback' => array( $this, 'permissions_write' ),
            )
        );
    }

    /**
     * Verify user can read data.
     */
    public function permissions_read( $request ) {
        if ( ! $this->verify_nonce( $request ) ) {
            return new WP_Error( 'rest_nonce_invalid', __( 'Invalid nonce.', 'personal-inventory-tracker' ), array( 'status' => 403 ) );
        }
        return current_user_can( 'read' );
    }

    /**
     * Verify user can modify data and the site is not in read only mode.
     */
    public function permissions_write( $request ) {
        if ( ! $this->verify_nonce( $request ) ) {
            return new WP_Error( 'rest_nonce_invalid', __( 'Invalid nonce.', 'personal-inventory-tracker' ), array( 'status' => 403 ) );
        }
        if ( get_option( 'pit_read_only' ) ) {
            return new WP_Error( 'pit_read_only', __( 'Read-only mode enabled.', 'personal-inventory-tracker' ), array( 'status' => 403 ) );
        }
        return current_user_can( 'edit_posts' );
    }

    /**
     * Validate nonce using check_ajax_referer allowing header based nonces.
     */
    protected function verify_nonce( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( $nonce ) {
            $_REQUEST['_wpnonce'] = $nonce; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        return (bool) check_ajax_referer( 'wp_rest', '_wpnonce', false );
    }

    /**
     * Schema for a single item.
     */
    public function get_item_schema() {
        return array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'pit_item',
            'type'       => 'object',
            'properties' => array(
                'id'        => array(
                    'description' => __( 'Unique identifier for the item.', 'personal-inventory-tracker' ),
                    'type'        => 'integer',
                    'readonly'    => true,
                ),
                'title'     => array(
                    'description' => __( 'Item name.', 'personal-inventory-tracker' ),
                    'type'        => 'string',
                ),
                'qty'       => array(
                    'description' => __( 'Quantity on hand.', 'personal-inventory-tracker' ),
                    'type'        => 'integer',
                    'default'     => 0,
                ),
                'purchased' => array(
                    'description' => __( 'Whether the item is purchased.', 'personal-inventory-tracker' ),
                    'type'        => 'boolean',
                    'default'     => false,
                ),
            ),
        );
    }

    /**
     * Args for creating/updating items.
     *
     * @param bool $partial Whether fields are optional.
     * @return array
     */
    protected function get_item_args( $partial = false ) {
        $required = ! $partial;
        return array(
            'title' => array(
                'description' => __( 'Item name.', 'personal-inventory-tracker' ),
                'type'        => 'string',
                'required'    => $required,
            ),
            'qty'   => array(
                'description' => __( 'Quantity on hand.', 'personal-inventory-tracker' ),
                'type'        => 'integer',
                'required'    => $required,
            ),
            'purchased' => array(
                'description' => __( 'Purchased flag.', 'personal-inventory-tracker' ),
                'type'        => 'boolean',
                'required'    => false,
            ),
        );
    }

    /**
     * Prepare an item for JSON response.
     */
    protected function prepare_item( $post ) {
        return array(
            'id'        => $post->ID,
            'title'     => $post->post_title,
            'qty'       => (int) get_post_meta( $post->ID, 'qty', true ),
            'purchased' => (bool) get_post_meta( $post->ID, 'purchased', true ),
        );
    }

    /**
     * GET /items handler.
     */
    public function get_items( $request ) {
        $posts = get_posts(
            array(
                'post_type'      => 'pit_item',
                'posts_per_page' => -1,
            )
        );

        $items = array();
        foreach ( $posts as $post ) {
            $items[] = $this->prepare_item( $post );
        }

        return rest_ensure_response( $items );
    }

    /**
     * POST /items handler.
     */
    public function create_item( $request ) {
        $id = wp_insert_post(
            array(
                'post_type'   => 'pit_item',
                'post_title'  => sanitize_text_field( $request['title'] ),
                'post_status' => 'publish',
            ),
            true
        );

        if ( is_wp_error( $id ) ) {
            return $id;
        }

        update_post_meta( $id, 'qty', (int) $request['qty'] );
        update_post_meta( $id, 'purchased', ! empty( $request['purchased'] ) );

        return rest_ensure_response( $this->prepare_item( get_post( $id ) ) );
    }

    /**
     * POST /items/{id} handler.
     */
    public function update_item( $request ) {
        $id = (int) $request['id'];

        if ( isset( $request['title'] ) ) {
            wp_update_post(
                array(
                    'ID'         => $id,
                    'post_title' => sanitize_text_field( $request['title'] ),
                )
            );
        }

        if ( isset( $request['qty'] ) ) {
            update_post_meta( $id, 'qty', (int) $request['qty'] );
        }

        if ( isset( $request['purchased'] ) ) {
            update_post_meta( $id, 'purchased', ! empty( $request['purchased'] ) );
        }

        return rest_ensure_response( $this->prepare_item( get_post( $id ) ) );
    }

    /**
     * DELETE /items/{id} handler.
     */
    public function delete_item( $request ) {
        $id      = (int) $request['id'];
        $deleted = wp_trash_post( $id );

        if ( ! $deleted ) {
            return new WP_Error( 'pit_not_deleted', __( 'Unable to delete item.', 'personal-inventory-tracker' ), array( 'status' => 500 ) );
        }

        return rest_ensure_response( true );
    }

    /**
     * POST /items/batch handler.
     */
    public function batch_update( $request ) {
        $items   = $request->get_param( 'items' );
        $results = array();
        if ( is_array( $items ) ) {
            foreach ( $items as $item ) {
                $req = new WP_REST_Request();
                foreach ( $item as $key => $value ) {
                    $req->set_param( $key, $value );
                }
                $results[] = $this->update_item( $req );
            }
        }
        return rest_ensure_response( $results );
    }

    /**
     * GET /export handler.
     */
    public function export_items( $request ) {
        return $this->get_items( $request );
    }

    /**
     * POST /import handler.
     */
    public function import_items( $request ) {
        $items   = $request->get_param( 'items' );
        $results = array();
        if ( is_array( $items ) ) {
            foreach ( $items as $item ) {
                $req = new WP_REST_Request();
                foreach ( $item as $key => $value ) {
                    $req->set_param( $key, $value );
                }
                $results[] = $this->create_item( $req );
            }
        }
        return rest_ensure_response( $results );
    }

    /**
     * POST /recommendations/refresh handler.
     */
    public function refresh_recommendations( $request ) {
        // Placeholder for recommendation refresh logic.
        do_action( 'pit_refresh_recommendations' );
        return rest_ensure_response( true );
    }
}

