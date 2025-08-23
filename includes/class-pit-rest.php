<?php
/**
 * REST API for Personal Inventory Tracker.
 *
 * @package Personal_Inventory_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Main REST controller for Personal Inventory Tracker.
 */
class PIT_REST {
/**
 * REST namespace.
 */
const REST_NAMESPACE = 'pit/v1';

/**
 * Constructor.
 */
public function __construct() {
add_action( 'rest_api_init', array( $this, 'register_routes' ) );
}

/**
 * Register REST routes.
 */
public function register_routes() {
// Nonce endpoint.
register_rest_route(
self::REST_NAMESPACE,
'/nonce',
array(
'methods'             => WP_REST_Server::READABLE,
'callback'            => array( $this, 'get_nonce' ),
'permission_callback' => '__return_true',
)
);

// Items CRUD.
register_rest_route(
self::REST_NAMESPACE,
'/items',
array(
array(
'methods'             => WP_REST_Server::READABLE,
'callback'            => array( $this, 'get_items' ),
'permission_callback' => array( $this, 'permissions_check' ),
),
array(
'methods'             => WP_REST_Server::CREATABLE,
'callback'            => array( $this, 'create_item' ),
'permission_callback' => array( $this, 'permissions_check' ),
'args'                => array(
'title'   => array(
'required' => true,
'type'     => 'string',
),
'content' => array(
'required' => false,
'type'     => 'string',
),
),
),
'schema' => array( $this, 'get_item_schema' ),
)
);

register_rest_route(
self::REST_NAMESPACE,
'/items/(?P<id>\d+)',
array(
array(
'methods'             => WP_REST_Server::READABLE,
'callback'            => array( $this, 'get_item' ),
'permission_callback' => array( $this, 'permissions_check' ),
'args'                => array(
'id' => array(
'required' => true,
'type'     => 'integer',
),
),
),
array(
'methods'             => WP_REST_Server::EDITABLE,
'callback'            => array( $this, 'update_item' ),
'permission_callback' => array( $this, 'permissions_check' ),
'args'                => array(
'id'      => array(
'required' => true,
'type'     => 'integer',
),
'title'   => array(
'required' => false,
'type'     => 'string',
),
'content' => array(
'required' => false,
'type'     => 'string',
),
),
),
array(
'methods'             => WP_REST_Server::DELETABLE,
'callback'            => array( $this, 'delete_item' ),
'permission_callback' => array( $this, 'permissions_check' ),
'args'                => array(
'id' => array(
'required' => true,
'type'     => 'integer',
),
),
),
'schema' => array( $this, 'get_item_schema' ),
)
);

register_rest_route(
self::REST_NAMESPACE,
'/items/batch',
array(
'methods'             => WP_REST_Server::CREATABLE,
'callback'            => array( $this, 'batch_update_items' ),
'permission_callback' => array( $this, 'permissions_check' ),
'args'                => array(
'items' => array(
'required' => true,
'type'     => 'array',
),
),
)
);

register_rest_route(
self::REST_NAMESPACE,
'/items/import',
array(
'methods'             => WP_REST_Server::CREATABLE,
'callback'            => array( $this, 'import_items' ),
'permission_callback' => array( $this, 'permissions_check' ),
'args'                => array(
'items' => array(
'required' => true,
'type'     => 'array',
),
),
)
);

register_rest_route(
self::REST_NAMESPACE,
'/items/export',
array(
'methods'             => WP_REST_Server::READABLE,
'callback'            => array( $this, 'export_items' ),
'permission_callback' => array( $this, 'permissions_check' ),
)
);

register_rest_route(
self::REST_NAMESPACE,
'/recommendations/refresh',
array(
'methods'             => WP_REST_Server::CREATABLE,
'callback'            => array( $this, 'refresh_recommendations' ),
'permission_callback' => array( $this, 'permissions_check' ),
)
);
}

/**
 * Permission check including nonce validation.
 *
 * @param WP_REST_Request $request Request object.
 * @return true|WP_Error
 */
public function permissions_check( WP_REST_Request $request ) {
if ( ! current_user_can( 'edit_posts' ) ) {
return new WP_Error( 'rest_forbidden', __( 'You cannot access this resource.', 'pit' ), array( 'status' => rest_authorization_required_code() ) );
}

$nonce = $request->get_param( '_wpnonce' );
if ( ! $nonce ) {
$nonce = $request->get_header( 'X-WP-Nonce' );
}

if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
return new WP_Error( 'rest_nonce_invalid', __( 'Invalid nonce.', 'pit' ), array( 'status' => 403 ) );
}

if ( $this->is_read_only() && 'GET' !== $request->get_method() ) {
return new WP_Error( 'rest_read_only', __( 'The site is in read-only mode.', 'pit' ), array( 'status' => 403 ) );
}

return true;
}

/**
 * Check if plugin is in read-only mode.
 *
 * @return bool
 */
protected function is_read_only() {
if ( function_exists( 'pit_is_read_only' ) ) {
return (bool) pit_is_read_only();
}

return (bool) apply_filters( 'pit_is_read_only', false );
}

/**
 * Get nonce value for REST requests.
 *
 * @return WP_REST_Response
 */
public function get_nonce() {
return rest_ensure_response(
array(
'nonce' => wp_create_nonce( 'wp_rest' ),
)
);
}

/**
 * Get all items.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
public function get_items( WP_REST_Request $request ) {
$posts = get_posts(
array(
'post_type'      => 'pit_item',
'posts_per_page' => -1,
)
);

$data = array();
foreach ( $posts as $post ) {
$data[] = $this->prepare_item_data( $post );
}

return rest_ensure_response( $data );
}

/**
 * Get single item.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
public function get_item( WP_REST_Request $request ) {
$post = get_post( (int) $request['id'] );
if ( ! $post || 'pit_item' !== $post->post_type ) {
return new WP_Error( 'rest_item_invalid', __( 'Item not found.', 'pit' ), array( 'status' => 404 ) );
}

return rest_ensure_response( $this->prepare_item_data( $post ) );
}

/**
 * Create an item.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
public function create_item( WP_REST_Request $request ) {
$postarr = array(
'post_type'   => 'pit_item',
'post_status' => 'publish',
'post_title'  => sanitize_text_field( $request['title'] ),
'post_content'=> wp_kses_post( $request['content'] ),
);

$id = wp_insert_post( $postarr, true );
if ( is_wp_error( $id ) ) {
return $id;
}

$post = get_post( $id );
return rest_ensure_response( $this->prepare_item_data( $post ) );
}

/**
 * Update an item.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
public function update_item( WP_REST_Request $request ) {
$postarr = array(
'ID'          => (int) $request['id'],
'post_title'  => sanitize_text_field( $request['title'] ),
'post_content'=> wp_kses_post( $request['content'] ),
);

$id = wp_update_post( $postarr, true );
if ( is_wp_error( $id ) ) {
return $id;
}

$post = get_post( $id );
return rest_ensure_response( $this->prepare_item_data( $post ) );
}

/**
 * Delete an item.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
public function delete_item( WP_REST_Request $request ) {
$result = wp_delete_post( (int) $request['id'], true );
if ( ! $result ) {
return new WP_Error( 'rest_cannot_delete', __( 'Could not delete item.', 'pit' ), array( 'status' => 500 ) );
}

return rest_ensure_response( array( 'deleted' => true ) );
}

/**
 * Batch update items.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
public function batch_update_items( WP_REST_Request $request ) {
$items   = $request->get_param( 'items' );
$results = array();

foreach ( $items as $item ) {
$id = isset( $item['id'] ) ? (int) $item['id'] : 0;

if ( $id ) {
$postarr = array(
'ID'          => $id,
'post_title'  => sanitize_text_field( $item['title'] ),
'post_content'=> wp_kses_post( $item['content'] ),
);
$res = wp_update_post( $postarr, true );
} else {
$postarr = array(
'post_type'   => 'pit_item',
'post_status' => 'publish',
'post_title'  => sanitize_text_field( $item['title'] ),
'post_content'=> wp_kses_post( isset( $item['content'] ) ? $item['content'] : '' ),
);
$res = wp_insert_post( $postarr, true );
}

if ( is_wp_error( $res ) ) {
$results[] = array( 'error' => $res->get_error_message() );
} else {
$post      = get_post( $res );
$results[] = $this->prepare_item_data( $post );
}
}

return rest_ensure_response( $results );
}

/**
 * Import items.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
public function import_items( WP_REST_Request $request ) {
$items   = $request->get_param( 'items' );
$results = array();

foreach ( $items as $item ) {
$postarr = array(
'post_type'   => 'pit_item',
'post_status' => 'publish',
'post_title'  => sanitize_text_field( $item['title'] ),
'post_content'=> wp_kses_post( isset( $item['content'] ) ? $item['content'] : '' ),
);

$id = wp_insert_post( $postarr, true );
if ( is_wp_error( $id ) ) {
$results[] = array( 'error' => $id->get_error_message() );
} else {
$post      = get_post( $id );
$results[] = $this->prepare_item_data( $post );
}
}

return rest_ensure_response( $results );
}

/**
 * Export all items.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
public function export_items( WP_REST_Request $request ) {
return $this->get_items( $request );
}

/**
 * Refresh recommendations.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
public function refresh_recommendations( WP_REST_Request $request ) {
if ( function_exists( 'pit_refresh_recommendations' ) ) {
pit_refresh_recommendations();
}

return rest_ensure_response( array( 'refreshed' => true ) );
}

/**
 * Prepare item data.
 *
 * @param WP_Post $post Post object.
 * @return array
 */
protected function prepare_item_data( WP_Post $post ) {
return array(
'id'      => $post->ID,
'title'   => get_the_title( $post ),
'content' => apply_filters( 'the_content', $post->post_content ),
);
}

/**
 * JSON schema for an item.
 *
 * @return array
 */
public function get_item_schema() {
return array(
'$schema'    => 'http://json-schema.org/draft-04/schema#',
'title'      => 'pit-item',
'type'       => 'object',
'properties' => array(
'id'      => array(
'description' => __( 'Unique identifier for the item.', 'pit' ),
'type'        => 'integer',
'readonly'    => true,
),
'title'   => array(
'description' => __( 'The title for the item.', 'pit' ),
'type'        => 'string',
),
'content' => array(
'description' => __( 'The content for the item.', 'pit' ),
'type'        => 'string',
),
),
);
}
}

// Initialize.
new PIT_REST();
