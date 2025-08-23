<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIT_REST {

    public function register_routes() {
        register_rest_route(
            'pit/v1',
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
                    'permission_callback' => array( $this, 'permissions_write' ),
                ),
            )
        );

        register_rest_route(
            'pit/v1',
            '/items/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_item' ),
                    'permission_callback' => array( $this, 'permissions_write' ),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_item' ),
                    'permission_callback' => array( $this, 'permissions_write' ),
                ),
            )
        );

        register_rest_route(
            'pit/v1',
            '/export',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'rest_export' ),
                'permission_callback' => array( $this, 'permissions_read' ),
            )
        );

        register_rest_route(
            'pit/v1',
            '/import',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'rest_import' ),
                'permission_callback' => array( $this, 'permissions_write' ),
                'args'                => array(
                    'csv' => array(
                        'required' => true,
                        'type'     => 'string',
                    ),
                ),
            )
        );
    }

    public function permissions_read( $request ) {
        return current_user_can( 'read' );
    }

    public function permissions_write( $request ) {
        if ( get_option( 'pit_read_only' ) ) {
            return new WP_Error( 'pit_read_only', __( 'Read-only mode enabled.', 'personal-inventory-tracker' ), array( 'status' => 403 ) );
        }
        return current_user_can( 'edit_posts' );
    }

    protected function prepare_item( $post ) {
        return array(
            'id'        => $post->ID,
            'title'     => $post->post_title,
            'qty'       => (int) get_post_meta( $post->ID, 'qty', true ),
            'purchased' => (bool) get_post_meta( $post->ID, 'purchased', true ),
        );
    }

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

    public function delete_item( $request ) {
        $id = (int) $request['id'];
        $deleted = wp_trash_post( $id );

        if ( ! $deleted ) {
            return new WP_Error( 'pit_not_deleted', __( 'Unable to delete item.', 'personal-inventory-tracker' ), array( 'status' => 500 ) );
        }

        return rest_ensure_response( true );
    }

    public function rest_export( $request ) {
        $csv      = PIT_Import_Export::generate_csv();
        $response = new WP_REST_Response( $csv );
        $response->header( 'Content-Type', 'text/csv; charset=utf-8' );
        return $response;
    }

    public function rest_import( $request ) {
        $csv = (string) $request['csv'];
        if ( ! $csv ) {
            return new WP_Error( 'pit_no_csv', __( 'No CSV data provided.', 'personal-inventory-tracker' ), array( 'status' => 400 ) );
        }
        $count = PIT_Import_Export::import_from_csv( $csv );
        return rest_ensure_response( array( 'imported' => $count ) );
    }
}
