<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once PIT_PLUGIN_DIR . 'pit-functions.php';

class PIT_REST {
    protected $namespace = 'pit/v1';
    protected $post_type = 'pit_item';

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/items',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_items' ),
                    'permission_callback' => array( $this, 'permissions_read' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_item' ),
                    'permission_callback' => array( $this, 'permissions_edit' ),
                    'args'                => $this->get_item_schema(),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/items/(?P<id>\\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_item' ),
                    'permission_callback' => array( $this, 'permissions_read' ),
                    'args'                => array(
                        'id' => array(
                            'description' => __( 'Item ID.', 'personal-inventory-tracker' ),
                            'type'        => 'integer',
                            'required'    => true,
                        ),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_item' ),
                    'permission_callback' => array( $this, 'permissions_edit' ),
                    'args'                => $this->get_item_schema(),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_item' ),
                    'permission_callback' => array( $this, 'permissions_edit' ),
                    'args'                => array(
                        'id' => array(
                            'type'     => 'integer',
                            'required' => true,
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/items/batch',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'batch_update_items' ),
                'permission_callback' => array( $this, 'permissions_edit' ),
                'args'                => array(
                    'items' => array(
                        'description' => __( 'Array of items with id and fields.', 'personal-inventory-tracker' ),
                        'type'        => 'array',
                        'required'    => true,
                    ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/import',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'import_items' ),
                'permission_callback' => array( $this, 'permissions_edit' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/export',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'export_items' ),
                'permission_callback' => array( $this, 'permissions_read' ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/recommendations/refresh',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'refresh_recommendations' ),
                'permission_callback' => array( $this, 'permissions_edit' ),
            )
        );
    }

    protected function get_item_schema() {
        return array(
            'id' => array(
                'description' => __( 'Item ID.', 'personal-inventory-tracker' ),
                'type'        => 'integer',
            ),
            'post_title' => array(
                'description' => __( 'Item title.', 'personal-inventory-tracker' ),
                'type'        => 'string',
                'required'    => false,
            ),
            'qty' => array(
                'description' => __( 'Quantity available.', 'personal-inventory-tracker' ),
                'type'        => 'integer',
            ),
            'reorder_threshold' => array(
                'description' => __( 'Quantity when reorder is triggered.', 'personal-inventory-tracker' ),
                'type'        => 'integer',
            ),
            'reorder_interval' => array(
                'description' => __( 'Days between reorders.', 'personal-inventory-tracker' ),
                'type'        => 'integer',
            ),
            'last_reordered' => array(
                'description' => __( 'Timestamp of last reorder.', 'personal-inventory-tracker' ),
                'type'        => 'integer',
            ),
        );
    }

    protected function prepare_response( $data ) {
        $data['nonce'] = wp_create_nonce( 'wp_rest' );
        return rest_ensure_response( $data );
    }

    protected function check_permissions( $write = false ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return false;
        }

        if ( $write && get_option( 'pit_read_only', false ) ) {
            return false;
        }

        return true;
    }

    public function permissions_read( $request ) {
        return $this->check_permissions( false );
    }

    public function permissions_edit( $request ) {
        return $this->check_permissions( true );
    }

    public function get_items( WP_REST_Request $request ) {
        $posts = get_posts(
            array(
                'post_type'      => $this->post_type,
                'posts_per_page' => -1,
            )
        );

        $items = array();
        foreach ( $posts as $post ) {
            $item            = pit_get_item( $post->ID );
            $item['id']      = $post->ID;
            $item['title']   = $post->post_title;
            $items[]         = $item;
        }

        return $this->prepare_response( array( 'items' => $items ) );
    }

    public function get_item( WP_REST_Request $request ) {
        $id = absint( $request['id'] );
        if ( ! $id ) {
            return new WP_Error( 'pit_invalid_id', __( 'Invalid item ID.', 'personal-inventory-tracker' ), array( 'status' => 400 ) );
        }

        $item          = pit_get_item( $id );
        $item['id']    = $id;
        $item['title'] = get_the_title( $id );

        return $this->prepare_response( array( 'item' => $item ) );
    }

    public function create_item( WP_REST_Request $request ) {
        check_ajax_referer( 'wp_rest', '_wpnonce' );

        $data = $request->get_json_params();

        $post_id = wp_insert_post(
            array(
                'post_type'   => $this->post_type,
                'post_title'  => sanitize_text_field( $data['post_title'] ?? '' ),
                'post_status' => 'publish',
            )
        );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        unset( $data['post_title'] );
        pit_update_item( $post_id, $data );

        $item          = pit_get_item( $post_id );
        $item['id']    = $post_id;
        $item['title'] = get_the_title( $post_id );

        return $this->prepare_response( array( 'item' => $item ) );
    }

    public function update_item( WP_REST_Request $request ) {
        check_ajax_referer( 'wp_rest', '_wpnonce' );

        $id   = absint( $request['id'] );
        $data = $request->get_json_params();

        $updated = pit_update_item( $id, $data );
        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        $updated['id']    = $id;
        $updated['title'] = get_the_title( $id );

        return $this->prepare_response( array( 'item' => $updated ) );
    }

    public function delete_item( WP_REST_Request $request ) {
        check_ajax_referer( 'wp_rest', '_wpnonce' );

        $id      = absint( $request['id'] );
        $deleted = wp_delete_post( $id, true );

        if ( ! $deleted ) {
            return new WP_Error( 'pit_delete_failed', __( 'Failed to delete item.', 'personal-inventory-tracker' ), array( 'status' => 500 ) );
        }

        return $this->prepare_response( array( 'deleted' => true, 'id' => $id ) );
    }

    public function batch_update_items( WP_REST_Request $request ) {
        check_ajax_referer( 'wp_rest', '_wpnonce' );

        $items   = $request->get_json_params()['items'] ?? array();
        $updated = array();

        foreach ( $items as $item_data ) {
            if ( empty( $item_data['id'] ) ) {
                continue;
            }
            $id = absint( $item_data['id'] );
            unset( $item_data['id'] );
            $result            = pit_update_item( $id, $item_data );
            if ( is_wp_error( $result ) ) {
                $updated[] = array(
                    'id'    => $id,
                    'error' => $result->get_error_message(),
                );
                continue;
            }
            $result['id']    = $id;
            $result['title'] = get_the_title( $id );
            $updated[]       = $result;
        }

        return $this->prepare_response( array( 'items' => $updated ) );
    }

    public function import_items( WP_REST_Request $request ) {
        check_ajax_referer( 'wp_rest', '_wpnonce' );

        $items   = $request->get_json_params()['items'] ?? array();
        $created = array();

        foreach ( $items as $item_data ) {
            $post_id = wp_insert_post(
                array(
                    'post_type'   => $this->post_type,
                    'post_title'  => sanitize_text_field( $item_data['post_title'] ?? '' ),
                    'post_status' => 'publish',
                )
            );
            if ( is_wp_error( $post_id ) ) {
                continue;
            }
            unset( $item_data['post_title'] );
            pit_update_item( $post_id, $item_data );

            $item          = pit_get_item( $post_id );
            $item['id']    = $post_id;
            $item['title'] = get_the_title( $post_id );
            $created[]     = $item;
        }

        return $this->prepare_response( array( 'items' => $created ) );
    }

    public function export_items( WP_REST_Request $request ) {
        return $this->get_items( $request );
    }

    public function refresh_recommendations( WP_REST_Request $request ) {
        check_ajax_referer( 'wp_rest', '_wpnonce' );

        $posts = get_posts(
            array(
                'post_type'      => $this->post_type,
                'posts_per_page' => -1,
            )
        );

        $results = array();
        foreach ( $posts as $post ) {
            $item               = pit_get_item( $post->ID );
            $calc               = pit_calculate_reorder_needed( $item );
            $item['id']         = $post->ID;
            $item['title']      = $post->post_title;
            $item['reorder_needed'] = $calc['needed'];
            $item['reorder_reason'] = $calc['reason'];
            update_post_meta( $post->ID, 'reorder_needed', $calc['needed'] );
            update_post_meta( $post->ID, 'reorder_reason', $calc['reason'] );
            $results[]          = $item;
        }

        return $this->prepare_response( array( 'items' => $results ) );
    }
}
