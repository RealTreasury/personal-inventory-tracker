<?php
/**
 * Settings handler for Personal Inventory Tracker.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register and render plugin settings using the WordPress Settings API.
 */
class PIT_Settings {

    const OPTION_KEY   = 'pit_settings';
    const OPTION_GROUP = 'pit_settings_group';
    const CAPABILITY   = 'manage_options';

    /**
     * Constructor hooks into admin actions when in the admin dashboard.
     */
    public function __construct() {
        if ( is_admin() ) {
            add_action( 'admin_init', [ $this, 'register_settings' ] );
            add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        }
    }

    /**
     * Set default options on plugin activation.
     */
    public static function activate() {
        $defaults = [
            'default_unit_list'   => '',
            'default_interval'    => 30,
            'frontend_readonly'   => 0,
            'ocr_regex'           => '',
            'ocr_min_confidence'  => 0,
        ];

        add_option( self::OPTION_KEY, $defaults );
    }

    /**
     * Add top-level menu page for the plugin settings.
     */
    public function add_settings_page() {
        add_menu_page(
            __( 'Personal Inventory Settings', 'personal-inventory-tracker' ),
            __( 'Personal Inventory', 'personal-inventory-tracker' ),
            self::CAPABILITY,
            self::OPTION_KEY,
            [ $this, 'render_settings_page' ],
            'dashicons-archive'
        );
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Personal Inventory Settings', 'personal-inventory-tracker' ); ?></h1>
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( self::OPTION_KEY );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register settings, sections, and fields.
     */
    public function register_settings() {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_KEY,
            [
                'sanitize_callback' => [ $this, 'sanitize' ],
                'capability'        => self::CAPABILITY,
            ]
        );

        add_settings_section(
            'pit_main_section',
            __( 'General Settings', 'personal-inventory-tracker' ),
            null,
            self::OPTION_KEY
        );

        add_settings_field(
            'default_unit_list',
            __( 'Default Unit List', 'personal-inventory-tracker' ),
            [ $this, 'field_default_unit_list' ],
            self::OPTION_KEY,
            'pit_main_section'
        );

        add_settings_field(
            'default_interval',
            __( 'Default Interval (days)', 'personal-inventory-tracker' ),
            [ $this, 'field_default_interval' ],
            self::OPTION_KEY,
            'pit_main_section'
        );

        add_settings_field(
            'frontend_readonly',
            __( 'Front-end Read Only', 'personal-inventory-tracker' ),
            [ $this, 'field_frontend_readonly' ],
            self::OPTION_KEY,
            'pit_main_section'
        );

        add_settings_field(
            'ocr_regex',
            __( 'OCR Parsing Regex', 'personal-inventory-tracker' ),
            [ $this, 'field_ocr_regex' ],
            self::OPTION_KEY,
            'pit_main_section'
        );

        add_settings_field(
            'ocr_min_confidence',
            __( 'OCR Minimum Confidence', 'personal-inventory-tracker' ),
            [ $this, 'field_ocr_min_confidence' ],
            self::OPTION_KEY,
            'pit_main_section'
        );
    }

    /**
     * Sanitize settings input.
     *
     * @param array $input Raw input values.
     * @return array Sanitized values.
     */
    public function sanitize( $input ) {
        $sanitized = [];

        $sanitized['default_unit_list'] = isset( $input['default_unit_list'] )
            ? sanitize_textarea_field( $input['default_unit_list'] )
            : '';

        $sanitized['default_interval'] = isset( $input['default_interval'] )
            ? max( 0, absint( $input['default_interval'] ) )
            : 0;

        $sanitized['frontend_readonly'] = ! empty( $input['frontend_readonly'] ) ? 1 : 0;

        $sanitized['ocr_regex'] = isset( $input['ocr_regex'] )
            ? sanitize_text_field( $input['ocr_regex'] )
            : '';

        if ( $sanitized['ocr_regex'] && false === @preg_match( $sanitized['ocr_regex'], '' ) ) {
            add_settings_error(
                self::OPTION_KEY,
                'invalid_regex',
                __( 'Invalid OCR regex pattern.', 'personal-inventory-tracker' )
            );
            $sanitized['ocr_regex'] = '';
        }

        $sanitized['ocr_min_confidence'] = isset( $input['ocr_min_confidence'] )
            ? floatval( $input['ocr_min_confidence'] )
            : 0;

        if ( $sanitized['ocr_min_confidence'] < 0 ) {
            $sanitized['ocr_min_confidence'] = 0;
        } elseif ( $sanitized['ocr_min_confidence'] > 100 ) {
            $sanitized['ocr_min_confidence'] = 100;
        }

        return $sanitized;
    }

    /**
     * Helper to get a single option value.
     *
     * @param string $key     Option key within the settings array.
     * @param mixed  $default Default value if key not set.
     * @return mixed
     */
    private function get_option( $key, $default = '' ) {
        $options = get_option( self::OPTION_KEY, [] );
        return isset( $options[ $key ] ) ? $options[ $key ] : $default;
    }

    /** Field callbacks ********************************************************/

    /**
     * Render textarea for default unit list.
     */
    public function field_default_unit_list() {
        $value = $this->get_option( 'default_unit_list' );
        printf(
            '<textarea name="%1$s[default_unit_list]" rows="5" cols="50">%2$s</textarea><p class="description">%3$s</p>',
            esc_attr( self::OPTION_KEY ),
            esc_textarea( $value ),
            esc_html__( 'Comma-separated list of units.', 'personal-inventory-tracker' )
        );
    }

    /**
     * Render number input for default interval.
     */
    public function field_default_interval() {
        $value = $this->get_option( 'default_interval', 0 );
        printf(
            '<input type="number" min="0" name="%1$s[default_interval]" value="%2$s" />',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $value )
        );
    }

    /**
     * Render checkbox for front-end read-only toggle.
     */
    public function field_frontend_readonly() {
        $value = $this->get_option( 'frontend_readonly', 0 );
        printf(
            '<label><input type="checkbox" name="%1$s[frontend_readonly]" value="1" %2$s /> %3$s</label>',
            esc_attr( self::OPTION_KEY ),
            checked( 1, $value, false ),
            esc_html__( 'Disable editing on the front-end.', 'personal-inventory-tracker' )
        );
    }

    /**
     * Render text input for OCR regex.
     */
    public function field_ocr_regex() {
        $value = $this->get_option( 'ocr_regex' );
        printf(
            '<input type="text" class="regular-text" name="%1$s[ocr_regex]" value="%2$s" />',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $value )
        );
    }

    /**
     * Render number input for OCR minimum confidence.
     */
    public function field_ocr_min_confidence() {
        $value = $this->get_option( 'ocr_min_confidence', 0 );
        printf(
            '<input type="number" min="0" max="100" step="0.1" name="%1$s[ocr_min_confidence]" value="%2$s" />',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $value )
        );
    }
}

// Instantiate on admin pages.
if ( is_admin() ) {
    new PIT_Settings();
}

