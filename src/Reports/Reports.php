<?php

namespace RealTreasury\Inventory\Reports;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
    define( 'DAY_IN_SECONDS', 86400 );
}

class Reports {

    /**
     * Retrieve summary data from cache or compute it.
     *
     * @return array
     */
    public function get_summary() {
        return \PIT_Cache::get_or_set( 'pit_reco_summary', array( $this, 'calculate_summary' ) );
    }

    /**
     * Compute summary stats for inventory items.
     *
     * @return array
     */
    private function calculate_summary() {
        $ids = get_posts( array(
            'post_type'      => 'pit_item',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        $total_items  = count( $ids );
        $low_stock    = 0;
        $due_soon     = 0;
        $recent       = array();
        $now          = current_time( 'timestamp' );
        $recent_cut   = $now - ( 14 * DAY_IN_SECONDS );

        foreach ( $ids as $id ) {
            $qty        = (int) get_post_meta( $id, 'pit_qty', true );
            $threshold  = (int) get_post_meta( $id, 'pit_threshold', true );
            $interval   = (int) get_post_meta( $id, 'pit_interval', true );
            $last_purch = get_post_meta( $id, 'pit_last_purchased', true );
            $last_ts    = $last_purch ? strtotime( $last_purch ) : false;

            if ( $qty <= $threshold ) {
                $low_stock++;
            }

            if ( $interval > 0 && $last_ts ) {
                $next = $last_ts + ( $interval * DAY_IN_SECONDS );
                if ( $next <= $now + ( 7 * DAY_IN_SECONDS ) ) {
                    $due_soon++;
                }
            }

            if ( $last_ts && $last_ts >= $recent_cut ) {
                $recent[] = array(
                    'id'    => $id,
                    'title' => get_the_title( $id ),
                    'date'  => $last_purch,
                );
            }
        }

        return array(
            'totals' => array(
                'items'    => $total_items,
                'low'      => $low_stock,
                'due_soon' => $due_soon,
            ),
            'recent' => $recent,
        );
    }

    /**
     * Render dashboard HTML and charts.
     */
    public function render_dashboard() {
        $data   = $this->get_summary();
        $totals = isset( $data['totals'] ) ? $data['totals'] : array();
        $recent = isset( $data['recent'] ) ? $data['recent'] : array();

        $total_items = isset( $totals['items'] ) ? (int) $totals['items'] : 0;
        $low_stock   = isset( $totals['low'] ) ? (int) $totals['low'] : 0;
        $due_soon    = isset( $totals['due_soon'] ) ? (int) $totals['due_soon'] : 0;

        echo '<div class="pit-dashboard-reports">';
        echo '<h2>' . esc_html__( 'Inventory Overview', 'personal-inventory-tracker' ) . '</h2>';
        echo '<canvas id="pit-overview-chart" width="300" height="150"></canvas>';
        echo '<ul class="pit-stats">';
        printf( '<li>%s: %d</li>', esc_html__( 'Total Items', 'personal-inventory-tracker' ), $total_items );
        printf( '<li>%s: %d</li>', esc_html__( 'Low Stock', 'personal-inventory-tracker' ), $low_stock );
        printf( '<li>%s: %d</li>', esc_html__( 'Due Soon', 'personal-inventory-tracker' ), $due_soon );
        echo '</ul>';

        if ( ! empty( $recent ) ) {
            echo '<h3>' . esc_html__( 'Recent Purchases', 'personal-inventory-tracker' ) . '</h3>';
            echo '<ul class="pit-recent-purchases">';
            foreach ( $recent as $purchase ) {
                $date = date_i18n( get_option( 'date_format' ), strtotime( $purchase['date'] ) );
                printf( '<li>%s – %s</li>', esc_html( $purchase['title'] ), esc_html( $date ) );
            }
            echo '</ul>';
        }
        echo '</div>';

        // Simple bar chart.
        echo '<script>(function(){var c=document.getElementById("pit-overview-chart");if(!c||!c.getContext)return;var ctx=c.getContext("2d");var vals=[' . $total_items . ',' . $low_stock . ',' . $due_soon . '];var max=Math.max.apply(null,vals)||1;var colors=["#4caf50","#ff9800","#f44336"];var width=50;for(var i=0;i<vals.length;i++){var h=(vals[i]/max)*100;ctx.fillStyle=colors[i];ctx.fillRect(10+i*(width+10),110-h,width,h);}ctx.fillStyle="#000";ctx.textBaseline="top";ctx.fillText("' . esc_js( __( 'Total', 'personal-inventory-tracker' ) ) . '",10,130);ctx.fillText("' . esc_js( __( 'Low', 'personal-inventory-tracker' ) ) . '",10+width+10,130);ctx.fillText("' . esc_js( __( 'Due', 'personal-inventory-tracker' ) ) . '",10+2*(width+10),130);}());</script>';
    }
}
\class_alias( __NAMESPACE__ . '\\Reports', 'PIT\\Reports\\Reports' );
