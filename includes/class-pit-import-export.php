<?php
/**
 * Import and export tools for Personal Inventory Tracker.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIT_Import_Export {
    /**
     * Singleton instance.
     *
     * @var self
     */
    protected static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Hook listeners.
     */
    private function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        // Bulk export in admin list table.
        add_filter( 'bulk_actions-edit-pit_item', array( $this, 'register_bulk_export' ) );
        add_filter( 'handle_bulk_actions-edit-pit_item', array( $this, 'handle_bulk_export' ), 10, 3 );
    }

    /**
     * Register REST API routes for import and export.
     */
    public function register_rest_routes() {
        register_rest_route(
            'pit/v1',
            '/export',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'rest_export' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => array(
                    'nonce' => array( 'required' => true ),
                ),
            )
        );

        register_rest_route(
            'pit/v1',
            '/import',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'rest_import' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => array(
                    'nonce'   => array( 'required' => true ),
                    'preview' => array( 'required' => false ),
                ),
            )
        );
    }

    /**
     * Permission check for REST requests.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool
     */
    public function permissions_check( $request ) {
        $nonce = $request->get_param( 'nonce' );
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return false;
        }
        return current_user_can( 'manage_options' );
    }

    /**
     * Export data via REST.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|void
     */
    public function rest_export( $request ) {
        $csv = $this->generate_csv();

        return new WP_REST_Response( $csv, 200, array(
            'Content-Type'              => 'text/csv; charset=UTF-8',
            'Content-Disposition'       => 'attachment; filename="pit-export.csv"',
        ) );
    }

    /**
     * Import data via REST.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function rest_import( $request ) {
        $preview = (bool) $request->get_param( 'preview' );
        $mapping = $request->get_param( 'mapping' );
        $file    = $request->get_file_params();

        if ( empty( $file['file'] ) || UPLOAD_ERR_OK !== $file['file']['error'] ) {
            return new WP_REST_Response( array( 'error' => 'Upload failed' ), 400 );
        }

        $handle = fopen( $file['file']['tmp_name'], 'r' );
        if ( ! $handle ) {
            return new WP_REST_Response( array( 'error' => 'Unable to open file' ), 400 );
        }

        $rows = $this->parse_csv( $handle, $mapping );
        fclose( $handle );

        if ( $preview ) {
            return new WP_REST_Response( array_slice( $rows, 0, 5 ) );
        }

        $imported = $this->upsert_items( $rows );
        return new WP_REST_Response( array( 'imported' => $imported ) );
    }

    /**
     * Register bulk export action in list table.
     *
     * @param array $actions Existing actions.
     * @return array
     */
    public function register_bulk_export( $actions ) {
        $actions['pit_export'] = __( 'Export to CSV', 'personal-inventory-tracker' );
        return $actions;
    }

    /**
     * Handle bulk export action.
     *
     * @param string $redirect_to Redirect URL.
     * @param string $doaction Action name.
     * @param array  $post_ids Selected IDs.
     * @return string
     */
    public function handle_bulk_export( $redirect_to, $doaction, $post_ids ) {
        if ( 'pit_export' !== $doaction ) {
            return $redirect_to;
        }

        if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-posts' ) ) {
            wp_die( esc_html__( 'Security check failed', 'personal-inventory-tracker' ) );
        }

        $items = get_posts( array(
            'post_type'      => 'pit_item',
            'post__in'       => array_map( 'intval', $post_ids ),
            'posts_per_page' => -1,
        ) );
        $csv = $this->generate_csv( $items );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="pit-export.csv"' );
        echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * Generate CSV for items.
     *
     * @param WP_Post[]|null $items Optional list of items.
     * @return string CSV string.
     */
    public function generate_csv( $items = null ) {
        if ( null === $items ) {
            $items = get_posts( array(
                'post_type'      => 'pit_item',
                'posts_per_page' => -1,
            ) );
        }

        $fh  = fopen( 'php://temp', 'w+' );
        // Add BOM for UTF-8 Excel compatibility.
        fprintf( $fh, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

        $headers = array( 'id', 'name', 'category_slug', 'qty', 'unit', 'reorder_threshold', 'estimated_interval_days', 'last_purchased', 'notes' );
        fputcsv( $fh, $headers );

        foreach ( $items as $item ) {
            $category = '';
            $terms    = get_the_terms( $item->ID, 'pit_category' );
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $category = $terms[0]->slug;
            }

            $row = array(
                $item->ID,
                $item->post_title,
                $category,
                get_post_meta( $item->ID, 'qty', true ),
                get_post_meta( $item->ID, 'unit', true ),
                get_post_meta( $item->ID, 'reorder_threshold', true ),
                get_post_meta( $item->ID, 'estimated_interval_days', true ),
                get_post_meta( $item->ID, 'last_purchased', true ),
                get_post_meta( $item->ID, 'notes', true ),
            );
            fputcsv( $fh, array_map( 'sanitize_text_field', $row ) );
        }

        rewind( $fh );
        $csv = stream_get_contents( $fh );
        fclose( $fh );
        return $csv;
    }

    /**
     * Parse CSV file into array of associative arrays.
     *
     * @param resource    $handle  File handle.
     * @param array|null  $mapping Column mapping.
     * @return array
     */
    protected function parse_csv( $handle, $mapping = null ) {
        $rows     = array();
        $headers  = fgetcsv( $handle );
        if ( ! $headers ) {
            return $rows;
        }

        if ( ! $mapping ) {
            $mapping = array();
            foreach ( $headers as $index => $header ) {
                $mapping[ $index ] = $header;
            }
        }

        while ( ( $data = fgetcsv( $handle ) ) ) {
            $row = array();
            foreach ( $mapping as $index => $field ) {
                $value       = isset( $data[ $index ] ) ? $data[ $index ] : '';
                $row[ $field ] = sanitize_text_field( $value );
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Upsert items from CSV rows.
     *
     * @param array $rows Rows of data.
     * @return int Number of items processed.
     */
    protected function upsert_items( $rows ) {
        $count = 0;
        foreach ( $rows as $row ) {
            if ( empty( $row['name'] ) ) {
                continue;
            }
            $existing = get_page_by_title( $row['name'], OBJECT, 'pit_item' );
            $data     = array(
                'post_title'  => $row['name'],
                'post_type'   => 'pit_item',
                'post_status' => 'publish',
            );

            if ( $existing ) {
                $data['ID'] = $existing->ID;
                wp_update_post( $data );
                $item_id = $existing->ID;
            } else {
                $item_id = wp_insert_post( $data );
            }

            if ( ! empty( $row['category_slug'] ) ) {
                wp_set_post_terms( $item_id, array( $row['category_slug'] ), 'pit_category', false );
            }

            $meta_fields = array( 'qty', 'unit', 'reorder_threshold', 'estimated_interval_days', 'last_purchased', 'notes' );
            foreach ( $meta_fields as $field ) {
                if ( isset( $row[ $field ] ) ) {
                    update_post_meta( $item_id, $field, sanitize_text_field( $row[ $field ] ) );
                }
            }
            $count++;
        }
        return $count;
    }
}
