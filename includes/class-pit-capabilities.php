<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIT_Capabilities {

    public static function add_capabilities() {
        $roles = array( 'administrator', 'editor' );

        foreach ( $roles as $slug ) {
            $role = get_role( $slug );
            if ( ! $role ) {
                continue;
            }
            $role->add_cap( 'view_inventory' );
            $role->add_cap( 'manage_inventory_items' );
            $role->add_cap( 'manage_inventory_settings' );
        }
    }

    public static function remove_capabilities() {
        $roles = array( 'administrator', 'editor' );

        foreach ( $roles as $slug ) {
            $role = get_role( $slug );
            if ( ! $role ) {
                continue;
            }
            $role->remove_cap( 'view_inventory' );
            $role->remove_cap( 'manage_inventory_items' );
            $role->remove_cap( 'manage_inventory_settings' );
        }
    }
}
