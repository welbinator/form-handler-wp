<?php
/**
 * Form submissions storage and retrieval.
 *
 * Creates and manages the fhw_submissions database table, which records
 * every form submission with a hashed IP, the submitted fields (JSON),
 * and the email send status.
 *
 * @package Form_Handler_WP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FHW_Submissions
 */
class FHW_Submissions {

	/**
	 * Maximum number of fields to store per submission.
	 *
	 * @var int
	 */
	const MAX_FIELDS = 30;

	/**
	 * Maximum character length for a single field value.
	 *
	 * @var int
	 */
	const MAX_FIELD_VALUE_LENGTH = 5000;

	/**
	 * Get the full table name with WordPress prefix.
	 *
	 * @return string
	 */
	private static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'fhw_submissions';
	}

	/**
	 * Create the fhw_submissions table using dbDelta.
	 *
	 * Safe to call repeatedly — dbDelta only applies changes.
	 */
	public static function create_table() {
		global $wpdb;

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			submitted_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			action_name  VARCHAR(100)        NOT NULL DEFAULT '',
			ip_hash      VARCHAR(64)         NOT NULL DEFAULT '',
			fields       LONGTEXT            NOT NULL DEFAULT '',
			email_status VARCHAR(20)         NOT NULL DEFAULT 'sent',
			PRIMARY KEY  (id),
			KEY action_name (action_name),
			KEY submitted_at (submitted_at),
			KEY email_status (email_status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'fhw_db_version', FHW_VERSION );
	}

	/**
	 * Drop the fhw_submissions table (called on plugin uninstall).
	 */
	public static function drop_table() {
		global $wpdb;

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	/**
	 * Save a form submission to the database.
	 *
	 * The IP address is stored as a SHA-256 hash. Fields are JSON-encoded
	 * after sanitizing each value as plain text.
	 *
	 * @param string $action_name  Registered form action name.
	 * @param string $ip           Raw client IP address.
	 * @param array  $fields       Associative array of submitted field values.
	 * @param string $email_status 'sent' or 'failed'.
	 *
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function save( $action_name, $ip, array $fields, $email_status ) {
		global $wpdb;

		$action_name  = sanitize_key( $action_name );
		$ip_hash      = hash( 'sha256', sanitize_text_field( $ip ) );
		$email_status = in_array( $email_status, array( 'sent', 'failed' ), true ) ? $email_status : 'sent';

		// Enforce max field count and sanitize each value.
		$clean_fields = array();
		$count        = 0;
		foreach ( $fields as $key => $value ) {
			if ( $count >= self::MAX_FIELDS ) {
				break;
			}
			$clean_key = sanitize_key( $key );
			if ( '' === $clean_key ) {
				continue;
			}
			$clean_value = sanitize_text_field( (string) $value );
			if ( strlen( $clean_value ) > self::MAX_FIELD_VALUE_LENGTH ) {
				$clean_value = substr( $clean_value, 0, self::MAX_FIELD_VALUE_LENGTH );
			}
			$clean_fields[ $clean_key ] = $clean_value;
			++$count;
		}

		$fields_json = wp_json_encode( $clean_fields );
		if ( false === $fields_json ) {
			$fields_json = '{}';
		}

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table_name(),
			array(
				'submitted_at' => current_time( 'mysql', true ),
				'action_name'  => $action_name,
				'ip_hash'      => $ip_hash,
				'fields'       => $fields_json,
				'email_status' => $email_status,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Retrieve paginated submission entries.
	 *
	 * @param int    $limit              Number of rows to return.
	 * @param int    $offset             Rows to skip (0-indexed).
	 * @param string $action_name_filter Optional action name to filter by.
	 *
	 * @return array Array of row objects.
	 */
	public function get_entries( $limit, $offset, $action_name_filter = '' ) {
		global $wpdb;

		$table  = self::table_name();
		$limit  = absint( $limit );
		$offset = absint( $offset );

		if ( '' !== $action_name_filter ) {
			$action_name_filter = sanitize_key( $action_name_filter );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT * FROM {$table} WHERE action_name = %s ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
					$action_name_filter,
					$limit,
					$offset
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT * FROM {$table} ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
					$limit,
					$offset
				),
				ARRAY_A
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count total submissions, optionally filtered by action name.
	 *
	 * @param string $action_name_filter Optional action name to filter by.
	 *
	 * @return int Total row count.
	 */
	public function get_count( $action_name_filter = '' ) {
		global $wpdb;

		$table = self::table_name();

		if ( '' !== $action_name_filter ) {
			$action_name_filter = sanitize_key( $action_name_filter );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT COUNT(*) FROM {$table} WHERE action_name = %s",
					$action_name_filter
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$table}"
			);
		}

		return (int) $count;
	}

	/**
	 * Delete a single submission by ID.
	 *
	 * @param int $id Row ID.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function delete_entry( $id ) {
		global $wpdb;

		$id = absint( $id );
		if ( 0 === $id ) {
			return false;
		}

		$result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table_name(),
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete all submissions (truncate).
	 *
	 * @return bool True on success.
	 */
	public function clear_all() {
		global $wpdb;

		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table}" );

		return true;
	}

	/**
	 * Delete all submissions for a specific form action.
	 *
	 * @param string $action_name Form action name.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_by_form( $action_name ) {
		global $wpdb;

		$action_name = sanitize_key( $action_name );
		if ( '' === $action_name ) {
			return false;
		}

		$result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table_name(),
			array( 'action_name' => $action_name ),
			array( '%s' )
		);

		return false !== $result;
	}
}
