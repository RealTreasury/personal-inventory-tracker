<?php
namespace RealTreasury\Inventory {
    function get_posts( $args ) {
        global $pit_posts;
        $paged = isset( $args['paged'] ) ? (int) $args['paged'] : 1;
        if ( $paged > 1 ) {
            return array();
        }
        if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
            return array_map( function( $p ) { return $p['ID']; }, $pit_posts );
        }
        return array_map( function( $p ) { return (object) $p; }, $pit_posts );
    }
    function get_post_meta( $id, $key = '', $single = false ) {
        global $pit_meta;
        if ( '' === $key ) {
            return $pit_meta[ $id ] ?? array();
        }
        $values = $pit_meta[ $id ][ $key ] ?? array();
        if ( $single ) {
            return $values[0] ?? '';
        }
        return $values;
    }
    function wp_get_post_terms( $id, $taxonomy, $args = array() ) {
        return array( 'general' );
    }
    function get_the_post_thumbnail_url( $id, $size = 'full' ) {
        return '';
    }
    function wp_unslash( $value ) {
        return $value;
    }
    function esc_html( $text ) {
        return $text;
    }
}

namespace {
use PHPUnit\Framework\TestCase;
use RealTreasury\Inventory\Import_Export;

class ExportFormatsTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['pit_posts'] = array(
            array( 'ID' => 1, 'post_title' => 'Item One' ),
            array( 'ID' => 2, 'post_title' => 'Item Two' ),
        );
        $GLOBALS['pit_meta'] = array(
            1 => array( 'pit_qty' => array( 1 ), 'pit_unit' => array( 'pc' ) ),
            2 => array( 'pit_qty' => array( 2 ), 'pit_unit' => array( 'pc' ) ),
        );
    }

    public function test_generate_pdf_has_pdf_header() {
        $pdf = Import_Export::generate_pdf();
        $this->assertStringStartsWith( '%PDF', $pdf );
    }

    public function test_generate_excel_contains_table() {
        $xls = Import_Export::generate_excel();
        $this->assertStringContainsString( '<table>', $xls );
    }
}
}
