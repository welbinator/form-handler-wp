<?php
/**
 * Integration contract.
 *
 * Every integration (Mailchimp, ActiveCampaign, etc.) must implement this
 * interface so the registry and UI can treat them uniformly.
 *
 * @package Form_Handler_WP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface FHW_Integration
 */
interface FHW_Integration {

	/**
	 * Unique snake_case identifier, e.g. 'mailchimp'.
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Human-readable label, e.g. 'Mailchimp'.
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Whether the integration has a valid API key / credentials saved.
	 *
	 * @return bool
	 */
	public function is_connected(): bool;

	/**
	 * Field definitions for the global Integrations settings tab.
	 *
	 * Each entry:
	 *   [ 'key' => string, 'label' => string, 'type' => 'password'|'text', 'description' => string ]
	 *
	 * @return array
	 */
	public function get_settings_fields(): array;

	/**
	 * Save global settings posted from the Integrations tab.
	 *
	 * Receives the raw $_POST array (already nonce-verified by the caller).
	 *
	 * @param array $post Raw POST data.
	 */
	public function save_settings( array $post ): void;

	/**
	 * Field definitions shown inside the per-form config UI.
	 *
	 * Each entry:
	 *   [ 'key' => string, 'label' => string, 'type' => 'text'|'audience_select'|'field_map'|'tags', 'description' => string ]
	 *
	 * @return array
	 */
	public function get_form_fields(): array;

	/**
	 * Fetch remote options for a 'select' type form field (e.g. audience list).
	 *
	 * Returns an array of [ 'value' => string, 'label' => string ] pairs.
	 * Return empty array if not applicable or API call fails.
	 *
	 * @param string $field_key The form field key being populated.
	 * @return array
	 */
	public function get_remote_options( string $field_key ): array;

	/**
	 * Execute the integration after a successful form submission.
	 *
	 * Implementations should be non-fatal — log errors but do not throw.
	 *
	 * @param array $form        Registered form config array.
	 * @param array $post_fields Sanitized submitted field values.
	 */
	public function run( array $form, array $post_fields ): void;
}
