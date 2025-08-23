<?php
require_once __DIR__ . '/class-pit-list-table.php';
class PIT_Admin {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        add_option( 'pit_items', [] );
        add_option( 'pit_show_intro', 1 );
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'maybe_dismiss_intro' ] );
        add_action( 'admin_notices', [ $this, 'admin_notices' ] );

        add_action( 'admin_post_pit_save_item', [ $this, 'handle_save_item' ] );
        add_action( 'admin_post_pit_quick_adjust', [ $this, 'handle_quick_adjust' ] );
        add_action( 'admin_post_pit_mark_purchased', [ $this, 'handle_mark_purchased' ] );

        add_action( 'wp_ajax_pit_update_items', [ $this, 'ajax_update_items' ] );
    }

    public static function get_items() {
        $items = get_option( 'pit_items', [] );
        return is_array( $items ) ? $items : [];
    }

    public function register_menu() {
        add_menu_page( __( 'Inventory (PIT)', 'pit' ), __( 'Inventory (PIT)', 'pit' ), 'manage_options', 'pit-dashboard', [ $this, 'render_dashboard' ], 'dashicons-archive' );
        add_submenu_page( 'pit-dashboard', __( 'Dashboard', 'pit' ), __( 'Dashboard', 'pit' ), 'manage_options', 'pit-dashboard', [ $this, 'render_dashboard' ] );
        add_submenu_page( 'pit-dashboard', __( 'Items', 'pit' ), __( 'Items', 'pit' ), 'manage_options', 'pit-items', [ $this, 'render_items' ] );
        add_submenu_page( 'pit-dashboard', __( 'Add/Edit Item', 'pit' ), __( 'Add/Edit Item', 'pit' ), 'manage_options', 'pit-add-item', [ $this, 'render_add_item' ] );
        add_submenu_page( 'pit-dashboard', __( 'Import/Export', 'pit' ), __( 'Import/Export', 'pit' ), 'manage_options', 'pit-import-export', [ $this, 'render_import_export' ] );
        add_submenu_page( 'pit-dashboard', __( 'OCR Receipt', 'pit' ), __( 'OCR Receipt', 'pit' ), 'manage_options', 'pit-ocr', [ $this, 'render_ocr' ] );
    }

    public function render_dashboard() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Inventory Dashboard', 'pit' ) . '</h1></div>';
    }

    public function render_items() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $list_table = new PIT_List_Table();
        $list_table->prepare_items();
        echo '<div class="wrap"><h1>' . esc_html__( 'Items', 'pit' ) . '</h1>';
        echo '<form method="post">';
        $list_table->search_box( __( 'Search Items', 'pit' ), 'pit-search' );
        $list_table->display();
        echo '</form></div>';
    }

    public function render_add_item() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $items = self::get_items();
        $id    = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        $item  = $id && isset( $items[ $id ] ) ? $items[ $id ] : [
            'name' => '',
            'category' => '',
            'qty' => 0,
            'unit' => '',
            'last_purchased' => '',
            'threshold' => 0,
            'interval' => '',
            'status' => '',
        ];
        echo '<div class="wrap"><h1>' . ( $id ? esc_html__( 'Edit Item', 'pit' ) : esc_html__( 'Add Item', 'pit' ) ) . '</h1>';
        settings_errors( 'pit_messages' );
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'pit_save_item', 'pit_nonce' );
        echo '<input type="hidden" name="action" value="pit_save_item" />';
        if ( $id ) {
            echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '" />';
        }
        echo '<table class="form-table"><tr><th><label for="pit-name">' . esc_html__( 'Name', 'pit' ) . '</label></th><td><input name="name" type="text" id="pit-name" value="' . esc_attr( $item['name'] ) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="pit-category">' . esc_html__( 'Category', 'pit' ) . '</label></th><td><input name="category" type="text" id="pit-category" value="' . esc_attr( $item['category'] ) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="pit-qty">' . esc_html__( 'Quantity', 'pit' ) . '</label></th><td><input name="qty" type="number" step="0.01" id="pit-qty" value="' . esc_attr( $item['qty'] ) . '" class="small-text"></td></tr>';
        echo '<tr><th><label for="pit-unit">' . esc_html__( 'Unit', 'pit' ) . '</label></th><td><input name="unit" type="text" id="pit-unit" value="' . esc_attr( $item['unit'] ) . '" class="regular-text"></td></tr>';
        echo '<tr><th><label for="pit-last">' . esc_html__( 'Last Purchased', 'pit' ) . '</label></th><td><input name="last_purchased" type="date" id="pit-last" value="' . esc_attr( $item['last_purchased'] ) . '"></td></tr>';
        echo '<tr><th><label for="pit-threshold">' . esc_html__( 'Threshold', 'pit' ) . '</label></th><td><input name="threshold" type="number" step="0.01" id="pit-threshold" value="' . esc_attr( $item['threshold'] ) . '" class="small-text"></td></tr>';
        echo '<tr><th><label for="pit-interval">' . esc_html__( 'Interval', 'pit' ) . '</label></th><td><input name="interval" type="text" id="pit-interval" value="' . esc_attr( $item['interval'] ) . '"></td></tr>';
        echo '<tr><th><label for="pit-status">' . esc_html__( 'Status', 'pit' ) . '</label></th><td><select name="status" id="pit-status"><option value="in-stock"' . selected( $item['status'], 'in-stock', false ) . '>' . esc_html__( 'In Stock', 'pit' ) . '</option><option value="low"' . selected( $item['status'], 'low', false ) . '>' . esc_html__( 'Low', 'pit' ) . '</option><option value="out"' . selected( $item['status'], 'out', false ) . '>' . esc_html__( 'Out', 'pit' ) . '</option></select></td></tr>';
        echo '</table>';
        submit_button( $id ? __( 'Update Item', 'pit' ) : __( 'Add Item', 'pit' ) );
        echo '</form></div>';
    }

    public function render_import_export() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Import/Export', 'pit' ) . '</h1><p>' . esc_html__( 'Coming soon...', 'pit' ) . '</p></div>';
    }

    public function render_ocr() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        wp_enqueue_script( 'tesseract', 'https://cdn.jsdelivr.net/npm/tesseract.js@2/dist/tesseract.min.js', [], null, true );
        wp_enqueue_script( 'pit-ocr', plugins_url( 'assets/js/pit-ocr.js', dirname( __FILE__ ) ), [ 'tesseract' ], '1.0', true );
        wp_localize_script( 'pit-ocr', 'pitOCR', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'pit_ocr' ),
            'items'    => self::get_items(),
        ] );
        echo '<div class="wrap"><h1>' . esc_html__( 'OCR Receipt', 'pit' ) . '</h1>';
        echo '<input type="file" id="pit-receipt" accept="image/*" />';
        echo '<div id="pit-ocr-results"></div>';
        echo '<button id="pit-ocr-update" class="button button-primary">' . esc_html__( 'Update Inventory', 'pit' ) . '</button>';
        echo '</div>';
    }

    public function handle_save_item() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'pit' ) );
        }
        check_admin_referer( 'pit_save_item', 'pit_nonce' );
        $items = self::get_items();
        $id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $data  = [
            'name' => sanitize_text_field( $_POST['name'] ?? '' ),
            'category' => sanitize_text_field( $_POST['category'] ?? '' ),
            'qty' => floatval( $_POST['qty'] ?? 0 ),
            'unit' => sanitize_text_field( $_POST['unit'] ?? '' ),
            'last_purchased' => sanitize_text_field( $_POST['last_purchased'] ?? '' ),
            'threshold' => floatval( $_POST['threshold'] ?? 0 ),
            'interval' => sanitize_text_field( $_POST['interval'] ?? '' ),
            'status' => sanitize_text_field( $_POST['status'] ?? '' ),
        ];
        if ( $id ) {
            $items[ $id ] = array_merge( [ 'id' => $id ], $data );
        } else {
            $id = time();
            $items[ $id ] = array_merge( [ 'id' => $id ], $data );
        }
        update_option( 'pit_items', $items );
        add_settings_error( 'pit_messages', 'pit_message', __( 'Item saved.', 'pit' ), 'updated' );
        set_transient( 'settings_errors', get_settings_errors(), 30 );
        wp_redirect( add_query_arg( [ 'page' => 'pit-add-item', 'id' => $id ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_quick_adjust() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'pit' ) );
        }
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        check_admin_referer( 'pit_quick_adjust_' . $id );
        $items = self::get_items();
        if ( isset( $items[ $id ] ) ) {
            $items[ $id ]['qty'] += 1;
            update_option( 'pit_items', $items );
            add_settings_error( 'pit_messages', 'pit_message', __( 'Quantity adjusted.', 'pit' ), 'updated' );
            set_transient( 'settings_errors', get_settings_errors(), 30 );
        }
        wp_redirect( admin_url( 'admin.php?page=pit-items' ) );
        exit;
    }

    public function handle_mark_purchased() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'pit' ) );
        }
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        check_admin_referer( 'pit_mark_purchased_' . $id );
        $items = self::get_items();
        if ( isset( $items[ $id ] ) ) {
            $items[ $id ]['last_purchased'] = current_time( 'Y-m-d' );
            update_option( 'pit_items', $items );
            add_settings_error( 'pit_messages', 'pit_message', __( 'Marked as purchased today.', 'pit' ), 'updated' );
            set_transient( 'settings_errors', get_settings_errors(), 30 );
        }
        wp_redirect( admin_url( 'admin.php?page=pit-items' ) );
        exit;
    }

    public function ajax_update_items() {
        check_ajax_referer( 'pit_ocr', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'unauthorized' );
        }
        $data = isset( $_POST['items'] ) ? json_decode( wp_unslash( $_POST['items'] ), true ) : [];
        if ( ! is_array( $data ) ) {
            $data = [];
        }
        $items = self::get_items();
        foreach ( $data as $id => $qty ) {
            $id  = intval( $id );
            $qty = floatval( $qty );
            if ( isset( $items[ $id ] ) ) {
                $items[ $id ]['qty'] += $qty;
            }
        }
        update_option( 'pit_items', $items );
        wp_send_json_success();
    }

    public function admin_notices() {
        if ( $errors = get_transient( 'settings_errors' ) ) {
            foreach ( $errors as $error ) {
                printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $error['type'] ), esc_html( $error['message'] ) );
            }
            delete_transient( 'settings_errors' );
        }
        if ( get_option( 'pit_show_intro' ) ) {
            $dismiss_url = add_query_arg( 'pit-dismiss-intro', '1' );
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Welcome to Personal Inventory Tracker!', 'pit' ) . '</p><p><a href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss', 'pit' ) . '</a></p></div>';
        }
    }

    public function maybe_dismiss_intro() {
        if ( isset( $_GET['pit-dismiss-intro'] ) ) {
            update_option( 'pit_show_intro', 0 );
            wp_redirect( remove_query_arg( 'pit-dismiss-intro' ) );
            exit;
        }
    }
}
