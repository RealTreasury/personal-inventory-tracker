<?php
/**
 * Handles cron scheduling and recommendation refreshes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIT_Cron {
    const CRON_HOOK = 'pit_refresh_recommendations_daily';

    public static function init() {
        add_action( self::CRON_HOOK, array( __CLASS__, 'refresh' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function activate() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    public static function refresh() {
        $items = apply_filters( 'pit_get_items_for_recommendations', array() );
        $recommendations = array();

        foreach ( $items as $item ) {
            $recommendations[] = apply_filters( 'pit_compute_recommendation', array(), $item );
        }

        $summary = array(
            'last_run' => current_time( 'mysql' ),
            'items'    => count( $items ),
            'results'  => $recommendations,
        );

        update_option( 'pit_reco_summary', $summary );

        return $summary;
    }

    public static function register_routes() {
        register_rest_route(
            'pit/v1',
            '/refresh-recommendations',
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'rest_refresh' ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            )
        );
    }

    public static function rest_refresh( $request ) {
        $summary = self::refresh();

        return rest_ensure_response( $summary );
    }
}
