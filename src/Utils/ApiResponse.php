<?php
/**
 * API Response helper.
 *
 * @package PersonalInventoryTracker
 */

namespace RealTreasury\Inventory\Utils;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper class for standardizing API responses.
 */
class ApiResponse {

    /**
     * Create success response.
     *
     * @param mixed  $data    Response data.
     * @param string $message Success message.
     * @param array  $meta    Additional metadata.
     * @return \WP_REST_Response Success response.
     */
    public static function success( $data = null, $message = null, $meta = [] ) {
        $response_data = [
            'success' => true,
            'data'    => $data,
        ];

        if ( $message ) {
            $response_data['message'] = $message;
        }

        if ( ! empty( $meta ) ) {
            $response_data['meta'] = $meta;
        }

        return rest_ensure_response( $response_data );
    }

    /**
     * Create error response.
     *
     * @param string $message Error message.
     * @param int    $status  HTTP status code.
     * @param array  $errors  Detailed errors.
     * @return \WP_Error Error response.
     */
    public static function error( $message, $status = 400, $errors = [] ) {
        $error_data = [
            'status'  => $status,
            'message' => $message,
        ];

        if ( ! empty( $errors ) ) {
            $error_data['errors'] = $errors;
        }

        return new \WP_Error( 'api_error', $message, $error_data );
    }

    /**
     * Create validation error response.
     *
     * @param array $errors Validation errors.
     * @return \WP_Error Validation error response.
     */
    public static function validation_error( $errors ) {
        return self::error(
            __( 'Validation failed.', 'personal-inventory-tracker' ),
            422,
            $errors
        );
    }

    /**
     * Create not found error response.
     *
     * @param string $resource Resource name.
     * @return \WP_Error Not found error response.
     */
    public static function not_found( $resource = 'Resource' ) {
        return self::error(
            sprintf( __( '%s not found.', 'personal-inventory-tracker' ), $resource ),
            404
        );
    }

    /**
     * Create unauthorized error response.
     *
     * @param string $message Custom message.
     * @return \WP_Error Unauthorized error response.
     */
    public static function unauthorized( $message = null ) {
        $default_message = __( 'You are not authorized to perform this action.', 'personal-inventory-tracker' );
        return self::error( $message ?: $default_message, 403 );
    }

    /**
     * Create internal server error response.
     *
     * @param string $message Custom message.
     * @return \WP_Error Internal server error response.
     */
    public static function server_error( $message = null ) {
        $default_message = __( 'An internal server error occurred.', 'personal-inventory-tracker' );
        return self::error( $message ?: $default_message, 500 );
    }

    /**
     * Create paginated response.
     *
     * @param array $items       Response items.
     * @param int   $total       Total items.
     * @param int   $total_pages Total pages.
     * @param int   $current_page Current page.
     * @param int   $per_page    Items per page.
     * @param string $message    Success message.
     * @return \WP_REST_Response Paginated response.
     */
    public static function paginated( $items, $total, $total_pages, $current_page, $per_page, $message = null ) {
        $meta = [
            'pagination' => [
                'total'        => $total,
                'total_pages'  => $total_pages,
                'current_page' => $current_page,
                'per_page'     => $per_page,
                'has_next'     => $current_page < $total_pages,
                'has_prev'     => $current_page > 1,
            ],
        ];

        return self::success( $items, $message, $meta );
    }

    /**
     * Handle service response.
     *
     * @param array $service_response Response from service layer.
     * @return \WP_REST_Response|\WP_Error Formatted response.
     */
    public static function from_service( $service_response ) {
        if ( ! isset( $service_response['success'] ) ) {
            return self::server_error( __( 'Invalid service response format.', 'personal-inventory-tracker' ) );
        }

        if ( $service_response['success'] ) {
            $data = isset( $service_response['data'] ) ? $service_response['data'] : null;
            $message = isset( $service_response['message'] ) ? $service_response['message'] : null;
            $meta = isset( $service_response['meta'] ) ? $service_response['meta'] : [];
            
            return self::success( $data, $message, $meta );
        } else {
            $message = isset( $service_response['message'] ) ? $service_response['message'] : __( 'Operation failed.', 'personal-inventory-tracker' );
            $errors = isset( $service_response['errors'] ) ? $service_response['errors'] : [];
            $status = isset( $service_response['status'] ) ? $service_response['status'] : 400;
            
            return self::error( $message, $status, $errors );
        }
    }
}