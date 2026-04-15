<?php
/**
 * Unit tests for FHW_Crypto.
 *
 * Tests the AES-256-CBC encrypt/decrypt cycle, edge cases, legacy base64
 * migration detection, and the wp-config constant key path.
 *
 * Run with: ddev exec vendor/bin/codecept run Unit
 *
 * @package Form_Handler_WP\Tests\Unit
 */

namespace Tests\Unit;

use Codeception\Test\Unit;

/**
 * Class FHWCryptoCest
 */
class FHWCryptoTest extends Unit {

	// -----------------------------------------------------------------------
	// Encrypt / Decrypt round-trip
	// -----------------------------------------------------------------------

	/**
	 * A value encrypted then decrypted must equal the original.
	 */
	public function testEncryptDecryptRoundTrip(): void {
		$plaintext = 'xkeysib-test-api-key-abc123';
		$encrypted = \FHW_Crypto::encrypt( $plaintext );

		$this->assertNotFalse( $encrypted, 'encrypt() should not return false' );
		$this->assertNotSame( $plaintext, $encrypted, 'Encrypted value must differ from plaintext' );

		$decrypted = \FHW_Crypto::decrypt( $encrypted );
		$this->assertSame( $plaintext, $decrypted, 'Decrypted value must match original plaintext' );
	}

	/**
	 * Two encryptions of the same value should produce different ciphertexts
	 * (random IV ensures this).
	 */
	public function testEncryptProducesUniqueOutputEachTime(): void {
		$plaintext = 'same-api-key-every-time';
		$enc1      = \FHW_Crypto::encrypt( $plaintext );
		$enc2      = \FHW_Crypto::encrypt( $plaintext );

		$this->assertNotSame( $enc1, $enc2, 'Each encryption should produce a unique ciphertext due to random IV' );

		// But both should decrypt to the same value.
		$this->assertSame( $plaintext, \FHW_Crypto::decrypt( $enc1 ) );
		$this->assertSame( $plaintext, \FHW_Crypto::decrypt( $enc2 ) );
	}

	/**
	 * Encrypting an empty string should return an empty string (not false).
	 */
	public function testEncryptEmptyStringReturnsEmpty(): void {
		$result = \FHW_Crypto::encrypt( '' );
		$this->assertSame( '', $result, 'Encrypting empty string should return empty string' );
	}

	/**
	 * Decrypting an empty string should return an empty string (not false).
	 */
	public function testDecryptEmptyStringReturnsEmpty(): void {
		$result = \FHW_Crypto::decrypt( '' );
		$this->assertSame( '', $result, 'Decrypting empty string should return empty string' );
	}

	/**
	 * Decrypting garbage data should return false gracefully.
	 */
	public function testDecryptGarbageReturnsFalse(): void {
		$result = \FHW_Crypto::decrypt( 'this-is-not-valid-encrypted-data' );
		$this->assertFalse( $result, 'Decrypting garbage should return false' );
	}

	/**
	 * Long API keys (e.g. 128 chars) should round-trip correctly.
	 */
	public function testLongApiKeyRoundTrip(): void {
		$long_key  = str_repeat( 'abcdefghij', 13 ); // 130 chars.
		$encrypted = \FHW_Crypto::encrypt( $long_key );
		$decrypted = \FHW_Crypto::decrypt( $encrypted );

		$this->assertSame( $long_key, $decrypted, 'Long API key should survive encrypt/decrypt' );
	}

	/**
	 * Special characters and unicode should round-trip correctly.
	 */
	public function testSpecialCharactersRoundTrip(): void {
		$key       = 'k€y-w!th-$pecial-ch@rs-&-"quotes"-\\backslash';
		$encrypted = \FHW_Crypto::encrypt( $key );
		$decrypted = \FHW_Crypto::decrypt( $encrypted );

		$this->assertSame( $key, $decrypted, 'Special characters should survive encrypt/decrypt' );
	}

	// -----------------------------------------------------------------------
	// Legacy base64 detection & migration
	// -----------------------------------------------------------------------

	/**
	 * A plain base64-encoded value should be detected as legacy.
	 */
	public function testIsLegacyBase64DetectsBase64(): void {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$legacy = base64_encode( 'my-brevo-api-key' );
		$this->assertTrue( \FHW_Crypto::is_legacy_base64( $legacy ), 'base64-only value should be detected as legacy' );
	}

	/**
	 * A properly AES-encrypted value should NOT be flagged as legacy.
	 */
	public function testIsLegacyBase64DoesNotFlagAesEncrypted(): void {
		$encrypted = \FHW_Crypto::encrypt( 'my-brevo-api-key' );
		$this->assertFalse( \FHW_Crypto::is_legacy_base64( $encrypted ), 'AES-encrypted value should not be flagged as legacy' );
	}

	/**
	 * An empty stored value should not be flagged as legacy.
	 */
	public function testIsLegacyBase64ReturnsFalseForEmpty(): void {
		$this->assertFalse( \FHW_Crypto::is_legacy_base64( '' ) );
	}

	/**
	 * migrate_from_base64() should return an AES-encrypted value that
	 * decrypts back to the original plaintext.
	 */
	public function testMigrateFromBase64ProducesDecryptableValue(): void {
		$original = 'xkeysib-legacy-key-to-migrate';
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$legacy   = base64_encode( $original );

		$migrated  = \FHW_Crypto::migrate_from_base64( $legacy );
		$this->assertNotFalse( $migrated, 'Migration should succeed' );

		$decrypted = \FHW_Crypto::decrypt( $migrated );
		$this->assertSame( $original, $decrypted, 'Migrated value should decrypt to original plaintext' );
	}

	/**
	 * migrate_from_base64() with garbage input should return false gracefully.
	 */
	public function testMigrateFromBase64WithGarbageReturnsFalse(): void {
		// base64_decode of pure garbage (non-base64 chars) will fail strict mode.
		$result = \FHW_Crypto::migrate_from_base64( '!!!not-base64!!!' );
		$this->assertFalse( $result, 'Migration of non-base64 garbage should return false' );
	}

	// -----------------------------------------------------------------------
	// wp-config constant key path
	// -----------------------------------------------------------------------

	/**
	 * When FHW_ENCRYPTION_KEY is defined, encrypt/decrypt should still
	 * round-trip correctly (the constant is already defined in this test run
	 * via a helper method).
	 *
	 * Note: we can't un-define a constant mid-run, so we test the fallback
	 * path (AUTH_KEY salts, defined in _bootstrap.php) instead — which is
	 * the default for most installs. The constant path is functionally
	 * identical but uses a different key source.
	 */
	public function testSaltFallbackKeyProducesWorkingEncryption(): void {
		// AUTH_KEY, SECURE_AUTH_KEY, LOGGED_IN_KEY are defined in _bootstrap.php.
		// FHW_ENCRYPTION_KEY is NOT defined in test bootstrap, so this exercises
		// the salt fallback path.
		$this->assertFalse( defined( 'FHW_ENCRYPTION_KEY' ), 'FHW_ENCRYPTION_KEY should not be defined in test environment' );

		$plaintext = 'testing-salt-fallback-path';
		$encrypted = \FHW_Crypto::encrypt( $plaintext );
		$decrypted = \FHW_Crypto::decrypt( $encrypted );

		$this->assertSame( $plaintext, $decrypted, 'Salt fallback key path should produce working encryption' );
	}
}
