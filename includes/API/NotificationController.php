<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;
use KG_Core\Notifications\NotificationManager;

/**
 * NotificationController - Notification preferences
 * 
 * Handles notification preference management including:
 * - Getting and updating user notification preferences
 * - Push notification subscription management
 */
class NotificationController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register all notification-related REST API routes
     */
    public function register_routes() {
        register_rest_route( 'kg/v1', '/notifications/preferences', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_preferences' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        register_rest_route( 'kg/v1', '/notifications/preferences', [
            'methods'  => 'PUT',
            'callback' => [ $this, 'update_preferences' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);

        register_rest_route( 'kg/v1', '/notifications/push/subscribe', [
            'methods'  => 'POST',
            'callback' => [ $this, 'subscribe_push' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'endpoint' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
                'keys' => [
                    'required' => true,
                    'type' => 'object',
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/notifications/push/unsubscribe', [
            'methods'  => 'DELETE',
            'callback' => [ $this, 'unsubscribe_push' ],
            'permission_callback' => [ $this, 'check_authentication' ],
        ]);
    }

    /**
     * Check if user is authenticated via JWT
     * 
     * @param \WP_REST_Request $request The request object
     * @return bool True if authenticated, false otherwise
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

        // Store user ID in request for later use
        $request->set_param( 'authenticated_user_id', $payload['user_id'] );
        
        return true;
    }

    /**
     * Get authenticated user ID from request
     * 
     * @param \WP_REST_Request $request The request object
     * @return int User ID
     */
    private function get_authenticated_user_id( $request ) {
        return $request->get_param( 'authenticated_user_id' );
    }

    /**
     * Get user's notification preferences
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function get_preferences( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );

        $notification_manager = new NotificationManager();
        $preferences = $notification_manager->get_user_preferences( $user_id );

        if ( is_wp_error( $preferences ) ) {
            return $preferences;
        }

        return new \WP_REST_Response( [
            'success' => true,
            'data' => $preferences,
        ], 200 );
    }

    /**
     * Update user's notification preferences
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function update_preferences( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $preferences = $request->get_json_params();

        if ( empty( $preferences ) || ! is_array( $preferences ) ) {
            return new \WP_Error( 'invalid_preferences', 'Preferences must be provided as an object', [ 'status' => 400 ] );
        }

        // Validate preferences structure
        $validated_preferences = $this->validate_preferences( $preferences );
        if ( is_wp_error( $validated_preferences ) ) {
            return $validated_preferences;
        }

        $notification_manager = new NotificationManager();
        $result = $notification_manager->update_user_preferences( $user_id, $validated_preferences );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Preferences updated successfully',
            'data' => $result,
        ], 200 );
    }

    /**
     * Subscribe to push notifications
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function subscribe_push( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $endpoint = $request->get_param( 'endpoint' );
        $keys = $request->get_param( 'keys' );

        if ( empty( $endpoint ) ) {
            return new \WP_Error( 'missing_endpoint', 'Push notification endpoint is required', [ 'status' => 400 ] );
        }

        if ( empty( $keys ) || ! is_array( $keys ) ) {
            return new \WP_Error( 'missing_keys', 'Push notification keys are required', [ 'status' => 400 ] );
        }

        // Validate keys structure
        if ( ! isset( $keys['p256dh'] ) || ! isset( $keys['auth'] ) ) {
            return new \WP_Error( 'invalid_keys', 'Keys must contain p256dh and auth properties', [ 'status' => 400 ] );
        }

        // Sanitize keys
        $sanitized_keys = [
            'p256dh' => sanitize_text_field( $keys['p256dh'] ),
            'auth' => sanitize_text_field( $keys['auth'] ),
        ];

        $notification_manager = new NotificationManager();
        $result = $notification_manager->subscribe_push( $user_id, $endpoint, $sanitized_keys );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Successfully subscribed to push notifications',
            'data' => $result,
        ], 201 );
    }

    /**
     * Unsubscribe from push notifications
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function unsubscribe_push( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );

        $notification_manager = new NotificationManager();
        $result = $notification_manager->unsubscribe_push( $user_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Successfully unsubscribed from push notifications',
        ], 200 );
    }

    /**
     * Validate notification preferences structure
     * 
     * @param array $preferences Preferences to validate
     * @return array|\WP_Error Validated preferences or error
     */
    private function validate_preferences( $preferences ) {
        $valid_channels = [ 'email', 'push', 'sms' ];
        $valid_types = [ 'vaccine_reminder', 'vaccine_due', 'vaccine_overdue', 'side_effect_followup' ];
        
        $validated = [];

        foreach ( $preferences as $key => $value ) {
            // Check if it's a channel preference
            if ( in_array( $key, $valid_channels, true ) ) {
                $validated[ $key ] = (bool) $value;
                continue;
            }

            // Check if it's a notification type preference
            if ( in_array( $key, $valid_types, true ) ) {
                $validated[ $key ] = (bool) $value;
                continue;
            }

            // Check if it's a nested preference object
            if ( is_array( $value ) ) {
                foreach ( $value as $sub_key => $sub_value ) {
                    if ( in_array( $sub_key, $valid_channels, true ) || in_array( $sub_key, $valid_types, true ) ) {
                        if ( ! isset( $validated[ $key ] ) ) {
                            $validated[ $key ] = [];
                        }
                        $validated[ $key ][ $sub_key ] = (bool) $sub_value;
                    }
                }
                continue;
            }

            // Allow other custom preferences
            $validated[ $key ] = $value;
        }

        return $validated;
    }
}
