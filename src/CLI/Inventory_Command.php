<?php

namespace RealTreasury\Inventory\CLI;

use RealTreasury\Inventory\Import_Export;
use WP_CLI;
use WP_CLI_Command;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Inventory_Command extends WP_CLI_Command {
    /**
     * Export inventory items to a CSV file or STDOUT.
     *
     * ## OPTIONS
     *
     * [--file=<path>]
     * : File path to save the export. Outputs to STDOUT when omitted.
     *
     * ## EXAMPLES
     *
     *     wp pit inventory export --file=items.csv
     */
    public function export( $args, $assoc_args ) {
        $csv = Import_Export::generate_csv();
        if ( isset( $assoc_args['file'] ) ) {
            $written = file_put_contents( $assoc_args['file'], $csv );
            if ( false === $written ) {
                WP_CLI::error( sprintf( 'Could not write to %s', $assoc_args['file'] ) );
            }
            WP_CLI::success( sprintf( 'Exported inventory to %s', $assoc_args['file'] ) );
        } else {
            WP_CLI::line( $csv );
        }
    }

    /**
     * Import inventory items from a CSV file.
     *
     * ## OPTIONS
     *
     * <file>
     * : Path to the CSV file to import.
     *
     * ## EXAMPLES
     *
     *     wp pit inventory import items.csv
     */
    public function import( $args, $assoc_args ) {
        $file = $args[0];
        if ( ! file_exists( $file ) ) {
            WP_CLI::error( sprintf( 'File not found: %s', $file ) );
        }
        $contents = file_get_contents( $file );
        $lines    = array_map( 'str_getcsv', preg_split( '/[\r\n]+/', trim( $contents ) ) );
        $headers  = array_shift( $lines );
        $mapping  = array();
        foreach ( Import_Export::get_headers() as $field ) {
            $index = array_search( $field, $headers, true );
            if ( false !== $index ) {
                $mapping[ $field ] = $index;
            }
        }
        $rows = implode( "\n", array_map( function( $row ) {
            return implode( ',', $row );
        }, $lines ) );
        Import_Export::import_from_csv_string( $rows, $mapping );
        WP_CLI::success( 'Inventory imported.' );
    }

    /**
     * Classify uncategorized items using GPT.
     *
     * ## EXAMPLES
     *
     *     wp pit inventory classify
     */
    public function classify( $args, $assoc_args ) {
        $count = Import_Export::classify_uncategorized();
        WP_CLI::success( sprintf( 'Classified %d items.', $count ) );
    }
}

\class_alias( __NAMESPACE__ . '\\Inventory_Command', 'PIT\\CLI\\Inventory_Command' );
