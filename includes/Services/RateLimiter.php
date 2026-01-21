<?php
namespace KG_Core\Services;

/**
 * RateLimiter - Rate limiting service using transients
 * 
 * Supports both legacy (action-based) and new (endpoint-based) rate limiting
 */
class RateLimiter {
    
    /**
     * Default rate limits for different endpoint types
     */
    private static $default_limits = [
        'public_read' => ['requests' => 100, 'window' => 60],
        'public_write' => ['requests' => 20, 'window' => 60],
        'authenticated' => ['requests' => 200, 'window' => 60],
        'search' => ['requests' => 30, 'window' => 60],
        'heavy' => ['requests' => 10, 'window' => 60],
    ];
    
    /**
     * Check rate limit for endpoint type (new standardized method)
     * 
     * @param string $endpoint_type Endpoint type (public_read, public_write, authenticated, search, heavy)
     * @param string|null $identifier Optional identifier (defaults to current user/IP)
     * @return bool|WP_Error True if allowed, WP_Error if rate limited
     */
    public static function check($endpoint_type, $identifier = null) {
        // Support legacy signature (action, user_id, max_attempts, time_window)
        if (is_numeric($identifier)) {
            return self::check_legacy($endpoint_type, $identifier, func_num_args() > 2 ? func_get_arg(2) : 5, func_num_args() > 3 ? func_get_arg(3) : 60);
        }
        
        if ($identifier === null) {
            $identifier = self::getIdentifier();
        }
        
        $limits = self::getLimits($endpoint_type);
        $transient_key = 'kg_rate_' . md5($endpoint_type . '_' . $identifier);
        $data = get_transient($transient_key);
        
        if ($data === false) {
            // First request in this window
            set_transient($transient_key, ['count' => 1, 'reset' => time() + $limits['window']], $limits['window']);
            return true;
        }
        
        if ($data['count'] >= $limits['requests']) {
            return new \WP_Error('rate_limit_exceeded', 
                sprintf(__('Too many requests. Please wait %d seconds.', 'kg-core'), $data['reset'] - time()),
                ['status' => 429, 'retry_after' => $data['reset'] - time()]
            );
        }
        
        $data['count']++;
        set_transient($transient_key, $data, $data['reset'] - time());
        return true;
    }
    
    /**
     * Get rate limit headers for endpoint type
     * 
     * @param string $endpoint_type Endpoint type
     * @param string|null $identifier Optional identifier (defaults to current user/IP)
     * @return array Headers array
     */
    public static function getHeaders($endpoint_type, $identifier = null) {
        if ($identifier === null) {
            $identifier = self::getIdentifier();
        }
        
        $limits = self::getLimits($endpoint_type);
        $transient_key = 'kg_rate_' . md5($endpoint_type . '_' . $identifier);
        $data = get_transient($transient_key);
        
        $remaining = $limits['requests'];
        $reset = time() + $limits['window'];
        
        if ($data !== false) {
            $remaining = max(0, $limits['requests'] - $data['count']);
            $reset = $data['reset'];
        }
        
        return [
            'X-RateLimit-Limit' => $limits['requests'],
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => $reset,
        ];
    }
    
    /**
     * Get identifier for current request (user ID or IP)
     * 
     * @return string Identifier
     */
    private static function getIdentifier() {
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }
        
        // Safely get IP address
        $ip = '';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        } else {
            $ip = 'unknown';
        }
        
        // Handle proxy headers (take first IP)
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        
        // Validate IP address format
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return 'ip_' . $ip;
        }
        
        return 'ip_unknown';
    }
    
    /**
     * Get rate limits for endpoint type
     * 
     * @param string $endpoint_type Endpoint type
     * @return array Limits array with 'requests' and 'window' keys
     */
    private static function getLimits($endpoint_type) {
        $limits = apply_filters('kg_rate_limits', self::$default_limits);
        return $limits[$endpoint_type] ?? $limits['public_read'];
    }
    
    /**
     * Legacy check method (backward compatibility)
     * 
     * @param string $action Action identifier
     * @param int $user_id User ID
     * @param int $max_attempts Maximum attempts allowed
     * @param int $time_window Time window in seconds
     * @return bool|WP_Error True if allowed, WP_Error if rate limited
     */
    private static function check_legacy($action, $user_id, $max_attempts = 5, $time_window = 60) {
        $transient_key = 'rate_limit_' . $action . '_' . $user_id;
        $attempts = get_transient($transient_key);
        
        if ($attempts === false) {
            // First attempt in this window
            set_transient($transient_key, 1, $time_window);
            return true;
        }
        
        if ($attempts >= $max_attempts) {
            return new \WP_Error(
                'rate_limit_exceeded',
                sprintf('Rate limit exceeded. Maximum %d requests per %d seconds allowed.', $max_attempts, $time_window),
                ['status' => 429]
            );
        }
        
        // Increment attempts
        set_transient($transient_key, $attempts + 1, $time_window);
        return true;
    }
    
    /**
     * Reset rate limit for a specific action and user (legacy)
     * 
     * @param string $action Action identifier
     * @param int $user_id User ID
     */
    public static function reset($action, $user_id) {
        $transient_key = 'rate_limit_' . $action . '_' . $user_id;
        delete_transient($transient_key);
    }
}
