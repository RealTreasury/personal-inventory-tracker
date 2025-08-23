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
            'image_url',
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
        $args = array(
            'post_type'      => 'pit_item',
            'posts_per_page' => -1,
            'post_status'    => 'any',
        );
        if ( ! empty( $item_ids ) ) {
            $args['post__in'] = $item_ids;
        }
        $posts = get_posts( $args );

        $extra_meta = array();
        foreach ( $posts as $post ) {
            $meta = get_post_meta( $post->ID );
            foreach ( $meta as $key => $values ) {
                if ( 0 === strpos( $key, 'pit_meta_' ) ) {
                    $extra_meta[] = substr( $key, 9 );
                }
            }
        }
        $extra_meta = array_unique( $extra_meta );
        $headers    = array_merge( self::get_headers(), array_map( function( $k ) {
            return 'meta_' . $k;
        }, $extra_meta ) );

        $fh = fopen( 'php://temp', 'w+' );
        fputcsv( $fh, $headers );

        foreach ( $posts as $post ) {
            $category = wp_get_post_terms( $post->ID, 'pit_category', array( 'fields' => 'slugs' ) );
            $data     = array(
                'id'                      => $post->ID,
                'name'                    => $post->post_title,
                'category_slug'           => $category ? $category[0] : '',
                'qty'                     => get_post_meta( $post->ID, 'pit_qty', true ),
                'unit'                    => get_post_meta( $post->ID, 'pit_unit', true ),
                'reorder_threshold'       => get_post_meta( $post->ID, 'pit_threshold', true ),
                'estimated_interval_days' => get_post_meta( $post->ID, 'pit_interval', true ),
                'last_purchased'          => get_post_meta( $post->ID, 'pit_last_purchased', true ),
                'notes'                   => get_post_meta( $post->ID, 'pit_notes', true ),
                'image_url'               => get_the_post_thumbnail_url( $post->ID, 'full' ),
            );
            foreach ( $extra_meta as $meta_key ) {
                $data[ 'meta_' . $meta_key ] = get_post_meta( $post->ID, 'pit_meta_' . $meta_key, true );
            }
            $row = array();
            foreach ( $headers as $header ) {
                $value = isset( $data[ $header ] ) ? $data[ $header ] : '';
                if ( is_string( $value ) ) {
                    $value = wp_unslash( $value );
                }
                $row[] = $value;
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
            foreach ( $mapping as $field => $index ) {
                if ( isset( $line[ $index ] ) ) {
                    $data[ $field ] = sanitize_text_field( $line[ $index ] );
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
            if ( empty( $data['category_slug'] ) ) {
                $settings = Settings::get_settings();
                if ( ! empty( $settings['auto_categorize'] ) ) {
                    $slug = \RealTreasury\Inventory\Services\CategoryClassifier::suggest_category(
                        $data['name'],
                        $data['notes'] ?? ''
                    );
                    if ( $slug ) {
                        $data['category_slug'] = $slug;
                    }
                }
            }
            if ( ! empty( $data['category_slug'] ) ) {
                $term = get_term_by( 'slug', $data['category_slug'], 'pit_category' );
                if ( $term ) {
                    wp_set_post_terms( $id, array( $term->term_id ), 'pit_category', false );
                    do_action( 'pit_category_auto_assigned', $id, $data['category_slug'] );
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
            if ( isset( $data['image_url'] ) && $data['image_url'] ) {
                if ( ! function_exists( 'media_sideload_image' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/media.php';
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                }
                $image_id = media_sideload_image( esc_url_raw( $data['image_url'] ), $id, null, 'id' );
                if ( ! is_wp_error( $image_id ) ) {
                    set_post_thumbnail( $id, $image_id );
                }
            }
            foreach ( $data as $key => $value ) {
                if ( 0 === strpos( $key, 'meta_' ) ) {
                    update_post_meta( $id, 'pit_' . $key, sanitize_text_field( $value ) );
                }
            }
        }
    }

    public static function classify_uncategorized() {
        $settings = Settings::get_settings();
        if ( empty( $settings['auto_categorize'] ) ) {
            return 0;
        }
        $posts = get_posts(
            array(
                'post_type'      => 'pit_item',
                'posts_per_page' => -1,
                'tax_query'      => array(
                    array(
                        'taxonomy' => 'pit_category',
                        'operator' => 'NOT EXISTS',
                    ),
                ),
            )
        );
        $count = 0;
        foreach ( $posts as $post ) {
            $notes = get_post_meta( $post->ID, 'pit_notes', true );
            $slug  = \RealTreasury\Inventory\Services\CategoryClassifier::suggest_category( $post->post_title, $notes );
            if ( $slug ) {
                $term = get_term_by( 'slug', $slug, 'pit_category' );
                if ( ! $term ) {
                    $term = wp_insert_term( $slug, 'pit_category', array( 'slug' => $slug ) );
                }
                if ( ! is_wp_error( $term ) ) {
                    $term_id = is_array( $term ) ? $term['term_id'] : $term->term_id;
                    wp_set_post_terms( $post->ID, array( $term_id ), 'pit_category', false );
                    do_action( 'pit_category_auto_assigned', $post->ID, $slug );
                    $count++;
                }
            }
        }
        return $count;
    }

    public static function register_rest_routes() {
        register_rest_route(
            'pit/v1',
            '/export',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'rest_export' ),
                'permission_callback' => function() {
                    return current_user_can( 'manage_inventory_items' );
                },
            )
        );
        register_rest_route(
            'pit/v1',
            '/import',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'rest_import' ),
                'permission_callback' => function() {
                    return current_user_can( 'manage_inventory_items' );
                },
            )
        );
    }

    public static function rest_export( \WP_REST_Request $request ) {
        $csv = self::generate_csv();
        return new \WP_REST_Response( $csv, 200, array( 'Content-Type' => 'text/csv; charset=utf-8' ) );
    }

    public static function rest_import( \WP_REST_Request $request ) {
        $csv = $request->get_param( 'csv' );
        if ( empty( $csv ) ) {
            return new \WP_Error( 'pit_no_csv', __( 'No CSV data supplied.', 'personal-inventory-tracker' ), array( 'status' => 400 ) );
        }
        $csv     = wp_unslash( $csv );
        $lines   = array_map( 'str_getcsv', preg_split( '/[\r\n]+/', trim( $csv ) ) );
        $headers = array_shift( $lines );
        $mapping = array();
        foreach ( self::get_headers() as $field ) {
            $index = array_search( $field, $headers, true );
            if ( false !== $index ) {
                $mapping[ $field ] = $index;
            }
        }
        self::import_from_csv_string( implode( "\n", array_map( function( $row ) {
            return implode( ',', $row );
        }, $lines ) ), $mapping );
        return rest_ensure_response( true );
    }
}

\class_alias( __NAMESPACE__ . '\\Import_Export', 'PIT_Import_Export' );
