<?php
namespace KG_Core\Notifications;

/**
 * PushSubscriptionManager - Manage push notification subscriptions
 */
class PushSubscriptionManager {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'kg_push_subscriptions';
    }
    
    /**
     * Subscribe a user to push notifications
     * 
     * @param int $user_id User ID
     * @param string $endpoint Push endpoint URL
     * @param string $p256dh_key P256DH key
     * @param string $auth_key Auth key
     * @param string|null $user_agent User agent string
     * @return int|WP_Error Subscription ID on success, WP_Error on failure
     */
    public function subscribe($user_id, $endpoint, $p256dh_key, $auth_key, $user_agent = null) {
        global $wpdb;
        
        // Validate inputs
        if (empty($user_id) || empty($endpoint) || empty($p256dh_key) || empty($auth_key)) {
            return new \WP_Error('invalid_input', 'User ID, endpoint, p256dh_key, and auth_key are required');
        }
        
        // Check if subscription already exists for this endpoint
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_active FROM {$this->table_name} WHERE endpoint = %s",
            $endpoint
        ));
        
        // Detect device type from user agent
        $device_type = $this->detect_device_type($user_agent);
        
        if ($existing) {
            // Update existing subscription
            $result = $wpdb->update(
                $this->table_name,
                [
                    'user_id' => $user_id,
                    'p256dh_key' => $p256dh_key,
                    'auth_key' => $auth_key,
                    'user_agent' => $user_agent,
                    'device_type' => $device_type,
                    'is_active' => true,
                    'last_used_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $existing->id],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s'],
                ['%d']
            );
            
            if ($result === false) {
                return new \WP_Error('update_failed', $wpdb->last_error);
            }
            
            return (int) $existing->id;
        }
        
        // Create new subscription
        $result = $wpdb->insert(
            $this->table_name,
            [
                'user_id' => $user_id,
                'endpoint' => $endpoint,
                'p256dh_key' => $p256dh_key,
                'auth_key' => $auth_key,
                'user_agent' => $user_agent,
                'device_type' => $device_type,
                'is_active' => true,
                'last_used_at' => current_time('mysql'),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );
        
        if (!$result) {
            return new \WP_Error('insert_failed', $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Unsubscribe from push notifications
     * 
     * @param int $user_id User ID
     * @param string|null $endpoint Optional endpoint to unsubscribe specific device
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function unsubscribe($user_id, $endpoint = null) {
        global $wpdb;
        
        if ($endpoint) {
            // Unsubscribe specific endpoint
            $result = $wpdb->delete(
                $this->table_name,
                [
                    'user_id' => $user_id,
                    'endpoint' => $endpoint
                ],
                ['%d', '%s']
            );
        } else {
            // Unsubscribe all devices for user
            $result = $wpdb->delete(
                $this->table_name,
                ['user_id' => $user_id],
                ['%d']
            );
        }
        
        if ($result === false) {
            return new \WP_Error('unsubscribe_failed', $wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * Get active subscriptions for a user
     * 
     * @param int $user_id User ID
     * @return array Array of subscription objects
     */
    public function get_user_subscriptions($user_id) {
        global $wpdb;
        
        $subscriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE user_id = %d AND is_active = 1",
            $user_id
        ), ARRAY_A);
        
        return $subscriptions ?: [];
    }
    
    /**
     * Mark subscription as inactive (after failed push)
     * 
     * @param int $subscription_id Subscription ID
     * @return bool True on success, false on failure
     */
    public function mark_inactive($subscription_id) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table_name,
            ['is_active' => false, 'updated_at' => current_time('mysql')],
            ['id' => $subscription_id],
            ['%d', '%s'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Update last_used_at timestamp
     * 
     * @param int $subscription_id Subscription ID
     * @return bool True on success, false on failure
     */
    public function update_last_used($subscription_id) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table_name,
            ['last_used_at' => current_time('mysql'), 'updated_at' => current_time('mysql')],
            ['id' => $subscription_id],
            ['%s', '%s'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Clean up old inactive subscriptions
     * 
     * @param int $days Days of inactivity before cleanup (default 90)
     * @return int Number of deleted subscriptions
     */
    public function cleanup_old_subscriptions($days = 90) {
        global $wpdb;
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} 
            WHERE is_active = 0 
            OR (last_used_at IS NOT NULL AND last_used_at < DATE_SUB(NOW(), INTERVAL %d DAY))",
            $days
        ));
        
        return $result ?: 0;
    }
    
    /**
     * Get total active subscriptions count
     * 
     * @return int Count of active subscriptions
     */
    public function get_active_count() {
        global $wpdb;
        
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE is_active = 1"
        );
    }
    
    /**
     * Detect device type from user agent
     * 
     * @param string|null $user_agent User agent string
     * @return string Device type (desktop, mobile, tablet)
     */
    private function detect_device_type($user_agent) {
        if (empty($user_agent)) {
            return 'desktop';
        }
        
        $user_agent = strtolower($user_agent);
        
        // Check for tablet
        if (preg_match('/(ipad|tablet|playbook|silk)|(android(?!.*mobile))/i', $user_agent)) {
            return 'tablet';
        }
        
        // Check for mobile
        if (preg_match('/(mobile|phone|iphone|ipod|blackberry|android|windows phone)/i', $user_agent)) {
            return 'mobile';
        }
        
        return 'desktop';
    }
}
