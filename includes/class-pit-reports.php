<?php
/**
 * Reporting utilities for Personal Inventory Tracker.
 */

if ( ! class_exists( 'PIT_Reports' ) ) {
    class PIT_Reports {
        /**
         * Retrieve cached summary information.
         *
         * @return array
         */
        public static function get_summary() {
            $summary = get_option( 'pit_reco_summary', array() );
            return is_array( $summary ) ? $summary : array();
        }

        /**
         * Total counts and value of inventory.
         *
         * @return array{items:int,value:float}
         */
        public static function get_totals() {
            $summary = self::get_summary();
            $totals  = isset( $summary['totals'] ) && is_array( $summary['totals'] ) ? $summary['totals'] : array();
            return array(
                'items' => isset( $totals['items'] ) ? intval( $totals['items'] ) : 0,
                'value' => isset( $totals['value'] ) ? floatval( $totals['value'] ) : 0.0,
            );
        }

        /**
         * Number of low stock items.
         *
         * @return int
         */
        public static function get_low_stock_count() {
            $summary = self::get_summary();
            return isset( $summary['low_stock']['count'] ) ? intval( $summary['low_stock']['count'] ) : 0;
        }

        /**
         * Number of items due soon.
         *
         * @return int
         */
        public static function get_due_soon_count() {
            $summary = self::get_summary();
            return isset( $summary['due_soon']['count'] ) ? intval( $summary['due_soon']['count'] ) : 0;
        }

        /**
         * Recent purchases.
         *
         * @param int $limit Number to return.
         * @return array
         */
        public static function get_recent_purchases( $limit = 5 ) {
            $summary   = self::get_summary();
            $purchases = isset( $summary['recent_purchases'] ) && is_array( $summary['recent_purchases'] ) ? $summary['recent_purchases'] : array();
            return array_slice( $purchases, 0, $limit );
        }

        /**
         * Render dashboard information.
         */
        public static function render_dashboard() {
            $totals = self::get_totals();
            $low    = self::get_low_stock_count();
            $due    = self::get_due_soon_count();
            $recent = self::get_recent_purchases();
            ?>
            <div class="pit-dashboard">
                <h2><?php esc_html_e( 'Inventory Overview', 'personal-inventory-tracker' ); ?></h2>
                <p><strong><?php esc_html_e( 'Total Items:', 'personal-inventory-tracker' ); ?></strong> <?php echo esc_html( $totals['items'] ); ?></p>
                <p><strong><?php esc_html_e( 'Total Value:', 'personal-inventory-tracker' ); ?></strong> <?php echo esc_html( number_format( $totals['value'], 2 ) ); ?></p>
                <p><strong><?php esc_html_e( 'Low Stock Items:', 'personal-inventory-tracker' ); ?></strong> <?php echo esc_html( $low ); ?></p>
                <p><strong><?php esc_html_e( 'Items Due Soon:', 'personal-inventory-tracker' ); ?></strong> <?php echo esc_html( $due ); ?></p>
                <canvas id="pit-stock-chart" width="150" height="100"></canvas>
                <canvas id="pit-purchase-chart" width="150" height="100"></canvas>
                <?php if ( ! empty( $recent ) ) : ?>
                <h3><?php esc_html_e( 'Recent Purchases', 'personal-inventory-tracker' ); ?></h3>
                <ul>
                    <?php foreach ( $recent as $purchase ) : ?>
                    <li><?php echo esc_html( $purchase['name'] . ' (' . $purchase['date'] . ')' ); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <script>
            (function(){
                var low = <?php echo (int) $low; ?>;
                var due = <?php echo (int) $due; ?>;
                var ok  = Math.max(0, <?php echo (int) $totals['items']; ?> - low - due);
                var ctx = document.getElementById('pit-stock-chart').getContext('2d');
                var total = low + due + ok;
                var start = 0;
                var vals = [low, due, ok];
                var colors = ['#e74c3c','#f1c40f','#2ecc71'];
                vals.forEach(function(v,i){
                    if (v === 0) { return; }
                    var angle = 2*Math.PI*(v/total);
                    ctx.beginPath();
                    ctx.moveTo(75,50);
                    ctx.fillStyle = colors[i];
                    ctx.arc(75,50,50,start,start+angle);
                    ctx.closePath();
                    ctx.fill();
                    start += angle;
                });

                var ctx2 = document.getElementById('pit-purchase-chart').getContext('2d');
                var purchases = <?php echo json_encode( array_map( function( $p ) { return isset( $p['amount'] ) ? (float) $p['amount'] : 0; }, $recent ) ); ?>;
                var max = Math.max.apply(null, purchases.concat([1]));
                purchases.forEach(function(v,i){
                    var h = (v/max) * 90;
                    ctx2.fillStyle = '#3498db';
                    ctx2.fillRect(i*25+10, 90-h, 15, h);
                });
            })();
            </script>
            <?php
        }
    }
}
