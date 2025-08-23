<?php
namespace RealTreasury\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rest {

    protected function verify_nonce( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce ) {
            $nonce = $request->get_param( '_wpnonce' );
        }
        if ( $nonce ) {
            $_REQUEST['_wpnonce'] = $nonce;
        }
        if ( false === check_ajax_referer( 'wp_rest', '_wpnonce', false ) ) {
            return new \WP_Error( 'pit_invalid_nonce', __( 'Invalid nonce.', 'personal-inventory-tracker' ), array( 'status' => 403 ) );
        }
        return true;
    }

    public function get_item_schema() {
        return array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'pit_item',
            'type'       => 'object',
            'properties' => array(
                'id'        => array(
                    'type'     => 'integer',
                    'readonly' => true,
                ),
                'title'     => array(
                    'type' => 'string',
                ),
                'qty'       => array(
                    'type' => 'integer',
                ),
                'purchased' => array(
                    'type' => 'boolean',
                ),
            ),
        );
    }

    public function register_routes() {
        register_rest_route(
            'pit/v1',
            '/items',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_items' ),
                    'permission_callback' => array( $this, 'permissions_read' ),
                    'schema'              => array( $this, 'get_item_schema' ),
                ),
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_item' ),
                    'permission_callback' => array( $this, 'permissions_write' ),
                    'args'                => array(
                        'title'     => array(
                            'type'     => 'string',
                            'required' => true,
                        ),
                        'qty'       => array(
                            'type'    => 'integer',
                            'default' => 0,
                        ),
                        'purchased' => array(
                            'type'    => 'boolean',
                            'default' => false,
                        ),
                    ),
                    'schema'              => array( $this, 'get_item_schema' ),
                ),
            )
        );

        register_rest_route(
            'pit/v1',
            '/items/(?P<id>\d+)',
            array(
                array(
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_item' ),
                    'permission_callback' => array( $this, 'permissions_write' ),
                    'args'                => array(
                        'id'        => array(
                            'type'     => 'integer',
                            'required' => true,
                        ),
                        'title'     => array(
                            'type' => 'string',
                        ),
                        'qty'       => array(
                            'type' => 'integer',
                        ),
                        'purchased' => array(
                            'type' => 'boolean',
                        ),
                    ),
                    'schema'              => array( $this, 'get_item_schema' ),
                ),
                array(
                    'methods'             => \WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_item' ),
                    'permission_callback' => array( $this, 'permissions_write' ),
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
            'pit/v1',
            '/items/batch',
            array(
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'batch_update_items' ),
                'permission_callback' => array( $this, 'permissions_write' ),
                'args'                => array(
                    'items' => array(
                        'type'     => 'array',
                        'required' => true,
                        'items'    => array(
                            'type' => 'object',
                        ),
                    ),
                ),
                'schema'              => array( $this, 'get_item_schema' ),
            )
        );

        register_rest_route(
            'pit/v1',
            '/items/import',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'import_items' ),
                'permission_callback' => array( $this, 'permissions_write' ),
                'args'                => array(
                    'items' => array(
                        'type'     => 'array',
                        'required' => true,
                        'items'    => array(
                            'type' => 'object',
                        ),
                    ),
                    'settings' => array(
                        'type'    => 'object',
                        'required' => false,
                    ),
                ),
                'schema'              => array( $this, 'get_item_schema' ),
            )
        );
    }

    public function permissions_read( $request ) {
        $public_access = get_option( 'pit_public_access', false );
        return $public_access || current_user_can( 'read' );
    }

    private function verify_write( $request ) {
        $nonce_check = $this->verify_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) {
            return $nonce_check;
        }

        if ( get_option( 'pit_read_only_mode', false ) ) {
            return new \WP_Error( 'read_only', __( 'Read-only mode enabled', 'personal-inventory-tracker' ), array( 'status' => 403 ) );
        }

        if ( ! current_user_can( 'manage_inventory_items' ) ) {
            return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to manage inventory items.', 'personal-inventory-tracker' ), array( 'status' => rest_authorization_required_code() ) );
        }

        return true;
    }

    public function permissions_write( $request ) {
        return $this->verify_write( $request );
    }

    public function get_items( $request ) {
        // Implementation placeholder.
        return array();
    }

    public function create_item( $request ) {
        // Implementation placeholder.
        return array();
    }

    public function update_item( $request ) {
        // Implementation placeholder.
        return array();
    }

    public function delete_item( $request ) {
        // Implementation placeholder.
        return true;
    }

    public function batch_update_items( $request ) {
        // Implementation placeholder.
        return array();
    }

    public function import_items( $request ) {
        // Implementation placeholder.
        return array();
    }
}
