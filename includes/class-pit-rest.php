<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIT_REST {

    protected function verify_nonce( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce ) {
            $nonce = $request->get_param( '_wpnonce' );
        }
        if ( $nonce ) {
            $_REQUEST['_wpnonce'] = $nonce;
        }
        if ( false === check_ajax_referer( 'wp_rest', '_wpnonce', false ) ) {
            return new WP_Error( 'pit_invalid_nonce', __( 'Invalid nonce.', 'personal-inventory-tracker' ), array( 'status' => 403 ) );
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
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_items' ),
                    'permission_callback' => array( $this, 'permissions_read' ),
                    'schema'              => array( $this, 'get_item_schema' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
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
                    'methods'             => WP_REST_Server::EDITABLE,
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
                    'methods'             => WP_REST_Server::DELETABLE,
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
                'methods'             => WP_REST_Server::EDITABLE,
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
                'methods'             => WP_REST_Server::CREATABLE,
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
                ),
                'schema'              => array( $this, 'get_item_schema' ),
            )
        );

        register_rest_route(
            'pit/v1',
            '/items/export',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'export_items' ),
                'permission_callback' => array( $this, 'permissions_read' ),
                'schema'              => array( $this, 'get_item_schema' ),
            )
        );

        register_rest_route(
            'pit/v1',
            '/recommendations/refresh',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( 'PIT_Cron', 'refresh_recommendations' ),
                'permission_callback' => array( $this, 'permissions_write' ),
            )
        );
    }

    public function permissions_read( $request ) {
        $nonce = $this->verify_nonce( $request );
        if ( true !== $nonce ) {
            return $nonce;
        }
        return current_user_can( 'read' );
    }

    public function permissions_write( $request ) {
        $nonce = $this->verify_nonce( $request );
        if ( true !== $nonce ) {
            return $nonce;
        }
        if ( get_option( 'pit_read_only' ) ) {
            return new WP_Error( 'pit_read_only', __( 'Read-only mode enabled.', 'personal-inventory-tracker' ), array( 'status' => 403 ) );
        }
        return current_user_can( 'edit_posts' );
    }

    protected function prepare_item( $post ) {
        return array(
            'id'        => $post->ID,
            'title'     => sanitize_text_field( $post->post_title ),
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

    public function batch_update_items( $request ) {
        $updates = $request->get_param( 'items' );
        $results = array();
        if ( is_array( $updates ) ) {
            foreach ( $updates as $update ) {
                $id = isset( $update['id'] ) ? (int) $update['id'] : 0;
                if ( ! $id ) {
                    continue;
                }
                if ( isset( $update['title'] ) ) {
                    wp_update_post(
                        array(
                            'ID'         => $id,
                            'post_title' => sanitize_text_field( $update['title'] ),
                        )
                    );
                }
                if ( isset( $update['qty'] ) ) {
                    update_post_meta( $id, 'qty', (int) $update['qty'] );
                }
                if ( isset( $update['purchased'] ) ) {
                    update_post_meta( $id, 'purchased', ! empty( $update['purchased'] ) );
                }
                $results[] = $this->prepare_item( get_post( $id ) );
            }
        }
        return rest_ensure_response( $results );
    }

    public function import_items( $request ) {
        $items   = $request->get_param( 'items' );
        $created = array();
        if ( is_array( $items ) ) {
            foreach ( $items as $item ) {
                $id = wp_insert_post(
                    array(
                        'post_type'   => 'pit_item',
                        'post_title'  => sanitize_text_field( $item['title'] ),
                        'post_status' => 'publish',
                    ),
                    true
                );
                if ( is_wp_error( $id ) ) {
                    continue;
                }
                if ( isset( $item['qty'] ) ) {
                    update_post_meta( $id, 'qty', (int) $item['qty'] );
                }
                if ( isset( $item['purchased'] ) ) {
                    update_post_meta( $id, 'purchased', ! empty( $item['purchased'] ) );
                }
                $created[] = $this->prepare_item( get_post( $id ) );
            }
        }
        return rest_ensure_response( $created );
    }

    public function export_items( $request ) {
        return $this->get_items( $request );
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
}
