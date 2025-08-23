<?php
/**
 * Plugin Name: Personal Inventory Tracker
 * Description: Simple inventory tracking plugin with secure form and REST handling.
 * Version: 1.0.0
 * Author: OpenAI Assistant
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Register the admin menu for inventory management.
 */
function pit_register_admin_menu() {
    add_menu_page(
        __( 'Inventory', 'personal-inventory-tracker' ),
        __( 'Inventory', 'personal-inventory-tracker' ),
        'manage_options',
        'pit_inventory',
        'pit_render_admin_page'
    );
}
add_action( 'admin_menu', 'pit_register_admin_menu' );

/**
 * Render the admin page and handle form submissions.
 */
function pit_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_POST['pit_new_item_nonce'] ) && check_admin_referer( 'pit_add_item', 'pit_new_item_nonce' ) ) {
        $item_name = isset( $_POST['item_name'] ) ? sanitize_text_field( $_POST['item_name'] ) : '';
        $item_qty  = isset( $_POST['item_qty'] ) ? absint( $_POST['item_qty'] ) : 0;

        update_option( 'pit_last_item', array(
            'name' => $item_name,
            'qty'  => $item_qty,
        ) );

        echo '<div class="updated"><p>' . esc_html__( 'Item saved.', 'personal-inventory-tracker' ) . '</p></div>';
    }

    $stored = get_option( 'pit_last_item', array( 'name' => '', 'qty' => 0 ) );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Inventory', 'personal-inventory-tracker' ); ?></h1>
        <form method="post">
            <?php wp_nonce_field( 'pit_add_item', 'pit_new_item_nonce' ); ?>
            <p>
                <label for="item_name"><?php esc_html_e( 'Item Name', 'personal-inventory-tracker' ); ?></label>
                <input type="text" name="item_name" id="item_name" value="<?php echo esc_attr( $stored['name'] ); ?>" />
            </p>
            <p>
                <label for="item_qty"><?php esc_html_e( 'Quantity', 'personal-inventory-tracker' ); ?></label>
                <input type="number" name="item_qty" id="item_qty" value="<?php echo esc_attr( $stored['qty'] ); ?>" />
            </p>
            <?php submit_button( __( 'Save Item', 'personal-inventory-tracker' ) ); ?>
        </form>
    </div>
    <?php
}

/**
 * Register REST API routes.
 */
function pit_register_routes() {
    register_rest_route( 'pit/v1', '/item', array(
        'methods'             => 'POST',
        'permission_callback' => 'pit_rest_permission',
        'callback'            => 'pit_rest_add_item',
    ) );
}
add_action( 'rest_api_init', 'pit_register_routes' );

/**
 * REST API permission check.
 *
 * @param WP_REST_Request $request REST request.
 * @return true|WP_Error
 */
function pit_rest_permission( WP_REST_Request $request ) {
    $nonce = $request->get_header( 'X-WP-Nonce' );
    if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return new WP_Error( 'rest_forbidden', __( 'Invalid nonce.', 'personal-inventory-tracker' ), array( 'status' => 403 ) );
    }

    return current_user_can( 'edit_posts' );
}

/**
 * REST API callback to add an item.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function pit_rest_add_item( WP_REST_Request $request ) {
    $name = sanitize_text_field( $request->get_param( 'name' ) );
    $qty  = absint( $request->get_param( 'qty' ) );

    $item = array(
        'name' => $name,
        'qty'  => $qty,
    );

    update_option( 'pit_rest_item', $item );

    return rest_ensure_response( array(
        'success' => true,
        'item'    => array(
            'name' => esc_html( $item['name'] ),
            'qty'  => (int) $item['qty'],
        ),
    ) );
}
