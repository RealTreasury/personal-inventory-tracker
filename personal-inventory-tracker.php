<?php
/**
 * Plugin Name:       Personal Inventory Tracker Pro
 * Description:       Advanced personal inventory management with modern dashboard, analytics, OCR scanning, and more.
 * Version:           2.0.0
 * Author:            Enhanced by Claude
 * License:           GPL-2.0+
 * Text Domain:       personal-inventory-tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PIT_PLUGIN_FILE', __FILE__ );
define( 'PIT_PLUGIN_DIR', plugin_dir_path( PIT_PLUGIN_FILE ) );
define( 'PIT_PLUGIN_URL', plugin_dir_url( PIT_PLUGIN_FILE ) );
define( 'PIT_PLUGIN_BASENAME', plugin_basename( PIT_PLUGIN_FILE ) );
define( 'PIT_VERSION', '2.0.0' );

require_once PIT_PLUGIN_DIR . 'vendor/autoload.php';
require_once PIT_PLUGIN_DIR . 'pit-functions.php';

use RealTreasury\Inventory\{ Admin, CPT, Cron, Taxonomy, Settings, Database, Enhanced_REST };

// Enhanced frontend functionality
function pit_enqueue_enhanced_frontend() {
    if ( ! is_singular() ) {
        return;
    }

    $post = get_post();
    if ( ! $post ) {
        return;
    }

    $content   = $post->post_content;
    $has_app   = has_shortcode( $content, 'pit_enhanced' ) || has_shortcode( $content, 'pit_dashboard' ) || has_shortcode( $content, 'pit_app' );
    $has_ocr   = has_shortcode( $content, 'pit_ocr_scanner' );

    if ( ! $has_app && ! $has_ocr ) {
        return;
    }

    $app_js  = PIT_PLUGIN_DIR . 'assets/app.js';
    $app_css = PIT_PLUGIN_DIR . 'assets/app.css';

    $script_args = [
        'in_footer' => true,
        'strategy'  => 'defer',
    ];


    if ( file_exists( $app_css ) ) {
        wp_register_style( 'pit-enhanced', PIT_PLUGIN_URL . 'assets/app.css', [], PIT_VERSION );
        wp_enqueue_style( 'pit-enhanced' );
    }

    if ( file_exists( $app_js ) ) {
        wp_register_script( 'pit-enhanced', PIT_PLUGIN_URL . 'assets/app.js', array(), PIT_VERSION, $script_args );
        wp_enqueue_script( 'pit-enhanced' );
        wp_script_add_data( 'pit-enhanced', 'type', 'module' );

        wp_localize_script( 'pit-enhanced', 'pitApp', [
            'restUrl'   => rest_url( 'pit/v2/' ),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'currentUser' => get_current_user_id(),
            'userCan'   => [
                'edit'   => current_user_can( 'edit_posts' ),
                'delete' => current_user_can( 'delete_posts' ),
                'manage' => current_user_can( 'manage_options' ),
            ],
            'settings' => [
                'publicAccess'    => get_option( 'pit_public_access', false ),
                'readOnlyMode'    => get_option( 'pit_read_only_mode', false ),
                'defaultConfidence' => get_option( 'pit_ocr_confidence', 60 ),
                'currency'        => get_option( 'pit_currency', '$' ),
            ],
            'i18n' => [
                'dashboard'     => __( 'Dashboard', 'personal-inventory-tracker' ),
                'inventory'     => __( 'Inventory', 'personal-inventory-tracker' ),
                'analytics'     => __( 'Analytics', 'personal-inventory-tracker' ),
                'scanner'       => __( 'Scanner', 'personal-inventory-tracker' ),
                'shoppingList'  => __( 'Shopping List', 'personal-inventory-tracker' ),
                'search'        => __( 'Search items...', 'personal-inventory-tracker' ),
                'addItem'       => __( 'Add Item', 'personal-inventory-tracker' ),
                'totalItems'    => __( 'Total Items', 'personal-inventory-tracker' ),
                'lowStock'      => __( 'Low Stock', 'personal-inventory-tracker' ),
                'outOfStock'    => __( 'Out of Stock', 'personal-inventory-tracker' ),
                'recentPurchases' => __( 'Recent Purchases', 'personal-inventory-tracker' ),
                'exportData'    => __( 'Export Data', 'personal-inventory-tracker' ),
                'importData'    => __( 'Import Data', 'personal-inventory-tracker' ),
                'scanReceipt'   => __( 'Scan Receipt', 'personal-inventory-tracker' ),
                'categories'    => __( 'Categories', 'personal-inventory-tracker' ),
                'confirmDelete' => __( 'Are you sure you want to delete this item?', 'personal-inventory-tracker' ),
                'loading'       => __( 'Loading...', 'personal-inventory-tracker' ),
                'error'         => __( 'An error occurred', 'personal-inventory-tracker' ),
                'success'       => __( 'Operation completed successfully', 'personal-inventory-tracker' ),
            ],
        ] );
    }
}
add_action( 'wp_enqueue_scripts', 'pit_enqueue_enhanced_frontend' );

// Enhanced shortcode
function pit_enhanced_shortcode( $atts = [] ) {
    $atts = shortcode_atts(
        [
            'view'     => 'dashboard',
            'public'   => 'false',
            'readonly' => 'false',
        ],
        $atts
    );

    $quick_add_notice = '';
    $read_only        = get_option( 'pit_read_only_mode', false );

    if (
        isset( $_POST['action'], $_POST['pit_nonce'] ) &&
        'pit_quick_add' === $_POST['action'] &&
        wp_verify_nonce( $_POST['pit_nonce'], 'pit_quick_add' ) &&
        current_user_can( 'edit_posts' ) &&
        ! $read_only
    ) {
        $item_name = sanitize_text_field( wp_unslash( $_POST['item_name'] ) );
        $quantity  = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 0;

        if ( $item_name ) {
            $post_id = wp_insert_post(
                [
                    'post_type'   => 'pit_item',
                    'post_title'  => $item_name,
                    'post_status' => 'publish',
                ]
            );

            if ( ! is_wp_error( $post_id ) ) {
                update_post_meta( $post_id, 'pit_qty', $quantity );
                update_post_meta( $post_id, 'pit_created_via', 'fallback' );

                $quick_add_notice = '<div style="background: #d1fae5; border: 1px solid #a7f3d0; color: #065f46; padding: 1rem; border-radius: 8px; margin: 1rem 0;">' .
                    esc_html(
                        sprintf(
                            __( 'Successfully added "%s" with quantity %d', 'personal-inventory-tracker' ),
                            $item_name,
                            $quantity
                        )
                    ) .
                    '</div>';
            }
        }
    }

    $can_edit   = current_user_can( 'edit_posts' );
    $can_manage = current_user_can( 'manage_options' );
    $settings   = [
        'publicAccess'      => get_option( 'pit_public_access', false ),
        'readOnlyMode'      => $read_only,
        'defaultConfidence' => get_option( 'pit_ocr_confidence', 60 ),
        'currency'          => get_option( 'pit_currency', '$' ),
        'dateFormat'        => get_option( 'date_format' ),
        'timeFormat'        => get_option( 'time_format' ),
    ];
    $has_access = $settings['publicAccess'] || is_user_logged_in();

    $recent_items = get_posts(
        [
            'post_type'      => 'pit_item',
            'posts_per_page' => 10,
            'post_status'    => 'publish',
        ]
    );

    $view = sanitize_key( $atts['view'] );
    $inventory_app_props = [
        'view'      => $view,
        'canEdit'   => $can_edit,
        'canManage' => $can_manage,
        'readOnly'  => $read_only,
        'settings'  => $settings,
    ];

    ob_start();
    include PIT_PLUGIN_DIR . 'templates/frontend-app.php';
    return ob_get_clean();
}

// Initialize enhanced functionality
function pit_init_enhanced() {
    // Register enhanced REST API
    $rest_api = new Enhanced_REST();
    add_action( 'rest_api_init', [ $rest_api, 'register_routes' ] );
    
    // Register shortcode
    add_shortcode('pit_enhanced', 'pit_enhanced_shortcode');
    add_shortcode('pit_dashboard', 'pit_enhanced_shortcode');
    
    // Backward compatibility
    add_shortcode('pit_app', 'pit_enhanced_shortcode');
}

// Activation/Deactivation hooks
function pit_activate() {
    CPT::activate();
    Taxonomy::activate();
    Cron::activate();
    Settings::activate();
    
    // Add enhanced options
    add_option('pit_public_access', false);
    add_option('pit_read_only_mode', false);
    add_option('pit_ocr_confidence', 60);
    add_option('pit_currency', '$');
    add_option('pit_version', PIT_VERSION);

    Database::migrate();

    flush_rewrite_rules();
}

function pit_deactivate() {
    CPT::deactivate();
    Taxonomy::deactivate();
    Cron::deactivate();
    flush_rewrite_rules();
}

// Register hooks
register_activation_hook(PIT_PLUGIN_FILE, 'pit_activate');
register_deactivation_hook(PIT_PLUGIN_FILE, 'pit_deactivate');

// Initialize
add_action( 'init', [ CPT::class, 'register' ] );
add_action( 'init', [ Taxonomy::class, 'register' ] );
add_action( 'plugins_loaded', [ Database::class, 'migrate' ] );
add_action( 'plugins_loaded', function() {
    pit_init_enhanced();
    Settings::init();
    ( new Admin() )->init();
    Cron::init();
});

// Admin enqueue (keep existing admin functionality)
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'pit_') !== false) {
        wp_enqueue_style('pit-admin', PIT_PLUGIN_URL . 'assets/admin.css', [], PIT_VERSION);
        wp_enqueue_script('pit-admin', PIT_PLUGIN_URL . 'assets/admin.js', [], PIT_VERSION, true);
    }
});

// Add settings page for enhanced options
add_action('admin_menu', function() {
    add_submenu_page(
        'pit_dashboard',
        __('Enhanced Settings', 'personal-inventory-tracker'),
        __('Enhanced Settings', 'personal-inventory-tracker'),
        'manage_options',
        'pit_enhanced_settings',
        'pit_enhanced_settings_page'
    );
});

function pit_enhanced_settings_page() {
    if ( isset( $_POST['submit'] ) ) {
        check_admin_referer( 'pit_enhanced_settings', 'pit_nonce' );

        if ( current_user_can( 'manage_inventory_items' ) ) {
            $public_access  = isset( $_POST['public_access'] ) ? boolval( wp_unslash( $_POST['public_access'] ) ) : false;
            $read_only_mode = isset( $_POST['read_only_mode'] ) ? boolval( wp_unslash( $_POST['read_only_mode'] ) ) : false;
            $ocr_confidence = isset( $_POST['ocr_confidence'] ) ? absint( wp_unslash( $_POST['ocr_confidence'] ) ) : 60;
            $currency       = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '$';

            update_option( 'pit_public_access', $public_access );
            update_option( 'pit_read_only_mode', $read_only_mode );
            update_option( 'pit_ocr_confidence', $ocr_confidence );
            update_option( 'pit_currency', $currency );

            echo '<div class="notice notice-success"><p>' . __( 'Settings saved!', 'personal-inventory-tracker' ) . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __( 'You do not have permission to save these settings.', 'personal-inventory-tracker' ) . '</p></div>';
        }
    }

    $public_access  = get_option( 'pit_public_access', false );
    $read_only_mode = get_option( 'pit_read_only_mode', false );
    $ocr_confidence = get_option( 'pit_ocr_confidence', 60 );
    $currency       = get_option( 'pit_currency', '$' );
    ?>
    <div class="wrap">
        <h1><?php _e('Enhanced Settings', 'personal-inventory-tracker'); ?></h1>
        <form method="post">
            <?php wp_nonce_field( 'pit_enhanced_settings', 'pit_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th><?php _e('Public Access', 'personal-inventory-tracker'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="public_access" value="1" <?php checked($public_access); ?>>
                            <?php _e('Allow non-logged-in users to view inventory', 'personal-inventory-tracker'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Read-Only Mode', 'personal-inventory-tracker'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="read_only_mode" value="1" <?php checked($read_only_mode); ?>>
                            <?php _e('Disable editing for all users', 'personal-inventory-tracker'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('OCR Confidence', 'personal-inventory-tracker'); ?></th>
                    <td>
                        <input type="range" name="ocr_confidence" min="30" max="95" value="<?php echo $ocr_confidence; ?>" class="regular-text">
                        <span id="confidence-value"><?php echo $ocr_confidence; ?>%</span>
                        <script>
                        document.querySelector('input[name="ocr_confidence"]').addEventListener('input', function() {
                            document.getElementById('confidence-value').textContent = this.value + '%';
                        });
                        </script>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Currency Symbol', 'personal-inventory-tracker'); ?></th>
                    <td>
                        <input type="text" name="currency" value="<?php echo esc_attr($currency); ?>" class="regular-text">
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
