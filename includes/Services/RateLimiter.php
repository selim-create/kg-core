<?php
namespace KG_Core\Services;

/**
 * RateLimiter - Simple rate limiting service using transients
 */
class RateLimiter {
    
    /**
     * Check if action is rate limited
     * 
     * @param string $action Action identifier
     * @param int $user_id User ID
     * @param int $max_attempts Maximum attempts allowed
     * @param int $time_window Time window in seconds
     * @return bool|WP_Error True if allowed, WP_Error if rate limited
     */
    public static function check( $action, $user_id, $max_attempts = 5, $time_window = 60 ) {
        $transient_key = 'rate_limit_' . $action . '_' . $user_id;
        $attempts = get_transient( $transient_key );
        
        if ( $attempts === false ) {
            // First attempt in this window
            set_transient( $transient_key, 1, $time_window );
            return true;
        }
        
        if ( $attempts >= $max_attempts ) {
            return new \WP_Error(
                'rate_limit_exceeded',
                sprintf( 'Rate limit exceeded. Maximum %d requests per %d seconds allowed.', $max_attempts, $time_window ),
                [ 'status' => 429 ]
            );
        }
        
        // Increment attempts
        set_transient( $transient_key, $attempts + 1, $time_window );
        return true;
    }
    
    /**
     * Reset rate limit for a specific action and user
     * 
     * @param string $action Action identifier
     * @param int $user_id User ID
     */
    public static function reset( $action, $user_id ) {
        $transient_key = 'rate_limit_' . $action . '_' . $user_id;
        delete_transient( $transient_key );
    }
}
