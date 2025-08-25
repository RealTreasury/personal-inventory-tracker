<?php
/**
 * Tests for Database security improvements.
 */

// WordPress function stubs for testing.
$wpdb_prepare_calls = array();
$wpdb_query_calls = array();

global $wpdb;

class MockWpdb {
    public $postmeta = 'wp_postmeta';
    public $prepare_calls = array();
    public $query_calls = array();
    
    public function prepare( $query, ...$args ) {
        global $wpdb_prepare_calls;
        $wpdb_prepare_calls[] = array( 'query' => $query, 'args' => $args );
        return sprintf( $query, ...$args );
    }
    
    public function query( $query ) {
        global $wpdb_query_calls;
        $wpdb_query_calls[] = $query;
        return true;
    }
    
    public function get_var( $query ) {
        // Mock response for index existence check
        if ( strpos( $query, 'SHOW INDEX' ) !== false ) {
            return null; // No existing index
        }
        return null;
    }
}

$wpdb = new MockWpdb();

function get_option( $option, $default = false ) {
    if ( $option === 'pit_db_version' ) {
        return '0'; // Simulate first install
    }
    return $default;
}

function update_option( $option, $value ) {
    return true;
}

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ );
}

// Load the Database class
require_once __DIR__ . '/../src/Database.php';

use PHPUnit\Framework\TestCase;
use RealTreasury\Inventory\Database;

class DatabaseSecurityTest extends TestCase {
    protected function setUp(): void {
        global $wpdb_prepare_calls, $wpdb_query_calls;
        $wpdb_prepare_calls = array();
        $wpdb_query_calls = array();
    }
    
    public function test_migrate_data_uses_prepared_statements() {
        global $wpdb_prepare_calls, $wpdb_query_calls;
        
        Database::migrate();
        
        // Check that prepare() was called for UPDATE statements
        $found_prepared_updates = 0;
        foreach ( $wpdb_prepare_calls as $call ) {
            if ( strpos( $call['query'], 'UPDATE' ) !== false ) {
                $found_prepared_updates++;
                // Verify the query uses placeholders
                $this->assertStringContainsString( '%s', $call['query'] );
                $this->assertCount( 2, $call['args'] ); // Should have 2 arguments for SET and WHERE
            }
        }
        
        // Should have 6 prepared UPDATE statements (4 legacy + 2 deprecated)
        $this->assertGreaterThanOrEqual( 6, $found_prepared_updates, 'All UPDATE statements should use prepared queries' );
    }
    
    public function test_migrate_data_does_not_use_direct_updates() {
        global $wpdb_query_calls;
        
        Database::migrate();
        
        // Check that no direct UPDATE queries were executed
        foreach ( $wpdb_query_calls as $query ) {
            if ( strpos( $query, 'UPDATE' ) !== false ) {
                // Only CREATE INDEX and DROP INDEX should be direct queries
                $this->assertFalse( 
                    strpos( $query, 'meta_key' ) !== false,
                    'UPDATE queries with meta_key should not be executed directly: ' . $query
                );
            }
        }
    }
    
    public function test_schema_migration_allows_direct_ddl() {
        global $wpdb_query_calls;
        
        Database::migrate();
        
        // CREATE INDEX statements should still be allowed as direct queries
        // because they don't support parameter binding
        $found_create_index = false;
        foreach ( $wpdb_query_calls as $query ) {
            if ( strpos( $query, 'CREATE INDEX' ) !== false ) {
                $found_create_index = true;
                break;
            }
        }
        
        $this->assertTrue( $found_create_index, 'CREATE INDEX statements should be executed directly' );
    }
    
    public function test_rollback_uses_prepared_statements_for_checks() {
        global $wpdb_prepare_calls;
        
        Database::rollback();
        
        // Verify that SHOW INDEX checks use prepared statements
        $found_prepared_show = false;
        foreach ( $wpdb_prepare_calls as $call ) {
            if ( strpos( $call['query'], 'SHOW INDEX' ) !== false ) {
                $found_prepared_show = true;
                $this->assertStringContainsString( '%s', $call['query'] );
                break;
            }
        }
        
        $this->assertTrue( $found_prepared_show, 'SHOW INDEX statements should use prepared queries' );
    }
}