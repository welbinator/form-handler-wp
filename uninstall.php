<?php
/**
 * Uninstall Form Handler WP.
 *
 * Runs when the plugin is deleted via the WordPress admin.
 * Removes all plugin options and drops the email log table.
 *
 * @package Form_Handler_WP
 */

// Only run via WordPress uninstall — never directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Drop the email log table.
global $wpdb;

$table_name = $wpdb->prefix . 'fhw_email_log';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// Delete all plugin options.
$options = array(
	'fhw_brevo_api_key_enc',
	'fhw_sender_email',
	'fhw_sender_name',
	'fhw_override_wp_mail',
	'fhw_registered_forms',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Clean up any rate-limiting transients.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_fhw_rl_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_fhw_rl_' ) . '%'
	)
);
