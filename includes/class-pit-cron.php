<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIT_Cron {

    const HOOK = 'pit_cron_event';

    public static function init() {
        // Hook cron event callbacks.
    }

    public static function activate() {
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::HOOK );
        }
    }

    public static function deactivate() {
        $timestamp = wp_next_scheduled( self::HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK );
        }
    }
}
