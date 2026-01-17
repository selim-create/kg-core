<?php
namespace KG_Core\API;

use KG_Core\Auth\JWTHandler;
use KG_Core\Export\VaccinePdfExporter;
use KG_Core\Health\VaccineStatsCalculator;

/**
 * VaccineExportController - API endpoints for PDF export
 */
class VaccineExportController {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		// Get vaccine statistics
		register_rest_route( 'kg/v1', '/health/vaccines/stats', [
			'methods'  => 'GET',
			'callback' => [ $this, 'get_stats' ],
			'permission_callback' => [ $this, 'check_authentication' ],
			'args' => [
				'child_id' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		]);

		// Export PDF schedule
		register_rest_route( 'kg/v1', '/health/vaccines/export/pdf', [
			'methods'  => 'GET',
			'callback' => [ $this, 'export_schedule_pdf' ],
			'permission_callback' => [ $this, 'check_authentication' ],
			'args' => [
				'child_id' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		]);

		// Export PDF history
		register_rest_route( 'kg/v1', '/health/vaccines/export/history', [
			'methods'  => 'GET',
			'callback' => [ $this, 'export_history_pdf' ],
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
	 * Get vaccine statistics
	 */
	public function get_stats( $request ) {
		$user_id = $this->get_authenticated_user_id( $request );
		$child_id = $request->get_param( 'child_id' );

		// Verify child belongs to user
		if ( ! $this->verify_child_ownership( $user_id, $child_id ) ) {
			return new \WP_Error( 'forbidden', 'Child does not belong to user', [ 'status' => 403 ] );
		}

		$stats_calculator = new VaccineStatsCalculator();
		$stats = $stats_calculator->get_child_stats( $child_id );

		if ( is_wp_error( $stats ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error' => $stats->get_error_message(),
			], 500 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'data' => $stats,
		], 200 );
	}

	/**
	 * Export vaccine schedule as PDF
	 */
	public function export_schedule_pdf( $request ) {
		$user_id = $this->get_authenticated_user_id( $request );
		$child_id = $request->get_param( 'child_id' );

		// Verify child belongs to user
		if ( ! $this->verify_child_ownership( $user_id, $child_id ) ) {
			return new \WP_Error( 'forbidden', 'Child does not belong to user', [ 'status' => 403 ] );
		}

		$exporter = new VaccinePdfExporter();
		$pdf_data = $exporter->export( $child_id, 'schedule' );

		if ( is_wp_error( $pdf_data ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error' => $pdf_data->get_error_message(),
			], 500 );
		}

		// Get child name for filename
		$child_name = $this->get_child_name( $user_id, $child_id );
		$filename = sanitize_file_name( $child_name . '-vaccine-schedule.pdf' );

		// Return PDF response with proper headers
		$response = new \WP_REST_Response( $pdf_data );
		$response->set_status( 200 );
		$response->header( 'Content-Type', 'application/pdf' );
		$response->header( 'Content-Disposition', 'attachment; filename="' . $filename . '"' );
		$response->header( 'Content-Length', strlen( $pdf_data ) );
		
		return $response;
	}

	/**
	 * Export vaccine history as PDF
	 */
	public function export_history_pdf( $request ) {
		$user_id = $this->get_authenticated_user_id( $request );
		$child_id = $request->get_param( 'child_id' );

		// Verify child belongs to user
		if ( ! $this->verify_child_ownership( $user_id, $child_id ) ) {
			return new \WP_Error( 'forbidden', 'Child does not belong to user', [ 'status' => 403 ] );
		}

		$exporter = new VaccinePdfExporter();
		$pdf_data = $exporter->export( $child_id, 'history' );

		if ( is_wp_error( $pdf_data ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error' => $pdf_data->get_error_message(),
			], 500 );
		}

		// Get child name for filename
		$child_name = $this->get_child_name( $user_id, $child_id );
		$filename = sanitize_file_name( $child_name . '-vaccine-history.pdf' );

		// Return PDF response with proper headers
		$response = new \WP_REST_Response( $pdf_data );
		$response->set_status( 200 );
		$response->header( 'Content-Type', 'application/pdf' );
		$response->header( 'Content-Disposition', 'attachment; filename="' . $filename . '"' );
		$response->header( 'Content-Length', strlen( $pdf_data ) );
		
		return $response;
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
	 * Get child name for filename
	 */
	private function get_child_name( $user_id, $child_id ) {
		$children = get_user_meta( $user_id, '_kg_children', true );
		
		if ( ! is_array( $children ) ) {
			return 'child';
		}

		foreach ( $children as $child ) {
			if ( isset( $child['id'] ) && $child['id'] === $child_id ) {
				return isset( $child['name'] ) ? $child['name'] : 'child';
			}
		}

		return 'child';
	}
}
