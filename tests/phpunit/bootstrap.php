<?php
require __DIR__ . '/../../vendor/autoload.php';

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = null ) {
        return $text;
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $text ) {
        return trim( $text );
    }
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $text ) {
        return trim( $text );
    }
}
if ( ! function_exists( 'get_page_by_title' ) ) {
    function get_page_by_title( $title, $output = OBJECT, $post_type = 'page' ) {
        return null;
    }
}

if ( ! function_exists( 'wp_insert_post' ) ) {
    function wp_insert_post( $postarr ) {
        return 0;
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return false;
    }
}
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'OBJECT' ) ) {
    define( 'OBJECT', 'OBJECT' );
}
