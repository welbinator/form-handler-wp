<?php
/**
 * Plugin Name:       Form Handler WP
 * Plugin URI:        https://github.com/welbinator/form-handler-wp
 * Description:       Secure AJAX form handling with Brevo transactional email. Build your own forms; we handle the sending.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            James Welbes
 * Author URI:        https://jameswelbes.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       form-handler-wp
 * Domain Path:       /languages
 *
 * @package Form_Handler_WP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'FHW_VERSION', '1.0.0' );
define( 'FHW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FHW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FHW_PLUGIN_FILE', __FILE__ );
define( 'FHW_LOG_TABLE', 'fhw_email_log' );

// Load required files.
require_once FHW_PLUGIN_DIR . 'includes/interface-fhw-mailer.php';
require_once FHW_PLUGIN_DIR . 'includes/class-fhw-brevo-api.php';
require_once FHW_PLUGIN_DIR . 'includes/class-fhw-logger.php';
require_once FHW_PLUGIN_DIR . 'includes/class-fhw-form-registry.php';
require_once FHW_PLUGIN_DIR . 'includes/class-fhw-handler.php';
require_once FHW_PLUGIN_DIR . 'includes/class-fhw-settings.php';

/**
 * Activation hook: create DB table.
 */
function fhw_activate() {
	FHW_Logger::create_table();
	flush_rewrite_rules();
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
}
add_action( 'init', 'fhw_init' );

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
 * @param null|bool $return Short-circuit return value.
 * @param array     $atts   wp_mail() arguments.
 * @return bool True if sent, false on error.
 */
function fhw_intercept_wp_mail( $return, $atts ) {
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
		$logger->log( implode( ',', wp_list_pluck( $recipients, 'email' ) ), $subject, 'failed', $result->get_error_message(), 0 );
		return false;
	}

	$logger->log( implode( ',', wp_list_pluck( $recipients, 'email' ) ), $subject, 'sent', '', isset( $result['messageId'] ) ? $result['messageId'] : 0 );
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
 * Enqueue front-end script that exposes the AJAX URL.
 */
function fhw_enqueue_scripts() {
	wp_localize_script(
		'jquery',
		'fhwData',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'fhw_enqueue_scripts' );
