<?php
/**
 * Encryption helper for sensitive stored values (e.g. API keys).
 *
 * Uses AES-256-CBC via OpenSSL. Requires either:
 *   - A FHW_ENCRYPTION_KEY constant defined in wp-config.php (preferred), or
 *   - Falls back to deriving a key from WordPress auth keys/salts.
 *
 * Base64 is NOT encryption. This class provides real symmetric encryption
 * so that API keys stored in wp_options cannot be trivially decoded.
 *
 * @package Form_Handler_WP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FHW_Crypto
 */
class FHW_Crypto {

	/**
	 * Cipher method.
	 *
	 * @var string
	 */
	const CIPHER = 'AES-256-CBC';

	/**
	 * Get the encryption key.
	 *
	 * Priority:
	 *   1. FHW_ENCRYPTION_KEY constant (defined in wp-config.php) — recommended
	 *   2. Derived from WordPress AUTH_KEY + SECURE_AUTH_KEY salts
	 *
	 * The key is hashed to exactly 32 bytes (256 bits) for AES-256.
	 *
	 * @return string 32-byte binary key.
	 */
	private static function get_key() {
		if ( defined( 'FHW_ENCRYPTION_KEY' ) && '' !== (string) FHW_ENCRYPTION_KEY ) {
			return hash( 'sha256', (string) FHW_ENCRYPTION_KEY, true );
		}

		// Fall back to WordPress salts (unique per install).
		$salt_base  = '';
		$salt_base .= defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
		$salt_base .= defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '';
		$salt_base .= defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : '';

		if ( '' !== $salt_base ) {
			return hash( 'sha256', $salt_base, true );
		}

		// Last resort: site URL + DB prefix (weakest, but better than base64).
		return hash( 'sha256', get_site_url() . $GLOBALS['wpdb']->prefix, true );
	}

	/**
	 * Encrypt a plaintext string.
	 *
	 * Returns a base64-encoded string of: IV + ciphertext.
	 * Safe to store in wp_options.
	 *
	 * @param string $plaintext The value to encrypt.
	 * @return string|false Encrypted + encoded string, or false on failure.
	 */
	public static function encrypt( $plaintext ) {
		if ( '' === $plaintext ) {
			return '';
		}

		$key    = self::get_key();
		$iv_len = openssl_cipher_iv_length( self::CIPHER );
		$iv     = openssl_random_pseudo_bytes( $iv_len );

		$ciphertext = openssl_encrypt( $plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $ciphertext ) {
			return false;
		}

		// Prepend IV so we can decrypt later; base64 for safe DB storage.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( $iv . $ciphertext );
	}

	/**
	 * Decrypt a previously encrypted string.
	 *
	 * @param string $encoded The encrypted + base64-encoded value from storage.
	 * @return string|false Decrypted plaintext, or false on failure.
	 */
	public static function decrypt( $encoded ) {
		if ( '' === $encoded ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$raw    = base64_decode( $encoded, true );
		$key    = self::get_key();
		$iv_len = openssl_cipher_iv_length( self::CIPHER );

		if ( false === $raw || strlen( $raw ) <= $iv_len ) {
			return false;
		}

		$iv         = substr( $raw, 0, $iv_len );
		$ciphertext = substr( $raw, $iv_len );

		return openssl_decrypt( $ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );
	}

	/**
	 * Check whether a stored value looks like old base64-only encoding
	 * (i.e. not yet migrated to AES encryption).
	 *
	 * Heuristic: try to decrypt it. If decryption fails or returns something
	 * that looks like a raw API key (no IV prefix), treat as legacy base64.
	 *
	 * @param string $stored Raw value from wp_options.
	 * @return bool True if this looks like a legacy base64 value.
	 */
	public static function is_legacy_base64( $stored ) {
		if ( '' === $stored ) {
			return false;
		}

		$decrypted = self::decrypt( $stored );

		// If decryption failed entirely it's almost certainly legacy base64.
		if ( false === $decrypted ) {
			return true;
		}

		// If decrypted value is empty but stored is not — legacy.
		if ( '' === $decrypted ) {
			return true;
		}

		return false;
	}

	/**
	 * Migrate a legacy base64-encoded value to AES encryption.
	 *
	 * @param string $stored Legacy base64 value from wp_options.
	 * @return string|false New AES-encrypted value, or false on failure.
	 */
	public static function migrate_from_base64( $stored ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$plaintext = base64_decode( $stored, true );

		if ( false === $plaintext || '' === $plaintext ) {
			return false;
		}

		return self::encrypt( $plaintext );
	}
}
