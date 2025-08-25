<?php
/**
 * Tests for REST API security improvements.
 */

// WordPress function stubs for testing.
function current_user_can( $capability, $post_id = null ) {
    global $pit_user_permissions;
    if ( $post_id && isset( $pit_user_permissions[ $post_id ] ) ) {
        return $pit_user_permissions[ $post_id ];
    }
    return $pit_user_permissions[ $capability ] ?? false;
}

function get_post( $post_id ) {
    global $pit_posts;
    return $pit_posts[ $post_id ] ?? null;
}

function wp_update_post( $args, $wp_error = false ) {
    global $pit_posts;
    if ( ! isset( $pit_posts[ $args['ID'] ] ) ) {
        return $wp_error ? new WP_Error( 'not_found', 'Post not found' ) : 0;
    }
    $pit_posts[ $args['ID'] ]['post_title'] = $args['post_title'];
    return $args['ID'];
}

function wp_trash_post( $post_id ) {
    global $pit_posts;
    if ( ! isset( $pit_posts[ $post_id ] ) ) {
        return false;
    }
    unset( $pit_posts[ $post_id ] );
    return true;
}

function sanitize_text_field( $value ) {
    return is_string( $value ) ? trim( $value ) : '';
}

function rest_ensure_response( $data ) {
    return $data;
}

function __( $text, $domain = null ) {
    return $text;
}

class WP_Error {
    public $errors = array();
    public $error_data = array();
    
    public function __construct( $code = '', $message = '', $data = '' ) {
        $this->errors[ $code ] = array( $message );
        if ( ! empty( $data ) ) {
            $this->error_data[ $code ] = $data;
        }
    }
    
    public function get_error_message() {
        return reset( $this->errors )[0] ?? '';
    }
}

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ );
}

// Mock request class
class MockRequest {
    private $data;
    
    public function __construct( $data = array() ) {
        $this->data = $data;
    }
    
    public function offsetGet( $key ) {
        return $this->data[ $key ] ?? null;
    }
    
    public function offsetExists( $key ) {
        return isset( $this->data[ $key ] );
    }
    
    public function offsetSet( $key, $value ) {
        $this->data[ $key ] = $value;
    }
    
    public function offsetUnset( $key ) {
        unset( $this->data[ $key ] );
    }
}

// Load the REST API class
require_once __DIR__ . '/../src/REST/Rest_Api.php';

use PHPUnit\Framework\TestCase;
use RealTreasury\Inventory\REST\Rest_Api;

class RestApiSecurityTest extends TestCase {
    private $api;
    
    protected function setUp(): void {
        global $pit_user_permissions, $pit_posts;
        
        $this->api = new Rest_Api();
        
        // Reset global state
        $pit_user_permissions = array();
        $pit_posts = array(
            1 => (object) array( 'ID' => 1, 'post_type' => 'pit_item', 'post_title' => 'Test Item' ),
            2 => (object) array( 'ID' => 2, 'post_type' => 'other_type', 'post_title' => 'Other Item' ),
        );
    }
    
    public function test_update_item_requires_post_existence() {
        global $pit_user_permissions;
        $pit_user_permissions['edit_post'] = true;
        
        $request = new MockRequest( array( 'id' => 999, 'title' => 'Updated Title' ) );
        $result = $this->api->update_item( $request );
        
        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertContains( 'Item not found', $result->get_error_message() );
    }
    
    public function test_update_item_requires_correct_post_type() {
        global $pit_user_permissions;
        $pit_user_permissions['edit_post'] = true;
        
        $request = new MockRequest( array( 'id' => 2, 'title' => 'Updated Title' ) );
        $result = $this->api->update_item( $request );
        
        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertContains( 'Item not found', $result->get_error_message() );
    }
    
    public function test_update_item_requires_edit_permission() {
        global $pit_user_permissions;
        $pit_user_permissions[1] = false; // Cannot edit post 1
        
        $request = new MockRequest( array( 'id' => 1, 'title' => 'Updated Title' ) );
        $result = $this->api->update_item( $request );
        
        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertContains( 'cannot edit', $result->get_error_message() );
    }
    
    public function test_update_item_succeeds_with_permission() {
        global $pit_user_permissions;
        $pit_user_permissions[1] = true; // Can edit post 1
        
        $request = new MockRequest( array( 'id' => 1, 'title' => 'Updated Title' ) );
        $result = $this->api->update_item( $request );
        
        $this->assertNotInstanceOf( WP_Error::class, $result );
    }
    
    public function test_delete_item_requires_post_existence() {
        global $pit_user_permissions;
        $pit_user_permissions['delete_post'] = true;
        
        $request = new MockRequest( array( 'id' => 999 ) );
        $result = $this->api->delete_item( $request );
        
        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertContains( 'Item not found', $result->get_error_message() );
    }
    
    public function test_delete_item_requires_delete_permission() {
        global $pit_user_permissions;
        $pit_user_permissions[1] = false; // Cannot delete post 1
        
        $request = new MockRequest( array( 'id' => 1 ) );
        $result = $this->api->delete_item( $request );
        
        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertContains( 'cannot delete', $result->get_error_message() );
    }
    
    public function test_delete_item_succeeds_with_permission() {
        global $pit_user_permissions;
        $pit_user_permissions[1] = true; // Can delete post 1
        
        $request = new MockRequest( array( 'id' => 1 ) );
        $result = $this->api->delete_item( $request );
        
        $this->assertTrue( $result );
    }
    
    public function test_batch_update_validates_individual_permissions() {
        global $pit_user_permissions;
        $pit_user_permissions[1] = true;  // Can edit post 1
        $pit_user_permissions[2] = false; // Cannot edit post 2
        
        $request = new MockRequest( array(
            'items' => array(
                array( 'id' => 1, 'title' => 'Updated 1' ),
                array( 'id' => 2, 'title' => 'Updated 2' ), // Should fail
            )
        ) );
        
        $result = $this->api->batch_update_items( $request );
        
        $this->assertArrayHasKey( 'items', $result );
        $this->assertArrayHasKey( 'errors', $result );
        $this->assertCount( 1, $result['items'] ); // Only post 1 should be updated
        $this->assertCount( 1, $result['errors'] ); // Post 2 should generate an error
    }
}