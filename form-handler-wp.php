<?php
/**
 * Plugin Name:       Form Handler WP
 * Plugin URI:        https://github.com/welbinator/form-handler-wp
 * Description:       Secure AJAX form handling with Brevo transactional email. Build your own forms; we handle the sending.
 * Version:           1.0.8
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            James Welbes
 * Author URI:        https://jameswelbes.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       form-handler-wp
 * Domain Path:       /languages
 * Update URI:        https://github.com/welbinator/form-handler-wp
 *
 * @package Form_Handler_WP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'FHW_VERSION', '1.0.8' );
define( 'FHW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FHW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FHW_PLUGIN_FILE', __FILE__ );
define( 'FHW_LOG_TABLE', 'fhw_email_log' );

// Load required files.
require_once FHW_PLUGIN_DIR . 'includes/class-fhw-crypto.php';
require_once FHW_PLUGIN_DIR . 'includes/interface-fhw-mailer.php';
require_once FHW_PLUGIN_DIR . 'includes/class-fhw-brevo-api.php';
require_once FHW_PLUGIN_DIR . 'includes/class-fhw-logger.php';
require_once FHW_PLUGIN_DIR . 'includes/class-fhw-form-registry.php';
require_once FHW_PLUGIN_DIR . 'includes/class-fhw-handler.php';
require_once FHW_PLUGIN_DIR . 'includes/class-fhw-settings.php';

/**
 * Activation hook: create DB table and migrate legacy base64 API key.
 */
function fhw_activate() {
	FHW_Logger::create_table();
	fhw_maybe_migrate_api_key();
	flush_rewrite_rules();
}

/**
 * Migrate a legacy base64-encoded API key to AES-256 encryption.
 *
 * Safe to run repeatedly — checks whether migration is needed first.
 * Called on plugin activation and on admin_init (to catch sites that
 * activated before this version was released).
 */
function fhw_maybe_migrate_api_key() {
	// If key is stored as a constant, nothing to migrate in the DB.
	if ( defined( 'FHW_BREVO_API_KEY' ) ) {
		return;
	}

	$stored = get_option( 'fhw_brevo_api_key_enc', '' );
	if ( '' === $stored ) {
		return;
	}

	// Check if this looks like a legacy base64 value (not yet AES-encrypted).
	if ( FHW_Crypto::is_legacy_base64( $stored ) ) {
		$migrated = FHW_Crypto::migrate_from_base64( $stored );
		if ( false !== $migrated ) {
			update_option( 'fhw_brevo_api_key_enc', $migrated );
		}
	}
}
register_activation_hook( __FILE__, 'fhw_activate' );

/**
 * Initialize the plugin.
 */
function fhw_init() {
	// Load text domain.
	load_plugin_textdomain( 'form-handler-wp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Boot settings / admin menu.
	if ( is_admin() ) {
		new FHW_Settings();
	}

	// Register all AJAX hooks for each registered form.
	$registry = new FHW_Form_Registry();
	$forms    = $registry->get_forms();

	if ( ! empty( $forms ) ) {
		foreach ( $forms as $form ) {
			$action = sanitize_key( $form['action_name'] );
			if ( $action ) {
				add_action( "wp_ajax_{$action}", 'fhw_generic_handler' );
				add_action( "wp_ajax_nopriv_{$action}", 'fhw_generic_handler' );
			}
		}
	}

	// Optional: intercept wp_mail() and route through Brevo.
	$override_wp_mail = get_option( 'fhw_override_wp_mail', '0' );
	if ( '1' === $override_wp_mail ) {
		add_filter( 'pre_wp_mail', 'fhw_intercept_wp_mail', 10, 2 );
	}

	// Nonce endpoint: ?fhw_get_nonce=<action_name> returns JSON { nonce: "..." }.
	// Only issues nonces for registered form actions.
	if ( isset( $_GET['fhw_get_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$nonce_action = sanitize_key( wp_unslash( $_GET['fhw_get_nonce'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $nonce_action ) {
			$registry = new FHW_Form_Registry();
			$form     = $registry->get_form( $nonce_action );
			if ( $form ) {
				$nonce_id = 'fhw_' . $nonce_action . '_nonce';
				wp_send_json( array( 'nonce' => wp_create_nonce( $nonce_id ) ) );
			} else {
				wp_send_json_error( array( 'message' => 'Unknown form action.' ), 404 );
			}
		}
	}
}
add_action( 'init', 'fhw_init' );
add_action( 'admin_init', 'fhw_maybe_migrate_api_key' );

/**
 * Generic AJAX handler — dispatches to FHW_Handler.
 */
function fhw_generic_handler() {
	$handler = new FHW_Handler();
	$handler->handle();
}

/**
 * Intercept wp_mail() and route through Brevo.
 *
 * @param null|bool $short_circuit Short-circuit return value.
 * @param array     $atts          wp_mail() arguments.
 * @return bool True if sent, false on error.
 */
function fhw_intercept_wp_mail( $short_circuit, $atts ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
	$to      = isset( $atts['to'] ) ? $atts['to'] : '';
	$subject = isset( $atts['subject'] ) ? $atts['subject'] : '';
	$message = isset( $atts['message'] ) ? $atts['message'] : '';
	$headers = isset( $atts['headers'] ) ? $atts['headers'] : array();

	// Build recipient list.
	$recipients = array();
	$to_list    = is_array( $to ) ? $to : explode( ',', $to );
	foreach ( $to_list as $email ) {
		$email = sanitize_email( trim( $email ) );
		if ( is_email( $email ) ) {
			$recipients[] = array( 'email' => $email );
		}
	}

	if ( empty( $recipients ) ) {
		return false;
	}

	// Get sender.
	$sender_email = sanitize_email( get_option( 'fhw_sender_email', get_option( 'admin_email' ) ) );
	$sender_name  = sanitize_text_field( get_option( 'fhw_sender_name', get_bloginfo( 'name' ) ) );

	$payload = array(
		'sender'      => array(
			'name'  => $sender_name,
			'email' => $sender_email,
		),
		'to'          => $recipients,
		'subject'     => sanitize_text_field( $subject ),
		'htmlContent' => wp_kses_post( $message ),
	);

	// Parse Reply-To from headers.
	if ( ! empty( $headers ) ) {
		$headers_arr = is_array( $headers ) ? $headers : explode( "\n", $headers );
		foreach ( $headers_arr as $header ) {
			if ( false !== stripos( $header, 'reply-to:' ) ) {
				$reply_email = sanitize_email( trim( str_ireplace( 'reply-to:', '', $header ) ) );
				if ( is_email( $reply_email ) ) {
					$payload['replyTo'] = array( 'email' => $reply_email );
				}
			}
		}
	}

	$brevo  = new FHW_Brevo_API();
	$result = $brevo->send( $payload );

	$logger = new FHW_Logger();
	if ( is_wp_error( $result ) ) {
		$logger->log( implode( ',', wp_list_pluck( $recipients, 'email' ) ), $subject, 'failed', $result->get_error_message(), '' );
		return false;
	}

	$logger->log( implode( ',', wp_list_pluck( $recipients, 'email' ) ), $subject, 'sent', '', isset( $result['messageId'] ) ? $result['messageId'] : '' );
	return true;
}

/**
 * Output a nonce field for a registered form action.
 *
 * @param string $action The registered form action name.
 */
function fhw_nonce_field( $action ) {
	$action   = sanitize_key( $action );
	$nonce_id = 'fhw_' . $action . '_nonce';
	wp_nonce_field( $nonce_id, $nonce_id );
}

/**
 * Enqueue front-end scripts and styles.
 *
 * Loads fhw-forms.js which auto-intercepts any <form data-fhw-form="action">.
 */
function fhw_enqueue_scripts() {
	wp_enqueue_style(
		'fhw-forms',
		FHW_PLUGIN_URL . 'assets/css/fhw-forms.css',
		array(),
		FHW_VERSION
	);

	wp_enqueue_script(
		'fhw-forms',
		FHW_PLUGIN_URL . 'assets/js/fhw-forms.js',
		array(),
		FHW_VERSION,
		true
	);

	wp_localize_script(
		'fhw-forms',
		'fhwData',
		array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonceUrl' => home_url( '/?fhw_get_nonce=' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'fhw_enqueue_scripts' );
