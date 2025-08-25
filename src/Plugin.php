<?php
/**
 * Main plugin bootstrap class.
 *
 * @package PersonalInventoryTracker
 */

namespace RealTreasury\Inventory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main Plugin class responsible for initialization and dependency management.
 */
class Plugin {

    /**
     * Plugin instance.
     *
     * @var Plugin
     */
    private static $instance = null;

    /**
     * Service container.
     *
     * @var Container
     */
    private $container;

    /**
     * Plugin version.
     *
     * @var string
     */
    const VERSION = '2.0.0';

    /**
     * Get plugin instance (singleton).
     *
     * @return Plugin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to enforce singleton pattern.
     */
    private function __construct() {
        $this->container = new Container();
        $this->init();
    }

    /**
     * Initialize the plugin.
     */
    private function init() {
        // Register core services
        $this->register_services();

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Register core services in the container.
     */
    private function register_services() {
        // Register settings service
        $this->container->bind( 'settings', function() {
            return new Settings();
        } );

        // Register database service
        $this->container->bind( 'database', function() {
            return new Database();
        } );

        // Register repository services
        $this->container->bind( 'item_repository', function() {
            return new Repositories\ItemRepository();
        } );

        // Register business services
        $this->container->bind( 'item_service', function() {
            return new Services\ItemService( $this->container->get( 'item_repository' ) );
        } );

        // Register REST API service
        $this->container->bind( 'rest_api', function() {
            return new REST\Items_Controller( $this->container );
        } );

        // Register admin service
        $this->container->bind( 'admin', function() {
            return new Admin\Admin( $this->container );
        } );
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks() {
        // Plugin activation/deactivation
        register_activation_hook( PIT_PLUGIN_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( PIT_PLUGIN_FILE, [ $this, 'deactivate' ] );

        // Initialize services
        add_action( 'init', [ $this, 'init_services' ] );
        add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );

        // Load textdomain
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

        // Admin initialization
        if ( is_admin() ) {
            add_action( 'admin_init', [ $this, 'init_admin' ] );
        }
    }

    /**
     * Plugin activation handler.
     */
    public function activate() {
        // Run database migrations
        $this->container->get( 'database' )->migrate();

        // Initialize settings
        $this->container->get( 'settings' )->activate();

        // Initialize capabilities
        Capabilities::init();

        // Initialize custom post types and taxonomies
        CPT::init();
        Taxonomy::init();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation handler.
     */
    public function deactivate() {
        // Clean up scheduled events
        wp_clear_scheduled_hook( 'pit_generate_recommendations' );
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Initialize core services.
     */
    public function init_services() {
        // Initialize CPT and taxonomy
        CPT::init();
        Taxonomy::init();

        // Initialize capabilities
        Capabilities::init();

        // Initialize settings
        $this->container->get( 'settings' )->init();

        // Initialize cron jobs
        Cron::init();
    }

    /**
     * Initialize REST API.
     */
    public function init_rest_api() {
        $this->container->get( 'rest_api' )->register_routes();
    }

    /**
     * Initialize admin functionality.
     */
    public function init_admin() {
        $this->container->get( 'admin' )->init();
    }

    /**
     * Load plugin textdomain.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'personal-inventory-tracker',
            false,
            dirname( PIT_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Get service from container.
     *
     * @param string $service Service name.
     * @return mixed Service instance.
     */
    public function get( $service ) {
        return $this->container->get( $service );
    }

    /**
     * Get plugin version.
     *
     * @return string Plugin version.
     */
    public function get_version() {
        return self::VERSION;
    }
}