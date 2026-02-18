<?php
/**
 * Notification Manager
 *
 * @package KG_Core
 * @subpackage Notifications
 */

namespace KG_Core\Notifications;

use WP_Error;

/**
 * Class NotificationManager
 *
 * Central handler for all notification operations
 */
class NotificationManager {

	/**
	 * Template engine instance
	 *
	 * @var TemplateEngine
	 */
	private $template_engine;

	/**
	 * Email service instance
	 *
	 * @var EmailService
	 */
	private $email_service;

	/**
	 * Notification queue instance
	 *
	 * @var NotificationQueue
	 */
	private $notification_queue;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->template_engine    = new TemplateEngine();
		$this->email_service      = new EmailService();
		$this->notification_queue = new NotificationQueue();
	}

	/**
	 * Schedule a vaccine reminder notification
	 *
	 * @param int    $user_id        User ID.
	 * @param int    $child_id       Child ID.
	 * @param string $vaccine_code   Vaccine code.
	 * @param string $scheduled_date Scheduled vaccination date (Y-m-d).
	 * @param int    $days_before    Days before scheduled date to send reminder.
	 * @return int|WP_Error Queue ID on success, WP_Error on failure.
	 */
	public function schedule_vaccine_reminder( $user_id, $child_id, $vaccine_code, $scheduled_date, $days_before = 3 ) {
		$preferences = $this->get_user_preferences( $user_id );

		if ( ! isset( $preferences['vaccine_reminders'] ) || ! $preferences['vaccine_reminders'] ) {
			return new WP_Error( 'reminders_disabled', 'User has disabled vaccine reminders' );
		}

		$reminder_date = date( 'Y-m-d H:i:s', strtotime( $scheduled_date . ' -' . $days_before . ' days' ) );

		global $wpdb;

		// Get child name.
		$child = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT post_title FROM {$wpdb->posts} WHERE ID = %d",
				$child_id
			)
		);

		$child_name = $child ? $child->post_title : 'Your child';

		// Get vaccine name.
		$vaccine_name = $this->get_vaccine_name( $vaccine_code );

		$payload = array(
			'child_id'        => $child_id,
			'child_name'      => $child_name,
			'vaccine_code'    => $vaccine_code,
			'vaccine_name'    => $vaccine_name,
			'scheduled_date'  => date( 'F j, Y', strtotime( $scheduled_date ) ),
			'days_before'     => $days_before,
		);

		$channel = isset( $preferences['preferred_channel'] ) ? $preferences['preferred_channel'] : 'email';

		return $this->notification_queue->add(
			$user_id,
			$channel,
			'vaccine_reminder',
			$payload,
			$reminder_date
		);
	}

	/**
	 * Send immediate notification
	 *
	 * @param int    $user_id       User ID.
	 * @param string $template_key  Template identifier.
	 * @param array  $placeholders  Placeholder values.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function send_immediate_notification( $user_id, $template_key, $placeholders = array() ) {
		$preferences = $this->get_user_preferences( $user_id );
		$channel = isset( $preferences['preferred_channel'] ) ? $preferences['preferred_channel'] : 'email';

		if ( 'email' === $channel ) {
			return $this->email_service->send_from_template( $user_id, $template_key, $placeholders );
		}

		return new WP_Error( 'unsupported_channel', 'Channel not supported: ' . $channel );
	}

	/**
	 * Get user notification preferences
	 *
	 * @param int $user_id User ID.
	 * @return array User preferences.
	 */
	public function get_user_preferences( $user_id ) {
		$preferences = get_user_meta( $user_id, 'notification_preferences', true );

		if ( ! is_array( $preferences ) ) {
			$preferences = array(
				'vaccine_reminders'  => true,
				'milestone_alerts'   => true,
				'health_tips'        => false,
				'preferred_channel'  => 'email',
			);
		}

		return $preferences;
	}

	/**
	 * Update user notification preferences
	 *
	 * @param int   $user_id      User ID.
	 * @param array $preferences  Preferences to update.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update_user_preferences( $user_id, $preferences ) {
		if ( ! is_array( $preferences ) ) {
			return new WP_Error( 'invalid_preferences', 'Preferences must be an array' );
		}

		$current_preferences = $this->get_user_preferences( $user_id );
		$updated_preferences = array_merge( $current_preferences, $preferences );

		$result = update_user_meta( $user_id, 'notification_preferences', $updated_preferences );

		if ( false === $result ) {
			return new WP_Error( 'update_failed', 'Failed to update user preferences' );
		}

		return true;
	}

	/**
	 * Get vaccine name from code
	 *
	 * @param string $vaccine_code Vaccine code.
	 * @return string Vaccine name.
	 */
	private function get_vaccine_name( $vaccine_code ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'vaccines';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$vaccine = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT vaccine_name FROM {$table_name} WHERE vaccine_code = %s LIMIT 1",
				$vaccine_code
			)
		);

		return $vaccine ? $vaccine->vaccine_name : $vaccine_code;
	}
}
