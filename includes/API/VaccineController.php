<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;
use KG_Core\Health\VaccineManager;
use KG_Core\Health\VaccineRecordManager;
use KG_Core\Health\VaccineScheduleCalculator;
use KG_Core\Health\SideEffectTracker;

/**
 * VaccineController - Public vaccine endpoints
 * 
 * Handles all public-facing vaccine-related API endpoints including:
 * - Vaccine definitions and schedules
 * - Child vaccine tracking
 * - Status updates and reporting
 */
class VaccineController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register all vaccine-related REST API routes
     */
    public function register_routes() {
        // Public endpoints
        register_rest_route( 'kg/v1', '/health/vaccines/master', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_master_vaccines' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'kg/v1', '/health/vaccines/schedule-versions', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_schedule_versions' ],
            'permission_callback' => '__return_true',
        ]);

        // Authenticated endpoints
        register_rest_route( 'kg/v1', '/health/vaccines', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_child_schedule' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/health/vaccines/mark-done', [
            'methods'  => 'POST',
            'callback' => [ $this, 'mark_vaccine_done' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'record_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'actual_date' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'notes' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/health/vaccines/update-status', [
            'methods'  => 'POST',
            'callback' => [ $this, 'update_status' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'record_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'status' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'notes' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/health/vaccines/add-private', [
            'methods'  => 'POST',
            'callback' => [ $this, 'add_private_vaccine' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'vaccine_code' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/health/vaccines/side-effects', [
            'methods'  => 'POST',
            'callback' => [ $this, 'report_side_effects' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'record_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'side_effects' => [
                    'required' => true,
                    'type' => 'array',
                ],
                'severity' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/health/vaccines/upcoming', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_upcoming_vaccines' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/health/vaccines/history', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_vaccine_history' ],
            'permission_callback' => [ $this, 'check_authentication' ],
            'args' => [
                'child_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
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
     * Get all vaccine definitions (master list)
     * Public endpoint - no authentication required
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function get_master_vaccines( $request ) {
        $vaccine_manager = new VaccineManager();
        $vaccines = $vaccine_manager->get_all_vaccines();

        if ( is_wp_error( $vaccines ) ) {
            return $vaccines;
        }

        return new \WP_REST_Response( [
            'success' => true,
            'data' => $vaccines,
        ], 200 );
    }

    /**
     * Get available schedule versions
     * Public endpoint - no authentication required
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function get_schedule_versions( $request ) {
        $vaccine_manager = new VaccineManager();
        $versions = $vaccine_manager->get_schedule_versions();

        if ( is_wp_error( $versions ) ) {
            return $versions;
        }

        return new \WP_REST_Response( [
            'success' => true,
            'data' => $versions,
        ], 200 );
    }

    /**
     * Get child's vaccine schedule
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function get_child_schedule( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = $request->get_param( 'child_id' );

        // Verify child belongs to user
        if ( ! $this->verify_child_ownership( $user_id, $child_id ) ) {
            return new \WP_Error( 'forbidden', 'Child does not belong to user', [ 'status' => 403 ] );
        }

        $record_manager = new VaccineRecordManager();
        $schedule = $record_manager->get_child_vaccines( $child_id );

        if ( is_wp_error( $schedule ) ) {
            return $schedule;
        }

        return new \WP_REST_Response( [
            'success' => true,
            'data' => $schedule,
        ], 200 );
    }

    /**
     * Mark vaccine as done
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function mark_vaccine_done( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $record_id = $request->get_param( 'record_id' );
        $actual_date = $request->get_param( 'actual_date' );
        $notes = $request->get_param( 'notes' );

        // Verify record belongs to user's child
        if ( ! $this->verify_record_ownership( $user_id, $record_id ) ) {
            return new \WP_Error( 'forbidden', 'Vaccine record does not belong to user', [ 'status' => 403 ] );
        }

        // Validate date format
        if ( ! $this->validate_date_format( $actual_date ) ) {
            return new \WP_Error( 'invalid_date', 'Invalid date format. Use YYYY-MM-DD', [ 'status' => 400 ] );
        }

        $record_manager = new VaccineRecordManager();
        $result = $record_manager->mark_as_done( $record_id, $actual_date, $notes );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Vaccine marked as done successfully',
            'data' => $result,
        ], 200 );
    }

    /**
     * Update vaccine status
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function update_status( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $record_id = $request->get_param( 'record_id' );
        $status = $request->get_param( 'status' );
        $notes = $request->get_param( 'notes' );

        // Verify record belongs to user's child
        if ( ! $this->verify_record_ownership( $user_id, $record_id ) ) {
            return new \WP_Error( 'forbidden', 'Vaccine record does not belong to user', [ 'status' => 403 ] );
        }

        // Validate status
        $valid_statuses = [ 'scheduled', 'done', 'skipped', 'overdue' ];
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            return new \WP_Error( 'invalid_status', 'Invalid status. Must be one of: ' . implode( ', ', $valid_statuses ), [ 'status' => 400 ] );
        }

        $record_manager = new VaccineRecordManager();
        $result = $record_manager->update_status( $record_id, $status, $notes );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Vaccine status updated successfully',
            'data' => $result,
        ], 200 );
    }

    /**
     * Add private vaccine to child's schedule
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function add_private_vaccine( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = $request->get_param( 'child_id' );
        $vaccine_code = $request->get_param( 'vaccine_code' );

        // Verify child belongs to user
        if ( ! $this->verify_child_ownership( $user_id, $child_id ) ) {
            return new \WP_Error( 'forbidden', 'Child does not belong to user', [ 'status' => 403 ] );
        }

        $record_manager = new VaccineRecordManager();
        $result = $record_manager->add_private_vaccine( $user_id, $child_id, $vaccine_code );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Private vaccine added successfully',
            'data' => $result,
        ], 201 );
    }

    /**
     * Report side effects for a vaccine
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function report_side_effects( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $record_id = $request->get_param( 'record_id' );
        $side_effects = $request->get_param( 'side_effects' );
        $severity = $request->get_param( 'severity' );

        // Verify record belongs to user's child
        if ( ! $this->verify_record_ownership( $user_id, $record_id ) ) {
            return new \WP_Error( 'forbidden', 'Vaccine record does not belong to user', [ 'status' => 403 ] );
        }

        // Validate severity
        $valid_severities = [ 'mild', 'moderate', 'severe' ];
        if ( ! in_array( $severity, $valid_severities, true ) ) {
            return new \WP_Error( 'invalid_severity', 'Invalid severity. Must be one of: ' . implode( ', ', $valid_severities ), [ 'status' => 400 ] );
        }

        // Sanitize side effects array
        if ( ! is_array( $side_effects ) ) {
            return new \WP_Error( 'invalid_side_effects', 'Side effects must be an array', [ 'status' => 400 ] );
        }

        $sanitized_effects = [];
        foreach ( $side_effects as $effect ) {
            if ( is_array( $effect ) ) {
                $sanitized_effect = [];
                foreach ( $effect as $key => $value ) {
                    $sanitized_effect[ sanitize_key( $key ) ] = sanitize_text_field( $value );
                }
                $sanitized_effects[] = $sanitized_effect;
            } elseif ( is_string( $effect ) ) {
                // Support simple string array format as well
                $sanitized_effects[] = sanitize_text_field( $effect );
            }
        }

        $side_effect_tracker = new SideEffectTracker();
        $result = $side_effect_tracker->record_side_effects( $record_id, $sanitized_effects, $severity );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Side effects reported successfully',
            'data' => $result,
        ], 200 );
    }

    /**
     * Get upcoming vaccines for a child
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function get_upcoming_vaccines( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = $request->get_param( 'child_id' );

        // Verify child belongs to user
        if ( ! $this->verify_child_ownership( $user_id, $child_id ) ) {
            return new \WP_Error( 'forbidden', 'Child does not belong to user', [ 'status' => 403 ] );
        }

        $record_manager = new VaccineRecordManager();
        $upcoming = $record_manager->get_upcoming_vaccines( $child_id );

        if ( is_wp_error( $upcoming ) ) {
            return $upcoming;
        }

        return new \WP_REST_Response( [
            'success' => true,
            'data' => $upcoming,
        ], 200 );
    }

    /**
     * Get vaccine history for a child
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function get_vaccine_history( $request ) {
        $user_id = $this->get_authenticated_user_id( $request );
        $child_id = $request->get_param( 'child_id' );

        // Verify child belongs to user
        if ( ! $this->verify_child_ownership( $user_id, $child_id ) ) {
            return new \WP_Error( 'forbidden', 'Child does not belong to user', [ 'status' => 403 ] );
        }

        $record_manager = new VaccineRecordManager();
        $history = $record_manager->get_child_vaccines( $child_id, 'done' );

        if ( is_wp_error( $history ) ) {
            return $history;
        }

        return new \WP_REST_Response( [
            'success' => true,
            'data' => $history,
        ], 200 );
    }

    /**
     * Verify that a child belongs to the authenticated user
     * 
     * @param int $user_id User ID
     * @param string $child_id Child ID
     * @return bool True if child belongs to user, false otherwise
     */
    private function verify_child_ownership( $user_id, $child_id ) {
        $children = get_user_meta( $user_id, '_kg_children', true );
        
        if ( ! is_array( $children ) ) {
            return false;
        }

        foreach ( $children as $child ) {
            if ( isset( $child['id'] ) && $child['id'] === $child_id ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify that a vaccine record belongs to the authenticated user's child
     * 
     * @param int $user_id User ID
     * @param int $record_id Vaccine record ID
     * @return bool True if record belongs to user's child, false otherwise
     */
    private function verify_record_ownership( $user_id, $record_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kg_vaccine_records';

        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT child_id FROM {$table_name} WHERE id = %d",
            $record_id
        ) );

        if ( ! $record ) {
            return false;
        }

        return $this->verify_child_ownership( $user_id, $record->child_id );
    }

    /**
     * Validate date format (YYYY-MM-DD)
     * 
     * @param string $date Date string to validate
     * @return bool True if valid, false otherwise
     */
    private function validate_date_format( $date ) {
        $d = \DateTime::createFromFormat( 'Y-m-d', $date );
        return $d && $d->format( 'Y-m-d' ) === $date;
    }
}
