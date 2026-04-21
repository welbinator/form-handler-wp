<?php
/**
 * Encryption helper for sensitive stored values (e.g. API keys).
 *
 * Uses AES-256-GCM (authenticated encryption) via OpenSSL. GCM provides
 * both confidentiality and integrity — no separate HMAC needed. Bit-flip
 * and ciphertext forgery attacks that were possible with AES-256-CBC are
 * not possible with GCM.
 *
 * Storage format (v2): "v2:" + base64( nonce[12] + ciphertext + tag[16] )
 * Legacy format (v1):  base64( iv[16] + ciphertext )  — AES-256-CBC, no HMAC
 *
 * Requires either:
 *   - A FHW_ENCRYPTION_KEY constant defined in wp-config.php (strongly recommended), or
 *   - Falls back to deriving a key from WordPress auth keys/salts.
 *
 * If neither source is available the plugin logs an admin notice and refuses
 * to encrypt or decrypt — better to surface the problem than silently use a
 * weak or predictable key.
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
	 * Current cipher. AES-256-GCM provides authenticated encryption.
	 *
	 * @var string
	 */
	const CIPHER = 'AES-256-GCM';

	/**
	 * Legacy cipher used in v1 format (CBC, no HMAC).
	 *
	 * @var string
	 */
	const CIPHER_LEGACY = 'AES-256-CBC';

	/**
	 * GCM nonce length in bytes.
	 *
	 * @var int
	 */
	const NONCE_LEN = 12;

	/**
	 * GCM authentication tag length in bytes.
	 *
	 * @var int
	 */
	const TAG_LEN = 16;

	/**
	 * Prefix that identifies v2 (GCM) encrypted values in storage.
	 *
	 * @var string
	 */
	const V2_PREFIX = 'v2:';

	/**
	 * Get the encryption key.
	 *
	 * Priority:
	 *   1. FHW_ENCRYPTION_KEY constant (defined in wp-config.php) — recommended
	 *   2. Derived from WordPress AUTH_KEY + SECURE_AUTH_KEY + LOGGED_IN_KEY salts
	 *
	 * Returns false if no usable key source is available. The last-resort
	 * site_url/db_prefix fallback has been removed because it produced a
	 * predictable key on sites with default WP salts.
	 *
	 * @return string|false 32-byte binary key, or false if no key source available.
	 */
	private static function get_key() {
		if ( defined( 'FHW_ENCRYPTION_KEY' ) && '' !== (string) FHW_ENCRYPTION_KEY ) {
			return hash( 'sha256', (string) FHW_ENCRYPTION_KEY, true );
		}

		// Fall back to WordPress salts (unique per install after wp-config generation).
		$salt_base  = '';
		$salt_base .= defined( 'AUTH_KEY' ) ? AUTH_KEY : '';
		$salt_base .= defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '';
		$salt_base .= defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : '';

		if ( '' !== $salt_base ) {
			return hash( 'sha256', $salt_base, true );
		}

		// No usable key source. Refuse to operate rather than silently use a
		// predictable key — the old site_url+prefix fallback is gone.
		return false;
	}

	/**
	 * Encrypt a plaintext string using AES-256-GCM (v2 format).
	 *
	 * Returns a string of the form: "v2:" + base64( nonce + ciphertext + tag )
	 * Safe to store in wp_options.
	 *
	 * @param string $plaintext The value to encrypt.
	 * @return string|false Encrypted string, or false on failure.
	 */
	public static function encrypt( $plaintext ) {
		if ( '' === $plaintext ) {
			return '';
		}

		$key = self::get_key();
		if ( false === $key ) {
			return false;
		}

		$nonce = openssl_random_pseudo_bytes( self::NONCE_LEN );
		$tag   = '';

		$ciphertext = openssl_encrypt(
			$plaintext,
			self::CIPHER,
			$key,
			OPENSSL_RAW_DATA,
			$nonce,
			$tag,
			'',
			self::TAG_LEN
		);

		if ( false === $ciphertext ) {
			return false;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return self::V2_PREFIX . base64_encode( $nonce . $ciphertext . $tag );
	}

	/**
	 * Decrypt a stored value.
	 *
	 * Handles both current v2 (GCM) and legacy v1 (CBC) formats transparently
	 * so existing stored values continue to work until they are re-encrypted.
	 *
	 * @param string $encoded The encrypted value from storage.
	 * @return string|false Decrypted plaintext, or false on failure.
	 */
	public static function decrypt( $encoded ) {
		if ( '' === $encoded ) {
			return '';
		}

		if ( str_starts_with( $encoded, self::V2_PREFIX ) ) {
			return self::decrypt_gcm( substr( $encoded, strlen( self::V2_PREFIX ) ) );
		}

		// No v2 prefix — treat as legacy CBC format.
		return self::decrypt_cbc_legacy( $encoded );
	}

	/**
	 * Decrypt a v2 GCM value.
	 *
	 * @param string $b64 Base64-encoded nonce + ciphertext + tag (no prefix).
	 * @return string|false
	 */
	private static function decrypt_gcm( $b64 ) {
		$key = self::get_key();
		if ( false === $key ) {
			return false;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$raw = base64_decode( $b64, true );
		if ( false === $raw ) {
			return false;
		}

		$min_len = self::NONCE_LEN + self::TAG_LEN + 1;
		if ( strlen( $raw ) < $min_len ) {
			return false;
		}

		$nonce      = substr( $raw, 0, self::NONCE_LEN );
		$tag        = substr( $raw, -self::TAG_LEN );
		$ciphertext = substr( $raw, self::NONCE_LEN, -self::TAG_LEN );

		return openssl_decrypt(
			$ciphertext,
			self::CIPHER,
			$key,
			OPENSSL_RAW_DATA,
			$nonce,
			$tag
		);
	}

	/**
	 * Decrypt a legacy v1 CBC value (no HMAC — retained for migration only).
	 *
	 * @param string $encoded Base64-encoded iv + ciphertext.
	 * @return string|false
	 */
	private static function decrypt_cbc_legacy( $encoded ) {
		$key = self::get_key();
		if ( false === $key ) {
			return false;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$raw    = base64_decode( $encoded, true );
		$iv_len = openssl_cipher_iv_length( self::CIPHER_LEGACY );

		if ( false === $raw || strlen( $raw ) <= $iv_len ) {
			return false;
		}

		$iv         = substr( $raw, 0, $iv_len );
		$ciphertext = substr( $raw, $iv_len );

		return openssl_decrypt( $ciphertext, self::CIPHER_LEGACY, $key, OPENSSL_RAW_DATA, $iv );
	}

	/**
	 * Check whether a stored value is a legacy v1 CBC value that should be
	 * migrated to v2 GCM.
	 *
	 * @param string $stored Raw value from wp_options.
	 * @return bool True if this is a v1 CBC value (needs migration).
	 */
	public static function is_legacy_cbc( $stored ) {
		if ( '' === $stored ) {
			return false;
		}
		return ! str_starts_with( $stored, self::V2_PREFIX );
	}

	/**
	 * Check whether a stored value looks like old base64-only encoding
	 * (i.e. not yet migrated to AES encryption at all).
	 *
	 * @param string $stored Raw value from wp_options.
	 * @return bool True if this looks like a legacy base64 value.
	 */
	public static function is_legacy_base64( $stored ) {
		if ( '' === $stored ) {
			return false;
		}

		$decrypted = self::decrypt( $stored );

		if ( false === $decrypted ) {
			return true;
		}

		if ( '' === $decrypted ) {
			return true;
		}

		return false;
	}

	/**
	 * Migrate a legacy base64-encoded value to v2 AES-GCM encryption.
	 *
	 * @param string $stored Legacy base64 value from wp_options.
	 * @return string|false New AES-GCM encrypted value, or false on failure.
	 */
	public static function migrate_from_base64( $stored ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$plaintext = base64_decode( $stored, true );

		if ( false === $plaintext || '' === $plaintext ) {
			return false;
		}

		return self::encrypt( $plaintext );
	}

	/**
	 * Migrate a legacy v1 CBC value to v2 GCM encryption.
	 *
	 * Decrypts with CBC then re-encrypts with GCM. Call this when you detect
	 * is_legacy_cbc() returns true on a stored value.
	 *
	 * @param string $stored Legacy v1 CBC value from wp_options.
	 * @return string|false New v2 GCM encrypted value, or false on failure.
	 */
	public static function migrate_from_cbc( $stored ) {
		$plaintext = self::decrypt_cbc_legacy( $stored );

		if ( false === $plaintext || '' === $plaintext ) {
			return false;
		}

		return self::encrypt( $plaintext );
	}
}
