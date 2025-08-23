<?php
// WordPress function stubs for testing.
function absint( $value ) {
    return abs( intval( $value ) );
}
function sanitize_text_field( $value ) {
    return is_string( $value ) ? trim( $value ) : '';
}
function esc_html( $text ) {
    return $text;
}
function update_post_meta( $post_id, $key, $value ) {
    global $pit_meta;
    if ( ! isset( $pit_meta[ $post_id ] ) ) {
        $pit_meta[ $post_id ] = array();
    }
    $pit_meta[ $post_id ][ $key ] = $value;
}
function get_post_meta( $post_id ) {
    global $pit_meta;
    if ( ! isset( $pit_meta[ $post_id ] ) ) {
        return array();
    }
    $data = array();
    foreach ( $pit_meta[ $post_id ] as $k => $v ) {
        $data[ $k ] = array( $v );
    }
    return $data;
}
function wp_update_post( $args ) {
    global $pit_posts;
    $pit_posts[ $args['ID'] ]['post_title'] = $args['post_title'];
}
function __( $text, $domain = null ) {
    return $text;
}
function maybe_unserialize( $value ) {
    return $value;
}
function add_action( $hook, $callback ) {
    // No-op for tests.
}
function get_post_type( $post_id ) {
    return 'pit_item';
}
class PIT_Cache {
    public static function clear_inventory_caches() {}
}

class WP_Error {
    public $errors = array();
    public function __construct( $code = '', $message = '' ) {
        $this->errors[ $code ] = $message;
    }
}

define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/../pit-functions.php';

use PHPUnit\Framework\TestCase;

class PitUpdateItemTest extends TestCase {
    protected function setUp(): void {
        global $pit_meta, $pit_posts;
        $pit_meta  = array();
        $pit_posts = array(
            1 => array( 'post_title' => 'Item 1' ),
        );
    }

    public function test_rejects_array_values() {
        $result = pit_update_item( 1, array( 'qty' => array( 2 ) ) );
        $this->assertInstanceOf( WP_Error::class, $result );
    }

    public function test_ignores_unknown_fields() {
        $result = pit_update_item( 1, array( 'unknown' => 'foo', 'qty' => '3' ) );
        $this->assertArrayNotHasKey( 'unknown', $result );
        $this->assertSame( 3, $result['qty'] );
    }
}
