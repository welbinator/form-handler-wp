<?php
/**
 * AJAX form submission handler.
 *
 * Verifies nonces, sanitizes input, enforces rate limits, builds and
 * sends the email, logs the result, and returns a JSON response.
 *
 * @package Form_Handler_WP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FHW_Handler
 */
class FHW_Handler {

	/**
	 * Form registry instance.
	 *
	 * @var FHW_Form_Registry
	 */
	private $registry;

	/**
	 * Logger instance.
	 *
	 * @var FHW_Logger
	 */
	private $logger;

	/**
	 * Spam checker instance.
	 *
	 * @var FHW_Spam_Checker
	 */
	private $spam_checker;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->registry     = new FHW_Form_Registry();
		$this->logger       = new FHW_Logger();
		$this->spam_checker = new FHW_Spam_Checker();
	}

	/**
	 * Main entry point — called by fhw_generic_handler().
	 *
	 * Determines which action was submitted, looks up the form config,
	 * and runs the full pipeline.
	 */
	public function handle() {
		// Determine which action was triggered.
		// WordPress sets $_REQUEST['action'] before dispatching wp_ajax_* hooks.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( '' === $action ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'form-handler-wp' ) ), 400 );
		}

		// Look up the form config.
		$form = $this->registry->get_form( $action );
		if ( ! $form ) {
			wp_send_json_error( array( 'message' => __( 'Unknown form action.', 'form-handler-wp' ) ), 404 );
		}

		// Verify nonce.
		$nonce_key = 'fhw_' . $action . '_nonce';
		if ( ! isset( $_POST[ $nonce_key ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) ), $nonce_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'form-handler-wp' ) ), 403 );
		}

		// Honeypot check.
		if ( ! empty( $form['honeypot_field'] ) ) {
			$honeypot_value = isset( $_POST[ $form['honeypot_field'] ] ) ? sanitize_text_field( wp_unslash( $_POST[ $form['honeypot_field'] ] ) ) : '';
			if ( '' !== $honeypot_value ) {
				// Silently succeed to fool bots.
				wp_send_json_success( array( 'message' => wp_kses_post( $form['success_message'] ) ) );
			}
		}

		// Sanitize submitted fields according to schema (needed for spam check and email).
		$post_fields = $this->get_all_post_fields( $form['field_schema'] ?? array() );

		// Spam check (if enabled for this form).
		if ( '1' === ( $form['spam_filter'] ?? '1' ) ) {
			$enabled_rules = array(
				'no_user_agent'    => $form['spam_rule_no_user_agent'] ?? '1',
				'all_digits'       => $form['spam_rule_all_digits'] ?? '1',
				'no_spaces'        => $form['spam_rule_no_spaces'] ?? '1',
				'ai_greeting'      => $form['spam_rule_ai_greeting'] ?? '1',
				'buy_link'         => $form['spam_rule_buy_link'] ?? '1',
				'spammy_email_url' => $form['spam_rule_spammy_email_url'] ?? '1',
			);
			$user_agent    = isset( $_SERVER['HTTP_USER_AGENT'] )
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
				: '';
			$spam_reason   = $this->spam_checker->is_spam( $post_fields, $user_agent, $enabled_rules );
			if ( false !== $spam_reason ) {
				// Log the blocked submission.
				$submissions = new FHW_Submissions();
				$submissions->save(
					$action,
					isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
					$post_fields,
					'spam'
				);
				wp_send_json_error(
					array(
						'message' => __( 'Your submission could not be processed.', 'form-handler-wp' ),
					)
				);
			}
		}

		// Rate limiting.
		if ( ! empty( $form['rate_limit'] ) && $form['rate_limit'] > 0 ) {
			$rate_error = $this->check_rate_limit( $action, (int) $form['rate_limit'] );
			if ( is_wp_error( $rate_error ) ) {
				wp_send_json_error( array( 'message' => $rate_error->get_error_message() ), 429 );
			}
		}

		// Build subject from template.
		$subject = $this->resolve_subject( $form['subject_tpl'], $post_fields );

		// Build email body.
		$html_email = '1' === ( $form['html_email'] ?? '0' );
		$body       = $this->build_body( $post_fields, $html_email );

		// Build recipients array.
		$recipients = array();
		foreach ( explode( ',', $form['to_emails'] ) as $email ) {
			$email = sanitize_email( trim( $email ) );
			if ( is_email( $email ) ) {
				$recipients[] = array( 'email' => $email );
			}
		}

		if ( empty( $recipients ) ) {
			wp_send_json_error( array( 'message' => __( 'Form configuration error: no valid recipient.', 'form-handler-wp' ) ), 500 );
		}

		// Sender.
		$sender_email = sanitize_email( get_option( 'fhw_sender_email', get_option( 'admin_email' ) ) );
		$sender_name  = sanitize_text_field( get_option( 'fhw_sender_name', get_bloginfo( 'name' ) ) );

		// Build payload.
		$payload = array(
			'sender'  => array(
				'name'  => $sender_name,
				'email' => $sender_email,
			),
			'to'      => $recipients,
			'subject' => $subject,
		);

		// Reply-To.
		if ( ! empty( $form['reply_to_field'] ) ) {
			$reply_to_email = '';
			// Look for the value in the sanitized POST data.
			foreach ( $post_fields as $key => $value ) {
				if ( $form['reply_to_field'] === $key ) {
					$reply_to_email = sanitize_email( $value );
					break;
				}
			}
			if ( is_email( $reply_to_email ) ) {
				$payload['replyTo'] = array( 'email' => $reply_to_email );
			}
		}

		if ( $html_email ) {
			$payload['htmlContent'] = $body;
		} else {
			$payload['textContent'] = $body;
		}

		// Send.
		$brevo  = new FHW_Brevo_API();
		$result = $brevo->send( $payload );

		// Record submission before sending JSON response so it's always saved.
		$email_status = is_wp_error( $result ) ? 'failed' : 'sent';
		$submissions  = new FHW_Submissions();
		$submissions->save(
			$action,
			isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			$post_fields,
			$email_status
		);

		if ( is_wp_error( $result ) ) {
			$this->logger->log(
				implode( ', ', wp_list_pluck( $recipients, 'email' ) ),
				$subject,
				'failed',
				$result->get_error_message(),
				''
			);
			wp_send_json_error( array( 'message' => __( 'Your message could not be sent. Please try again later.', 'form-handler-wp' ) ), 500 );
		}

		$this->logger->log(
			implode( ', ', wp_list_pluck( $recipients, 'email' ) ),
			$subject,
			'sent',
			'',
			isset( $result['messageId'] ) ? $result['messageId'] : ''
		);

		// Send auto-reply confirmation to the submitter if enabled.
		if ( '1' === ( $form['autoreply_enabled'] ?? '0' ) && ! empty( $form['autoreply_to_field'] ) ) {
			$this->send_autoreply( $form, $post_fields );
		}

		// Record rate limit hit.
		if ( ! empty( $form['rate_limit'] ) && $form['rate_limit'] > 0 ) {
			$this->record_rate_limit( $action );
		}

		wp_send_json_success(
			array(
				'message' => '' !== $form['success_message'] ? wp_kses_post( $form['success_message'] ) : __( 'Thank you! Your message has been sent.', 'form-handler-wp' ),
			)
		);
	}

	/**
	 * Sanitize POST fields according to the form's field schema.
	 *
	 * @param array $schema Array of { field_name, field_type } objects.
	 * @return array Sanitized key=>value pairs.
	 */
	private function sanitize_fields( array $schema ) {
		$sanitized = array();

		foreach ( $schema as $field_def ) {
			$field_name = sanitize_key( $field_def['field_name'] ?? '' );
			$field_type = sanitize_key( $field_def['field_type'] ?? 'text' );

			if ( '' === $field_name ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification -- nonce already verified above.
			$raw = isset( $_POST[ $field_name ] ) ? wp_unslash( $_POST[ $field_name ] ) : '';

			switch ( $field_type ) {
				case 'email':
					$sanitized[ $field_name ] = sanitize_email( $raw );
					break;
				case 'textarea':
					$sanitized[ $field_name ] = sanitize_textarea_field( $raw );
					break;
				case 'url':
					$sanitized[ $field_name ] = esc_url_raw( $raw );
					break;
				case 'number':
					$sanitized[ $field_name ] = is_numeric( $raw ) ? $raw + 0 : '';
					break;
				case 'checkbox':
					$sanitized[ $field_name ] = ! empty( $raw ) ? '1' : '0';
					break;
				case 'text':
				default:
					$sanitized[ $field_name ] = sanitize_text_field( $raw );
					break;
			}
		}

		return $sanitized;
	}

	/**
	 * Maximum number of POST fields to include in an email.
	 *
	 * Caps the open-ended POST sweep to prevent bots from flooding the
	 * email body with dozens of junk fields.
	 *
	 * @var int
	 */
	const MAX_EMAIL_FIELDS = 30;

	/**
	 * Maximum character length for a single field value in the email body.
	 *
	 * @var int
	 */
	const MAX_FIELD_VALUE_LENGTH = 5000;

	/**
	 * Get all non-schema POST fields (for dynamic forms with no strict schema).
	 * Merges schema-sanitized fields with any extra POST data (sanitized as text).
	 *
	 * Enforces field count and value length caps to prevent email flooding.
	 *
	 * @param array $schema Form field schema.
	 * @return array
	 */
	private function get_all_post_fields( array $schema ) {
		$schema_keys = array_column( $schema, 'field_name' );
		$sanitized   = $this->sanitize_fields( $schema );

		// Also add any POST keys not in the schema (sanitized generically).
		// phpcs:ignore WordPress.Security.NonceVerification -- already verified.
		foreach ( $_POST as $key => $value ) {
			// Enforce field count cap.
			if ( count( $sanitized ) >= self::MAX_EMAIL_FIELDS ) {
				break;
			}

			$key = sanitize_key( $key );
			// Skip WordPress internals and nonces.
			if ( in_array( $key, array( 'action', 'nonce' ), true ) || false !== strpos( $key, '_nonce' ) ) {
				continue;
			}
			if ( ! in_array( $key, $schema_keys, true ) && ! isset( $sanitized[ $key ] ) ) {
				$sanitized[ $key ] = sanitize_text_field( wp_unslash( $value ) );
			}
		}

		// Enforce value length cap on all fields.
		foreach ( $sanitized as $key => $value ) {
			if ( is_string( $value ) && strlen( $value ) > self::MAX_FIELD_VALUE_LENGTH ) {
				$sanitized[ $key ] = substr( $value, 0, self::MAX_FIELD_VALUE_LENGTH );
			}
		}

		return $sanitized;
	}

	/**
	 * Resolve subject template placeholders.
	 *
	 * Supported: {field_name} for any posted field, {site_name} for the blog name.
	 *
	 * @param string $tpl    Subject template.
	 * @param array  $fields Sanitized field values.
	 * @return string Resolved subject.
	 */
	private function resolve_subject( $tpl, array $fields ) {
		$subject = $tpl;

		// Replace {site_name}.
		$subject = str_replace( '{site_name}', sanitize_text_field( get_bloginfo( 'name' ) ), $subject );

		// Replace {field_name} tokens.
		foreach ( $fields as $key => $value ) {
			$subject = str_replace( '{' . $key . '}', sanitize_text_field( (string) $value ), $subject );
		}

		// Strip any remaining {tokens} to prevent header injection.
		$subject = preg_replace( '/\{[^}]+\}/', '', $subject );

		// Final sanitize to prevent header injection.
		$subject = sanitize_text_field( $subject );
		$subject = str_replace( array( "\r", "\n" ), ' ', $subject );

		return $subject;
	}

	/**
	 * Build the email body from submitted fields.
	 *
	 * @param array $fields    Sanitized field values.
	 * @param bool  $html_mode Whether to build HTML or plain text.
	 * @return string
	 */
	private function build_body( array $fields, $html_mode ) {
		if ( $html_mode ) {
			$rows = '';
			foreach ( $fields as $key => $value ) {
				// Skip internal keys.
				if ( in_array( $key, array( 'action' ), true ) || false !== strpos( $key, '_nonce' ) ) {
					continue;
				}
				$label = esc_html( ucwords( str_replace( array( '_', '-' ), ' ', $key ) ) );
				$val   = nl2br( esc_html( (string) $value ) );
				$rows .= "<tr><th style=\"text-align:left;padding:6px 12px;background:#f5f5f5;\">{$label}</th><td style=\"padding:6px 12px;\">{$val}</td></tr>\n";
			}
			return '<table style="border-collapse:collapse;width:100%;font-family:sans-serif;">' . $rows . '</table>';
		}

		// Plain text.
		$lines = array();
		foreach ( $fields as $key => $value ) {
			if ( in_array( $key, array( 'action' ), true ) || false !== strpos( $key, '_nonce' ) ) {
				continue;
			}
			$label   = ucwords( str_replace( array( '_', '-' ), ' ', $key ) );
			$lines[] = $label . ': ' . (string) $value;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Check whether the current IP has exceeded the rate limit.
	 *
	 * @param string $action     Form action name.
	 * @param int    $max_per_hr Maximum submissions per hour.
	 * @return true|WP_Error True if under limit, WP_Error if exceeded.
	 */
	private function check_rate_limit( $action, $max_per_hr ) {
		$ip            = $this->get_client_ip();
		$transient_key = 'fhw_rl_' . md5( $action . '_' . $ip );
		$count         = (int) get_transient( $transient_key );

		if ( $count >= $max_per_hr ) {
			return new WP_Error(
				'fhw_rate_limit',
				__( 'You have submitted this form too many times. Please try again later.', 'form-handler-wp' )
			);
		}

		return true;
	}

	/**
	 * Record a form submission for rate limiting.
	 *
	 * @param string $action Form action name.
	 */
	private function record_rate_limit( $action ) {
		$ip            = $this->get_client_ip();
		$transient_key = 'fhw_rl_' . md5( $action . '_' . $ip );
		$count         = (int) get_transient( $transient_key );
		set_transient( $transient_key, $count + 1, HOUR_IN_SECONDS );
	}

	/**
	 * Send an auto-reply confirmation email to the form submitter.
	 *
	 * @param array $form        Form config.
	 * @param array $post_fields Sanitized submitted field values.
	 */
	private function send_autoreply( array $form, array $post_fields ) {
		$to_field = $form['autoreply_to_field'];
		$to_email = '';

		foreach ( $post_fields as $key => $value ) {
			if ( $to_field === $key ) {
				$to_email = sanitize_email( $value );
				break;
			}
		}

		if ( ! is_email( $to_email ) ) {
			return;
		}

		$sender_email = sanitize_email( get_option( 'fhw_sender_email', get_option( 'admin_email' ) ) );
		$sender_name  = sanitize_text_field( get_option( 'fhw_sender_name', get_bloginfo( 'name' ) ) );

		// Resolve subject placeholders.
		$subject = ! empty( $form['autoreply_subject'] )
			? $this->resolve_subject( $form['autoreply_subject'], $post_fields )
			: $this->resolve_subject(
				/* translators: %s: site name placeholder token */
				__( 'Thanks for contacting {site_name}!', 'form-handler-wp' ),
				$post_fields
			);

		// Build body: use configured message or a generic fallback.
		$html_email = '1' === ( $form['html_email'] ?? '0' );

		if ( ! empty( $form['autoreply_message'] ) ) {
			// Replace placeholders in the custom message.
			$body = $form['autoreply_message'];
			$body = str_replace( '{site_name}', esc_html( get_bloginfo( 'name' ) ), $body );
			foreach ( $post_fields as $key => $value ) {
				$body = str_replace( '{' . $key . '}', esc_html( (string) $value ), $body );
			}
			$body = preg_replace( '/\{[^}]+\}/', '', $body );
			if ( ! $html_email ) {
				$body = wp_strip_all_tags( $body );
			}
		} else {
			$site_name = sanitize_text_field( get_bloginfo( 'name' ) );
			if ( $html_email ) {
				$body = '<p>' . sprintf(
					/* translators: %s: site name */
					esc_html__( 'Thank you for reaching out to %s. We have received your message and will get back to you shortly.', 'form-handler-wp' ),
					esc_html( $site_name )
				) . '</p>';
			} else {
				$body = sprintf(
					/* translators: %s: site name */
					__( 'Thank you for reaching out to %s. We have received your message and will get back to you shortly.', 'form-handler-wp' ),
					$site_name
				);
			}
		}

		$payload = array(
			'sender'  => array(
				'name'  => $sender_name,
				'email' => $sender_email,
			),
			'to'      => array( array( 'email' => $to_email ) ),
			'subject' => $subject,
		);

		if ( $html_email ) {
			$payload['htmlContent'] = $body;
		} else {
			$payload['textContent'] = $body;
		}

		$brevo  = new FHW_Brevo_API();
		$result = $brevo->send( $payload );

		// Log the auto-reply (best-effort — don't fail the main request).
		$this->logger->log(
			$to_email,
			'[auto-reply] ' . $subject,
			is_wp_error( $result ) ? 'failed' : 'sent',
			is_wp_error( $result ) ? $result->get_error_message() : '',
			! is_wp_error( $result ) && isset( $result['messageId'] ) ? $result['messageId'] : ''
		);
	}

	/**
	 * Get the client IP address.
	 *
	 * Uses only REMOTE_ADDR by default, which cannot be spoofed by the client.
	 * Proxy headers (X-Forwarded-For etc.) are intentionally ignored because
	 * they are trivially spoofable and would allow bots to bypass rate limiting
	 * by rotating fake header values.
	 *
	 * If your site is behind a trusted reverse proxy (e.g. Nginx, Cloudflare,
	 * a load balancer) and you need real visitor IPs, define the constant:
	 *   define( 'FHW_TRUSTED_PROXY', true );
	 * in wp-config.php. When set, the rightmost IP in X-Forwarded-For is used
	 * (the rightmost is appended by the proxy itself and cannot be faked).
	 *
	 * @return string Validated IP address, or '0.0.0.0' as fallback.
	 */
	private function get_client_ip() {
		// Always start with REMOTE_ADDR — the only unspoofable value.
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		// Only trust forwarded headers when the site owner has explicitly
		// declared they are behind a trusted proxy.
		if ( defined( 'FHW_TRUSTED_PROXY' ) && FHW_TRUSTED_PROXY
			&& ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			// X-Forwarded-For: client, proxy1, proxy2
			// The rightmost IP is appended by the trusted proxy — use that.
			$parts     = array_map( 'trim', explode( ',', $forwarded ) );
			$rightmost = end( $parts );
			if ( filter_var( $rightmost, FILTER_VALIDATE_IP ) ) {
				return $rightmost;
			}
		}

		if ( filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
			return $remote_addr;
		}

		return '0.0.0.0';
	}
}
