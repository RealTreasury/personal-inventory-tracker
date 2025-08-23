<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class PIT_List_Table extends WP_List_Table {
    private $items_data = [];

    public function __construct() {
        parent::__construct([
            'singular' => 'pit_item',
            'plural'   => 'pit_items',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'             => '<input type="checkbox" />',
            'name'           => __( 'Name', 'pit' ),
            'category'       => __( 'Category', 'pit' ),
            'qty'            => __( 'Qty', 'pit' ),
            'unit'           => __( 'Unit', 'pit' ),
            'last_purchased' => __( 'Last Purchased', 'pit' ),
            'threshold'      => __( 'Threshold', 'pit' ),
            'interval'       => __( 'Interval', 'pit' ),
            'status'         => __( 'Status', 'pit' ),
        ];
    }

    protected function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="id[]" value="%s" />', $item['id'] );
    }

    protected function column_name( $item ) {
        $actions = [
            'edit'          => sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=pit-add-item&id=' . $item['id'] ) ), __( 'Edit', 'pit' ) ),
            'quick_adjust'  => sprintf( '<a href="%s">%s</a>', esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pit_quick_adjust&id=' . $item['id'] ), 'pit_quick_adjust_' . $item['id'] ) ), __( 'Quick Adjust', 'pit' ) ),
            'mark_purchased'=> sprintf( '<a href="%s">%s</a>', esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pit_mark_purchased&id=' . $item['id'] ), 'pit_mark_purchased_' . $item['id'] ) ), __( 'Mark Purchased Today', 'pit' ) ),
        ];
        return sprintf( '%1$s %2$s', esc_html( $item['name'] ), $this->row_actions( $actions ) );
    }

    protected function get_sortable_columns() {
        return [
            'name'     => [ 'name', true ],
            'category' => [ 'category', false ],
            'status'   => [ 'status', false ],
        ];
    }

    protected function get_bulk_actions() {
        return [
            'delete' => __( 'Delete', 'pit' ),
            'export' => __( 'Export', 'pit' ),
        ];
    }

    public function process_bulk_action() {
        if ( 'delete' === $this->current_action() && ! empty( $_POST['id'] ) ) {
            check_admin_referer( 'bulk-' . $this->_args['plural'] );
            $ids = array_map( 'intval', (array) $_POST['id'] );
            $items = PIT_Admin::get_items();
            foreach ( $ids as $id ) {
                unset( $items[ $id ] );
            }
            update_option( 'pit_items', $items );
        }

        if ( 'export' === $this->current_action() && ! empty( $_POST['id'] ) ) {
            check_admin_referer( 'bulk-' . $this->_args['plural'] );
            $ids = array_map( 'intval', (array) $_POST['id'] );
            $items = PIT_Admin::get_items();
            header( 'Content-Type: text/csv' );
            header( 'Content-Disposition: attachment;filename=pit-export.csv' );
            $out = fopen( 'php://output', 'w' );
            if ( ! empty( $items ) ) {
                fputcsv( $out, array_keys( reset( $items ) ) );
            }
            foreach ( $ids as $id ) {
                if ( isset( $items[ $id ] ) ) {
                    fputcsv( $out, $items[ $id ] );
                }
            }
            fclose( $out );
            exit;
        }
    }

    public function prepare_items() {
        $items = PIT_Admin::get_items();

        if ( ! empty( $_REQUEST['s'] ) ) {
            $search = wp_unslash( $_REQUEST['s'] );
            $items = array_filter( $items, function( $item ) use ( $search ) {
                return false !== stripos( $item['name'], $search );
            } );
        }

        if ( ! empty( $_REQUEST['pit_category'] ) ) {
            $cat = wp_unslash( $_REQUEST['pit_category'] );
            $items = array_filter( $items, function( $item ) use ( $cat ) {
                return $item['category'] === $cat;
            } );
        }

        if ( ! empty( $_REQUEST['pit_status'] ) ) {
            $status = wp_unslash( $_REQUEST['pit_status'] );
            $items = array_filter( $items, function( $item ) use ( $status ) {
                return $item['status'] === $status;
            } );
        }

        $this->items_data = $items;

        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [ $columns, $hidden, $sortable ];

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = count( $items );
        $this->items = array_slice( $items, ( $current_page - 1 ) * $per_page, $per_page );
        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ] );
    }

    protected function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }
        $items = PIT_Admin::get_items();
        $categories = array_unique( wp_list_pluck( $items, 'category' ) );
        echo '<div class="alignleft actions">';
        echo '<label class="screen-reader-text" for="pit-category">' . esc_html__( 'Filter by category', 'pit' ) . '</label>';
        echo '<select name="pit_category" id="pit-category">';
        echo '<option value="">' . esc_html__( 'All categories', 'pit' ) . '</option>';
        foreach ( $categories as $cat ) {
            printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $cat ), selected( $_REQUEST['pit_category'] ?? '', $cat, false ) );
        }
        echo '</select>';
        echo '<label class="screen-reader-text" for="pit-status">' . esc_html__( 'Filter by status', 'pit' ) . '</label>';
        echo '<select name="pit_status" id="pit-status">';
        echo '<option value="">' . esc_html__( 'All status', 'pit' ) . '</option>';
        $statuses = [ 'in-stock' => __( 'In Stock', 'pit' ), 'low' => __( 'Low', 'pit' ), 'out' => __( 'Out', 'pit' ) ];
        foreach ( $statuses as $val => $label ) {
            printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $val ), selected( $_REQUEST['pit_status'] ?? '', $val, false ), esc_html( $label ) );
        }
        echo '</select>';
        submit_button( __( 'Filter' ), 'button', false, false, [ 'id' => 'pit-filter-submit' ] );
        echo '</div>';
    }
    protected function column_default( $item, $column_name ) {
        return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
    }
}
