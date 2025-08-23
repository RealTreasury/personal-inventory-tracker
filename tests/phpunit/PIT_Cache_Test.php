<?php
/**
 * Tests for PIT_Cache.
 */

use PHPUnit\Framework\TestCase;

class PIT_Cache_Test extends TestCase {

    protected function tearDown(): void {
        $GLOBALS['pit_transients'] = [];
    }

    public function test_caches_false(): void {
        $key      = 'pit_false';
        $callback = static function() {
            return false;
        };

        $this->assertFalse( PIT_Cache::get_or_set( $key, $callback ) );

        $called   = false;
        $callback = static function() use ( &$called ) {
            $called = true;
            return true;
        };

        $this->assertFalse( PIT_Cache::get_or_set( $key, $callback ) );
        $this->assertFalse( $called );
    }

    public function test_caches_zero(): void {
        $key      = 'pit_zero';
        $callback = static function() {
            return 0;
        };

        $this->assertSame( 0, PIT_Cache::get_or_set( $key, $callback ) );

        $called   = false;
        $callback = static function() use ( &$called ) {
            $called = true;
            return 1;
        };

        $this->assertSame( 0, PIT_Cache::get_or_set( $key, $callback ) );
        $this->assertFalse( $called );
    }

    public function test_caches_empty_string(): void {
        $key      = 'pit_empty';
        $callback = static function() {
            return '';
        };

        $this->assertSame( '', PIT_Cache::get_or_set( $key, $callback ) );

        $called   = false;
        $callback = static function() use ( &$called ) {
            $called = true;
            return 'filled';
        };

        $this->assertSame( '', PIT_Cache::get_or_set( $key, $callback ) );
        $this->assertFalse( $called );
    }
}
