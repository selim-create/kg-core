<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;
use KG_Core\Notifications\VapidKeyManager;
use KG_Core\Notifications\PushSubscriptionManager;
use KG_Core\Notifications\PushNotificationService;

/**
 * PushNotificationController - Push notification API endpoints
 */
class PushNotificationController {
    
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Get VAPID public key (public endpoint)
        register_rest_route( 'kg/v1', '/notifications/vapid-public-key', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_vapid_public_key' ],
            'permission_callback' => '__return_true',
        ]);
        
        // Subscribe to push notifications
        register_rest_route( 'kg/v1', '/notifications/push/subscribe', [
            'methods'  => 'POST',
            'callback' => [ $this, 'subscribe' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'endpoint' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'p256dh' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'auth' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);
        
        // Unsubscribe from push notifications
        register_rest_route( 'kg/v1', '/notifications/push/unsubscribe', [
            'methods'  => 'DELETE',
            'callback' => [ $this, 'unsubscribe' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'endpoint' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        
        // Update notification preferences
        register_rest_route( 'kg/v1', '/notifications/preferences', [
            'methods'  => 'PUT',
            'callback' => [ $this, 'update_preferences' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);
        
        // Get notification preferences
        register_rest_route( 'kg/v1', '/notifications/preferences', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_preferences' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);
        
        // Send test push notification
        register_rest_route( 'kg/v1', '/notifications/test', [
            'methods'  => 'POST',
            'callback' => [ $this, 'send_test' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);
    }
    
    /**
     * Check authentication
     */
    public function check_authentication( $request ) {
        $token = JWTHandler::get_token_from_request();
        
        if ( ! $token ) {
            return false;
        }

        $payload = JWTHandler::validate_token( $token );
        
        if ( ! $payload ) {
            return false;
        }

        $request->set_param( 'authenticated_user_id', $payload['user_id'] );
        return true;
    }
    
    /**
     * Get authenticated user ID
     */
    private function get_authenticated_user_id( $request ) {
        return $request->get_param( 'authenticated_user_id' );
    }
    
    /**
     * Get VAPID public key
     */
    public function get_vapid_public_key( $request ) {
        $vapid_manager = new VapidKeyManager();
        
        // Ensure keys exist
        $result = $vapid_manager->ensure_keys_exist();
        if (is_wp_error($result)) {
            return new \WP_REST_Response( [
                'success' => false,
                'error' => $result->get_error_message(),
            ], 500 );
        }
        
        $public_key = $vapid_manager->get_public_key();
        
        return new \WP_REST_Response( [
            'success' => true,
            'data' => [
                'public_key' => $public_key
            ]
        ], 200 );
    }
    
    /**
     * Subscribe to push notifications
     */
    public function subscribe( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $endpoint = $request->get_param( 'endpoint' );
        $p256dh = $request->get_param( 'p256dh' );
        $auth = $request->get_param( 'auth' );
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
        
        $subscription_manager = new PushSubscriptionManager();
        $result = $subscription_manager->subscribe($user_id, $endpoint, $p256dh, $auth, $user_agent);
        
        if (is_wp_error($result)) {
            return new \WP_REST_Response( [
                'success' => false,
                'error' => $result->get_error_message(),
            ], 400 );
        }
        
        return new \WP_REST_Response( [
            'success' => true,
            'data' => [
                'subscription_id' => $result,
                'message' => 'Push notification subscription successful'
            ]
        ], 200 );
    }
    
    /**
     * Unsubscribe from push notifications
     */
    public function unsubscribe( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $endpoint = $request->get_param( 'endpoint' );
        
        $subscription_manager = new PushSubscriptionManager();
        $result = $subscription_manager->unsubscribe($user_id, $endpoint);
        
        if (is_wp_error($result)) {
            return new \WP_REST_Response( [
                'success' => false,
                'error' => $result->get_error_message(),
            ], 400 );
        }
        
        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Push notification unsubscribed successfully'
        ], 200 );
    }
    
    /**
     * Update notification preferences
     */
    public function update_preferences( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $params = $request->get_json_params();
        
        global $wpdb;
        $table = $wpdb->prefix . 'kg_notification_preferences';
        
        // Prepare data
        $data = [];
        $allowed_fields = [
            'email_enabled', 'push_enabled', 'vaccine_reminder_3day', 
            'vaccine_reminder_1day', 'vaccine_overdue', 'growth_tracking', 
            'weekly_digest', 'quiet_hours_start', 'quiet_hours_end'
        ];
        
        foreach ($allowed_fields as $field) {
            if (isset($params[$field])) {
                $data[$field] = $params[$field];
            }
        }
        
        if (empty($data)) {
            return new \WP_REST_Response( [
                'success' => false,
                'error' => 'No valid preferences provided',
            ], 400 );
        }
        
        // Check if preferences exist
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d",
            $user_id
        ));
        
        if ($existing) {
            // Update
            $data['updated_at'] = current_time('mysql');
            $result = $wpdb->update($table, $data, ['user_id' => $user_id]);
        } else {
            // Insert
            $data['user_id'] = $user_id;
            $data['created_at'] = current_time('mysql');
            $data['updated_at'] = current_time('mysql');
            $result = $wpdb->insert($table, $data);
        }
        
        if ($result === false) {
            return new \WP_REST_Response( [
                'success' => false,
                'error' => 'Failed to update preferences',
            ], 500 );
        }
        
        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Preferences updated successfully'
        ], 200 );
    }
    
    /**
     * Get notification preferences
     */
    public function get_preferences( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        
        global $wpdb;
        $table = $wpdb->prefix . 'kg_notification_preferences';
        
        $preferences = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
        
        // Return defaults if not found
        if (!$preferences) {
            $preferences = [
                'email_enabled' => true,
                'push_enabled' => true,
                'vaccine_reminder_3day' => true,
                'vaccine_reminder_1day' => true,
                'vaccine_overdue' => true,
                'growth_tracking' => true,
                'weekly_digest' => false,
                'quiet_hours_start' => null,
                'quiet_hours_end' => null
            ];
        }
        
        return new \WP_REST_Response( [
            'success' => true,
            'data' => $preferences
        ], 200 );
    }
    
    /**
     * Send test push notification
     */
    public function send_test( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        
        $push_service = new PushNotificationService();
        $result = $push_service->send_test($user_id);
        
        if (is_wp_error($result)) {
            return new \WP_REST_Response( [
                'success' => false,
                'error' => $result->get_error_message(),
            ], 400 );
        }
        
        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Test notification sent',
            'results' => $result
        ], 200 );
    }
}
