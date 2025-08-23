<?php
/**
 * Settings handler for the Personal Inventory Tracker plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class responsible for registering and rendering plugin settings.
 */
class PIT_Settings {

    /**
     * Stored options.
     *
     * @var array
     */
    private $options = array();

    /**
     * Option name used in the database.
     */
    const OPTION_NAME = 'pit_settings';

    /**
     * Constructor - hooks into WordPress to register menu and settings.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add top level settings page to the admin menu.
     */
    public function add_settings_page() {
        $capability = 'manage_options';

        add_menu_page(
            __( 'Personal Inventory Tracker', 'pit' ),
            __( 'Inventory', 'pit' ),
            $capability,
            'pit-settings',
            array( $this, 'render_settings_page' ),
            'dashicons-archive'
        );
    }

    /**
     * Register settings, sections and fields with the Settings API.
     */
    public function register_settings() {
        register_setting( 'pit_settings_group', self::OPTION_NAME, array( $this, 'sanitize_settings' ) );

        add_settings_section(
            'pit_main_section',
            __( 'General Settings', 'pit' ),
            '__return_false',
            'pit-settings'
        );

        add_settings_field(
            'default_units',
            __( 'Default Unit List', 'pit' ),
            array( $this, 'field_default_units' ),
            'pit-settings',
            'pit_main_section'
        );

        add_settings_field(
            'default_interval',
            __( 'Default Interval (days)', 'pit' ),
            array( $this, 'field_default_interval' ),
            'pit-settings',
            'pit_main_section'
        );

        add_settings_field(
            'frontend_readonly',
            __( 'Front-end Read Only', 'pit' ),
            array( $this, 'field_frontend_readonly' ),
            'pit-settings',
            'pit_main_section'
        );

        add_settings_field(
            'ocr_regex',
            __( 'OCR Parsing Regex', 'pit' ),
            array( $this, 'field_ocr_regex' ),
            'pit-settings',
            'pit_main_section'
        );

        add_settings_field(
            'ocr_confidence',
            __( 'OCR Minimum Confidence', 'pit' ),
            array( $this, 'field_ocr_confidence' ),
            'pit-settings',
            'pit_main_section'
        );
    }

    /**
     * Sanitize settings before saving.
     *
     * @param array $input Raw values from the form.
     * @return array Sanitized values to save.
     */
    public function sanitize_settings( $input ) {
        $output = array();

        if ( isset( $input['default_units'] ) ) {
            $units = explode( ',', $input['default_units'] );
            $units = array_filter( array_map( 'sanitize_text_field', array_map( 'trim', $units ) ) );
            $output['default_units'] = implode( ',', $units );
        }

        if ( isset( $input['default_interval'] ) ) {
            $output['default_interval'] = absint( $input['default_interval'] );
        }

        $output['frontend_readonly'] = empty( $input['frontend_readonly'] ) ? 0 : 1;

        if ( isset( $input['ocr_regex'] ) ) {
            $regex = trim( $input['ocr_regex'] );
            // Validate the regex. Suppress warnings from invalid patterns.
            if ( @preg_match( $regex, '' ) !== false ) {
                $output['ocr_regex'] = $regex;
            }
        }

        if ( isset( $input['ocr_confidence'] ) ) {
            $confidence = floatval( $input['ocr_confidence'] );
            $confidence  = max( 0, min( 100, $confidence ) );
            $output['ocr_confidence'] = $confidence;
        }

        return $output;
    }

    /**
     * Render default units field.
     */
    public function field_default_units() {
        $options = get_option( self::OPTION_NAME );
        $value   = isset( $options['default_units'] ) ? esc_attr( $options['default_units'] ) : '';
        printf( '<input type="text" id="pit_default_units" name="%s[default_units]" value="%s" class="regular-text" />', esc_attr( self::OPTION_NAME ), $value );
        echo '<p class="description">' . esc_html__( 'Comma separated list of measurement units.', 'pit' ) . '</p>';
    }

    /**
     * Render default interval field.
     */
    public function field_default_interval() {
        $options = get_option( self::OPTION_NAME );
        $value   = isset( $options['default_interval'] ) ? esc_attr( $options['default_interval'] ) : '';
        printf( '<input type="number" min="0" id="pit_default_interval" name="%s[default_interval]" value="%s" class="small-text" />', esc_attr( self::OPTION_NAME ), $value );
    }

    /**
     * Render front-end read only field.
     */
    public function field_frontend_readonly() {
        $options = get_option( self::OPTION_NAME );
        $checked = ! empty( $options['frontend_readonly'] ) ? 'checked="checked"' : '';
        printf( '<input type="checkbox" id="pit_frontend_readonly" name="%s[frontend_readonly]" value="1" %s />', esc_attr( self::OPTION_NAME ), $checked );
        echo '<label for="pit_frontend_readonly">' . esc_html__( 'Prevent front-end edits.', 'pit' ) . '</label>';
    }

    /**
     * Render OCR regex field.
     */
    public function field_ocr_regex() {
        $options = get_option( self::OPTION_NAME );
        $value   = isset( $options['ocr_regex'] ) ? esc_attr( $options['ocr_regex'] ) : '';
        printf( '<input type="text" id="pit_ocr_regex" name="%s[ocr_regex]" value="%s" class="regular-text code" />', esc_attr( self::OPTION_NAME ), $value );
        echo '<p class="description">' . esc_html__( 'PHP regex used to parse OCR text.', 'pit' ) . '</p>';
    }

    /**
     * Render OCR confidence field.
     */
    public function field_ocr_confidence() {
        $options = get_option( self::OPTION_NAME );
        $value   = isset( $options['ocr_confidence'] ) ? esc_attr( $options['ocr_confidence'] ) : '';
        printf( '<input type="number" step="0.01" min="0" max="100" id="pit_ocr_confidence" name="%s[ocr_confidence]" value="%s" class="small-text" />', esc_attr( self::OPTION_NAME ), $value );
    }

    /**
     * Display the settings page.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $this->options = get_option( self::OPTION_NAME );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'pit_settings_group' );
                do_settings_sections( 'pit-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}

if ( is_admin() ) {
    new PIT_Settings();
}

