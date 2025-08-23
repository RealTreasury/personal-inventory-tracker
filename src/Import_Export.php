<?php
namespace RealTreasury\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Import_Export {

    public static function get_headers() {
        return array(
            'id',
            'name',
            'category_slug',
            'qty',
            'unit',
            'reorder_threshold',
            'estimated_interval_days',
            'last_purchased',
            'notes',
        );
    }

    public static function output_csv( $item_ids = array() ) {
        $csv = self::generate_csv( $item_ids );
        if ( headers_sent() ) {
            return;
        }
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment;filename=pit-items.csv' );
        echo $csv;
        exit;
    }

    public static function generate_csv( $item_ids = array() ) {
        $fh = fopen( 'php://temp', 'w+' );
        fputcsv( $fh, self::get_headers() );

        $args = array(
            'post_type'      => 'pit_item',
            'posts_per_page' => -1,
            'post_status'    => 'any',
        );
        if ( ! empty( $item_ids ) ) {
            $args['post__in'] = $item_ids;
        }
        $posts = get_posts( $args );
        foreach ( $posts as $post ) {
            $category = wp_get_post_terms( $post->ID, 'pit_category', array( 'fields' => 'slugs' ) );
            $row      = array(
                $post->ID,
                $post->post_title,
                $category ? $category[0] : '',
                get_post_meta( $post->ID, 'pit_qty', true ),
                get_post_meta( $post->ID, 'pit_unit', true ),
                get_post_meta( $post->ID, 'pit_threshold', true ),
                get_post_meta( $post->ID, 'pit_interval', true ),
                get_post_meta( $post->ID, 'pit_last_purchased', true ),
                get_post_meta( $post->ID, 'pit_notes', true ),
            );
            foreach ( $row as &$value ) {
                if ( is_string( $value ) ) {
                    $value = wp_unslash( $value );
                }
            }
            fputcsv( $fh, $row );
        }
        rewind( $fh );
        $csv = stream_get_contents( $fh );
        fclose( $fh );
        return $csv;
    }

    public static function generate_pdf( $item_ids = array() ) {
        return '';
    }

    public static function generate_excel( $item_ids = array() ) {
        return '';
    }

    public static function import_from_csv_string( $csv, $mapping ) {
        $lines = array_map( 'str_getcsv', preg_split( '/[\r\n]+/', trim( $csv ) ) );
        foreach ( $lines as $line ) {
            $data = array();
            foreach ( self::get_headers() as $field ) {
                if ( isset( $mapping[ $field ] ) && isset( $line[ $mapping[ $field ] ] ) ) {
                    $data[ $field ] = sanitize_text_field( $line[ $mapping[ $field ] ] );
                }
            }
            if ( empty( $data['name'] ) ) {
                continue;
            }
            $existing = get_page_by_title( $data['name'], OBJECT, 'pit_item' );
            if ( $existing ) {
                $id = $existing->ID;
            } else {
                $id = wp_insert_post(
                    array(
                        'post_title'  => $data['name'],
                        'post_type'   => 'pit_item',
                        'post_status' => 'publish',
                    )
                );
            }
            if ( is_wp_error( $id ) ) {
                continue;
            }
            if ( ! empty( $data['category_slug'] ) ) {
                $term = get_term_by( 'slug', $data['category_slug'], 'pit_category' );
                if ( $term ) {
                    wp_set_post_terms( $id, array( $term->term_id ), 'pit_category', false );
                }
            }
            if ( isset( $data['qty'] ) ) {
                update_post_meta( $id, 'pit_qty', (int) $data['qty'] );
            }
            if ( isset( $data['unit'] ) ) {
                update_post_meta( $id, 'pit_unit', $data['unit'] );
            }
            if ( isset( $data['reorder_threshold'] ) ) {
                update_post_meta( $id, 'pit_threshold', (int) $data['reorder_threshold'] );
            }
            if ( isset( $data['estimated_interval_days'] ) ) {
                update_post_meta( $id, 'pit_interval', (int) $data['estimated_interval_days'] );
            }
            if ( isset( $data['last_purchased'] ) ) {
                update_post_meta( $id, 'pit_last_purchased', $data['last_purchased'] );
            }
            if ( isset( $data['notes'] ) ) {
                update_post_meta( $id, 'pit_notes', sanitize_textarea_field( $data['notes'] ) );
            }
        }
    }

    public static function register_rest_routes() {
        register_rest_route(
            'pit/v1',
            '/export',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'rest_export' ),
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            )
        );
        register_rest_route(
            'pit/v1',
            '/import',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'rest_import' ),
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            )
        );
    }

    public static function rest_export( \WP_REST_Request $request ) {
        $csv = self::generate_csv();
        if ( headers_sent() ) {
            return new \WP_Error( 'headers_sent', __( 'Headers already sent.', 'personal-inventory-tracker' ) );
        }
        return $csv;
    }

    public static function rest_import( \WP_REST_Request $request ) {
        $file = $request->get_file_params();
        if ( empty( $file['file'] ) || empty( $file['file']['tmp_name'] ) ) {
            return new \WP_Error( 'no_file', __( 'No file provided.', 'personal-inventory-tracker' ) );
        }
        $csv = file_get_contents( $file['file']['tmp_name'] );
        $mapping = array();
        self::import_from_csv_string( $csv, $mapping );
        return true;
    }
}
