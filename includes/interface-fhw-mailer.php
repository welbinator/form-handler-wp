<?php
/**
 * Mailer interface.
 *
 * All mailer classes must implement this interface so they can be swapped
 * out via the mailer factory without changing any calling code.
 *
 * @package Form_Handler_WP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface FHW_Mailer
 *
 * Implement this interface to add support for additional email providers
 * (e.g. SendGrid, Mailgun, Resend, Postmark).
 */
interface FHW_Mailer {

	/**
	 * Send an email.
	 *
	 * @param array $payload {
	 *     Normalized email payload.
	 *
	 *     @type array  $sender      { name: string, email: string }
	 *     @type array  $to          Array of { email: string, name?: string }
	 *     @type array  $replyTo     Optional. { email: string, name?: string }
	 *     @type string $subject     Email subject line.
	 *     @type string $htmlContent HTML body (use this OR textContent).
	 *     @type string $textContent Plain-text body (use this OR htmlContent).
	 *     @type array  $params      Optional. Key/value template variables for the provider.
	 * }
	 * @return array|WP_Error Parsed response body on success, WP_Error on failure.
	 */
	public function send( array $payload );
}
