<?php
/**
 * Item Service for business logic.
 *
 * @package PersonalInventoryTracker
 */

namespace RealTreasury\Inventory\Services;

use RealTreasury\Inventory\Models\InventoryItem;
use RealTreasury\Inventory\Repositories\ItemRepository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Service class for handling inventory item business logic.
 */
class ItemService {

    /**
     * Item repository.
     *
     * @var ItemRepository
     */
    private $repository;

    /**
     * Constructor.
     *
     * @param ItemRepository $repository Item repository.
     */
    public function __construct( ItemRepository $repository = null ) {
        $this->repository = $repository ?: new ItemRepository();
    }

    /**
     * Create a new inventory item.
     *
     * @param array $data Item data.
     * @return array Response with success status and data.
     */
    public function create_item( $data ) {
        try {
            $item = new InventoryItem( $data );
            
            if ( ! $item->validate() ) {
                return [
                    'success' => false,
                    'message' => __( 'Validation failed.', 'personal-inventory-tracker' ),
                    'errors'  => $item->get_errors(),
                ];
            }

            $item_id = $this->repository->save( $item );
            
            if ( ! $item_id ) {
                return [
                    'success' => false,
                    'message' => __( 'Failed to save item.', 'personal-inventory-tracker' ),
                ];
            }

            // Auto-categorize if enabled
            $this->auto_categorize_item( $item );

            return [
                'success' => true,
                'message' => __( 'Item created successfully.', 'personal-inventory-tracker' ),
                'data'    => $item->to_array(),
            ];

        } catch ( \Exception $e ) {
            error_log( 'PIT Item Service Error: ' . $e->getMessage() );
            return [
                'success' => false,
                'message' => __( 'An error occurred while creating the item.', 'personal-inventory-tracker' ),
            ];
        }
    }

    /**
     * Update an existing inventory item.
     *
     * @param int   $id   Item ID.
     * @param array $data Updated item data.
     * @return array Response with success status and data.
     */
    public function update_item( $id, $data ) {
        try {
            $existing_item = $this->repository->find( $id );
            
            if ( ! $existing_item ) {
                return [
                    'success' => false,
                    'message' => __( 'Item not found.', 'personal-inventory-tracker' ),
                ];
            }

            // Merge existing data with updates
            $existing_data = $existing_item->to_array();
            $updated_data = array_merge( $existing_data, $data );
            $updated_data['id'] = $id; // Ensure ID is preserved

            $item = new InventoryItem( $updated_data );
            
            if ( ! $item->validate() ) {
                return [
                    'success' => false,
                    'message' => __( 'Validation failed.', 'personal-inventory-tracker' ),
                    'errors'  => $item->get_errors(),
                ];
            }

            $result = $this->repository->save( $item );
            
            if ( ! $result ) {
                return [
                    'success' => false,
                    'message' => __( 'Failed to update item.', 'personal-inventory-tracker' ),
                ];
            }

            return [
                'success' => true,
                'message' => __( 'Item updated successfully.', 'personal-inventory-tracker' ),
                'data'    => $item->to_array(),
            ];

        } catch ( \Exception $e ) {
            error_log( 'PIT Item Service Error: ' . $e->getMessage() );
            return [
                'success' => false,
                'message' => __( 'An error occurred while updating the item.', 'personal-inventory-tracker' ),
            ];
        }
    }

    /**
     * Delete an inventory item.
     *
     * @param int $id Item ID.
     * @return array Response with success status.
     */
    public function delete_item( $id ) {
        try {
            $item = $this->repository->find( $id );
            
            if ( ! $item ) {
                return [
                    'success' => false,
                    'message' => __( 'Item not found.', 'personal-inventory-tracker' ),
                ];
            }

            $result = $this->repository->delete( $id );
            
            if ( ! $result ) {
                return [
                    'success' => false,
                    'message' => __( 'Failed to delete item.', 'personal-inventory-tracker' ),
                ];
            }

            return [
                'success' => true,
                'message' => __( 'Item deleted successfully.', 'personal-inventory-tracker' ),
            ];

        } catch ( \Exception $e ) {
            error_log( 'PIT Item Service Error: ' . $e->getMessage() );
            return [
                'success' => false,
                'message' => __( 'An error occurred while deleting the item.', 'personal-inventory-tracker' ),
            ];
        }
    }

    /**
     * Get shopping list (items that need restocking).
     *
     * @param int $threshold Low stock threshold.
     * @return array Shopping list data.
     */
    public function get_shopping_list( $threshold = 5 ) {
        try {
            $low_stock_items = $this->repository->find_low_stock( $threshold );
            
            $shopping_list = [];
            foreach ( $low_stock_items as $item ) {
                $shopping_list[] = [
                    'id'       => $item->id,
                    'title'    => $item->title,
                    'quantity' => $item->quantity,
                    'status'   => $item->status,
                    'priority' => $this->calculate_priority( $item ),
                ];
            }

            // Sort by priority
            usort( $shopping_list, function( $a, $b ) {
                return $b['priority'] - $a['priority'];
            } );

            return [
                'success' => true,
                'data'    => $shopping_list,
                'total'   => count( $shopping_list ),
            ];

        } catch ( \Exception $e ) {
            error_log( 'PIT Item Service Error: ' . $e->getMessage() );
            return [
                'success' => false,
                'message' => __( 'An error occurred while generating the shopping list.', 'personal-inventory-tracker' ),
            ];
        }
    }

    /**
     * Get analytics data.
     *
     * @return array Analytics data.
     */
    public function get_analytics() {
        try {
            $status_counts = $this->repository->get_status_counts();
            $expiring_items = $this->repository->find_expiring( 30 );
            $low_stock_items = $this->repository->find_low_stock();

            return [
                'success' => true,
                'data'    => [
                    'status_counts'    => $status_counts,
                    'expiring_count'   => count( $expiring_items ),
                    'low_stock_count'  => count( $low_stock_items ),
                    'total_items'      => array_sum( $status_counts ),
                ],
            ];

        } catch ( \Exception $e ) {
            error_log( 'PIT Item Service Error: ' . $e->getMessage() );
            return [
                'success' => false,
                'message' => __( 'An error occurred while generating analytics.', 'personal-inventory-tracker' ),
            ];
        }
    }

    /**
     * Auto-categorize an item using AI if enabled.
     *
     * @param InventoryItem $item Item to categorize.
     */
    private function auto_categorize_item( InventoryItem $item ) {
        $settings = \RealTreasury\Inventory\Settings::get_settings();
        
        if ( empty( $settings['auto_categorize'] ) || empty( $item->categories ) ) {
            return;
        }

        try {
            $suggested_category = CategoryClassifier::suggest_category( $item->title, $item->notes );
            
            if ( ! empty( $suggested_category ) ) {
                // Add suggested category to item
                $term = get_term_by( 'slug', $suggested_category, 'pit_category' );
                if ( $term ) {
                    wp_set_post_terms( $item->id, [ $term->term_id ], 'pit_category', true );
                }
            }
        } catch ( \Exception $e ) {
            // Log error but don't fail the item creation
            error_log( 'PIT Auto-categorization failed: ' . $e->getMessage() );
        }
    }

    /**
     * Calculate priority for shopping list items.
     *
     * @param InventoryItem $item Item to calculate priority for.
     * @return int Priority score (higher = more urgent).
     */
    private function calculate_priority( InventoryItem $item ) {
        $priority = 0;

        // Priority based on status
        switch ( $item->status ) {
            case 'out_of_stock':
                $priority += 100;
                break;
            case 'low_stock':
                $priority += 75;
                break;
            case 'expired':
                $priority += 50;
                break;
        }

        // Priority based on quantity (lower quantity = higher priority)
        if ( $item->quantity <= 0 ) {
            $priority += 50;
        } elseif ( $item->quantity <= 2 ) {
            $priority += 25;
        } elseif ( $item->quantity <= 5 ) {
            $priority += 10;
        }

        // Priority based on expiry date
        $days_until_expiry = $item->days_until_expiry();
        if ( null !== $days_until_expiry ) {
            if ( $days_until_expiry <= 0 ) {
                $priority += 75; // Already expired
            } elseif ( $days_until_expiry <= 7 ) {
                $priority += 50; // Expires within a week
            } elseif ( $days_until_expiry <= 30 ) {
                $priority += 25; // Expires within a month
            }
        }

        return $priority;
    }
}