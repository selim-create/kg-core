<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;
use KG_Core\Health\PrivateVaccineWizard;

/**
 * VaccinePrivateController - API endpoints for private vaccine wizard
 */
class VaccinePrivateController {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		// Get list of private vaccine types (public endpoint)
		register_rest_route( 'kg/v1', '/health/vaccines/private-types', [
			'methods'  => 'GET',
			'callback' => [ $this, 'get_private_types' ],
			'permission_callback' => '__return_true',
		]);

		// Get config for specific private vaccine type (authenticated)
		register_rest_route( 'kg/v1', '/health/vaccines/private-types/(?P<type>[\w-]+)/config', [
			'methods'  => 'GET',
			'callback' => [ $this, 'get_type_config' ],
			'permission_callback' => [ $this, 'check_authentication' ],
			'args' => [
				'type' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		]);

		// Validate vaccine addition
		register_rest_route( 'kg/v1', '/health/vaccines/private/validate', [
			'methods'  => 'POST',
			'callback' => [ $this, 'validate_vaccine' ],
			'permission_callback' => [ $this, 'check_authentication' ],
			'args' => [
				'child_id' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'type' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'brand_code' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'schedule_key' => [
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		]);

		// Add private vaccine
		register_rest_route( 'kg/v1', '/health/vaccines/private/add', [
			'methods'  => 'POST',
			'callback' => [ $this, 'add_vaccine' ],
			'permission_callback' => [ $this, 'check_authentication' ],
			'args' => [
				'child_id' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'type' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'brand_code' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'schedule_key' => [
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		]);

		// Remove private vaccine series
		register_rest_route( 'kg/v1', '/health/vaccines/private/(?P<record_id>\d+)', [
			'methods'  => 'DELETE',
			'callback' => [ $this, 'remove_vaccine' ],
			'permission_callback' => [ $this, 'check_authentication' ],
			'args' => [
				'record_id' => [
					'required' => true,
					'type' => 'integer',
					'sanitize_callback' => 'absint',
				],
			],
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
	 * Get list of private vaccine types
	 */
	public function get_private_types( $request ) {
		$wizard = new PrivateVaccineWizard();
		$types = $wizard->get_private_types();

		if ( is_wp_error( $types ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error' => $types->get_error_message(),
			], 500 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data' => $types,
		], 200 );
	}

	/**
	 * Get config for specific vaccine type
	 */
	public function get_type_config( $request ) {
		$type = $request->get_param( 'type' );
		
		$wizard = new PrivateVaccineWizard();
		$config = $wizard->get_type_config( $type );

		if ( is_wp_error( $config ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error' => $config->get_error_message(),
			], 404 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data' => $config,
		], 200 );
	}

	/**
	 * Validate vaccine addition
	 */
	public function validate_vaccine( $request ) {
		$user_id = $this->get_authenticated_user_id( $request );
		$child_id = $request->get_param( 'child_id' );
		$type = $request->get_param( 'type' );
		$brand_code = $request->get_param( 'brand_code' );
		$schedule_key = $request->get_param( 'schedule_key' );

		// Verify child belongs to user
		if ( ! $this->verify_child_ownership( $user_id, $child_id ) ) {
			return new \WP_Error( 'forbidden', 'Child does not belong to user', [ 'status' => 403 ] );
		}

		// Prepare options array
		$options = [];
		if ( $schedule_key ) {
			$options['schedule_key'] = $schedule_key;
		}

		$wizard = new PrivateVaccineWizard();
		$result = $wizard->validate_addition( $child_id, $type, $brand_code, $options );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error' => $result->get_error_message(),
			], 400 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data' => $result,
		], 200 );
	}

	/**
	 * Add private vaccine
	 */
	public function add_vaccine( $request ) {
		$user_id = $this->get_authenticated_user_id( $request );
		$child_id = $request->get_param( 'child_id' );
		$type = $request->get_param( 'type' );
		$brand_code = $request->get_param( 'brand_code' );
		$schedule_key = $request->get_param( 'schedule_key' );

		// Verify child belongs to user
		if ( ! $this->verify_child_ownership( $user_id, $child_id ) ) {
			return new \WP_Error( 'forbidden', 'Child does not belong to user', [ 'status' => 403 ] );
		}

		// Prepare options array
		$options = [];
		if ( $schedule_key ) {
			$options['schedule_key'] = $schedule_key;
		}

		$wizard = new PrivateVaccineWizard();
		$result = $wizard->add_to_schedule( $user_id, $child_id, $type, $brand_code, $options );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error' => $result->get_error_message(),
			], 400 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'message' => 'Private vaccine added successfully',
			'data' => $result,
		], 201 );
	}

	/**
	 * Remove private vaccine series
	 */
	public function remove_vaccine( $request ) {
		$user_id = $this->get_authenticated_user_id( $request );
		$record_id = $request->get_param( 'record_id' );

		// Verify record belongs to user's child
		if ( ! $this->verify_record_ownership( $user_id, $record_id ) ) {
			return new \WP_Error( 'forbidden', 'Vaccine record does not belong to user', [ 'status' => 403 ] );
		}

		$wizard = new PrivateVaccineWizard();
		$result = $wizard->remove_series( $user_id, $record_id );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error' => $result->get_error_message(),
			], 400 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'message' => 'Private vaccine removed successfully',
		], 200 );
	}

	/**
	 * Verify that a child belongs to the authenticated user
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
}
