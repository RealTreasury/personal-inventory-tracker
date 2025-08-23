<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIT_Import_Export {

    public static function csv_headers() {
        return array( 'id','name','category_slug','qty','unit','reorder_threshold','estimated_interval_days','last_purchased','notes' );
    }

    public static function export_csv( $item_ids = array() ) {
        $csv = self::generate_csv( $item_ids );
        if ( headers_sent() ) {
            return;
        }
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=pit-items.csv' );
        echo $csv;
        exit;
    }

    public static function generate_csv( $item_ids = array() ) {
        $rows = self::get_items_data( $item_ids );
        $handle = fopen( 'php://temp', 'r+' );
        // Add BOM for UTF-8
        fwrite( $handle, "\xEF\xBB\xBF" );
        fputcsv( $handle, self::csv_headers() );
        foreach ( $rows as $row ) {
            fputcsv( $handle, $row );
        }
        rewind( $handle );
        $csv = stream_get_contents( $handle );
        fclose( $handle );
        return $csv;
    }

    private static function get_items_data( $item_ids = array() ) {
        $args = array(
            'post_type'      => 'pit_item',
            'posts_per_page' => -1,
            'post_status'    => 'any',
        );
        if ( ! empty( $item_ids ) ) {
            $args['post__in'] = array_map( 'absint', $item_ids );
        }
        $posts = get_posts( $args );
        $data  = array();
        foreach ( $posts as $post ) {
            $cats = wp_get_post_terms( $post->ID, 'pit_category', array( 'fields' => 'slugs' ) );
            $data[] = array(
                $post->ID,
                $post->post_title,
                implode( ',', $cats ),
                get_post_meta( $post->ID, 'pit_qty', true ),
                get_post_meta( $post->ID, 'pit_unit', true ),
                get_post_meta( $post->ID, 'pit_threshold', true ),
                get_post_meta( $post->ID, 'pit_interval', true ),
                get_post_meta( $post->ID, 'pit_last_purchased', true ),
                get_post_meta( $post->ID, 'pit_notes', true ),
            );
        }
        return $data;
    }

    public static function import_from_csv( $csv, $mapping = array() ) {
        $rows = self::parse_csv( $csv );
        if ( empty( $rows ) ) {
            return 0;
        }
        $headers = array_shift( $rows );
        if ( empty( $mapping ) ) {
            foreach ( self::csv_headers() as $field ) {
                $index = array_search( $field, $headers, true );
                if ( false !== $index ) {
                    $mapping[ $field ] = $index;
                }
            }
        } else {
            foreach ( $mapping as $field => $header ) {
                $index = array_search( $header, $headers, true );
                if ( false !== $index ) {
                    $mapping[ $field ] = $index;
                } else {
                    unset( $mapping[ $field ] );
                }
            }
        }
        $count = 0;
        foreach ( $rows as $row ) {
            if ( empty( $mapping['name'] ) ) {
                continue;
            }
            $name = isset( $row[ $mapping['name'] ] ) ? sanitize_text_field( $row[ $mapping['name'] ] ) : '';
            if ( ! $name ) {
                continue;
            }
            $existing = get_page_by_title( $name, OBJECT, 'pit_item' );
            if ( $existing ) {
                $id = $existing->ID;
            } else {
                $id = wp_insert_post(
                    array(
                        'post_type'   => 'pit_item',
                        'post_title'  => $name,
                        'post_status' => 'publish',
                    ),
                    true
                );
                if ( is_wp_error( $id ) ) {
                    continue;
                }
            }

            if ( isset( $mapping['category_slug'] ) && ! empty( $row[ $mapping['category_slug'] ] ) ) {
                $slugs     = array_map( 'sanitize_title', explode( ',', $row[ $mapping['category_slug'] ] ) );
                $term_ids  = array();
                foreach ( $slugs as $slug ) {
                    $term = get_term_by( 'slug', $slug, 'pit_category' );
                    if ( ! $term ) {
                        $term = wp_insert_term( $slug, 'pit_category', array( 'slug' => $slug ) );
                    }
                    if ( ! is_wp_error( $term ) ) {
                        $term_ids[] = is_array( $term ) ? $term['term_id'] : $term->term_id;
                    }
                }
                if ( ! empty( $term_ids ) ) {
                    wp_set_post_terms( $id, $term_ids, 'pit_category', false );
                }
            }

            if ( isset( $mapping['qty'] ) ) {
                update_post_meta( $id, 'pit_qty', isset( $row[ $mapping['qty'] ] ) ? (int) $row[ $mapping['qty'] ] : 0 );
            }
            if ( isset( $mapping['unit'] ) ) {
                update_post_meta( $id, 'pit_unit', isset( $row[ $mapping['unit'] ] ) ? sanitize_text_field( $row[ $mapping['unit'] ] ) : '' );
            }
            if ( isset( $mapping['reorder_threshold'] ) ) {
                update_post_meta( $id, 'pit_threshold', isset( $row[ $mapping['reorder_threshold'] ] ) ? sanitize_text_field( $row[ $mapping['reorder_threshold'] ] ) : '' );
            }
            if ( isset( $mapping['estimated_interval_days'] ) ) {
                update_post_meta( $id, 'pit_interval', isset( $row[ $mapping['estimated_interval_days'] ] ) ? sanitize_text_field( $row[ $mapping['estimated_interval_days'] ] ) : '' );
            }
            if ( isset( $mapping['last_purchased'] ) ) {
                update_post_meta( $id, 'pit_last_purchased', isset( $row[ $mapping['last_purchased'] ] ) ? sanitize_text_field( $row[ $mapping['last_purchased'] ] ) : '' );
            }
            if ( isset( $mapping['notes'] ) ) {
                update_post_meta( $id, 'pit_notes', isset( $row[ $mapping['notes'] ] ) ? sanitize_textarea_field( $row[ $mapping['notes'] ] ) : '' );
            }
            $count++;
        }
        return $count;
    }

    public static function parse_csv( $csv ) {
        $rows   = array();
        $handle = fopen( 'php://temp', 'r+' );
        fwrite( $handle, $csv );
        rewind( $handle );
        while ( ( $data = fgetcsv( $handle ) ) !== false ) {
            $rows[] = $data;
        }
        fclose( $handle );
        return $rows;
    }
}
