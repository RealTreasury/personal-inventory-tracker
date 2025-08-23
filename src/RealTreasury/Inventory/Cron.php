<?php
namespace RealTreasury\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cron {

    const HOOK = 'pit_refresh_recommendations_daily';

    public static function init() {
        add_action( self::HOOK, [ __CLASS__, 'refresh_recommendations' ] );
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

    public static function refresh_recommendations() {
        $posts = get_posts(
            [
                'post_type'      => 'pit_item',
                'posts_per_page' => -1,
                'post_status'    => 'any',
            ]
        );

        $summary = [
            'timestamp' => current_time( 'timestamp' ),
            'total'     => 0,
            'quantity'  => 0,
            'interval'  => 0,
            'items'     => [],
        ];

        foreach ( $posts as $post ) {
            $item   = pit_get_item( $post->ID );
            $result = pit_calculate_reorder_needed( $item );
            if ( $result['needed'] ) {
                $summary['total']++;
                if ( isset( $summary[ $result['reason'] ] ) ) {
                    $summary[ $result['reason'] ]++;
                }
                $summary['items'][] = $post->ID;
            }
        }

        update_option( 'pit_reco_summary', $summary );

        return $summary;
    }
}

if ( ! class_exists( 'PIT_Cron' ) ) {
    class_alias( __NAMESPACE__ . '\\Cron', 'PIT_Cron' );
}
