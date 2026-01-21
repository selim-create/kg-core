<?php
namespace KG_Core\API;

/**
 * Pagination Helper for REST API endpoints
 * 
 * Provides standardized pagination across all endpoints
 */
class PaginationHelper {
    
    const DEFAULT_PER_PAGE = 12;
    const MAX_PER_PAGE = 100;
    const MIN_PER_PAGE = 1;
    
    /**
     * Get pagination argument definitions for register_rest_route
     * 
     * @return array Argument definitions
     */
    public static function get_args() {
        return [
            'page' => [
                'default' => 1,
                'type' => 'integer',
                'minimum' => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'default' => self::DEFAULT_PER_PAGE,
                'type' => 'integer',
                'minimum' => self::MIN_PER_PAGE,
                'maximum' => self::MAX_PER_PAGE,
                'sanitize_callback' => 'absint',
            ],
        ];
    }
    
    /**
     * Get pagination parameters from request
     * 
     * @param WP_REST_Request $request Request object
     * @return array Array with 'page', 'per_page', and 'offset' keys
     */
    public static function get_from_request($request) {
        $page = max(1, (int)($request->get_param('page') ?: 1));
        $per_page = max(
            self::MIN_PER_PAGE,
            min(
                self::MAX_PER_PAGE,
                (int)($request->get_param('per_page') ?: self::DEFAULT_PER_PAGE)
            )
        );
        
        return [
            'page' => $page,
            'per_page' => $per_page,
            'offset' => ($page - 1) * $per_page,
        ];
    }
    
    /**
     * Build pagination response metadata
     * 
     * @param int $total Total number of items
     * @param int $page Current page number
     * @param int $per_page Items per page
     * @return array Pagination metadata
     */
    public static function build_response($total, $page, $per_page) {
        return [
            'total' => (int)$total,
            'page' => (int)$page,
            'per_page' => (int)$per_page,
            'total_pages' => (int)ceil($total / $per_page),
            'has_more' => ($page * $per_page) < $total,
        ];
    }
}
