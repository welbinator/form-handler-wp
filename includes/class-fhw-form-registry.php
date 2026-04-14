<?php
/**
 * Form registry: CRUD for registered form handlers stored as a serialized option.
 *
 * @package Form_Handler_WP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FHW_Form_Registry
 */
class FHW_Form_Registry {

	/**
	 * Option name where forms are stored.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'fhw_registered_forms';

	/**
	 * Get all registered forms.
	 *
	 * @return array Indexed array of form config arrays.
	 */
	public function get_forms() {
		$forms = get_option( self::OPTION_KEY, array() );
		return is_array( $forms ) ? $forms : array();
	}

	/**
	 * Get a single form config by action name.
	 *
	 * @param string $action_name The unique action name (slug).
	 * @return array|false Form config or false if not found.
	 */
	public function get_form( $action_name ) {
		$action_name = sanitize_key( $action_name );
		$forms       = $this->get_forms();

		foreach ( $forms as $form ) {
			if ( isset( $form['action_name'] ) && $action_name === $form['action_name'] ) {
				return $form;
			}
		}

		return false;
	}

	/**
	 * Add a new form handler.
	 *
	 * @param array $data Raw form data from $_POST.
	 * @return true|WP_Error True on success, WP_Error on validation failure.
	 */
	public function add_form( array $data ) {
		$sanitized = $this->sanitize_form_data( $data );
		$error     = $this->validate_form_data( $sanitized );

		if ( is_wp_error( $error ) ) {
			return $error;
		}

		// Ensure action name is unique.
		if ( false !== $this->get_form( $sanitized['action_name'] ) ) {
			return new WP_Error(
				'fhw_duplicate_action',
				__( 'A form handler with that action name already exists.', 'form-handler-wp' )
			);
		}

		$forms   = $this->get_forms();
		$forms[] = $sanitized;
		update_option( self::OPTION_KEY, $forms );

		return true;
	}

	/**
	 * Update an existing form handler.
	 *
	 * @param string $original_action The existing action name (used as lookup key).
	 * @param array  $data            New form data.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function update_form( $original_action, array $data ) {
		$original_action = sanitize_key( $original_action );
		$sanitized       = $this->sanitize_form_data( $data );
		$error           = $this->validate_form_data( $sanitized );

		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$forms = $this->get_forms();
		$found = false;

		foreach ( $forms as $index => $form ) {
			if ( isset( $form['action_name'] ) && $original_action === $form['action_name'] ) {
				// If action name changed, verify uniqueness.
				if ( $sanitized['action_name'] !== $original_action ) {
					if ( false !== $this->get_form( $sanitized['action_name'] ) ) {
						return new WP_Error(
							'fhw_duplicate_action',
							__( 'A form handler with that action name already exists.', 'form-handler-wp' )
						);
					}
				}
				$forms[ $index ] = $sanitized;
				$found           = true;
				break;
			}
		}

		if ( ! $found ) {
			return new WP_Error( 'fhw_not_found', __( 'Form handler not found.', 'form-handler-wp' ) );
		}

		update_option( self::OPTION_KEY, $forms );
		return true;
	}

	/**
	 * Delete a form handler by action name.
	 *
	 * @param string $action_name The action name to delete.
	 * @return bool True if deleted, false if not found.
	 */
	public function delete_form( $action_name ) {
		$action_name = sanitize_key( $action_name );
		$forms       = $this->get_forms();
		$new_forms   = array();
		$deleted     = false;

		foreach ( $forms as $form ) {
			if ( isset( $form['action_name'] ) && $action_name === $form['action_name'] ) {
				$deleted = true;
				continue;
			}
			$new_forms[] = $form;
		}

		if ( $deleted ) {
			update_option( self::OPTION_KEY, array_values( $new_forms ) );
		}

		return $deleted;
	}

	/**
	 * Sanitize raw form submission data.
	 *
	 * @param array $data Raw POST data.
	 * @return array Sanitized form config.
	 */
	public function sanitize_form_data( array $data ) {
		$sanitized = array(
			'action_name'        => sanitize_key( $data['action_name'] ?? '' ),
			'to_emails'          => sanitize_text_field( $data['to_emails'] ?? '' ),
			'subject_tpl'        => sanitize_text_field( $data['subject_tpl'] ?? '' ),
			'reply_to_field'     => sanitize_key( $data['reply_to_field'] ?? '' ),
			'success_message'    => wp_kses_post( $data['success_message'] ?? '' ),
			'html_email'         => ! empty( $data['html_email'] ) ? '1' : '0',
			'honeypot_field'     => sanitize_key( $data['honeypot_field'] ?? '' ),
			'rate_limit'         => absint( $data['rate_limit'] ?? 0 ),
			'page_url'           => esc_url_raw( $data['page_url'] ?? '' ),
			'status'             => 'active',
			'field_schema'       => array(),
			// Auto-reply fields.
			'autoreply_enabled'  => ! empty( $data['autoreply_enabled'] ) ? '1' : '0',
			'autoreply_to_field' => sanitize_key( $data['autoreply_to_field'] ?? '' ),
			'autoreply_subject'  => sanitize_text_field( $data['autoreply_subject'] ?? '' ),
			'autoreply_message'  => wp_kses_post( $data['autoreply_message'] ?? '' ),
		);

		// Sanitize field schema (repeatable rows).
		if ( ! empty( $data['field_schema'] ) && is_array( $data['field_schema'] ) ) {
			$allowed_types = array( 'text', 'email', 'textarea', 'url', 'number', 'checkbox' );
			foreach ( $data['field_schema'] as $field ) {
				$field_name = sanitize_key( $field['field_name'] ?? '' );
				$field_type = sanitize_key( $field['field_type'] ?? 'text' );
				if ( '' === $field_name ) {
					continue;
				}
				if ( ! in_array( $field_type, $allowed_types, true ) ) {
					$field_type = 'text';
				}
				$sanitized['field_schema'][] = array(
					'field_name' => $field_name,
					'field_type' => $field_type,
				);
			}
		}

		return $sanitized;
	}

	/**
	 * Validate a sanitized form config.
	 *
	 * @param array $data Sanitized form config.
	 * @return true|WP_Error
	 */
	private function validate_form_data( array $data ) {
		if ( '' === $data['action_name'] ) {
			return new WP_Error( 'fhw_invalid_action', __( 'Action name is required and must be a valid slug (letters, numbers, underscores).', 'form-handler-wp' ) );
		}

		if ( '' === $data['to_emails'] ) {
			return new WP_Error( 'fhw_invalid_to', __( 'At least one recipient email is required.', 'form-handler-wp' ) );
		}

		// Validate each recipient email.
		$emails = explode( ',', $data['to_emails'] );
		foreach ( $emails as $email ) {
			if ( ! is_email( trim( $email ) ) ) {
				return new WP_Error(
					'fhw_invalid_email',
					sprintf(
						/* translators: %s: invalid email address */
						__( '"%s" is not a valid email address.', 'form-handler-wp' ),
						esc_html( trim( $email ) )
					)
				);
			}
		}

		if ( '' === $data['subject_tpl'] ) {
			return new WP_Error( 'fhw_invalid_subject', __( 'Subject template is required.', 'form-handler-wp' ) );
		}

		return true;
	}
}
