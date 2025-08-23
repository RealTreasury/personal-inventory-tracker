<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PIT_Settings {

    const OPTION_NAME = 'pit_settings';

    public static function activate() {
        add_option( self::OPTION_NAME, self::get_defaults() );
        add_option( 'pit_intro_dismissed', 0 );
    }

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
    }

    public static function get_defaults() {
        return array(
            'unit_list'         => array(),
            'default_interval'  => 0,
            'frontend_read_only'=> 0,
            'ocr_regex'         => '',
            'ocr_min_confidence'=> 60,
        );
    }

    public static function get_settings() {
        $options = get_option( self::OPTION_NAME, array() );
        return wp_parse_args( $options, self::get_defaults() );
    }

    public static function register_settings() {
        register_setting( 'pit_settings', self::OPTION_NAME, array( __CLASS__, 'sanitize' ) );

        add_settings_section(
            'pit_settings_main',
            __( 'Inventory Settings', 'personal-inventory-tracker' ),
            '__return_null',
            'pit_settings'
        );

        add_settings_field(
            'unit_list',
            __( 'Default Unit List', 'personal-inventory-tracker' ),
            array( __CLASS__, 'field_unit_list' ),
            'pit_settings',
            'pit_settings_main'
        );

        add_settings_field(
            'default_interval',
            __( 'Default Interval (days)', 'personal-inventory-tracker' ),
            array( __CLASS__, 'field_default_interval' ),
            'pit_settings',
            'pit_settings_main'
        );

        add_settings_field(
            'frontend_read_only',
            __( 'Front-end Read-only', 'personal-inventory-tracker' ),
            array( __CLASS__, 'field_frontend_read_only' ),
            'pit_settings',
            'pit_settings_main'
        );

        add_settings_field(
            'ocr_regex',
            __( 'OCR Parsing Regex', 'personal-inventory-tracker' ),
            array( __CLASS__, 'field_ocr_regex' ),
            'pit_settings',
            'pit_settings_main'
        );

        add_settings_field(
            'ocr_min_confidence',
            __( 'OCR Minimum Confidence', 'personal-inventory-tracker' ),
            array( __CLASS__, 'field_ocr_min_confidence' ),
            'pit_settings',
            'pit_settings_main'
        );
    }

    public static function add_menu() {
        add_submenu_page(
            'pit_dashboard',
            __( 'Settings', 'personal-inventory-tracker' ),
            __( 'Settings', 'personal-inventory-tracker' ),
            'manage_options',
            'pit_settings',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Inventory Settings', 'personal-inventory-tracker' ) . '</h1>';
        echo '<form action="' . esc_url( admin_url( 'options.php' ) ) . '" method="post">';
        settings_fields( 'pit_settings' );
        do_settings_sections( 'pit_settings' );
        submit_button();
        echo '</form></div>';
    }

    public static function field_unit_list() {
        $options = self::get_settings();
        $value   = implode( "\n", $options['unit_list'] );
        printf(
            '<textarea name="%s[unit_list]" rows="5" cols="50">%s</textarea><p class="description">%s</p>',
            esc_attr( self::OPTION_NAME ),
            esc_textarea( $value ),
            esc_html__( 'One unit per line.', 'personal-inventory-tracker' )
        );
    }

    public static function field_default_interval() {
        $options = self::get_settings();
        printf(
            '<input type="number" name="%s[default_interval]" value="%d" class="small-text" />',
            esc_attr( self::OPTION_NAME ),
            intval( $options['default_interval'] )
        );
    }

    public static function field_frontend_read_only() {
        $options = self::get_settings();
        printf(
            '<label><input type="checkbox" name="%s[frontend_read_only]" value="1" %s /> %s</label>',
            esc_attr( self::OPTION_NAME ),
            checked( $options['frontend_read_only'], 1, false ),
            esc_html__( 'Disable editing on front-end', 'personal-inventory-tracker' )
        );
    }

    public static function field_ocr_regex() {
        $options = self::get_settings();
        printf(
            '<input type="text" name="%s[ocr_regex]" value="%s" class="regular-text" />',
            esc_attr( self::OPTION_NAME ),
            esc_attr( $options['ocr_regex'] )
        );
    }

    public static function field_ocr_min_confidence() {
        $options = self::get_settings();
        printf(
            '<input type="number" name="%s[ocr_min_confidence]" value="%d" class="small-text" min="0" max="100" />',
            esc_attr( self::OPTION_NAME ),
            intval( $options['ocr_min_confidence'] )
        );
    }

    public static function sanitize( $input ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return self::get_settings();
        }

        $output = self::get_settings();

        if ( isset( $input['unit_list'] ) ) {
            $units = preg_split( '/\r?\n|,/', $input['unit_list'] );
            $units = array_filter( array_map( 'sanitize_text_field', array_map( 'trim', $units ) ) );
            $output['unit_list'] = $units;
        }

        $output['default_interval'] = isset( $input['default_interval'] ) ? absint( $input['default_interval'] ) : 0;
        $output['frontend_read_only'] = ! empty( $input['frontend_read_only'] ) ? 1 : 0;
        $output['ocr_regex'] = isset( $input['ocr_regex'] ) ? sanitize_text_field( $input['ocr_regex'] ) : '';

        $conf = isset( $input['ocr_min_confidence'] ) ? absint( $input['ocr_min_confidence'] ) : 60;
        $output['ocr_min_confidence'] = min( 100, max( 0, $conf ) );

        return $output;
    }
}

PIT_Settings::init();
