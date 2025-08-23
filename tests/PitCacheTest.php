<?php
// WordPress function stubs for testing.
if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $key ) {
        global $pit_transients;
        return $pit_transients[ $key ] ?? false;
    }
}

if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $key, $value, $expiration ) {
        global $pit_transients;
        $pit_transients[ $key ] = $value;
        return true;
    }
}

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ );
}

require_once __DIR__ . '/../includes/class-pit-cache.php';

use PHPUnit\Framework\TestCase;

class PitCacheTest extends TestCase {
    protected function setUp(): void {
        global $pit_transients;
        $pit_transients = array();
    }

    public function test_caches_false() {
        $calls  = 0;
        $result = PIT_Cache::get_or_set( 'test_false', function () use ( &$calls ) {
            $calls++;
            return false;
        } );
        $this->assertFalse( $result );

        $result = PIT_Cache::get_or_set( 'test_false', function () use ( &$calls ) {
            $calls++;
            return true;
        } );
        $this->assertFalse( $result );
        $this->assertSame( 1, $calls );
    }

    public function test_caches_zero() {
        $calls  = 0;
        $result = PIT_Cache::get_or_set( 'test_zero', function () use ( &$calls ) {
            $calls++;
            return 0;
        } );
        $this->assertSame( 0, $result );

        $result = PIT_Cache::get_or_set( 'test_zero', function () use ( &$calls ) {
            $calls++;
            return 1;
        } );
        $this->assertSame( 0, $result );
        $this->assertSame( 1, $calls );
    }

    public function test_caches_empty_string() {
        $calls  = 0;
        $result = PIT_Cache::get_or_set( 'test_empty', function () use ( &$calls ) {
            $calls++;
            return '';
        } );
        $this->assertSame( '', $result );

        $result = PIT_Cache::get_or_set( 'test_empty', function () use ( &$calls ) {
            $calls++;
            return 'filled';
        } );
        $this->assertSame( '', $result );
        $this->assertSame( 1, $calls );
    }
}
