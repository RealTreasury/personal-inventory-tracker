<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIT_Admin {

    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_post_pit_save_item', array( $this, 'save_item' ) );
        add_action( 'admin_post_pit_quick_adjust', array( $this, 'quick_adjust' ) );
        add_action( 'admin_post_pit_mark_purchased', array( $this, 'mark_purchased' ) );
        add_action( 'admin_post_pit_ocr_update', array( $this, 'ocr_update' ) );
        add_action( 'admin_notices', array( $this, 'maybe_show_notices' ) );
        add_action( 'admin_notices', array( $this, 'intro_notice' ) );
        add_action( 'admin_init', array( $this, 'maybe_dismiss_intro' ) );
    }

    public function register_menu() {
        $cap = 'manage_options';
        add_menu_page( __( 'Inventory (PIT)', 'personal-inventory-tracker' ), __( 'Inventory (PIT)', 'personal-inventory-tracker' ), $cap, 'pit_dashboard', array( $this, 'dashboard_page' ), 'dashicons-archive', 26 );
        add_submenu_page( 'pit_dashboard', __( 'Dashboard', 'personal-inventory-tracker' ), __( 'Dashboard', 'personal-inventory-tracker' ), $cap, 'pit_dashboard', array( $this, 'dashboard_page' ) );
        add_submenu_page( 'pit_dashboard', __( 'Items', 'personal-inventory-tracker' ), __( 'Items', 'personal-inventory-tracker' ), $cap, 'pit_items', array( $this, 'items_page' ) );
        add_submenu_page( 'pit_dashboard', __( 'Add/Edit Item', 'personal-inventory-tracker' ), __( 'Add/Edit Item', 'personal-inventory-tracker' ), $cap, 'pit_add_item', array( $this, 'add_item_page' ) );
        add_submenu_page( 'pit_dashboard', __( 'Import/Export', 'personal-inventory-tracker' ), __( 'Import/Export', 'personal-inventory-tracker' ), $cap, 'pit_import_export', array( $this, 'import_export_page' ) );
        add_submenu_page( 'pit_dashboard', __( 'OCR Receipt', 'personal-inventory-tracker' ), __( 'OCR Receipt', 'personal-inventory-tracker' ), $cap, 'pit_ocr_receipt', array( $this, 'ocr_receipt_page' ) );
    }

    public function dashboard_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Inventory Dashboard', 'personal-inventory-tracker' ) . '</h1></div>';
    }

    public function items_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        require_once PIT_PLUGIN_DIR . 'includes/class-pit-list-table.php';
        $list_table = new PIT_List_Table();
        $list_table->process_bulk_action();
        $list_table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( 'Items', 'personal-inventory-tracker' ) . '</h1>';
        echo ' <a href="' . esc_url( admin_url( 'admin.php?page=pit_add_item' ) ) . '" class="page-title-action">' . esc_html__( 'Add New', 'personal-inventory-tracker' ) . '</a>';
        echo '<hr class="wp-header-end" />';

        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="pit_items" />';
        $list_table->search_box( __( 'Search Items', 'personal-inventory-tracker' ), 'pit-item' );
        $this->category_filter();
        $this->status_filter();
        $list_table->display();
        echo '</form>';
        echo '</div>';
    }

    private function category_filter() {
        $selected = isset( $_REQUEST['pit_category'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['pit_category'] ) ) : '';
        wp_dropdown_categories(
            array(
                'show_option_all' => __( 'All Categories', 'personal-inventory-tracker' ),
                'taxonomy'       => 'pit_category',
                'name'           => 'pit_category',
                'orderby'        => 'name',
                'selected'       => $selected,
                'hierarchical'   => true,
                'show_count'     => true,
                'hide_empty'     => false,
            )
        );
    }

    private function status_filter() {
        $statuses = array(
            ''               => __( 'All Statuses', 'personal-inventory-tracker' ),
            'in_stock'       => __( 'In Stock', 'personal-inventory-tracker' ),
            'needs_purchase' => __( 'Needs Purchase', 'personal-inventory-tracker' ),
            'inactive'       => __( 'Inactive', 'personal-inventory-tracker' ),
        );
        $selected = isset( $_REQUEST['pit_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['pit_status'] ) ) : '';
        echo '<select name="pit_status">';
        foreach ( $statuses as $value => $label ) {
            printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $selected, $value, false ), esc_html( $label ) );
        }
        echo '</select>';
        submit_button( __( 'Filter' ), '', 'filter_action', false );
    }

    public function add_item_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $item_id = isset( $_GET['item_id'] ) ? absint( $_GET['item_id'] ) : 0;
        $item    = $item_id ? get_post( $item_id ) : null;

        echo '<div class="wrap"><h1>' . esc_html__( 'Add/Edit Item', 'personal-inventory-tracker' ) . '</h1>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'pit_save_item' );
        echo '<input type="hidden" name="action" value="pit_save_item" />';
        echo '<input type="hidden" name="item_id" value="' . esc_attr( $item_id ) . '" />';
        echo '<table class="form-table"><tbody>';

        echo '<tr><th><label for="pit_item_name">' . esc_html__( 'Name', 'personal-inventory-tracker' ) . '</label></th>';
        echo '<td><input name="pit_item_name" id="pit_item_name" type="text" class="regular-text" value="' . esc_attr( $item ? $item->post_title : '' ) . '" required /></td></tr>';

        echo '<tr><th><label for="pit_category">' . esc_html__( 'Category', 'personal-inventory-tracker' ) . '</label></th><td>';
        wp_dropdown_categories(
            array(
                'taxonomy'         => 'pit_category',
                'name'             => 'pit_category',
                'selected'         => $item_id ? wp_get_post_terms( $item_id, 'pit_category', array( 'fields' => 'ids' ) )[0] ?? 0 : 0,
                'show_option_none' => __( 'Select category', 'personal-inventory-tracker' ),
                'hide_empty'       => false,
            )
        );
        echo '</td></tr>';

        $qty       = $item_id ? get_post_meta( $item_id, 'pit_qty', true ) : '';
        $unit      = $item_id ? get_post_meta( $item_id, 'pit_unit', true ) : '';
        $last      = $item_id ? get_post_meta( $item_id, 'pit_last_purchased', true ) : '';
        $threshold = $item_id ? get_post_meta( $item_id, 'pit_threshold', true ) : '';
        $interval  = $item_id ? get_post_meta( $item_id, 'pit_interval', true ) : '';
        $status    = $item_id ? get_post_meta( $item_id, 'pit_status', true ) : 'in_stock';

        echo '<tr><th><label for="pit_qty">' . esc_html__( 'Quantity', 'personal-inventory-tracker' ) . '</label></th>';
        echo '<td><input name="pit_qty" id="pit_qty" type="number" value="' . esc_attr( $qty ) . '" /></td></tr>';

        echo '<tr><th><label for="pit_unit">' . esc_html__( 'Unit', 'personal-inventory-tracker' ) . '</label></th>';
        echo '<td><input name="pit_unit" id="pit_unit" type="text" value="' . esc_attr( $unit ) . '" /></td></tr>';

        echo '<tr><th><label for="pit_last_purchased">' . esc_html__( 'Last Purchased', 'personal-inventory-tracker' ) . '</label></th>';
        echo '<td><input name="pit_last_purchased" id="pit_last_purchased" type="date" value="' . esc_attr( $last ) . '" /></td></tr>';

        echo '<tr><th><label for="pit_threshold">' . esc_html__( 'Threshold', 'personal-inventory-tracker' ) . '</label></th>';
        echo '<td><input name="pit_threshold" id="pit_threshold" type="number" value="' . esc_attr( $threshold ) . '" /></td></tr>';

        echo '<tr><th><label for="pit_interval">' . esc_html__( 'Interval (days)', 'personal-inventory-tracker' ) . '</label></th>';
        echo '<td><input name="pit_interval" id="pit_interval" type="number" value="' . esc_attr( $interval ) . '" /></td></tr>';

        $statuses = array(
            'in_stock'       => __( 'In Stock', 'personal-inventory-tracker' ),
            'needs_purchase' => __( 'Needs Purchase', 'personal-inventory-tracker' ),
            'inactive'       => __( 'Inactive', 'personal-inventory-tracker' ),
        );
        echo '<tr><th><label for="pit_status">' . esc_html__( 'Status', 'personal-inventory-tracker' ) . '</label></th><td><select name="pit_status" id="pit_status">';
        foreach ( $statuses as $value => $label ) {
            printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $status, $value, false ), esc_html( $label ) );
        }
        echo '</select></td></tr>';

        echo '</tbody></table>';
        submit_button( $item_id ? __( 'Update Item', 'personal-inventory-tracker' ) : __( 'Add Item', 'personal-inventory-tracker' ) );
        echo '</form></div>';
    }

    public function save_item() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'personal-inventory-tracker' ) );
        }
        check_admin_referer( 'pit_save_item' );

        $item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
        $title   = isset( $_POST['pit_item_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pit_item_name'] ) ) : '';

        $post_data = array(
            'post_title'  => $title,
            'post_type'   => 'pit_item',
            'post_status' => 'publish',
        );

        if ( $item_id ) {
            $post_data['ID'] = $item_id;
            $item_id         = wp_update_post( $post_data, true );
        } else {
            $item_id = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $item_id ) ) {
            wp_safe_redirect( add_query_arg( 'pit_message', 'error', admin_url( 'admin.php?page=pit_items' ) ) );
            exit;
        }

        if ( isset( $_POST['pit_category'] ) ) {
            wp_set_post_terms( $item_id, array( absint( $_POST['pit_category'] ) ), 'pit_category', false );
        }

        $fields = array( 'pit_qty', 'pit_unit', 'pit_last_purchased', 'pit_threshold', 'pit_interval', 'pit_status' );
        foreach ( $fields as $field ) {
            $value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
            update_post_meta( $item_id, $field, $value );
        }

        wp_safe_redirect( add_query_arg( 'pit_message', 'saved', admin_url( 'admin.php?page=pit_items' ) ) );
        exit;
    }

    public function quick_adjust() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'personal-inventory-tracker' ) );
        }
        $item_id = isset( $_GET['item_id'] ) ? absint( $_GET['item_id'] ) : 0;
        check_admin_referer( 'pit_quick_adjust_' . $item_id );
        $qty = (int) get_post_meta( $item_id, 'pit_qty', true );
        update_post_meta( $item_id, 'pit_qty', $qty + 1 );
        wp_safe_redirect( add_query_arg( 'pit_message', 'adjusted', admin_url( 'admin.php?page=pit_items' ) ) );
        exit;
    }

    public function mark_purchased() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'personal-inventory-tracker' ) );
        }
        $item_id = isset( $_GET['item_id'] ) ? absint( $_GET['item_id'] ) : 0;
        check_admin_referer( 'pit_mark_purchased_' . $item_id );
        update_post_meta( $item_id, 'pit_last_purchased', current_time( 'Y-m-d' ) );
        wp_safe_redirect( add_query_arg( 'pit_message', 'purchased', admin_url( 'admin.php?page=pit_items' ) ) );
        exit;
    }

    public function import_export_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Import/Export', 'personal-inventory-tracker' ) . '</h1>';
        echo '<p>' . esc_html__( 'Export and import inventory items via CSV.', 'personal-inventory-tracker' ) . '</p>';
        echo '</div>';
    }

    public function ocr_receipt_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $items = get_posts( array(
            'post_type'      => 'pit_item',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ) );
        $choices = array();
        foreach ( $items as $item ) {
            $choices[] = array( 'id' => $item->ID, 'name' => $item->post_title );
        }

        echo '<div class="wrap"><h1>' . esc_html__( 'OCR Receipt', 'personal-inventory-tracker' ) . '</h1>';
        echo '<input type="file" id="pit-ocr-file" accept="image/*" />';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pit_ocr_update" />';
        wp_nonce_field( 'pit_ocr_update' );
        echo '<table class="widefat" id="pit-ocr-results"><thead><tr><th>' . esc_html__( 'Line', 'personal-inventory-tracker' ) . '</th><th>' . esc_html__( 'Item', 'personal-inventory-tracker' ) . '</th><th>' . esc_html__( 'Qty', 'personal-inventory-tracker' ) . '</th></tr></thead><tbody></tbody></table>';
        echo '<p><button class="button button-primary" id="pit-ocr-submit" type="submit">' . esc_html__( 'Update Inventory', 'personal-inventory-tracker' ) . '</button></p>';
        echo '</form></div>';

        echo '<script src="https://cdn.jsdelivr.net/npm/tesseract.js@2/dist/tesseract.min.js"></script>';
        echo '<script>const pitItems=' . wp_json_encode( $choices ) . ';' . file_get_contents( PIT_PLUGIN_DIR . 'includes/js/ocr.js' ) . '</script>';
    }

    public function ocr_update() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'personal-inventory-tracker' ) );
        }
        check_admin_referer( 'pit_ocr_update' );

        $updates = isset( $_POST['pit_ocr_updates'] ) ? json_decode( wp_unslash( $_POST['pit_ocr_updates'] ), true ) : array();
        if ( is_array( $updates ) ) {
            foreach ( $updates as $update ) {
                $id  = absint( $update['id'] );
                $qty = isset( $update['qty'] ) ? (int) $update['qty'] : 0;
                if ( $id && $qty ) {
                    $current = (int) get_post_meta( $id, 'pit_qty', true );
                    update_post_meta( $id, 'pit_qty', $current + $qty );
                }
            }
        }

        wp_safe_redirect( add_query_arg( 'pit_message', 'ocr_updated', admin_url( 'admin.php?page=pit_items' ) ) );
        exit;
    }

    public function maybe_show_notices() {
        if ( empty( $_GET['pit_message'] ) ) {
            return;
        }
        $message = sanitize_text_field( wp_unslash( $_GET['pit_message'] ) );
        $messages = array(
            'saved'     => __( 'Item saved.', 'personal-inventory-tracker' ),
            'adjusted'  => __( 'Quantity adjusted.', 'personal-inventory-tracker' ),
            'purchased' => __( 'Marked as purchased today.', 'personal-inventory-tracker' ),
            'ocr_updated' => __( 'Inventory updated from receipt.', 'personal-inventory-tracker' ),
            'error'     => __( 'An error occurred.', 'personal-inventory-tracker' ),
        );
        if ( isset( $messages[ $message ] ) ) {
            printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $messages[ $message ] ) );
        }
    }

    public function intro_notice() {
        if ( get_option( 'pit_intro_dismissed' ) ) {
            return;
        }
        $dismiss_url = add_query_arg( 'pit_dismiss_intro', 1 );
        echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Welcome to Personal Inventory Tracker!', 'personal-inventory-tracker' ) . '</p><p><a href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss', 'personal-inventory-tracker' ) . '</a></p></div>';
    }

    public function maybe_dismiss_intro() {
        if ( isset( $_GET['pit_dismiss_intro'] ) ) {
            update_option( 'pit_intro_dismissed', 1 );
        }
    }
}
