<?php
/**
 * Brevo Transactional Email API sender.
 *
 * Sends email via the Brevo (formerly Sendinblue) v3 API using an API key.
 * Implements FHW_Mailer so it can be swapped for other providers.
 *
 * @package Form_Handler_WP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FHW_Brevo_API
 */
class FHW_Brevo_API implements FHW_Mailer {

	/**
	 * Brevo SMTP API endpoint.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

	/**
	 * Get the Brevo API key.
	 *
	 * Priority: wp-config constant → encrypted option.
	 *
	 * @return string API key or empty string if not configured.
	 */
	private function get_api_key() {
		// Check for constant defined in wp-config.php first.
		if ( defined( 'FHW_BREVO_API_KEY' ) ) {
			$key = (string) constant( 'FHW_BREVO_API_KEY' );
			if ( '' !== $key ) {
				return $key;
			}
		}

		// Fall back to option (stored base64-encoded for light obfuscation).
		$stored = get_option( 'fhw_brevo_api_key_enc', '' );
		if ( '' !== $stored ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			return base64_decode( $stored );
		}

		return '';
	}

	/**
	 * Send an email via the Brevo API.
	 *
	 * @param array $payload Normalized email payload (see FHW_Mailer interface).
	 * @return array|WP_Error Parsed response body array on success, WP_Error on failure.
	 */
	public function send( array $payload ) {
		$api_key = $this->get_api_key();

		if ( '' === $api_key ) {
			return new WP_Error( 'fhw_no_api_key', __( 'Brevo API key is not configured.', 'form-handler-wp' ) );
		}

		// Build request body — only include keys that are set.
		$body = array();

		if ( ! empty( $payload['sender'] ) ) {
			$body['sender'] = array(
				'name'  => sanitize_text_field( $payload['sender']['name'] ),
				'email' => sanitize_email( $payload['sender']['email'] ),
			);
		}

		if ( ! empty( $payload['to'] ) && is_array( $payload['to'] ) ) {
			$body['to'] = array();
			foreach ( $payload['to'] as $recipient ) {
				$entry = array( 'email' => sanitize_email( $recipient['email'] ) );
				if ( ! empty( $recipient['name'] ) ) {
					$entry['name'] = sanitize_text_field( $recipient['name'] );
				}
				$body['to'][] = $entry;
			}
		}

		if ( ! empty( $payload['replyTo'] ) ) {
			$body['replyTo'] = array(
				'email' => sanitize_email( $payload['replyTo']['email'] ),
			);
			if ( ! empty( $payload['replyTo']['name'] ) ) {
				$body['replyTo']['name'] = sanitize_text_field( $payload['replyTo']['name'] );
			}
		}

		if ( ! empty( $payload['subject'] ) ) {
			$body['subject'] = sanitize_text_field( $payload['subject'] );
		}

		if ( ! empty( $payload['htmlContent'] ) ) {
			// Allow HTML in email body — this is the intended use.
			$body['htmlContent'] = wp_kses_post( $payload['htmlContent'] );
		} elseif ( ! empty( $payload['textContent'] ) ) {
			$body['textContent'] = sanitize_textarea_field( $payload['textContent'] );
		}

		if ( ! empty( $payload['params'] ) && is_array( $payload['params'] ) ) {
			// Params are template variables; sanitize each value.
			$sanitized_params = array();
			foreach ( $payload['params'] as $key => $value ) {
				$sanitized_params[ sanitize_key( $key ) ] = sanitize_text_field( $value );
			}
			$body['params'] = $sanitized_params;
		}

		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'timeout' => 30,
				'headers' => array(
					'api-key'      => $api_key,
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
					'User-Agent'   => 'sendinblue_plugins/wordpress',
					'sib-plugin'   => 'wp-' . FHW_VERSION,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$parsed        = json_decode( $response_body, true );

		// Handle rate limiting.
		if ( 429 === $status_code ) {
			$retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
			if ( ! empty( $retry_after ) ) {
				/* translators: %s: number of seconds to wait before retrying */
				$message = sprintf( __( 'Brevo API rate limit exceeded. Retry after %s seconds.', 'form-handler-wp' ), (int) $retry_after );
			} else {
				$message = __( 'Brevo API rate limit exceeded. Please try again later.', 'form-handler-wp' );
			}
			return new WP_Error( 'fhw_rate_limit', $message );
		}

		// 2xx = success.
		if ( $status_code >= 200 && $status_code < 300 ) {
			return is_array( $parsed ) ? $parsed : array( 'messageId' => '' );
		}

		// Extract error message from Brevo response body.
		$error_message = '';
		if ( is_array( $parsed ) && ! empty( $parsed['message'] ) ) {
			$error_message = sanitize_text_field( $parsed['message'] );
		}

		if ( '' === $error_message ) {
			/* translators: %d: HTTP status code */
			$error_message = sprintf( __( 'Brevo API request failed with status %d.', 'form-handler-wp' ), (int) $status_code );
		}

		// Log raw error for admins.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( 'Form Handler WP — Brevo API error (' . (int) $status_code . '): ' . $error_message );

		return new WP_Error( 'fhw_api_error', $error_message );
	}

	/**
	 * Send a test email to verify API configuration.
	 *
	 * @param string $to_email Recipient email address.
	 * @return array|WP_Error
	 */
	public function send_test( $to_email ) {
		$sender_email = sanitize_email( get_option( 'fhw_sender_email', get_option( 'admin_email' ) ) );
		$sender_name  = sanitize_text_field( get_option( 'fhw_sender_name', get_bloginfo( 'name' ) ) );
		$site_name    = get_bloginfo( 'name' );

		$payload = array(
			'sender'      => array(
				'name'  => $sender_name,
				'email' => $sender_email,
			),
			'to'          => array(
				array( 'email' => sanitize_email( $to_email ) ),
			),
			'subject'     => sprintf(
				/* translators: %s: site name */
				__( 'Form Handler WP test email from %s', 'form-handler-wp' ),
				$site_name
			),
			'htmlContent' => '<p>' . sprintf(
				/* translators: %s: site name */
				esc_html__( 'This is a test email sent by Form Handler WP from %s. If you received this, your Brevo API key is working correctly.', 'form-handler-wp' ),
				esc_html( $site_name )
			) . '</p>',
		);

		return $this->send( $payload );
	}
}
