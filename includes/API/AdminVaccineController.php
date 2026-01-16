<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;
use KG_Core\Health\VaccineManager;

/**
 * AdminVaccineController - Admin-only endpoints
 * 
 * Handles administrative vaccine and notification management including:
 * - Vaccine definition management (CRUD)
 * - Email template management
 * - Email logs and notification queue monitoring
 */
class AdminVaccineController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register all admin vaccine-related REST API routes
     */
    public function register_routes() {
        // Vaccine management routes
        register_rest_route( 'kg/v1', '/admin/vaccines', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_all_vaccines' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        register_rest_route( 'kg/v1', '/admin/vaccines', [
            'methods'  => 'POST',
            'callback' => [ $this, 'create_vaccine' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        register_rest_route( 'kg/v1', '/admin/vaccines/(?P<id>\d+)', [
            'methods'  => 'PUT',
            'callback' => [ $this, 'update_vaccine' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/admin/vaccines/(?P<id>\d+)', [
            'methods'  => 'DELETE',
            'callback' => [ $this, 'delete_vaccine' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Email template management routes
        register_rest_route( 'kg/v1', '/admin/email-templates', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_email_templates' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        register_rest_route( 'kg/v1', '/admin/email-templates', [
            'methods'  => 'POST',
            'callback' => [ $this, 'create_email_template' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        register_rest_route( 'kg/v1', '/admin/email-templates/(?P<id>\d+)', [
            'methods'  => 'PUT',
            'callback' => [ $this, 'update_email_template' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/admin/email-templates/(?P<id>\d+)', [
            'methods'  => 'DELETE',
            'callback' => [ $this, 'delete_email_template' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        register_rest_route( 'kg/v1', '/admin/email-templates/(?P<id>\d+)/test', [
            'methods'  => 'POST',
            'callback' => [ $this, 'send_test_email' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Monitoring routes
        register_rest_route( 'kg/v1', '/admin/email-logs', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_email_logs' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        register_rest_route( 'kg/v1', '/admin/notification-queue', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_notification_queue' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);
    }

    /**
     * Check if user is authenticated and has admin permissions
     * 
     * @param \WP_REST_Request $request The request object
     * @return bool True if authenticated and admin, false otherwise
     */
    public function check_admin_permission( $request ) {
        $token = JWTHandler::get_token_from_request();
        
        if ( ! $token ) {
            return false;
        }

        $payload = JWTHandler::validate_token( $token );
        
        if ( ! $payload ) {
            return false;
        }

        $user_id = $payload['user_id'];
        $user = get_user_by( 'id', $user_id );

        if ( ! $user || ! $user->has_cap( 'manage_options' ) ) {
            return false;
        }

        // Store user ID in request for later use
        $request->set_param( 'authenticated_user_id', $user_id );
        
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
     * Get all vaccine definitions
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function get_all_vaccines( $request ) {
        $vaccine_manager = new VaccineManager();
        $vaccines = $vaccine_manager->get_all_vaccines( false );

        if ( is_wp_error( $vaccines ) ) {
            return $vaccines;
        }

        return new \WP_REST_Response( [
            'success' => true,
            'data' => $vaccines,
        ], 200 );
    }

    /**
     * Create a new vaccine definition
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function create_vaccine( $request ) {
        $vaccine_data = $request->get_json_params();

        if ( empty( $vaccine_data ) ) {
            return new \WP_Error( 'missing_data', 'Vaccine data is required', [ 'status' => 400 ] );
        }

        // Validate required fields
        $required_fields = [ 'vaccine_code', 'vaccine_name', 'category' ];
        foreach ( $required_fields as $field ) {
            if ( ! isset( $vaccine_data[ $field ] ) || empty( $vaccine_data[ $field ] ) ) {
                return new \WP_Error( 'missing_field', "Field '{$field}' is required", [ 'status' => 400 ] );
            }
        }

        // Sanitize vaccine data
        $sanitized_data = $this->sanitize_vaccine_data( $vaccine_data );

        $vaccine_manager = new VaccineManager();
        $result = $vaccine_manager->create_vaccine( $sanitized_data );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Vaccine created successfully',
            'data' => $result,
        ], 201 );
    }

    /**
     * Update an existing vaccine definition
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function update_vaccine( $request ) {
        $vaccine_id = $request->get_param( 'id' );
        $vaccine_data = $request->get_json_params();

        if ( empty( $vaccine_data ) ) {
            return new \WP_Error( 'missing_data', 'Vaccine data is required', [ 'status' => 400 ] );
        }

        // Sanitize vaccine data
        $sanitized_data = $this->sanitize_vaccine_data( $vaccine_data );

        $vaccine_manager = new VaccineManager();
        $result = $vaccine_manager->update_vaccine( $vaccine_id, $sanitized_data );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Vaccine updated successfully',
            'data' => $result,
        ], 200 );
    }

    /**
     * Delete a vaccine definition
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function delete_vaccine( $request ) {
        $vaccine_id = $request->get_param( 'id' );

        $vaccine_manager = new VaccineManager();
        $result = $vaccine_manager->delete_vaccine( $vaccine_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Vaccine deleted successfully',
        ], 200 );
    }

    /**
     * Get all email templates
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function get_email_templates( $request ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kg_email_templates';

        $templates = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY id DESC" );

        if ( $wpdb->last_error ) {
            return new \WP_Error( 'database_error', $wpdb->last_error, [ 'status' => 500 ] );
        }

        return new \WP_REST_Response( [
            'success' => true,
            'data' => $templates,
        ], 200 );
    }

    /**
     * Create a new email template
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function create_email_template( $request ) {
        $template_data = $request->get_json_params();

        if ( empty( $template_data ) ) {
            return new \WP_Error( 'missing_data', 'Template data is required', [ 'status' => 400 ] );
        }

        // Validate required fields
        $required_fields = [ 'template_key', 'subject', 'body' ];
        foreach ( $required_fields as $field ) {
            if ( ! isset( $template_data[ $field ] ) || empty( $template_data[ $field ] ) ) {
                return new \WP_Error( 'missing_field', "Field '{$field}' is required", [ 'status' => 400 ] );
            }
        }

        // Sanitize template data
        $sanitized_data = [
            'template_key' => sanitize_key( $template_data['template_key'] ),
            'subject' => sanitize_text_field( $template_data['subject'] ),
            'body' => wp_kses_post( $template_data['body'] ),
            'description' => isset( $template_data['description'] ) ? sanitize_text_field( $template_data['description'] ) : '',
        ];

        global $wpdb;
        $table_name = $wpdb->prefix . 'kg_email_templates';

        $result = $wpdb->insert( $table_name, $sanitized_data );

        if ( ! $result ) {
            return new \WP_Error( 'database_error', $wpdb->last_error, [ 'status' => 500 ] );
        }

        $template_id = $wpdb->insert_id;
        $template = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $template_id ) );

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Email template created successfully',
            'data' => $template,
        ], 201 );
    }

    /**
     * Update an existing email template
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function update_email_template( $request ) {
        $template_id = $request->get_param( 'id' );
        $template_data = $request->get_json_params();

        if ( empty( $template_data ) ) {
            return new \WP_Error( 'missing_data', 'Template data is required', [ 'status' => 400 ] );
        }

        // Sanitize template data
        $sanitized_data = [];
        
        if ( isset( $template_data['template_key'] ) ) {
            $sanitized_data['template_key'] = sanitize_key( $template_data['template_key'] );
        }
        
        if ( isset( $template_data['subject'] ) ) {
            $sanitized_data['subject'] = sanitize_text_field( $template_data['subject'] );
        }
        
        if ( isset( $template_data['body'] ) ) {
            $sanitized_data['body'] = wp_kses_post( $template_data['body'] );
        }
        
        if ( isset( $template_data['description'] ) ) {
            $sanitized_data['description'] = sanitize_text_field( $template_data['description'] );
        }

        if ( empty( $sanitized_data ) ) {
            return new \WP_Error( 'no_data', 'No valid data to update', [ 'status' => 400 ] );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'kg_email_templates';

        $result = $wpdb->update( $table_name, $sanitized_data, [ 'id' => $template_id ] );

        if ( $result === false ) {
            return new \WP_Error( 'database_error', $wpdb->last_error, [ 'status' => 500 ] );
        }

        $template = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $template_id ) );

        if ( ! $template ) {
            return new \WP_Error( 'not_found', 'Template not found', [ 'status' => 404 ] );
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Email template updated successfully',
            'data' => $template,
        ], 200 );
    }

    /**
     * Delete an email template
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function delete_email_template( $request ) {
        $template_id = $request->get_param( 'id' );

        global $wpdb;
        $table_name = $wpdb->prefix . 'kg_email_templates';

        $result = $wpdb->delete( $table_name, [ 'id' => $template_id ] );

        if ( ! $result ) {
            return new \WP_Error( 'delete_failed', 'Failed to delete template or template not found', [ 'status' => 404 ] );
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Email template deleted successfully',
        ], 200 );
    }

    /**
     * Send test email using a template
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function send_test_email( $request ) {
        $template_id = $request->get_param( 'id' );
        $user_id = $this->get_authenticated_user_id( $request );

        global $wpdb;
        $table_name = $wpdb->prefix . 'kg_email_templates';

        $template = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $template_id ) );

        if ( ! $template ) {
            return new \WP_Error( 'not_found', 'Template not found', [ 'status' => 404 ] );
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            return new \WP_Error( 'user_not_found', 'User not found', [ 'status' => 404 ] );
        }

        // Send test email
        $to = $user->user_email;
        $subject = '[TEST] ' . $template->subject;
        $message = $template->body;

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $sent = wp_mail( $to, $subject, $message, $headers );

        if ( ! $sent ) {
            return new \WP_Error( 'send_failed', 'Failed to send test email', [ 'status' => 500 ] );
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Test email sent to ' . $to,
        ], 200 );
    }

    /**
     * Get email logs
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function get_email_logs( $request ) {
        $page = $request->get_param( 'page' ) ?: 1;
        $per_page = $request->get_param( 'per_page' ) ?: 50;
        $offset = ( $page - 1 ) * $per_page;

        global $wpdb;
        $table_name = $wpdb->prefix . 'kg_email_logs';

        $total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
        
        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ) );

        if ( $wpdb->last_error ) {
            return new \WP_Error( 'database_error', $wpdb->last_error, [ 'status' => 500 ] );
        }

        return new \WP_REST_Response( [
            'success' => true,
            'data' => $logs,
            'pagination' => [
                'total' => (int) $total,
                'page' => (int) $page,
                'per_page' => (int) $per_page,
                'total_pages' => ceil( $total / $per_page ),
            ],
        ], 200 );
    }

    /**
     * Get notification queue
     * 
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error Response object or error
     */
    public function get_notification_queue( $request ) {
        $status = $request->get_param( 'status' ) ?: 'pending';
        $page = $request->get_param( 'page' ) ?: 1;
        $per_page = $request->get_param( 'per_page' ) ?: 50;
        $offset = ( $page - 1 ) * $per_page;

        // Validate status
        $valid_statuses = [ 'pending', 'sent', 'failed', 'cancelled' ];
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            return new \WP_Error( 'invalid_status', 'Invalid status. Must be one of: ' . implode( ', ', $valid_statuses ), [ 'status' => 400 ] );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'kg_notification_queue';

        $total = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
            $status
        ) );
        
        $queue = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE status = %s ORDER BY scheduled_at ASC LIMIT %d OFFSET %d",
            $status,
            $per_page,
            $offset
        ) );

        if ( $wpdb->last_error ) {
            return new \WP_Error( 'database_error', $wpdb->last_error, [ 'status' => 500 ] );
        }

        return new \WP_REST_Response( [
            'success' => true,
            'data' => $queue,
            'pagination' => [
                'total' => (int) $total,
                'page' => (int) $page,
                'per_page' => (int) $per_page,
                'total_pages' => ceil( $total / $per_page ),
            ],
        ], 200 );
    }

    /**
     * Sanitize vaccine data
     * 
     * @param array $data Raw vaccine data
     * @return array Sanitized vaccine data
     */
    private function sanitize_vaccine_data( $data ) {
        $sanitized = [];

        if ( isset( $data['vaccine_code'] ) ) {
            $sanitized['vaccine_code'] = sanitize_text_field( $data['vaccine_code'] );
        }

        if ( isset( $data['vaccine_name'] ) ) {
            $sanitized['vaccine_name'] = sanitize_text_field( $data['vaccine_name'] );
        }

        if ( isset( $data['category'] ) ) {
            $sanitized['category'] = sanitize_text_field( $data['category'] );
        }

        if ( isset( $data['description'] ) ) {
            $sanitized['description'] = sanitize_textarea_field( $data['description'] );
        }

        if ( isset( $data['doses'] ) ) {
            $sanitized['doses'] = absint( $data['doses'] );
        }

        if ( isset( $data['is_mandatory'] ) ) {
            $sanitized['is_mandatory'] = (bool) $data['is_mandatory'];
        }

        if ( isset( $data['is_active'] ) ) {
            $sanitized['is_active'] = (bool) $data['is_active'];
        }

        if ( isset( $data['schedule_data'] ) && is_array( $data['schedule_data'] ) ) {
            $sanitized['schedule_data'] = array_map( function( $item ) {
                return [
                    'dose_number' => isset( $item['dose_number'] ) ? absint( $item['dose_number'] ) : 0,
                    'age_months' => isset( $item['age_months'] ) ? absint( $item['age_months'] ) : 0,
                    'age_label' => isset( $item['age_label'] ) ? sanitize_text_field( $item['age_label'] ) : '',
                ];
            }, $data['schedule_data'] );
        }

        if ( isset( $data['side_effects'] ) && is_array( $data['side_effects'] ) ) {
            $sanitized['side_effects'] = array_map( 'sanitize_text_field', $data['side_effects'] );
        }

        return $sanitized;
    }
}
