<?php
/**
 * Inventory Item Model.
 *
 * @package PersonalInventoryTracker
 */

namespace RealTreasury\Inventory\Models;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Model class for inventory items with validation and data handling.
 */
class InventoryItem {

    /**
     * Item ID.
     *
     * @var int
     */
    public $id;

    /**
     * Item title.
     *
     * @var string
     */
    public $title;

    /**
     * Item description.
     *
     * @var string
     */
    public $description;

    /**
     * Item quantity.
     *
     * @var int
     */
    public $quantity;

    /**
     * Item status.
     *
     * @var string
     */
    public $status;

    /**
     * Item location.
     *
     * @var string
     */
    public $location;

    /**
     * Purchase date.
     *
     * @var string
     */
    public $purchase_date;

    /**
     * Expiry date.
     *
     * @var string
     */
    public $expiry_date;

    /**
     * Additional notes.
     *
     * @var string
     */
    public $notes;

    /**
     * Item categories.
     *
     * @var array
     */
    public $categories;

    /**
     * Validation errors.
     *
     * @var array
     */
    private $errors = [];

    /**
     * Constructor.
     *
     * @param array $data Item data.
     */
    public function __construct( $data = [] ) {
        $this->fill( $data );
    }

    /**
     * Fill model with data.
     *
     * @param array $data Item data.
     */
    public function fill( $data ) {
        $this->id            = isset( $data['id'] ) ? (int) $data['id'] : 0;
        $this->title         = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
        $this->description   = isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '';
        $this->quantity      = isset( $data['quantity'] ) ? max( 0, (int) $data['quantity'] ) : 0;
        $this->status        = isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'in_stock';
        $this->location      = isset( $data['location'] ) ? sanitize_text_field( $data['location'] ) : '';
        $this->purchase_date = isset( $data['purchase_date'] ) ? sanitize_text_field( $data['purchase_date'] ) : '';
        $this->expiry_date   = isset( $data['expiry_date'] ) ? sanitize_text_field( $data['expiry_date'] ) : '';
        $this->notes         = isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '';
        $this->categories    = isset( $data['categories'] ) ? (array) $data['categories'] : [];
    }

    /**
     * Validate item data.
     *
     * @return bool True if valid, false otherwise.
     */
    public function validate() {
        $this->errors = [];

        // Title is required
        if ( empty( $this->title ) ) {
            $this->errors['title'] = __( 'Title is required.', 'personal-inventory-tracker' );
        } elseif ( strlen( $this->title ) > 200 ) {
            $this->errors['title'] = __( 'Title must be 200 characters or less.', 'personal-inventory-tracker' );
        }

        // Quantity must be non-negative
        if ( $this->quantity < 0 ) {
            $this->errors['quantity'] = __( 'Quantity must be zero or greater.', 'personal-inventory-tracker' );
        }

        // Validate status
        $valid_statuses = [ 'in_stock', 'low_stock', 'out_of_stock', 'expired', 'discontinued' ];
        if ( ! in_array( $this->status, $valid_statuses, true ) ) {
            $this->errors['status'] = __( 'Invalid status value.', 'personal-inventory-tracker' );
        }

        // Validate dates
        if ( ! empty( $this->purchase_date ) && ! $this->is_valid_date( $this->purchase_date ) ) {
            $this->errors['purchase_date'] = __( 'Invalid purchase date format.', 'personal-inventory-tracker' );
        }

        if ( ! empty( $this->expiry_date ) && ! $this->is_valid_date( $this->expiry_date ) ) {
            $this->errors['expiry_date'] = __( 'Invalid expiry date format.', 'personal-inventory-tracker' );
        }

        // Validate description length
        if ( strlen( $this->description ) > 2000 ) {
            $this->errors['description'] = __( 'Description must be 2000 characters or less.', 'personal-inventory-tracker' );
        }

        // Validate location length
        if ( strlen( $this->location ) > 200 ) {
            $this->errors['location'] = __( 'Location must be 200 characters or less.', 'personal-inventory-tracker' );
        }

        // Validate notes length
        if ( strlen( $this->notes ) > 1000 ) {
            $this->errors['notes'] = __( 'Notes must be 1000 characters or less.', 'personal-inventory-tracker' );
        }

        return empty( $this->errors );
    }

    /**
     * Get validation errors.
     *
     * @return array Validation errors.
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Check if item has validation errors.
     *
     * @return bool True if has errors, false otherwise.
     */
    public function has_errors() {
        return ! empty( $this->errors );
    }

    /**
     * Convert item to array.
     *
     * @return array Item data as array.
     */
    public function to_array() {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'description'   => $this->description,
            'quantity'      => $this->quantity,
            'status'        => $this->status,
            'location'      => $this->location,
            'purchase_date' => $this->purchase_date,
            'expiry_date'   => $this->expiry_date,
            'notes'         => $this->notes,
            'categories'    => $this->categories,
        ];
    }

    /**
     * Convert item to JSON.
     *
     * @return string Item data as JSON.
     */
    public function to_json() {
        return wp_json_encode( $this->to_array() );
    }

    /**
     * Check if the item needs restocking based on quantity and status.
     *
     * @param int $threshold Low stock threshold.
     * @return bool True if needs restocking.
     */
    public function needs_restocking( $threshold = 5 ) {
        return $this->quantity <= $threshold || 'low_stock' === $this->status || 'out_of_stock' === $this->status;
    }

    /**
     * Check if the item is expired.
     *
     * @return bool True if expired.
     */
    public function is_expired() {
        if ( empty( $this->expiry_date ) ) {
            return false;
        }

        $expiry = strtotime( $this->expiry_date );
        return $expiry && $expiry < time();
    }

    /**
     * Get the number of days until expiry.
     *
     * @return int|null Days until expiry, null if no expiry date.
     */
    public function days_until_expiry() {
        if ( empty( $this->expiry_date ) ) {
            return null;
        }

        $expiry = strtotime( $this->expiry_date );
        if ( ! $expiry ) {
            return null;
        }

        $diff = $expiry - time();
        return (int) ceil( $diff / DAY_IN_SECONDS );
    }

    /**
     * Check if a date string is valid.
     *
     * @param string $date Date string.
     * @return bool True if valid.
     */
    private function is_valid_date( $date ) {
        $parsed = strtotime( $date );
        return $parsed !== false;
    }

    /**
     * Get available statuses.
     *
     * @return array Available status options.
     */
    public static function get_available_statuses() {
        return [
            'in_stock'      => __( 'In Stock', 'personal-inventory-tracker' ),
            'low_stock'     => __( 'Low Stock', 'personal-inventory-tracker' ),
            'out_of_stock'  => __( 'Out of Stock', 'personal-inventory-tracker' ),
            'expired'       => __( 'Expired', 'personal-inventory-tracker' ),
            'discontinued'  => __( 'Discontinued', 'personal-inventory-tracker' ),
        ];
    }

    /**
     * Create item from WordPress post.
     *
     * @param \WP_Post $post WordPress post object.
     * @return InventoryItem Item instance.
     */
    public static function from_post( $post ) {
        $data = [
            'id'            => $post->ID,
            'title'         => get_the_title( $post ),
            'description'   => $post->post_content,
            'quantity'      => (int) get_post_meta( $post->ID, '_pit_qty', true ),
            'status'        => get_post_meta( $post->ID, '_pit_status', true ),
            'location'      => get_post_meta( $post->ID, '_pit_location', true ),
            'purchase_date' => get_post_meta( $post->ID, '_pit_purchase_date', true ),
            'expiry_date'   => get_post_meta( $post->ID, '_pit_expiry_date', true ),
            'notes'         => get_post_meta( $post->ID, '_pit_notes', true ),
        ];

        // Get categories
        $categories = wp_get_post_terms( $post->ID, 'pit_category' );
        if ( ! is_wp_error( $categories ) ) {
            $data['categories'] = array_map( function( $term ) {
                return [
                    'id'   => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                ];
            }, $categories );
        }

        return new self( $data );
    }
}