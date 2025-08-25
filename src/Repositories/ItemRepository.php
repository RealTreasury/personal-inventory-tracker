<?php
/**
 * Inventory Item Repository.
 *
 * @package PersonalInventoryTracker
 */

namespace RealTreasury\Inventory\Repositories;

use RealTreasury\Inventory\Models\InventoryItem;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Repository class for inventory items data access.
 */
class ItemRepository {

    /**
     * Find item by ID.
     *
     * @param int $id Item ID.
     * @return InventoryItem|null Item object or null if not found.
     */
    public function find( $id ) {
        $post = get_post( $id );
        
        if ( ! $post || 'pit_item' !== $post->post_type ) {
            return null;
        }

        return InventoryItem::from_post( $post );
    }

    /**
     * Find multiple items with filtering options.
     *
     * @param array $args Query arguments.
     * @return array Array of InventoryItem objects.
     */
    public function find_many( $args = [] ) {
        $defaults = [
            'post_type'      => 'pit_item',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ];

        $args = wp_parse_args( $args, $defaults );
        $query = new \WP_Query( $args );
        
        $items = [];
        foreach ( $query->posts as $post ) {
            $items[] = InventoryItem::from_post( $post );
        }

        return $items;
    }

    /**
     * Find items with pagination.
     *
     * @param array $args Query arguments.
     * @return array Results with items and pagination info.
     */
    public function find_with_pagination( $args = [] ) {
        $defaults = [
            'post_type'      => 'pit_item',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'paged'          => 1,
        ];

        $args = wp_parse_args( $args, $defaults );
        $query = new \WP_Query( $args );
        
        $items = [];
        foreach ( $query->posts as $post ) {
            $items[] = InventoryItem::from_post( $post );
        }

        return [
            'items'       => $items,
            'total'       => $query->found_posts,
            'total_pages' => $query->max_num_pages,
            'current_page'=> $args['paged'],
            'per_page'    => $args['posts_per_page'],
        ];
    }

    /**
     * Save inventory item.
     *
     * @param InventoryItem $item Item to save.
     * @return int|false Post ID on success, false on failure.
     */
    public function save( InventoryItem $item ) {
        if ( ! $item->validate() ) {
            return false;
        }

        $post_data = [
            'post_title'   => $item->title,
            'post_content' => $item->description,
            'post_type'    => 'pit_item',
            'post_status'  => 'publish',
        ];

        // Update existing item
        if ( $item->id > 0 ) {
            $post_data['ID'] = $item->id;
            $result = wp_update_post( $post_data );
        } else {
            // Create new item
            $result = wp_insert_post( $post_data );
        }

        if ( is_wp_error( $result ) || ! $result ) {
            return false;
        }

        $post_id = $result;
        $item->id = $post_id;

        // Save meta fields
        $this->save_meta_fields( $post_id, $item );

        // Save categories
        $this->save_categories( $post_id, $item->categories );

        return $post_id;
    }

    /**
     * Delete inventory item.
     *
     * @param int $id Item ID.
     * @return bool True on success, false on failure.
     */
    public function delete( $id ) {
        $result = wp_delete_post( $id, true );
        return ! is_wp_error( $result ) && $result;
    }

    /**
     * Search items by text.
     *
     * @param string $search_term Search term.
     * @param array  $args        Additional query arguments.
     * @return array Search results.
     */
    public function search( $search_term, $args = [] ) {
        $args = wp_parse_args( $args, [
            'post_type'      => 'pit_item',
            'post_status'    => 'publish',
            's'              => sanitize_text_field( $search_term ),
            'posts_per_page' => 50,
        ] );

        return $this->find_with_pagination( $args );
    }

    /**
     * Find items by category.
     *
     * @param string $category_slug Category slug.
     * @param array  $args          Additional query arguments.
     * @return array Items in category.
     */
    public function find_by_category( $category_slug, $args = [] ) {
        $args = wp_parse_args( $args, [
            'post_type'      => 'pit_item',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'tax_query'      => [
                [
                    'taxonomy' => 'pit_category',
                    'field'    => 'slug',
                    'terms'    => sanitize_title( $category_slug ),
                ]
            ],
        ] );

        return $this->find_many( $args );
    }

    /**
     * Find items by status.
     *
     * @param string $status Item status.
     * @param array  $args   Additional query arguments.
     * @return array Items with specified status.
     */
    public function find_by_status( $status, $args = [] ) {
        $args = wp_parse_args( $args, [
            'post_type'      => 'pit_item',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => '_pit_status',
                    'value' => sanitize_text_field( $status ),
                ]
            ],
        ] );

        return $this->find_many( $args );
    }

    /**
     * Find items that need restocking.
     *
     * @param int $threshold Low stock threshold.
     * @return array Items that need restocking.
     */
    public function find_low_stock( $threshold = 5 ) {
        $args = [
            'post_type'      => 'pit_item',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_pit_qty',
                    'value'   => $threshold,
                    'type'    => 'NUMERIC',
                    'compare' => '<=',
                ],
                [
                    'key'   => '_pit_status',
                    'value' => [ 'low_stock', 'out_of_stock' ],
                    'compare' => 'IN',
                ],
            ],
        ];

        return $this->find_many( $args );
    }

    /**
     * Find expiring items.
     *
     * @param int $days_ahead Number of days to look ahead.
     * @return array Expiring items.
     */
    public function find_expiring( $days_ahead = 30 ) {
        $expiry_date = date( 'Y-m-d', strtotime( "+{$days_ahead} days" ) );
        
        $args = [
            'post_type'      => 'pit_item',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_pit_expiry_date',
                    'value'   => $expiry_date,
                    'type'    => 'DATE',
                    'compare' => '<=',
                ],
            ],
        ];

        return $this->find_many( $args );
    }

    /**
     * Get item count by status.
     *
     * @return array Status counts.
     */
    public function get_status_counts() {
        global $wpdb;

        $query = "
            SELECT meta_value as status, COUNT(*) as count
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_pit_status'
            AND p.post_type = 'pit_item'
            AND p.post_status = 'publish'
            GROUP BY meta_value
        ";

        $results = $wpdb->get_results( $query );
        $counts = [];

        foreach ( $results as $result ) {
            $counts[ $result->status ] = (int) $result->count;
        }

        return $counts;
    }

    /**
     * Save meta fields for an item.
     *
     * @param int           $post_id Post ID.
     * @param InventoryItem $item    Item object.
     */
    private function save_meta_fields( $post_id, InventoryItem $item ) {
        $meta_fields = [
            '_pit_qty'           => $item->quantity,
            '_pit_status'        => $item->status,
            '_pit_location'      => $item->location,
            '_pit_purchase_date' => $item->purchase_date,
            '_pit_expiry_date'   => $item->expiry_date,
            '_pit_notes'         => $item->notes,
        ];

        foreach ( $meta_fields as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }
    }

    /**
     * Save categories for an item.
     *
     * @param int   $post_id    Post ID.
     * @param array $categories Categories array.
     */
    private function save_categories( $post_id, $categories ) {
        if ( empty( $categories ) ) {
            wp_set_post_terms( $post_id, [], 'pit_category' );
            return;
        }

        $term_ids = [];
        foreach ( $categories as $category ) {
            if ( is_array( $category ) && isset( $category['id'] ) ) {
                $term_ids[] = (int) $category['id'];
            } elseif ( is_numeric( $category ) ) {
                $term_ids[] = (int) $category;
            } elseif ( is_string( $category ) ) {
                // Try to find term by slug or name
                $term = get_term_by( 'slug', $category, 'pit_category' );
                if ( ! $term ) {
                    $term = get_term_by( 'name', $category, 'pit_category' );
                }
                if ( $term ) {
                    $term_ids[] = $term->term_id;
                }
            }
        }

        wp_set_post_terms( $post_id, $term_ids, 'pit_category' );
    }
}