<?php
/**
 * Email Service for Notifications
 *
 * @package KG_Core
 * @subpackage Notifications
 */

namespace KG_Core\Notifications;

use WP_Error;

/**
 * Class EmailService
 *
 * Handles email sending via WordPress wp_mail and email logging
 */
class EmailService {

	/**
	 * Template engine instance
	 *
	 * @var TemplateEngine
	 */
	private $template_engine;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->template_engine = new TemplateEngine();
	}

	/**
	 * Send email via WordPress wp_mail
	 *
	 * @param string $to         Recipient email address.
	 * @param string $subject    Email subject.
	 * @param string $body_html  HTML email body.
	 * @param string $body_text  Plain text email body (optional).
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function send( $to, $subject, $body_html, $body_text = '' ) {
		if ( empty( $to ) || ! is_email( $to ) ) {
			return new WP_Error( 'invalid_email', 'Invalid recipient email address' );
		}

		if ( empty( $subject ) ) {
			return new WP_Error( 'empty_subject', 'Email subject cannot be empty' );
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$sent = wp_mail( $to, $subject, $body_html, $headers );

		if ( ! $sent ) {
			return new WP_Error( 'email_send_failed', 'Failed to send email' );
		}

		return true;
	}

	/**
	 * Send email using template
	 *
	 * @param int    $user_id       WordPress user ID.
	 * @param string $template_key  Template identifier.
	 * @param array  $placeholders  Placeholder values.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function send_from_template( $user_id, $template_key, $placeholders = array() ) {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return new WP_Error( 'user_not_found', 'User not found' );
		}

		$rendered = $this->template_engine->render( $template_key, $placeholders );

		if ( is_wp_error( $rendered ) ) {
			$this->log_email(
				$user_id,
				$template_key,
				$user->user_email,
				'',
				'failed',
				array( 'error' => $rendered->get_error_message() )
			);
			return $rendered;
		}

		$result = $this->send(
			$user->user_email,
			$rendered['subject'],
			$rendered['body_html'],
			$rendered['body_text']
		);

		$status = is_wp_error( $result ) ? 'failed' : 'sent';
		$metadata = is_wp_error( $result ) ? array( 'error' => $result->get_error_message() ) : array();

		$this->log_email(
			$user_id,
			$template_key,
			$user->user_email,
			$rendered['subject'],
			$status,
			$metadata
		);

		return $result;
	}

	/**
	 * Log email to database
	 *
	 * @param int    $user_id       User ID.
	 * @param string $template_key  Template identifier.
	 * @param string $recipient     Recipient email address.
	 * @param string $subject       Email subject.
	 * @param string $status        Status (sent, failed, pending).
	 * @param array  $metadata      Additional metadata.
	 * @return int|WP_Error Log ID on success, WP_Error on failure.
	 */
	public function log_email( $user_id, $template_key, $recipient, $subject, $status, $metadata = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'notification_logs';

		$data = array(
			'user_id'      => $user_id,
			'channel'      => 'email',
			'template_key' => $template_key,
			'recipient'    => $recipient,
			'subject'      => $subject,
			'status'       => $status,
			'metadata'     => wp_json_encode( $metadata ),
			'sent_at'      => current_time( 'mysql' ),
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Required for email logging functionality
		$result = $wpdb->insert( $table_name, $data, $format );

		if ( false === $result ) {
			return new WP_Error( 'log_insert_failed', 'Failed to insert email log' );
		}

		return $wpdb->insert_id;
	}
}
