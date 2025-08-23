<?php
use PHPUnit\Framework\TestCase;
use RealTreasury\Inventory\Import_Export;

class ImportExportTest extends TestCase {
    public function test_invalid_csv_rows_return_errors() {
        $csv     = "item1,abc\n,2\nitem3,";
        $mapping = array(
            'name' => 0,
            'qty'  => 1,
        );
        $errors = Import_Export::import_from_csv_string( $csv, $mapping );
        $this->assertCount( 3, $errors );
        $this->assertSame(
            array(
                array(
                    'row'    => 1,
                    'errors' => array( 'Invalid quantity' ),
                ),
                array(
                    'row'    => 2,
                    'errors' => array( 'Missing required field: name' ),
                ),
                array(
                    'row'    => 3,
                    'errors' => array( 'Missing required field: qty' ),
                ),
            ),
            $errors
        );
    }
}
