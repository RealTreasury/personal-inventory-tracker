<?php

namespace RealTreasury\Inventory\REST;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rest_Api {

    protected function verify_nonce( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce ) {
            $nonce = $request->get_param( '_wpnonce' );
        }
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new \WP_Error( 'pit_invalid_nonce', __( 'Invalid nonce.', 'personal-inventory-tracker' ), array( 'status' => 403 ) );
        }
        return true;
    }

    protected function check_rate_limit( $request ) {
        $user_id = get_current_user_id();
        $key     = $user_id ? 'pit_rate_' . $user_id : 'pit_rate_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
        $count   = (int) get_transient( $key );

        if ( $count >= 100 ) {
            return new \WP_Error( 'pit_rate_limited', __( 'Too many requests.', 'personal-inventory-tracker' ), array( 'status' => 429 ) );
        }

        set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
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
                            'type'              => 'string',
                            'required'          => true,
                            'sanitize_callback' => 'sanitize_text_field',
                            'validate_callback' => 'rest_validate_request_arg',
                        ),
                        'qty'       => array(
                            'type'              => 'integer',
                            'default'           => 0,
                            'sanitize_callback' => 'absint',
                            'validate_callback' => 'rest_validate_request_arg',
                        ),
                        'purchased' => array(
                            'type'              => 'boolean',
                            'default'           => false,
                            'sanitize_callback' => 'rest_sanitize_boolean',
                            'validate_callback' => 'rest_validate_request_arg',
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
                            'type'              => 'integer',
                            'required'          => true,
                            'sanitize_callback' => 'absint',
                            'validate_callback' => 'rest_validate_request_arg',
                        ),
                        'title'     => array(
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                            'validate_callback' => 'rest_validate_request_arg',
                        ),
                        'qty'       => array(
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'validate_callback' => 'rest_validate_request_arg',
                        ),
                        'purchased' => array(
                            'type'              => 'boolean',
                            'sanitize_callback' => 'rest_sanitize_boolean',
                            'validate_callback' => 'rest_validate_request_arg',
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
                            'type'              => 'integer',
                            'required'          => true,
                            'sanitize_callback' => 'absint',
                            'validate_callback' => 'rest_validate_request_arg',
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
                        'type'              => 'array',
                        'required'          => true,
                        'validate_callback' => 'rest_validate_request_arg',
                        'sanitize_callback' => 'wp_unslash',
                        'items'             => array(
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
                    'items'    => array(
                        'type'              => 'array',
                        'required'          => true,
                        'validate_callback' => 'rest_validate_request_arg',
                        'sanitize_callback' => 'wp_unslash',
                        'items'             => array(
                            'type' => 'object',
                        ),
                    ),
                    'settings' => array(
                        'type'              => 'object',
                        'required'          => false,
                        'validate_callback' => 'rest_validate_request_arg',
                        'sanitize_callback' => 'wp_unslash',
                    ),
                ),
                'schema'              => array( $this, 'get_item_schema' ),
            )
        );

        register_rest_route(
            'pit/v1',
            '/export',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'export_items' ),
                'permission_callback' => array( $this, 'permissions_read' ),
                'args'                => array(
                    'format' => array(
                        'type'              => 'string',
                        'default'           => 'json',
                        'enum'              => array( 'json', 'csv', 'pdf' ),
                        'sanitize_callback' => 'sanitize_key',
                        'validate_callback' => 'rest_validate_request_arg',
                    ),
                ),
                'schema'              => array( $this, 'get_item_schema' ),
            )
        );

        register_rest_route(
            'pit/v1',
            '/recommendations/refresh',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array( '\RealTreasury\Inventory\Cron', 'refresh_recommendations' ),
                'permission_callback' => array( $this, 'permissions_write' ),
            )
        );
    }

    public function permissions_read( $request ) {
        $nonce = $this->verify_nonce( $request );
        if ( true !== $nonce ) {
            return $nonce;
        }

        $limit = $this->check_rate_limit( $request );
        if ( true !== $limit ) {
            return $limit;
        }

        if ( ! current_user_can( 'manage_inventory_items' ) ) {
            return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to manage inventory items.', 'personal-inventory-tracker' ), array( 'status' => rest_authorization_required_code() ) );
        }

        return true;
    }

    public function permissions_write( $request ) {
        $nonce = $this->verify_nonce( $request );
        if ( true !== $nonce ) {
            return $nonce;
        }

        $limit = $this->check_rate_limit( $request );
        if ( true !== $limit ) {
            return $limit;
        }

        if ( get_option( 'pit_read_only' ) ) {
            return new \WP_Error( 'pit_read_only', __( 'Read-only mode enabled.', 'personal-inventory-tracker' ), array( 'status' => 403 ) );
        }

        if ( ! current_user_can( 'manage_inventory_items' ) ) {
            return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to manage inventory items.', 'personal-inventory-tracker' ), array( 'status' => rest_authorization_required_code() ) );
        }

        return true;
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
        $items    = $request->get_param( 'items' );
        $settings = (array) $request->get_param( 'settings' );

        $update_existing = ! empty( $settings['updateExisting'] );
        $create_new      = ! empty( $settings['createNew'] );
        $skip_errors     = ! isset( $settings['skipErrors'] ) || $settings['skipErrors'];
        $validate_data   = ! empty( $settings['validateData'] );

        $imported = 0;
        $skipped  = 0;
        $errors   = array();

        if ( is_array( $items ) ) {
            foreach ( $items as $item ) {
                $title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
                if ( $validate_data && '' === $title ) {
                    $errors[] = __( 'Missing title.', 'personal-inventory-tracker' );
                    $skipped++;
                    if ( ! $skip_errors ) {
                        break;
                    }
                    continue;
                }

                $id = 0;
                if ( $update_existing && $title ) {
                    $existing = get_page_by_title( $title, OBJECT, 'pit_item' );
                    if ( $existing ) {
                        $id = $existing->ID;
                        wp_update_post(
                            array(
                                'ID'         => $id,
                                'post_title' => $title,
                            )
                        );
                    }
                }

                if ( ! $id ) {
                    if ( ! $create_new ) {
                        $skipped++;
                        continue;
                    }
                    $id = wp_insert_post(
                        array(
                            'post_type'   => 'pit_item',
                            'post_title'  => $title,
                            'post_status' => 'publish',
                        ),
                        true
                    );
                    if ( is_wp_error( $id ) ) {
                        $errors[] = $id->get_error_message();
                        $skipped++;
                        if ( ! $skip_errors ) {
                            break;
                        }
                        continue;
                    }
                }

                if ( isset( $item['qty'] ) ) {
                    update_post_meta( $id, 'pit_qty', (int) $item['qty'] );
                }
                if ( isset( $item['unit'] ) ) {
                    update_post_meta( $id, 'pit_unit', sanitize_text_field( $item['unit'] ) );
                }
                if ( isset( $item['threshold'] ) ) {
                    update_post_meta( $id, 'pit_threshold', (int) $item['threshold'] );
                }
                if ( isset( $item['reorder_threshold'] ) ) {
                    update_post_meta( $id, 'pit_threshold', (int) $item['reorder_threshold'] );
                }
                if ( isset( $item['estimated_interval_days'] ) ) {
                    update_post_meta( $id, 'pit_interval', (int) $item['estimated_interval_days'] );
                }
                if ( isset( $item['last_purchased'] ) ) {
                    update_post_meta( $id, 'pit_last_purchased', sanitize_text_field( $item['last_purchased'] ) );
                }
                if ( isset( $item['notes'] ) ) {
                    update_post_meta( $id, 'pit_notes', sanitize_textarea_field( $item['notes'] ) );
                }
                if ( isset( $item['purchased'] ) ) {
                    update_post_meta( $id, 'purchased', ! empty( $item['purchased'] ) );
                }
                if ( isset( $item['category'] ) ) {
                    $slug = sanitize_title( $item['category'] );
                    $term = get_term_by( 'slug', $slug, 'pit_category' );
                    if ( ! $term ) {
                        $term = wp_insert_term( $slug, 'pit_category', array( 'slug' => $slug ) );
                    }
                    if ( ! is_wp_error( $term ) ) {
                        $term_id = is_array( $term ) ? $term['term_id'] : $term->term_id;
                        wp_set_post_terms( $id, array( $term_id ), 'pit_category', false );
                    }
                }
                if ( isset( $item['image_url'] ) && $item['image_url'] ) {
                    if ( ! function_exists( 'media_sideload_image' ) ) {
                        require_once ABSPATH . 'wp-admin/includes/media.php';
                        require_once ABSPATH . 'wp-admin/includes/file.php';
                        require_once ABSPATH . 'wp-admin/includes/image.php';
                    }
                    $image_id = media_sideload_image( esc_url_raw( $item['image_url'] ), $id, null, 'id' );
                    if ( ! is_wp_error( $image_id ) ) {
                        set_post_thumbnail( $id, $image_id );
                    }
                }
                foreach ( $item as $key => $value ) {
                    if ( 0 === strpos( $key, 'meta_' ) ) {
                        update_post_meta( $id, 'pit_' . $key, sanitize_text_field( $value ) );
                    }
                }
                $imported++;
            }
        }

        return rest_ensure_response(
            array(
                'imported' => $imported,
                'skipped'  => $skipped,
                'errors'   => $errors,
            )
        );
    }

    public function export_items( $request ) {
        $format = sanitize_key( $request->get_param( 'format' ) );
        if ( 'csv' === $format ) {
            $csv = \RealTreasury\Inventory\Import_Export::generate_csv();
            return new \WP_REST_Response( $csv, 200, array( 'Content-Type' => 'text/csv; charset=utf-8' ) );
        }
        if ( 'pdf' === $format ) {
            $pdf = \RealTreasury\Inventory\Import_Export::generate_pdf();
            return new \WP_REST_Response( $pdf, 200, array( 'Content-Type' => 'application/pdf' ) );
        }
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
            return new \WP_Error( 'pit_not_deleted', __( 'Unable to delete item.', 'personal-inventory-tracker' ), array( 'status' => 500 ) );
        }

        return rest_ensure_response( true );
    }
}

\class_alias( __NAMESPACE__ . '\\Rest_Api', 'PIT\\REST\\Rest_Api' );
