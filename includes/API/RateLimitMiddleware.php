<?php
namespace KG_Core\API;

use KG_Core\Services\RateLimiter;

/**
 * Rate Limit Middleware for REST API endpoints
 * 
 * Automatically applies rate limiting to all /kg/v1/ endpoints
 * and adds rate limit headers to responses
 */
class RateLimitMiddleware {
    
    public function __construct() {
        add_filter('rest_pre_dispatch', [$this, 'check_rate_limit'], 10, 3);
        add_filter('rest_post_dispatch', [$this, 'add_rate_headers'], 10, 3);
    }
    
    /**
     * Check rate limit before processing request
     * 
     * @param mixed $result Response to replace the requested version with
     * @param WP_REST_Server $server Server instance
     * @param WP_REST_Request $request Request used to generate the response
     * @return mixed WP_Error if rate limited, otherwise original result
     */
    public function check_rate_limit($result, $server, $request) {
        $route = $request->get_route();
        
        // Only apply to /kg/v1/ endpoints
        if (strpos($route, '/kg/v1/') !== 0) {
            return $result;
        }
        
        // Skip rate limiting for admin users
        if (current_user_can('manage_options')) {
            return $result;
        }
        
        $endpoint_type = $this->get_endpoint_type($request);
        $check = RateLimiter::check($endpoint_type);
        
        return is_wp_error($check) ? $check : $result;
    }
    
    /**
     * Add rate limit headers to response
     * 
     * @param WP_HTTP_Response $response Result to send to the client
     * @param WP_REST_Server $server Server instance
     * @param WP_REST_Request $request Request used to generate the response
     * @return WP_HTTP_Response Response with rate limit headers
     */
    public function add_rate_headers($response, $server, $request) {
        $route = $request->get_route();
        
        // Only apply to /kg/v1/ endpoints
        if (strpos($route, '/kg/v1/') !== 0) {
            return $response;
        }
        
        // Only add headers to WP_REST_Response objects
        if (!($response instanceof \WP_REST_Response)) {
            return $response;
        }
        
        $endpoint_type = $this->get_endpoint_type($request);
        $headers = RateLimiter::getHeaders($endpoint_type);
        
        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }
        
        return $response;
    }
    
    /**
     * Determine endpoint type based on route and method
     * 
     * @param WP_REST_Request $request
     * @return string Endpoint type (heavy, search, authenticated, public_write, public_read)
     */
    private function get_endpoint_type($request) {
        $route = $request->get_route();
        $method = $request->get_method();
        
        // Heavy endpoints (AI, migrations)
        if (strpos($route, '/ai/') !== false || strpos($route, '/migrate/') !== false) {
            return 'heavy';
        }
        
        // Search endpoints
        if (strpos($route, '/search') !== false) {
            return 'search';
        }
        
        // Write operations
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return is_user_logged_in() ? 'authenticated' : 'public_write';
        }
        
        // Read operations
        return is_user_logged_in() ? 'authenticated' : 'public_read';
    }
}
