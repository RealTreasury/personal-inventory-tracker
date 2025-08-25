<?php
/**
 * Simple dependency injection container.
 *
 * @package PersonalInventoryTracker
 */

namespace RealTreasury\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Simple dependency injection container for managing services.
 */
class Container {

    /**
     * Service definitions.
     *
     * @var array
     */
    private $services = [];

    /**
     * Service instances.
     *
     * @var array
     */
    private $instances = [];

    /**
     * Bind a service to the container.
     *
     * @param string   $name     Service name.
     * @param callable $callback Service factory callback.
     */
    public function bind( $name, $callback ) {
        $this->services[ $name ] = $callback;
    }

    /**
     * Get a service from the container.
     *
     * @param string $name Service name.
     * @return mixed Service instance.
     * @throws \Exception If service not found.
     */
    public function get( $name ) {
        // Return existing instance if available (singleton pattern)
        if ( isset( $this->instances[ $name ] ) ) {
            return $this->instances[ $name ];
        }

        // Check if service is bound
        if ( ! isset( $this->services[ $name ] ) ) {
            throw new \Exception( sprintf( 'Service "%s" not found in container.', $name ) );
        }

        // Create and cache the instance
        $this->instances[ $name ] = call_user_func( $this->services[ $name ] );

        return $this->instances[ $name ];
    }

    /**
     * Check if a service is bound.
     *
     * @param string $name Service name.
     * @return bool True if service is bound.
     */
    public function has( $name ) {
        return isset( $this->services[ $name ] );
    }

    /**
     * Remove a service from the container.
     *
     * @param string $name Service name.
     */
    public function remove( $name ) {
        unset( $this->services[ $name ], $this->instances[ $name ] );
    }
}