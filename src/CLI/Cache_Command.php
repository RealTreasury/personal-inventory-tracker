<?php

namespace RealTreasury\Inventory\CLI;

use WP_CLI;
use WP_CLI_Command;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cache_Command extends WP_CLI_Command {
    /**
     * Clear inventory-related caches.
     *
     * ## EXAMPLES
     *
     *     wp pit cache clear
     */
    public function clear( $args, $assoc_args ) {
        \PIT_Cache::clear_inventory_caches();
        WP_CLI::success( 'Inventory caches cleared.' );
    }
}

\class_alias( __NAMESPACE__ . '\\Cache_Command', 'PIT\\CLI\\Cache_Command' );
