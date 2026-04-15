<?php
/**
 * Unit test bootstrap.
 *
 * Loads only the plugin files needed for unit tests — no WordPress required.
 * WordPress functions used by FHW_Crypto (get_site_url, $wpdb) are stubbed below.
 *
 * @package Form_Handler_WP\Tests
 */

// Plugin root.
define( 'ABSPATH', '/var/www/html/' );
define( 'FHW_VERSION', '1.0.8' );

// Stub WordPress functions used by FHW_Crypto fallback key derivation.
if ( ! function_exists( 'get_site_url' ) ) {
	function get_site_url() {
		return 'https://form-handler-wp.ddev.site';
	}
}

// Stub $wpdb global with prefix.
if ( ! isset( $GLOBALS['wpdb'] ) ) {
	$wpdb         = new stdClass();
	$wpdb->prefix = 'wp_';
	$GLOBALS['wpdb'] = $wpdb;
}

// Define WordPress salt constants for the fallback key path.
if ( ! defined( 'AUTH_KEY' ) ) {
	define( 'AUTH_KEY', 'test-auth-key-for-unit-tests-only' );
}
if ( ! defined( 'SECURE_AUTH_KEY' ) ) {
	define( 'SECURE_AUTH_KEY', 'test-secure-auth-key-for-unit-tests-only' );
}
if ( ! defined( 'LOGGED_IN_KEY' ) ) {
	define( 'LOGGED_IN_KEY', 'test-logged-in-key-for-unit-tests-only' );
}

// Load the class under test.
require_once dirname( __DIR__ ) . '/includes/class-fhw-crypto.php';
