<?php
/**
 * Notification Queue Management
 *
 * @package KG_Core
 * @subpackage Notifications
 */

namespace KG_Core\Notifications;

use WP_Error;

/**
 * Class NotificationQueue
 *
 * Manages notification queue operations
 */
class NotificationQueue {

	/**
	 * Email service instance
	 *
	 * @var EmailService
	 */
	private $email_service;

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
		$this->email_service   = new EmailService();
		$this->template_engine = new TemplateEngine();
	}

	/**
	 * Add notification to queue
	 *
	 * @param int    $user_id       User ID.
	 * @param string $channel       Notification channel (email, sms, push).
	 * @param string $template_key  Template identifier.
	 * @param array  $payload       Notification payload/placeholders.
	 * @param string $scheduled_at  Scheduled send time (MySQL datetime).
	 * @return int|WP_Error Queue ID on success, WP_Error on failure.
	 */
	public function add( $user_id, $channel, $template_key, $payload = array(), $scheduled_at = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'notification_queue';

		if ( null === $scheduled_at ) {
			$scheduled_at = current_time( 'mysql' );
		}

		$data = array(
			'user_id'      => $user_id,
			'channel'      => $channel,
			'template_key' => $template_key,
			'payload'      => wp_json_encode( $payload ),
			'status'       => 'pending',
			'scheduled_at' => $scheduled_at,
			'created_at'   => current_time( 'mysql' ),
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table_name, $data, $format );

		if ( false === $result ) {
			return new WP_Error( 'queue_insert_failed', 'Failed to add notification to queue' );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get pending notifications from queue
	 *
	 * @param int $limit Maximum number of notifications to retrieve.
	 * @return array Array of pending notifications.
	 */
	public function get_pending( $limit = 50 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'notification_queue';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$notifications = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} 
				WHERE status = 'pending' 
				AND scheduled_at <= %s 
				ORDER BY scheduled_at ASC 
				LIMIT %d",
				current_time( 'mysql' ),
				$limit
			),
			ARRAY_A
		);

		return $notifications ? $notifications : array();
	}

	/**
	 * Mark notification as sent
	 *
	 * @param int $queue_id Queue ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function mark_as_sent( $queue_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'notification_queue';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table_name,
			array(
				'status'  => 'sent',
				'sent_at' => current_time( 'mysql' ),
			),
			array( 'id' => $queue_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'update_failed', 'Failed to mark notification as sent' );
		}

		return true;
	}

	/**
	 * Mark notification as failed
	 *
	 * @param int    $queue_id       Queue ID.
	 * @param string $error_message  Error message.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function mark_as_failed( $queue_id, $error_message = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'notification_queue';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table_name,
			array(
				'status'        => 'failed',
				'error_message' => $error_message,
				'sent_at'       => current_time( 'mysql' ),
			),
			array( 'id' => $queue_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'update_failed', 'Failed to mark notification as failed' );
		}

		return true;
	}

	/**
	 * Process pending notifications from queue
	 *
	 * @param int $limit Maximum number of notifications to process.
	 * @return array Processing results with counts.
	 */
	public function process_queue( $limit = 50 ) {
		$notifications = $this->get_pending( $limit );

		$results = array(
			'processed' => 0,
			'sent'      => 0,
			'failed'    => 0,
		);

		foreach ( $notifications as $notification ) {
			$results['processed']++;

			$payload = json_decode( $notification['payload'], true );
			if ( ! is_array( $payload ) ) {
				$payload = array();
			}

			$result = null;

			if ( 'email' === $notification['channel'] ) {
				$result = $this->email_service->send_from_template(
					$notification['user_id'],
					$notification['template_key'],
					$payload
				);
			}

			if ( is_wp_error( $result ) ) {
				$this->mark_as_failed( $notification['id'], $result->get_error_message() );
				$results['failed']++;
			} elseif ( true === $result ) {
				$this->mark_as_sent( $notification['id'] );
				$results['sent']++;
			} else {
				$this->mark_as_failed( $notification['id'], 'Unknown error occurred' );
				$results['failed']++;
			}
		}

		return $results;
	}
}
