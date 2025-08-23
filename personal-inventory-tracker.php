<?php
/**
 * Plugin Name: Personal Inventory Tracker
 * Description: Track personal inventory items with a SPA front end.
 * Version: 1.0.0
 * Author: ChatGPT
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PIT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Register custom post type for inventory items.
 */
function pit_register_post_type() {
    register_post_type( 'pit_item', array(
        'label' => __( 'Inventory Items', 'pit' ),
        'public' => false,
        'show_ui' => true,
        'supports' => array( 'title' ),
    ) );
}
add_action( 'init', 'pit_register_post_type' );

/**
 * Shortcode to render the front-end application.
 */
function pit_shortcode_app() {
    pit_enqueue_assets();
    ob_start();
    include PIT_PLUGIN_DIR . 'templates/frontend-app.php';
    return ob_get_clean();
}
add_shortcode( 'pit_app', 'pit_shortcode_app' );

/**
 * Enqueue scripts and styles when shortcode is used.
 */
function pit_enqueue_assets() {
    wp_enqueue_style( 'pit-app', PIT_PLUGIN_URL . 'assets/app.css', array(), '1.0.0' );
    wp_enqueue_script( 'tesseract', 'https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js', array(), null, true );
    wp_register_script( 'pit-app', PIT_PLUGIN_URL . 'assets/app.js', array( 'tesseract' ), '1.0.0', true );

    $data = array(
        'restUrl' => esc_url_raw( rest_url( 'pit/v1' ) ),
        'nonce'   => wp_create_nonce( 'wp_rest' ),
        'i18n'    => array(
            'search'        => __( 'Search items...', 'pit' ),
            'allCategories' => __( 'All Categories', 'pit' ),
            'export'        => __( 'Export CSV', 'pit' ),
            'scan'          => __( 'Scan Receipt', 'pit' ),
            'itemName'      => __( 'Item name', 'pit' ),
            'category'      => __( 'Category', 'pit' ),
            'addItem'       => __( 'Add Item', 'pit' ),
            'delete'        => __( 'Delete', 'pit' ),
        ),
    );

    wp_localize_script( 'pit-app', 'PITAppData', $data );
    wp_enqueue_script( 'pit-app' );
}

/**
 * Register REST API routes for CRUD operations.
 */
function pit_register_rest_routes() {
    register_rest_route( 'pit/v1', '/items', array(
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'pit_rest_get_items',
            'permission_callback' => function() { return current_user_can( 'read' ); },
        ),
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'pit_rest_create_item',
            'permission_callback' => 'pit_rest_can_edit',
        ),
    ) );

    register_rest_route( 'pit/v1', '/items/(?P<id>\d+)', array(
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'pit_rest_get_item',
            'permission_callback' => function() { return current_user_can( 'read' ); },
        ),
        array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => 'pit_rest_update_item',
            'permission_callback' => 'pit_rest_can_edit',
        ),
        array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => 'pit_rest_delete_item',
            'permission_callback' => 'pit_rest_can_edit',
        ),
    ) );
}
add_action( 'rest_api_init', 'pit_register_rest_routes' );

/**
 * Permission check for modifying data that respects read-only mode.
 *
 * @return true|WP_Error
 */
function pit_rest_can_edit() {
    if ( get_option( 'pit_read_only' ) ) {
        return new WP_Error( 'pit_read_only', __( 'Read-only mode enabled.', 'pit' ), array( 'status' => 403 ) );
    }
    return current_user_can( 'edit_posts' );
}

/**
 * Prepare item data for REST responses.
 *
 * @param WP_Post $post Post object.
 * @return array
 */
function pit_prepare_item_data( $post ) {
    return array(
        'id'        => $post->ID,
        'name'      => $post->post_title,
        'quantity'  => (int) get_post_meta( $post->ID, 'quantity', true ),
        'category'  => get_post_meta( $post->ID, 'category', true ),
        'purchased' => (bool) get_post_meta( $post->ID, 'purchased', true ),
    );
}

/**
 * Get all items.
 */
function pit_rest_get_items( WP_REST_Request $request ) {
    $query = new WP_Query( array(
        'post_type'      => 'pit_item',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ) );
    $items = array();
    foreach ( $query->posts as $post ) {
        $items[] = pit_prepare_item_data( $post );
    }
    return rest_ensure_response( $items );
}

/**
 * Get single item.
 */
function pit_rest_get_item( WP_REST_Request $request ) {
    $post = get_post( (int) $request['id'] );
    if ( ! $post || 'pit_item' !== $post->post_type ) {
        return new WP_Error( 'not_found', __( 'Item not found', 'pit' ), array( 'status' => 404 ) );
    }
    return rest_ensure_response( pit_prepare_item_data( $post ) );
}

/**
 * Create item.
 */
function pit_rest_create_item( WP_REST_Request $request ) {
    $id = wp_insert_post( array(
        'post_type'   => 'pit_item',
        'post_title'  => sanitize_text_field( $request['name'] ),
        'post_status' => 'publish',
    ) );
    if ( is_wp_error( $id ) ) {
        return $id;
    }
    update_post_meta( $id, 'quantity', (int) $request['quantity'] );
    update_post_meta( $id, 'category', sanitize_text_field( $request['category'] ) );
    update_post_meta( $id, 'purchased', (bool) $request['purchased'] );
    $req = new WP_REST_Request( 'GET', '', array( 'id' => $id ) );
    return pit_rest_get_item( $req );
}

/**
 * Update item.
 */
function pit_rest_update_item( WP_REST_Request $request ) {
    $id   = (int) $request['id'];
    $post = get_post( $id );
    if ( ! $post || 'pit_item' !== $post->post_type ) {
        return new WP_Error( 'not_found', __( 'Item not found', 'pit' ), array( 'status' => 404 ) );
    }
    if ( isset( $request['name'] ) ) {
        wp_update_post( array( 'ID' => $id, 'post_title' => sanitize_text_field( $request['name'] ) ) );
    }
    if ( isset( $request['quantity'] ) ) {
        update_post_meta( $id, 'quantity', (int) $request['quantity'] );
    }
    if ( isset( $request['category'] ) ) {
        update_post_meta( $id, 'category', sanitize_text_field( $request['category'] ) );
    }
    if ( isset( $request['purchased'] ) ) {
        update_post_meta( $id, 'purchased', (bool) $request['purchased'] );
    }
    $req = new WP_REST_Request( 'GET', '', array( 'id' => $id ) );
    return pit_rest_get_item( $req );
}

/**
 * Delete item.
 */
function pit_rest_delete_item( WP_REST_Request $request ) {
    $id   = (int) $request['id'];
    $post = get_post( $id );
    if ( ! $post || 'pit_item' !== $post->post_type ) {
        return new WP_Error( 'not_found', __( 'Item not found', 'pit' ), array( 'status' => 404 ) );
    }
    wp_delete_post( $id, true );
    return rest_ensure_response( true );
}
