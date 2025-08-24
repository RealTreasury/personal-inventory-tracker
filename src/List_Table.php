<?php

namespace RealTreasury\Inventory;

use WP_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct(
            array(
                'singular' => 'pit_item',
                'plural'   => 'pit_items',
                'ajax'     => false,
            )
        );
    }

    public function get_columns() {
        return array(
            'cb'             => '<input type="checkbox" />',
            'name'           => __( 'Name', 'personal-inventory-tracker' ),
            'category'       => __( 'Category', 'personal-inventory-tracker' ),
            'qty'            => __( 'Qty', 'personal-inventory-tracker' ),
            'unit'           => __( 'Unit', 'personal-inventory-tracker' ),
            'last_purchased' => __( 'Last Purchased', 'personal-inventory-tracker' ),
            'threshold'      => __( 'Threshold', 'personal-inventory-tracker' ),
            'interval'       => __( 'Interval', 'personal-inventory-tracker' ),
            'status'         => __( 'Status', 'personal-inventory-tracker' ),
        );
    }

    protected function get_sortable_columns() {
        return array(
            'name'     => array( 'title', true ),
            'category' => array( 'category', false ),
            'qty'      => array( 'qty', false ),
            'status'   => array( 'status', false ),
        );
    }

    public function column_default( $item, $column_name ) {
        return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
    }

    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="item_ids[]" value="%s" />', esc_attr( $item['ID'] ) );
    }

    public function no_items() {
        esc_html_e( 'No inventory items found. Use the "Add New" button to create your first item.', 'personal-inventory-tracker' );
    }

    public function column_name( $item ) {
        $edit_link   = admin_url( 'admin.php?page=pit_add_item&item_id=' . absint( $item['ID'] ) );
        $adjust_link   = wp_nonce_url(
            admin_url( 'admin-post.php?action=pit_quick_adjust&item_id=' . absint( $item['ID'] ) ),
            'pit_quick_adjust_' . $item['ID'],
            'pit_nonce'
        );
        $purchase_link = wp_nonce_url(
            admin_url( 'admin-post.php?action=pit_mark_purchased&item_id=' . absint( $item['ID'] ) ),
            'pit_mark_purchased_' . $item['ID'],
            'pit_nonce'
        );

        $actions = array(
            'edit'           => '<a href="' . esc_url( $edit_link ) . '">' . __( 'Edit', 'personal-inventory-tracker' ) . '</a>',
            'quick_adjust'   => '<a href="' . esc_url( $adjust_link ) . '" onclick="return confirm(\'' . esc_js( __( 'Increase quantity for this item?', 'personal-inventory-tracker' ) ) . '\');">' . __( 'Quick Adjust', 'personal-inventory-tracker' ) . '</a>',
            'mark_purchased' => '<a href="' . esc_url( $purchase_link ) . '">' . __( 'Mark Purchased Today', 'personal-inventory-tracker' ) . '</a>',
        );

        return sprintf( '%1$s %2$s', esc_html( $item['name'] ), $this->row_actions( $actions ) );
    }

    protected function get_bulk_actions() {
        return array(
            'delete' => __( 'Delete', 'personal-inventory-tracker' ),
        );
    }

    public function prepare_items() {
        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $args         = array(
            'post_type'      => 'pit_item',
            'posts_per_page' => $per_page,
            'paged'          => $current_page,
            'post_status'    => 'any',
        );

        if ( ! empty( $_REQUEST['s'] ) ) {
            $args['s'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
        }

        if ( ! empty( $_REQUEST['pit_category'] ) ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'pit_category',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field( wp_unslash( $_REQUEST['pit_category'] ) ),
                ),
            );
        }

        if ( ! empty( $_REQUEST['pit_status'] ) ) {
            $args['meta_query'][] = array(
                'key'   => 'pit_status',
                'value' => sanitize_text_field( wp_unslash( $_REQUEST['pit_status'] ) ),
            );
        }

        $query = new \WP_Query( $args );
        $items = array();

        foreach ( $query->posts as $post ) {
            $items[] = array(
                'ID'             => $post->ID,
                'name'           => $post->post_title,
                'category'       => implode( ', ', wp_get_post_terms( $post->ID, 'pit_category', array( 'fields' => 'names' ) ) ),
                'qty'            => get_post_meta( $post->ID, 'pit_qty', true ),
                'unit'           => get_post_meta( $post->ID, 'pit_unit', true ),
                'last_purchased' => get_post_meta( $post->ID, 'pit_last_purchased', true ),
                'threshold'      => get_post_meta( $post->ID, 'pit_threshold', true ),
                'interval'       => get_post_meta( $post->ID, 'pit_interval', true ),
                'status'         => get_post_meta( $post->ID, 'pit_status', true ),
            );
        }

        $this->items = $items;

        $this->set_pagination_args(
            array(
                'total_items' => $query->found_posts,
                'per_page'    => $per_page,
                'total_pages' => $query->max_num_pages,
            )
        );
    }

    public function process_bulk_action() {
        $action = $this->current_action();
        if ( $action && ! empty( $_REQUEST['item_ids'] ) ) {
            check_admin_referer( 'bulk-' . $this->_args['plural'] );
            if ( 'delete' === $action ) {
                foreach ( (array) $_REQUEST['item_ids'] as $item_id ) {
                    wp_delete_post( absint( $item_id ), true );
                }
            }
        }
    }
}

\class_alias( __NAMESPACE__ . '\\List_Table', 'PIT_List_Table' );
