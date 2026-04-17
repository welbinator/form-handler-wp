<?php
/**
 * ActiveCampaign integration.
 *
 * Subscribes form submitters to an ActiveCampaign list via the
 * ActiveCampaign API v3.
 *
 * @package Form_Handler_WP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FHW_Integration_ActiveCampaign
 */
class FHW_Integration_ActiveCampaign implements FHW_Integration {

	/**
	 * Transient TTL for cached lists (5 minutes).
	 *
	 * @var int
	 */
	const CACHE_TTL = 300;

	// -----------------------------------------------------------------------
	// Interface: identity
	// -----------------------------------------------------------------------

	/**
	 * Return the integration id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'activecampaign';
	}

	/**
	 * Return the human-readable label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return 'ActiveCampaign';
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
		return '' !== $this->get_api_key() && '' !== $this->get_api_url();
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
				'key'         => 'fhw_activecampaign_api_url',
				'label'       => __( 'Account URL', 'form-handler-wp' ),
				'type'        => 'text',
				'description' => __( 'Your ActiveCampaign URL, e.g. https://youraccount.api-us1.com', 'form-handler-wp' ),
			),
			array(
				'key'         => 'fhw_activecampaign_api_key',
				'label'       => __( 'API Key', 'form-handler-wp' ),
				'type'        => 'password',
				'description' => __( 'Find your API key in ActiveCampaign → Settings → Developer.', 'form-handler-wp' ),
			),
		);
	}

	/**
	 * Persist global settings from POST data.
	 *
	 * @param array $post Raw POST data (nonce already verified by caller).
	 */
	public function save_settings( array $post ): void {
		// API URL (not sensitive — store plain).
		if ( isset( $post['fhw_activecampaign_api_url'] ) ) {
			$url = esc_url_raw( wp_unslash( $post['fhw_activecampaign_api_url'] ) );
			update_option( 'fhw_activecampaign_api_url', rtrim( $url, '/' ) );
		}

		// API key (sensitive — encrypt).
		if ( isset( $post['fhw_activecampaign_api_key'] ) ) {
			$key = sanitize_text_field( wp_unslash( $post['fhw_activecampaign_api_key'] ) );
			if ( '' !== $key && '••••••••' !== $key ) {
				$encrypted = FHW_Crypto::encrypt( $key );
				if ( false !== $encrypted ) {
					update_option( 'fhw_activecampaign_api_key_enc', $encrypted );
				}
			} elseif ( '' === $key ) {
				delete_option( 'fhw_activecampaign_api_key_enc' );
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
				'key'         => 'activecampaign_list_id',
				'label'       => __( 'List', 'form-handler-wp' ),
				'type'        => 'remote_select',
				'remote_key'  => 'lists',
				'description' => __( 'Which ActiveCampaign list to subscribe to.', 'form-handler-wp' ),
			),
			array(
				'key'         => 'activecampaign_email_field',
				'label'       => __( 'Email field', 'form-handler-wp' ),
				'type'        => 'field_map',
				'description' => __( 'Which submitted field contains the subscriber\'s email address.', 'form-handler-wp' ),
			),
			array(
				'key'         => 'activecampaign_fname_field',
				'label'       => __( 'First name field', 'form-handler-wp' ),
				'type'        => 'field_map',
				'required'    => false,
				'description' => __( 'Optional.', 'form-handler-wp' ),
			),
			array(
				'key'         => 'activecampaign_lname_field',
				'label'       => __( 'Last name field', 'form-handler-wp' ),
				'type'        => 'field_map',
				'required'    => false,
				'description' => __( 'Optional.', 'form-handler-wp' ),
			),
			array(
				'key'         => 'activecampaign_tags',
				'label'       => __( 'Tags', 'form-handler-wp' ),
				'type'        => 'text',
				'description' => __( 'Comma-separated tags to apply to the contact.', 'form-handler-wp' ),
			),
			array(
				'key'         => 'activecampaign_optin_field',
				'label'       => __( 'Opt-in field', 'form-handler-wp' ),
				'type'        => 'field_map',
				'required'    => false,
				'description' => __( 'Optional. Only subscribe if this field equals "1".', 'form-handler-wp' ),
			),
		);
	}

	/**
	 * Fetch remote select options (e.g. list from API).
	 *
	 * @param string $field_key The field key requesting options.
	 * @return array
	 */
	public function get_remote_options( string $field_key ): array {
		if ( 'lists' !== $field_key ) {
			return array();
		}

		$cache_key = 'fhw_activecampaign_lists';
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$api_key = $this->get_api_key();
		$api_url = $this->get_api_url();
		if ( '' === $api_key || '' === $api_url ) {
			return array();
		}

		// Enforce HTTPS before making requests.
		if ( 0 !== strpos( $api_url, 'https://' ) ) {
			return array();
		}

		$response = wp_remote_get(
			$api_url . '/api/3/lists?limit=100',
			array(
				'headers' => array(
					'Api-Token'    => $api_key,
					'Content-Type' => 'application/json',
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
			$options[] = array(
				'value' => sanitize_text_field( (string) $list['id'] ),
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
		$api_url = $this->get_api_url();
		if ( '' === $api_key || '' === $api_url ) {
			return;
		}

		// Enforce HTTPS — never send credentials over plain HTTP.
		if ( 0 !== strpos( $api_url, 'https://' ) ) {
			$this->log_error( 'ActiveCampaign: API URL must use HTTPS.' );
			return;
		}

		$list_id = sanitize_text_field( $form['activecampaign_list_id'] ?? '' );
		if ( '' === $list_id ) {
			return;
		}

		// Opt-in gate.
		$optin_field = sanitize_key( $form['activecampaign_optin_field'] ?? '' );
		if ( '' !== $optin_field ) {
			if ( '1' !== ( $post_fields[ $optin_field ] ?? '' ) ) {
				return;
			}
		}

		// Email.
		$email_field = sanitize_key( $form['activecampaign_email_field'] ?? '' );
		$email       = sanitize_email( $post_fields[ $email_field ] ?? '' );
		if ( ! is_email( $email ) ) {
			return;
		}

		// Build contact payload.
		$contact = array( 'email' => $email );

		$fname_field = sanitize_key( $form['activecampaign_fname_field'] ?? '' );
		$lname_field = sanitize_key( $form['activecampaign_lname_field'] ?? '' );
		if ( '' !== $fname_field && ! empty( $post_fields[ $fname_field ] ) ) {
			$contact['firstName'] = sanitize_text_field( $post_fields[ $fname_field ] );
		}
		if ( '' !== $lname_field && ! empty( $post_fields[ $lname_field ] ) ) {
			$contact['lastName'] = sanitize_text_field( $post_fields[ $lname_field ] );
		}

		// Upsert contact.
		$contact_id = $this->upsert_contact( $api_key, $api_url, $contact );
		if ( null === $contact_id ) {
			return;
		}

		// Subscribe to list.
		$this->subscribe_to_list( $api_key, $api_url, $contact_id, $list_id );

		// Apply tags.
		$tags_raw = sanitize_text_field( $form['activecampaign_tags'] ?? '' );
		if ( '' !== $tags_raw ) {
			foreach ( explode( ',', $tags_raw ) as $raw_tag ) {
				$tag = trim( $raw_tag );
				if ( '' !== $tag ) {
					// Cap at 255 chars (AC's documented limit).
					$this->apply_tag( $api_key, $api_url, $contact_id, substr( sanitize_text_field( $tag ), 0, 255 ) );
				}
			}
		}
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Create or update a contact, returning the contact ID.
	 *
	 * @param string $api_key API key.
	 * @param string $api_url Base API URL.
	 * @param array  $contact Contact data.
	 * @return int|null Contact ID, or null on failure.
	 */
	private function upsert_contact( string $api_key, string $api_url, array $contact ): ?int {
		$response = wp_remote_post(
			$api_url . '/api/3/contact/sync',
			array(
				'headers' => array(
					'Api-Token'    => $api_key,
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( array( 'contact' => $contact ) ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'ActiveCampaign upsert failed: ' . $response->get_error_message() );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$id   = isset( $body['contact']['id'] ) ? (int) $body['contact']['id'] : null;
		return $id > 0 ? $id : null;
	}

	/**
	 * Subscribe a contact to a list.
	 *
	 * @param string $api_key    API key.
	 * @param string $api_url    Base API URL.
	 * @param int    $contact_id Contact ID.
	 * @param string $list_id    List ID.
	 */
	private function subscribe_to_list( string $api_key, string $api_url, int $contact_id, string $list_id ): void {
		wp_remote_post(
			$api_url . '/api/3/contactLists',
			array(
				'headers' => array(
					'Api-Token'    => $api_key,
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'contactList' => array(
							'list'    => (int) $list_id,
							'contact' => $contact_id,
							'status'  => 1,
						),
					)
				),
				'timeout' => 10,
			)
		);
	}

	/**
	 * Apply a tag to a contact (creates the tag if it doesn't exist).
	 *
	 * @param string $api_key    API key.
	 * @param string $api_url    Base API URL.
	 * @param int    $contact_id Contact ID.
	 * @param string $tag        Tag name.
	 */
	private function apply_tag( string $api_key, string $api_url, int $contact_id, string $tag ): void {
		// First ensure the tag exists and get its ID.
		$response = wp_remote_post(
			$api_url . '/api/3/tags',
			array(
				'headers' => array(
					'Api-Token'    => $api_key,
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'tag' => array(
							'tag'         => sanitize_text_field( $tag ),
							'tagType'     => 'contact',
							'description' => '',
						),
					)
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$tag_id = isset( $body['tag']['id'] ) ? (int) $body['tag']['id'] : 0;
		if ( 0 === $tag_id ) {
			return;
		}

		// Apply tag to contact.
		wp_remote_post(
			$api_url . '/api/3/contactTags',
			array(
				'headers' => array(
					'Api-Token'    => $api_key,
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'contactTag' => array(
							'contact' => $contact_id,
							'tag'     => $tag_id,
						),
					)
				),
				'timeout' => 10,
			)
		);
	}

	/**
	 * Get decrypted API key.
	 *
	 * @return string
	 */
	private function get_api_key(): string {
		if ( defined( 'FHW_ACTIVECAMPAIGN_API_KEY' ) && '' !== FHW_ACTIVECAMPAIGN_API_KEY ) {
			return FHW_ACTIVECAMPAIGN_API_KEY;
		}
		$enc = get_option( 'fhw_activecampaign_api_key_enc', '' );
		if ( '' === $enc ) {
			return '';
		}
		$decrypted = FHW_Crypto::decrypt( $enc );
		return ( false !== $decrypted && '' !== $decrypted ) ? $decrypted : '';
	}

	/**
	 * Get the saved API URL.
	 *
	 * @return string
	 */
	private function get_api_url(): string {
		if ( defined( 'FHW_ACTIVECAMPAIGN_API_URL' ) && '' !== FHW_ACTIVECAMPAIGN_API_URL ) {
			return rtrim( FHW_ACTIVECAMPAIGN_API_URL, '/' );
		}
		return rtrim( get_option( 'fhw_activecampaign_api_url', '' ), '/' );
	}

	/**
	 * Log an error message when WP_DEBUG is enabled.
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
