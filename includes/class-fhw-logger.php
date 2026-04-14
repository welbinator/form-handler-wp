<?php
/**
 * Email log: write & read entries from the custom DB table.
 *
 * @package Form_Handler_WP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FHW_Logger
 */
class FHW_Logger {

	/**
	 * Create the email log table on activation.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . FHW_LOG_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			sent_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			recipient   VARCHAR(320)        NOT NULL DEFAULT '',
			subject     VARCHAR(998)        NOT NULL DEFAULT '',
			status      VARCHAR(20)         NOT NULL DEFAULT 'sent',
			message_id  VARCHAR(255)        NOT NULL DEFAULT '',
			error_msg   TEXT                         DEFAULT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY sent_at (sent_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Write a log entry.
	 *
	 * @param string $recipient  Recipient email(s).
	 * @param string $subject    Email subject.
	 * @param string $status     'sent' or 'failed'.
	 * @param string $error_msg  Error message (empty on success).
	 * @param string $message_id Provider-assigned message ID.
	 */
	public function log( $recipient, $subject, $status, $error_msg = '', $message_id = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . FHW_LOG_TABLE;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table_name,
			array(
				'sent_at'    => current_time( 'mysql' ),
				'recipient'  => sanitize_text_field( substr( $recipient, 0, 320 ) ),
				'subject'    => sanitize_text_field( substr( $subject, 0, 998 ) ),
				'status'     => sanitize_key( $status ),
				'message_id' => sanitize_text_field( substr( (string) $message_id, 0, 255 ) ),
				'error_msg'  => '' !== $error_msg ? sanitize_textarea_field( $error_msg ) : null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get the last N log entries.
	 *
	 * @param int $limit Number of entries to retrieve. Default 50.
	 * @return array
	 */
	public function get_entries( $limit = 50 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . FHW_LOG_TABLE;
		$limit      = absint( $limit );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, sent_at, recipient, subject, status, message_id, error_msg FROM {$table_name} ORDER BY sent_at DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Delete all log entries.
	 */
	public function clear_log() {
		global $wpdb;

		$table_name = $wpdb->prefix . FHW_LOG_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "TRUNCATE TABLE {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Drop the log table (called from uninstall.php).
	 */
	public static function drop_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . FHW_LOG_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
