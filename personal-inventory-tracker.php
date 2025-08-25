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

// Autoloader
require_once PIT_PLUGIN_DIR . 'vendor/autoload.php';
require_once PIT_PLUGIN_DIR . 'pit-functions.php';
require_once PIT_PLUGIN_DIR . 'includes/class-pit-cache.php';
require_once PIT_PLUGIN_DIR . 'includes/class-pit-blocks.php';
require_once PIT_PLUGIN_DIR . 'includes/class-pit-enhanced-rest.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'pit cache', 'RealTreasury\Inventory\CLI\Cache_Command' );
}

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

        $pit_settings = \RealTreasury\Inventory\Settings::get_settings();

        wp_localize_script( 'pit-enhanced', 'pitApp', [
            'restUrl'     => rest_url( 'pit/v2/' ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
            'currentUser' => get_current_user_id(),
            'userCan'     => [
                'edit'   => current_user_can( 'manage_inventory_items' ),
                'delete' => current_user_can( 'manage_inventory_items' ),
                'manage' => current_user_can( 'manage_inventory_settings' ),
            ],
            'settings'   => [
                'publicAccess'      => ! empty( $pit_settings['public_access'] ),
                'readOnlyMode'      => ! empty( $pit_settings['read_only_mode'] ),
                'defaultConfidence' => intval( $pit_settings['ocr_min_confidence'] ),
                'currency'          => $pit_settings['currency'],
            ],
            'i18n'       => [
                'dashboard'       => __( 'Dashboard', 'personal-inventory-tracker' ),
                'inventory'       => __( 'Inventory', 'personal-inventory-tracker' ),
                'analytics'       => __( 'Analytics', 'personal-inventory-tracker' ),
                'scanner'         => __( 'Scanner', 'personal-inventory-tracker' ),
                'shoppingList'    => __( 'Shopping List', 'personal-inventory-tracker' ),
                'search'          => __( 'Search items...', 'personal-inventory-tracker' ),
                'addItem'         => __( 'Add Item', 'personal-inventory-tracker' ),
                'totalItems'      => __( 'Total Items', 'personal-inventory-tracker' ),
                'lowStock'        => __( 'Low Stock', 'personal-inventory-tracker' ),
                'outOfStock'      => __( 'Out of Stock', 'personal-inventory-tracker' ),
                'recentPurchases' => __( 'Recent Purchases', 'personal-inventory-tracker' ),
                'scanReceipt'     => __( 'Scan Receipt', 'personal-inventory-tracker' ),
                'categories'      => __( 'Categories', 'personal-inventory-tracker' ),
                'confirmDelete'   => __( 'Are you sure you want to delete this item?', 'personal-inventory-tracker' ),
                'loading'         => __( 'Loading...', 'personal-inventory-tracker' ),
                'error'           => __( 'An error occurred', 'personal-inventory-tracker' ),
                'success'         => __( 'Operation completed successfully', 'personal-inventory-tracker' ),
            ],
            'assetUrl'   => esc_url_raw( PIT_PLUGIN_URL . 'assets/' ),
        ] );
    }

    if ( $has_ocr ) {
        wp_enqueue_script(
            'pit-ocr-scanner',
            PIT_PLUGIN_URL . 'assets/ocr-scanner.js',
            [],
            PIT_VERSION,
            [
                'in_footer' => true,
                'strategy'  => 'defer',
            ]
        );
        wp_script_add_data( 'pit-ocr-scanner', 'type', 'module' );

        $existing_items = get_posts(
            [
                'post_type'      => 'pit_item',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
            ]
        );

        $choices = [];
        foreach ( $existing_items as $item ) {
            $choices[] = [
                'id'    => $item->ID,
                'title' => $item->post_title,
            ];
        }

        wp_localize_script(
            'pit-ocr-scanner',
            'pitApp',
            [
                'restUrl' => rest_url( 'pit/v2/' ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
                'items'   => $choices,
                'assetUrl' => esc_url_raw( PIT_PLUGIN_URL . 'assets/' ),
            ]
        );
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
    $pit_settings     = \RealTreasury\Inventory\Settings::get_settings();
    $read_only        = ! empty( $pit_settings['read_only_mode'] );

    if (
        isset( $_POST['action'], $_POST['pit_nonce'] ) &&
        'pit_quick_add' === $_POST['action'] &&
        wp_verify_nonce( $_POST['pit_nonce'], 'pit_quick_add' ) &&
        current_user_can( 'manage_inventory_items' ) &&
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

    $can_edit   = current_user_can( 'manage_inventory_items' );
    $can_manage = current_user_can( 'manage_inventory_settings' );
    $settings   = [
        'publicAccess'      => ! empty( $pit_settings['public_access'] ),
        'readOnlyMode'      => $read_only,
        'defaultConfidence' => intval( $pit_settings['ocr_min_confidence'] ),
        'currency'          => $pit_settings['currency'],
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

// OCR scanner shortcode
function pit_ocr_scanner_shortcode() {
    ob_start();
    include PIT_PLUGIN_DIR . 'templates/ocr-scanner.php';
    return ob_get_clean();
}

// Initialize enhanced functionality
function pit_init_enhanced() {
    // Register enhanced REST API
    $rest_api = new PIT_Enhanced_REST();
    add_action('rest_api_init', [$rest_api, 'register_routes']);
    
    // Register shortcode
    add_shortcode('pit_enhanced', 'pit_enhanced_shortcode');
    add_shortcode('pit_dashboard', 'pit_enhanced_shortcode');
    add_shortcode('personal_inventory', 'pit_enhanced_shortcode');
    add_shortcode('pit_ocr_scanner', 'pit_ocr_scanner_shortcode');

    // Backward compatibility
    add_shortcode('pit_app', 'pit_enhanced_shortcode');
}

// Activation/Deactivation hooks
function pit_activate() {
    \RealTreasury\Inventory\CPT::activate();
    \RealTreasury\Inventory\Taxonomy::activate();
    \RealTreasury\Inventory\Cron::activate();
    \RealTreasury\Inventory\Settings::activate();
    \RealTreasury\Inventory\Capabilities::add_capabilities();
    
    add_option('pit_version', PIT_VERSION);

    flush_rewrite_rules();
}

function pit_deactivate() {
    \RealTreasury\Inventory\CPT::deactivate();
    \RealTreasury\Inventory\Taxonomy::deactivate();
    \RealTreasury\Inventory\Cron::deactivate();
    \RealTreasury\Inventory\Capabilities::remove_capabilities();
    flush_rewrite_rules();
}

// Register hooks
register_activation_hook(PIT_PLUGIN_FILE, 'pit_activate');
register_deactivation_hook(PIT_PLUGIN_FILE, 'pit_deactivate');
register_activation_hook(PIT_PLUGIN_FILE, array( \RealTreasury\Inventory\Database::class, 'migrate' ) );
register_deactivation_hook(PIT_PLUGIN_FILE, array( \RealTreasury\Inventory\Database::class, 'rollback' ) );

// Initialize
add_action('init', [\RealTreasury\Inventory\CPT::class, 'register']);
add_action('init', [\RealTreasury\Inventory\Taxonomy::class, 'register']);
add_action('plugins_loaded', [\RealTreasury\Inventory\Database::class, 'migrate']);
add_action('plugins_loaded', function() {
    pit_init_enhanced();
    ( new \RealTreasury\Inventory\Admin\Admin() )->init();
    \RealTreasury\Inventory\Cron::init();
    \RealTreasury\Inventory\Settings::init();
});

// Admin enqueue (keep existing admin functionality)
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'pit_') !== false) {
        wp_enqueue_style('pit-admin', PIT_PLUGIN_URL . 'assets/admin.css', [], PIT_VERSION);
        wp_enqueue_script('pit-admin', PIT_PLUGIN_URL . 'assets/admin.js', [], PIT_VERSION, true);
    }
});

