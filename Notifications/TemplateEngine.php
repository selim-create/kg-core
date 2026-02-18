<?php
/**
 * Template Engine for Email Notifications
 *
 * @package KG_Core
 * @subpackage Notifications
 */

namespace KG_Core\Notifications;

use WP_Error;

/**
 * Class TemplateEngine
 *
 * Handles email template rendering and placeholder replacement
 */
class TemplateEngine {

	/**
	 * Render a template with the given placeholders
	 *
	 * @param string $template_key  The template identifier.
	 * @param array  $placeholders  Associative array of placeholder values.
	 * @return array|WP_Error Array with 'subject', 'body_html', 'body_text' or WP_Error on failure.
	 */
	public function render( $template_key, $placeholders = array() ) {
		$template = $this->get_template( $template_key );

		if ( is_wp_error( $template ) ) {
			return $template;
		}

		$rendered = array(
			'subject'   => $this->replace_placeholders( $template['subject'], $placeholders ),
			'body_html' => $this->replace_placeholders( $template['body_html'], $placeholders ),
			'body_text' => $this->replace_placeholders( $template['body_text'], $placeholders ),
			'category'  => $template['category'] ?? 'system',
		);

		return $rendered;
	}

	/**
	 * Get template from database
	 *
	 * @param string $template_key  The template identifier.
	 * @return array|WP_Error Template data or WP_Error on failure.
	 */
	public function get_template( $template_key ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'kg_email_templates';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$template = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT template_key, subject, body_html, body_text, category, is_active 
				FROM {$table_name} 
				WHERE template_key = %s AND is_active = 1 
				LIMIT 1",
				$template_key
			),
			ARRAY_A
		);

		if ( ! $template ) {
			return new WP_Error(
				'template_not_found',
				sprintf( 'Template %s not found or is not active', $template_key )
			);
		}

		return $template;
	}

	/**
	 * Replace placeholders in content
	 *
	 * Replaces {{placeholder}} patterns with actual values
	 *
	 * @param string $content       The content with placeholders.
	 * @param array  $placeholders  Associative array of placeholder values.
	 * @return string Content with replaced placeholders.
	 */
	public function replace_placeholders( $content, $placeholders = array() ) {
		if ( empty( $content ) ) {
			return '';
		}

		if ( empty( $placeholders ) ) {
			return $content;
		}

		foreach ( $placeholders as $key => $value ) {
			$placeholder = '{{' . $key . '}}';
			$content     = str_replace( $placeholder, $value, $content );
		}

		return $content;
	}
}
