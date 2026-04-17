<?php
/**
 * Mailchimp integration.
 *
 * Subscribes form submitters to a Mailchimp audience via the
 * Mailchimp Marketing API v3.
 *
 * @package Form_Handler_WP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FHW_Integration_Mailchimp
 */
class FHW_Integration_Mailchimp implements FHW_Integration {

	/**
	 * Transient TTL for cached audience lists (5 minutes).
	 *
	 * @var int
	 */
	const CACHE_TTL = 300;

	/**
	 * Maximum tag length enforced before sending to API.
	 *
	 * Mailchimp's documented limit is 100 characters.
	 *
	 * @var int
	 */
	const MAX_TAG_LENGTH = 100;

	// -----------------------------------------------------------------------
	// Interface: identity
	// -----------------------------------------------------------------------

	/**
	 * Return the integration id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'mailchimp';
	}

	/**
	 * Return the human-readable label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return 'Mailchimp';
	}

	// -----------------------------------------------------------------------
	// Interface: connection status
	// -----------------------------------------------------------------------

	/**
	 * Return true when valid credentials are saved.
	 *
	 * @return bool
	 */
	public function is_connected(): bool {
		return '' !== $this->get_api_key();
	}

	// -----------------------------------------------------------------------
	// Interface: global settings fields
	// -----------------------------------------------------------------------

	/**
	 * Return global settings field definitions.
	 *
	 * @return array
	 */
	public function get_settings_fields(): array {
		return array(
			array(
				'key'         => 'fhw_mailchimp_api_key',
				'label'       => __( 'API Key', 'form-handler-wp' ),
				'type'        => 'password',
				'description' => __( 'Find your API key in Mailchimp → Account → Extras → API Keys.', 'form-handler-wp' ),
			),
		);
	}

	/**
	 * Persist global settings from POST data.
	 *
	 * @param array $post Raw POST data (nonce already verified by caller).
	 */
	public function save_settings( array $post ): void {
		if ( isset( $post['fhw_mailchimp_api_key'] ) ) {
			$key = sanitize_text_field( wp_unslash( $post['fhw_mailchimp_api_key'] ) );
			// Only update if a new non-placeholder value was posted.
			if ( '' !== $key && '••••••••' !== $key ) {
				$encrypted = FHW_Crypto::encrypt( $key );
				if ( false !== $encrypted ) {
					update_option( 'fhw_mailchimp_api_key_enc', $encrypted );
				}
			} elseif ( '' === $key ) {
				// Clearing the key — delete it.
				delete_option( 'fhw_mailchimp_api_key_enc' );
			}
		}
	}

	// -----------------------------------------------------------------------
	// Interface: per-form fields
	// -----------------------------------------------------------------------

	/**
	 * Return per-form field definitions.
	 *
	 * @return array
	 */
	public function get_form_fields(): array {
		return array(
			array(
				'key'         => 'mailchimp_list_id',
				'label'       => __( 'Audience', 'form-handler-wp' ),
				'type'        => 'remote_select',
				'remote_key'  => 'audiences',
				'description' => __( 'Which Mailchimp audience to subscribe to.', 'form-handler-wp' ),
			),
			array(
				'key'         => 'mailchimp_email_field',
				'label'       => __( 'Email field', 'form-handler-wp' ),
				'type'        => 'field_map',
				'description' => __( 'Which submitted field contains the subscriber\'s email address.', 'form-handler-wp' ),
			),
			array(
				'key'         => 'mailchimp_fname_field',
				'label'       => __( 'First name field', 'form-handler-wp' ),
				'type'        => 'field_map',
				'required'    => false,
				'description' => __( 'Optional.', 'form-handler-wp' ),
			),
			array(
				'key'         => 'mailchimp_lname_field',
				'label'       => __( 'Last name field', 'form-handler-wp' ),
				'type'        => 'field_map',
				'required'    => false,
				'description' => __( 'Optional.', 'form-handler-wp' ),
			),
			array(
				'key'         => 'mailchimp_tags',
				'label'       => __( 'Tags', 'form-handler-wp' ),
				'type'        => 'text',
				'description' => __( 'Comma-separated tags to apply to the subscriber.', 'form-handler-wp' ),
			),
			array(
				'key'         => 'mailchimp_phone_field',
				'label'       => __( 'Phone field', 'form-handler-wp' ),
				'type'        => 'field_map',
				'required'    => false,
				'description' => __( 'Optional. Maps to Mailchimp PHONE merge field.', 'form-handler-wp' ),
			),
			array(
				'key'         => 'mailchimp_addr1_field',
				'label'       => __( 'Street address field', 'form-handler-wp' ),
				'type'        => 'field_map',
				'required'    => false,
				'description' => __( 'Optional. Street address (addr1).', 'form-handler-wp' ),
			),
			array(
				'key'         => 'mailchimp_addr2_field',
				'label'       => __( 'Address line 2 field', 'form-handler-wp' ),
				'type'        => 'field_map',
				'required'    => false,
				'description' => __( 'Optional.', 'form-handler-wp' ),
			),
			array(
				'key'         => 'mailchimp_city_field',
				'label'       => __( 'City field', 'form-handler-wp' ),
				'type'        => 'field_map',
				'required'    => false,
				'description' => __( 'Optional.', 'form-handler-wp' ),
			),
			array(
				'key'         => 'mailchimp_state_field',
				'label'       => __( 'State / Province / Region field', 'form-handler-wp' ),
				'type'        => 'field_map',
				'required'    => false,
				'description' => __( 'Optional.', 'form-handler-wp' ),
			),
			array(
				'key'         => 'mailchimp_zip_field',
				'label'       => __( 'Postal / Zip code field', 'form-handler-wp' ),
				'type'        => 'field_map',
				'required'    => false,
				'description' => __( 'Optional.', 'form-handler-wp' ),
			),
			array(
				'key'         => 'mailchimp_country_field',
				'label'       => __( 'Country field', 'form-handler-wp' ),
				'type'        => 'field_map',
				'required'    => false,
				'description' => __( 'Optional. Use 2-letter country code (e.g. US).', 'form-handler-wp' ),
			),
			array(
				'key'         => 'mailchimp_optin_field',
				'label'       => __( 'Opt-in field', 'form-handler-wp' ),
				'type'        => 'field_map',
				'required'    => false,
				'description' => __( 'Optional. Only subscribe if this field equals "1" (e.g. a newsletter checkbox).', 'form-handler-wp' ),
			),
		);
	}

	/**
	 * Fetch remote select options (e.g. audience list from API).
	 *
	 * @param string $field_key The field key requesting options.
	 * @return array
	 */
	public function get_remote_options( string $field_key ): array {
		if ( 'audiences' !== $field_key ) {
			return array();
		}

		$cache_key = 'fhw_mailchimp_audiences';
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$api_key = $this->get_api_key();
		if ( '' === $api_key ) {
			return array();
		}

		$dc = $this->get_datacenter( $api_key );
		if ( '' === $dc ) {
			return array();
		}

		$response = wp_remote_get(
			'https://' . $dc . '.api.mailchimp.com/3.0/lists?count=200&fields=lists.id,lists.name',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( 'anystring:' . $api_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'Content-Type'  => 'application/json',
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['lists'] ) || ! is_array( $body['lists'] ) ) {
			return array();
		}

		$options = array();
		foreach ( $body['lists'] as $list ) {
			if ( empty( $list['id'] ) || empty( $list['name'] ) ) {
				continue;
			}
			$options[] = array(
				'value' => sanitize_text_field( $list['id'] ),
				'label' => sanitize_text_field( $list['name'] ),
			);
		}

		set_transient( $cache_key, $options, self::CACHE_TTL );
		return $options;
	}

	// -----------------------------------------------------------------------
	// Interface: run
	// -----------------------------------------------------------------------

	/**
	 * Execute the integration after a successful form submission.
	 *
	 * @param array $form        Registered form config.
	 * @param array $post_fields Sanitized submitted field values.
	 */
	public function run( array $form, array $post_fields ): void {
		$api_key = $this->get_api_key();
		if ( '' === $api_key ) {
			return;
		}

		// Validate list ID — must be alphanumeric (Mailchimp IDs are hex strings).
		$list_id = sanitize_text_field( $form['mailchimp_list_id'] ?? '' );
		if ( '' === $list_id || ! preg_match( '/^[a-f0-9]+$/i', $list_id ) ) {
			return;
		}

		// Opt-in gate.
		$optin_field = sanitize_key( $form['mailchimp_optin_field'] ?? '' );
		if ( '' !== $optin_field ) {
			if ( '1' !== ( $post_fields[ $optin_field ] ?? '' ) ) {
				return;
			}
		}

		// Email.
		$email_field = sanitize_key( $form['mailchimp_email_field'] ?? '' );
		$email       = sanitize_email( $post_fields[ $email_field ] ?? '' );
		if ( ! is_email( $email ) ) {
			return;
		}

		// Merge fields.
		$merge_fields = array();
		$fname_field  = sanitize_key( $form['mailchimp_fname_field'] ?? '' );
		$lname_field  = sanitize_key( $form['mailchimp_lname_field'] ?? '' );
		if ( '' !== $fname_field && ! empty( $post_fields[ $fname_field ] ) ) {
			$merge_fields['FNAME'] = sanitize_text_field( $post_fields[ $fname_field ] );
		}
		if ( '' !== $lname_field && ! empty( $post_fields[ $lname_field ] ) ) {
			$merge_fields['LNAME'] = sanitize_text_field( $post_fields[ $lname_field ] );
		}

		// Phone.
		$phone_field = sanitize_key( $form['mailchimp_phone_field'] ?? '' );
		if ( '' !== $phone_field && ! empty( $post_fields[ $phone_field ] ) ) {
			$merge_fields['PHONE'] = sanitize_text_field( $post_fields[ $phone_field ] );
		}

		// Address — only send if at least addr1 is present.
		$addr1_field   = sanitize_key( $form['mailchimp_addr1_field'] ?? '' );
		$addr2_field   = sanitize_key( $form['mailchimp_addr2_field'] ?? '' );
		$city_field    = sanitize_key( $form['mailchimp_city_field'] ?? '' );
		$state_field   = sanitize_key( $form['mailchimp_state_field'] ?? '' );
		$zip_field     = sanitize_key( $form['mailchimp_zip_field'] ?? '' );
		$country_field = sanitize_key( $form['mailchimp_country_field'] ?? '' );

		$addr1 = '' !== $addr1_field ? sanitize_text_field( $post_fields[ $addr1_field ] ?? '' ) : '';
		if ( '' !== $addr1 ) {
			// Country: enforce 2-char uppercase alpha code; fall back to 'US'.
			$country_raw = '' !== $country_field ? sanitize_text_field( $post_fields[ $country_field ] ?? '' ) : '';
			$country     = preg_match( '/^[a-zA-Z]{2}$/', $country_raw ) ? strtoupper( $country_raw ) : 'US';

			$merge_fields['ADDRESS'] = array(
				'addr1'   => $addr1,
				'addr2'   => '' !== $addr2_field ? sanitize_text_field( $post_fields[ $addr2_field ] ?? '' ) : '',
				'city'    => '' !== $city_field ? sanitize_text_field( $post_fields[ $city_field ] ?? '' ) : '',
				'state'   => '' !== $state_field ? sanitize_text_field( $post_fields[ $state_field ] ?? '' ) : '',
				'zip'     => '' !== $zip_field ? sanitize_text_field( $post_fields[ $zip_field ] ?? '' ) : '',
				'country' => $country,
			);
		}

		// Tags — sanitize and enforce length cap.
		$tags     = array();
		$tags_raw = sanitize_text_field( $form['mailchimp_tags'] ?? '' );
		if ( '' !== $tags_raw ) {
			foreach ( explode( ',', $tags_raw ) as $tag ) {
				$tag = trim( $tag );
				if ( '' !== $tag ) {
					$tags[] = substr( sanitize_text_field( $tag ), 0, self::MAX_TAG_LENGTH );
				}
			}
		}

		$this->subscribe( $api_key, $list_id, $email, $merge_fields, $tags );
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Add or update a subscriber in a Mailchimp audience.
	 *
	 * Uses PUT (upsert) so re-subscribing an existing contact doesn't error.
	 *
	 * @param string   $api_key      Decrypted API key.
	 * @param string   $list_id      Audience/list ID (pre-validated as hex).
	 * @param string   $email        Subscriber email (pre-validated).
	 * @param array    $merge_fields MERGE_FIELDS payload.
	 * @param string[] $tags         Tags to apply.
	 */
	private function subscribe( string $api_key, string $list_id, string $email, array $merge_fields, array $tags ): void {
		$dc = $this->get_datacenter( $api_key );
		if ( '' === $dc ) {
			return;
		}

		// MD5 of the lowercased email is the Mailchimp member identifier.
		$email_hash = md5( strtolower( trim( $email ) ) );
		$endpoint   = 'https://' . $dc . '.api.mailchimp.com/3.0/lists/' . $list_id . '/members/' . $email_hash;

		$body = array(
			'email_address' => $email,
			'status_if_new' => 'subscribed',
		);

		if ( ! empty( $merge_fields ) ) {
			$body['merge_fields'] = $merge_fields;
		}

		$response = wp_remote_request(
			$endpoint,
			array(
				'method'  => 'PUT',
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( 'anystring:' . $api_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Mailchimp subscribe failed: ' . $response->get_error_message() );
			return;
		}

		// Apply tags if any (separate endpoint).
		if ( ! empty( $tags ) ) {
			$this->apply_tags( $api_key, $list_id, $email_hash, $tags );
		}
	}

	/**
	 * Apply tags to an existing member.
	 *
	 * @param string   $api_key    Decrypted API key.
	 * @param string   $list_id    Audience/list ID.
	 * @param string   $email_hash MD5 hash of the subscriber email.
	 * @param string[] $tags       Tags to apply (pre-sanitized, length-capped).
	 */
	private function apply_tags( string $api_key, string $list_id, string $email_hash, array $tags ): void {
		$dc = $this->get_datacenter( $api_key );
		if ( '' === $dc ) {
			return;
		}

		$endpoint = 'https://' . $dc . '.api.mailchimp.com/3.0/lists/' . $list_id . '/members/' . $email_hash . '/tags';

		$tag_payload = array_map(
			static function ( $tag ) {
				return array(
					'name'   => $tag, // Already sanitized and length-capped in run().
					'status' => 'active',
				);
			},
			$tags
		);

		wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( 'anystring:' . $api_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'tags' => $tag_payload ) ),
				'timeout' => 10,
			)
		);
	}

	/**
	 * Get the decrypted API key, or empty string.
	 *
	 * Result is returned from a single decrypt call to avoid double invocation.
	 *
	 * @return string
	 */
	private function get_api_key(): string {
		if ( defined( 'FHW_MAILCHIMP_API_KEY' ) && '' !== FHW_MAILCHIMP_API_KEY ) {
			return FHW_MAILCHIMP_API_KEY;
		}
		$enc = get_option( 'fhw_mailchimp_api_key_enc', '' );
		if ( '' === $enc ) {
			return '';
		}
		$decrypted = FHW_Crypto::decrypt( $enc );
		return ( false !== $decrypted && '' !== $decrypted ) ? $decrypted : '';
	}

	/**
	 * Extract and validate the data centre prefix from a Mailchimp API key.
	 *
	 * Keys end with a dash-delimited DC suffix like 'us1', 'us6', 'eu1'.
	 * The suffix is validated against a strict pattern before use in URLs
	 * to prevent a crafted key from injecting arbitrary hostnames.
	 *
	 * @param string $api_key API key.
	 * @return string Validated DC slug (e.g. 'us1'), or empty string on failure.
	 */
	private function get_datacenter( string $api_key ): string {
		$parts = explode( '-', $api_key );
		$dc    = end( $parts );
		// Validate: must be 2–3 lowercase letters followed by 1–3 digits (e.g. us1, eu1, us20).
		if ( ! preg_match( '/^[a-z]{2,3}\d{1,3}$/', $dc ) ) {
			$this->log_error( 'Mailchimp: invalid or missing data centre in API key.' );
			return '';
		}
		return $dc;
	}

	/**
	 * Log an error message when WP_DEBUG is enabled.
	 *
	 * Avoids polluting production logs with third-party API noise.
	 *
	 * @param string $message Error message.
	 */
	private function log_error( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Form Handler WP] ' . $message );
		}
	}
}
