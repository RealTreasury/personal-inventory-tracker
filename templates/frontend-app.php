<?php
/**
 * Enhanced frontend template for Personal Inventory Tracker
 *
 * @package PersonalInventoryTracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<?php if ( ! $has_access ) : ?>
    <div id="pit-enhanced-app">
        <div class="pit-access-denied" style="text-align: center; padding: 3rem; background: white; border-radius: 12px; border: 1px solid #e5e7eb;">
            <svg style="width: 64px; height: 64px; margin: 0 auto 1rem; color: #6b7280;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
            <h3 style="font-size: 1.125rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">
                <?php esc_html_e( 'Access Required', 'personal-inventory-tracker' ); ?>
            </h3>
            <p style="color: #6b7280; margin-bottom: 1.5rem;">
                <?php esc_html_e( 'Please log in to access the inventory tracker.', 'personal-inventory-tracker' ); ?>
            </p>
            <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" 
               style="display: inline-block; padding: 0.5rem 1rem; background: #3b82f6; color: white; text-decoration: none; border-radius: 8px; font-weight: 500;">
                <?php esc_html_e( 'Log In', 'personal-inventory-tracker' ); ?>
            </a>
        </div>
    </div>
<?php else : ?>
    <!-- Loading Screen -->
    <div id="pit-loading-screen" style="position: fixed; inset: 0; background: rgba(249, 250, 251, 0.9); display: flex; align-items: center; justify-content: center; z-index: 9999;">
        <div style="text-align: center;">
            <div style="width: 48px; height: 48px; margin: 0 auto 1rem; border: 4px solid #e5e7eb; border-top: 4px solid #3b82f6; border-radius: 50%; animation: spin 1s linear infinite;"></div>
            <p style="color: #6b7280; font-weight: 500;"><?php esc_html_e( 'Loading Inventory Tracker...', 'personal-inventory-tracker' ); ?></p>
        </div>
    </div>

    <!-- Main App Container -->
    <div id="pit-enhanced-app" 
         data-view="<?php echo esc_attr( $view ); ?>"
         data-can-edit="<?php echo $can_edit ? 'true' : 'false'; ?>"
         data-can-manage="<?php echo $can_manage ? 'true' : 'false'; ?>"
         data-read-only="<?php echo $read_only ? 'true' : 'false'; ?>"
         data-settings="<?php echo esc_attr( wp_json_encode( $settings ) ); ?>"
         style="min-height: 100vh; background-color: #f9fafb;">
        
        <!-- Fallback content for users without JavaScript -->
        <noscript>
            <div style="text-align: center; padding: 3rem; background: white; margin: 2rem; border-radius: 12px; border: 1px solid #e5e7eb;">
                <svg style="width: 64px; height: 64px; margin: 0 auto 1rem; color: #f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <h3 style="font-size: 1.125rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem;">
                    <?php esc_html_e( 'JavaScript Required', 'personal-inventory-tracker' ); ?>
                </h3>
                <p style="color: #6b7280; margin-bottom: 1.5rem;">
                    <?php esc_html_e( 'This application requires JavaScript to function properly. Please enable JavaScript in your browser.', 'personal-inventory-tracker' ); ?>
                </p>
            </div>
        </noscript>
        
        <!-- Basic HTML fallback for critical functionality -->
        <div id="pit-fallback" style="display: none;">
            <div style="max-width: 1200px; margin: 0 auto; padding: 2rem;">
                <h1 style="font-size: 2rem; font-weight: 700; color: #111827; margin-bottom: 2rem;">
                    <?php esc_html_e( 'Personal Inventory Tracker', 'personal-inventory-tracker' ); ?>
                </h1>
                
                <?php if ( ! $read_only && $can_edit ) : ?>
                    <!-- Quick Add Form -->
                    <div style="background: white; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid #e5e7eb;">
                        <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">
                            <?php esc_html_e( 'Quick Add Item', 'personal-inventory-tracker' ); ?>
                        </h2>
                        <form method="post" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                            <?php wp_nonce_field( 'pit_quick_add', 'pit_nonce' ); ?>
                            <input type="hidden" name="action" value="pit_quick_add">
                            <div>
                                <label style="display: block; font-weight: 500; margin-bottom: 0.25rem;">
                                    <?php esc_html_e( 'Item Name', 'personal-inventory-tracker' ); ?>
                                </label>
                                <input type="text" name="item_name" required 
                                       style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; width: 200px;">
                            </div>
                            <div>
                                <label style="display: block; font-weight: 500; margin-bottom: 0.25rem;">
                                    <?php esc_html_e( 'Quantity', 'personal-inventory-tracker' ); ?>
                                </label>
                                <input type="number" name="quantity" value="1" min="0" 
                                       style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; width: 100px;">
                            </div>
                            <button type="submit" 
                                    style="padding: 0.5rem 1rem; background: #3b82f6; color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer;">
                                <?php esc_html_e( 'Add Item', 'personal-inventory-tracker' ); ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <!-- Recent Items -->
                <div style="background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e5e7eb;">
                    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">
                        <?php esc_html_e( 'Recent Items', 'personal-inventory-tracker' ); ?>
                    </h2>
                    
                    <?php if ( $recent_items ) : ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="border-bottom: 1px solid #e5e7eb;">
                                        <th style="text-align: left; padding: 0.5rem; font-weight: 600;">
                                            <?php esc_html_e( 'Name', 'personal-inventory-tracker' ); ?>
                                        </th>
                                        <th style="text-align: left; padding: 0.5rem; font-weight: 600;">
                                            <?php esc_html_e( 'Quantity', 'personal-inventory-tracker' ); ?>
                                        </th>
                                        <th style="text-align: left; padding: 0.5rem; font-weight: 600;">
                                            <?php esc_html_e( 'Category', 'personal-inventory-tracker' ); ?>
                                        </th>
                                        <th style="text-align: left; padding: 0.5rem; font-weight: 600;">
                                            <?php esc_html_e( 'Status', 'personal-inventory-tracker' ); ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $recent_items as $item ) :
                                        $qty = (int) get_post_meta( $item->ID, 'pit_qty', true );
        $threshold = (int) get_post_meta( $item->ID, 'pit_threshold', true );
        $categories = wp_get_post_terms( $item->ID, 'pit_category', [ 'fields' => 'names' ] );
        $category = $categories ? $categories[0] : __( 'Uncategorized', 'personal-inventory-tracker' );
        
        $status = 'In Stock';
        $status_color = '#10b981';
        
        if ( 0 === $qty ) {
            $status = __( 'Out of Stock', 'personal-inventory-tracker' );
            $status_color = '#ef4444';
        } elseif ( $threshold > 0 && $qty <= $threshold ) {
            $status = __( 'Low Stock', 'personal-inventory-tracker' );
            $status_color = '#f59e0b';
        }
    ?>
                                        <tr style="border-bottom: 1px solid #f3f4f6;">
                                            <td style="padding: 0.5rem; font-weight: 500;">
                                                <?php echo esc_html( $item->post_title ); ?>
                                            </td>
                                            <td style="padding: 0.5rem;">
                                                <?php echo esc_html( $qty ); ?>
                                            </td>
                                            <td style="padding: 0.5rem;">
                                                <?php echo esc_html( $category ); ?>
                                            </td>
                                            <td style="padding: 0.5rem;">
                                                <span style="display: inline-block; padding: 0.25rem 0.5rem; background: <?php echo esc_attr( $status_color ); ?>20; color: <?php echo esc_attr( $status_color ); ?>; border-radius: 12px; font-size: 0.75rem; font-weight: 500;">
                                                    <?php echo esc_html( $status ); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <p style="color: #6b7280; text-align: center; padding: 2rem;">
                            <?php esc_html_e( 'No items found. Add some items to get started!', 'personal-inventory-tracker' ); ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Links -->
                <div style="margin-top: 2rem; text-align: center;">
                    <p style="color: #6b7280; margin-bottom: 1rem;">
                        <?php esc_html_e( 'For the full experience, enable JavaScript in your browser.', 'personal-inventory-tracker' ); ?>
                    </p>
                    <?php if ( $can_manage ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=pit_dashboard' ) ); ?>" 
                           style="display: inline-block; padding: 0.5rem 1rem; background: #6b7280; color: white; text-decoration: none; border-radius: 6px; margin: 0 0.5rem;">
                            <?php esc_html_e( 'Admin Dashboard', 'personal-inventory-tracker' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Inline CSS for animations and responsive design -->
    <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            #pit-enhanced-app {
                padding: 1rem !important;
            }
        }
        
        /* Hide fallback content when React loads */
        .react-loaded #pit-fallback {
            display: none !important;
        }
        
        .react-loaded #pit-loading-screen {
            display: none !important;
        }
        
        /* Ensure proper spacing for WordPress themes */
        #pit-enhanced-app {
            margin: 0;
            padding: 0;
        }
        
        /* Print styles */
        @media print {
            #pit-loading-screen,
            #pit-fallback form,
            button,
            .no-print {
                display: none !important;
            }
        }
        
        /* High contrast mode support */
        @media (prefers-contrast: high) {
            #pit-enhanced-app {
                --primary: #0000ff;
                --text: #000000;
                --background: #ffffff;
            }
        }
        
        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>

    <!-- JavaScript to hide loading screen once React is ready -->
    <script>
        // Show fallback if React doesn't load within 5 seconds
        setTimeout(function() {
            var loadingScreen = document.getElementById('pit-loading-screen');
            var fallback = document.getElementById('pit-fallback');
            var app = document.getElementById('pit-enhanced-app');
            
            if (loadingScreen && loadingScreen.style.display !== 'none') {
                if (fallback) fallback.style.display = 'block';
                if (loadingScreen) loadingScreen.style.display = 'none';
                console.warn('Personal Inventory Tracker: React components did not load, showing fallback');
            }
        }, 5000);
        
        // Handle form submission for quick add (fallback)
        document.addEventListener('DOMContentLoaded', function() {
            var forms = document.querySelectorAll('form[action="pit_quick_add"]');
            forms.forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    var button = form.querySelector('button[type="submit"]');
                    if (button) {
                        button.textContent = '<?php echo esc_js( __( 'Adding...', 'personal-inventory-tracker' ) ); ?>';
                        button.disabled = true;
                    }
                });
            });
        });
        
        // Accessibility improvements
        document.addEventListener('DOMContentLoaded', function() {
            // Add skip link
            var skipLink = document.createElement('a');
            skipLink.href = '#pit-enhanced-app';
            skipLink.textContent = '<?php echo esc_js( __( 'Skip to main content', 'personal-inventory-tracker' ) ); ?>';
            skipLink.style.cssText = 'position:absolute;top:-40px;left:6px;background:#000;color:#fff;padding:8px;z-index:10000;text-decoration:none;border-radius:4px;';
            skipLink.addEventListener('focus', function() {
                this.style.top = '6px';
            });
            skipLink.addEventListener('blur', function() {
                this.style.top = '-40px';
            });
            document.body.insertBefore(skipLink, document.body.firstChild);
        });
    </script>
<?php endif; ?>

<?php
if ( ! empty( $quick_add_notice ) ) {
    echo $quick_add_notice;
}
?>
