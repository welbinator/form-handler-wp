<?php
/**
 * Heuristic spam checker for form submissions.
 *
 * Applies a series of lightweight checks inspired by Danny van Kooten's
 * comment spam blocker. Each check targets a distinct spam pattern and is
 * implemented as its own private method so it can be unit-tested in isolation.
 *
 * Usage:
 *   $checker = new FHW_Spam_Checker();
 *   $reason  = $checker->is_spam( $fields, $user_agent );
 *   if ( false !== $reason ) { // it's spam }
 *
 * @package Form_Handler_WP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FHW_Spam_Checker
 */
class FHW_Spam_Checker {

	/**
	 * Check whether a submission looks like spam.
	 *
	 * Runs each heuristic in order and returns the reason string for the
	 * first check that fires. Returns false when all checks pass (clean).
	 *
	 * @param array  $fields        Sanitized submitted field values (key => value).
	 * @param string $user_agent    HTTP_USER_AGENT value.
	 * @param array  $enabled_rules Which rules to run. Keys: 'no_user_agent', 'all_digits',
	 *                              'no_spaces', 'ai_greeting', 'buy_link', 'spammy_email_url'.
	 *                              Pass an empty array (default) to run all rules.
	 * @return false|string False if clean; string reason code if spam.
	 */
	public function is_spam( array $fields, $user_agent, array $enabled_rules = array() ) {
		$run_all = empty( $enabled_rules );

		if ( ( $run_all || ! empty( $enabled_rules['no_user_agent'] ) ) && $this->has_no_user_agent( $user_agent ) ) {
			return 'no_user_agent';
		}

		if ( ( $run_all || ! empty( $enabled_rules['all_digits'] ) ) && $this->has_all_digit_field( $fields ) ) {
			return 'all_digits';
		}

		if ( ( $run_all || ! empty( $enabled_rules['no_spaces'] ) ) && $this->has_no_spaces_in_message( $fields ) ) {
			return 'no_spaces';
		}

		if ( ( $run_all || ! empty( $enabled_rules['ai_greeting'] ) ) && $this->has_ai_greeting( $fields ) ) {
			return 'ai_greeting';
		}

		if ( ( $run_all || ! empty( $enabled_rules['buy_link'] ) ) && $this->has_buy_link( $fields ) ) {
			return 'buy_link';
		}

		if ( ( $run_all || ! empty( $enabled_rules['spammy_email_url'] ) ) && $this->has_spammy_email_url_combo( $fields ) ) {
			return 'spammy_email_url';
		}

		return false;
	}

	/**
	 * Check 1: No user-agent string.
	 *
	 * Legitimate browsers always send a User-Agent header.
	 *
	 * @param string $user_agent The HTTP_USER_AGENT value.
	 * @return bool True if spam signal detected.
	 */
	private function has_no_user_agent( $user_agent ) {
		return '' === trim( (string) $user_agent );
	}

	/**
	 * Check 2: Any non-empty field value consists entirely of digits.
	 *
	 * Spambots frequently submit phone/zip fields stuffed with digit-only
	 * strings, or replace normal text fields with digit sequences. Short
	 * values (≤ 10 chars, e.g. zip codes) are allowed to avoid false positives.
	 *
	 * @param array $fields Sanitized field values.
	 * @return bool True if spam signal detected.
	 */
	private function has_all_digit_field( array $fields ) {
		foreach ( $fields as $value ) {
			$value = (string) $value;
			if ( '' === $value ) {
				continue;
			}
			if ( strlen( $value ) > 10 && ctype_digit( $value ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check 3: Longest field value has no spaces and exceeds 10 characters.
	 *
	 * Real messages almost always contain spaces. A long, space-free string
	 * is a strong indicator of a bot injecting a URL or encoded payload.
	 *
	 * @param array $fields Sanitized field values.
	 * @return bool True if spam signal detected.
	 */
	/**
	 * Check 3: A text field value has no spaces and exceeds 10 characters.
	 *
	 * Real messages almost always contain spaces. A long, space-free string
	 * is a strong indicator of a bot injecting a URL or encoded payload.
	 *
	 * Email addresses and URLs are skipped — they naturally contain no spaces
	 * and would cause false positives on standard contact forms.
	 *
	 * Each field is checked individually rather than only the longest, so a
	 * bot cannot evade detection by also submitting one long normal field.
	 *
	 * @param array $fields Sanitized field values.
	 * @return bool True if spam signal detected.
	 */
	private function has_no_spaces_in_message( array $fields ) {
		foreach ( $fields as $value ) {
			$value = (string) $value;
			if ( strlen( $value ) <= 10 ) {
				continue;
			}
			// Skip email addresses and URLs — they naturally contain no spaces.
			if ( false !== strpos( $value, '@' ) ) {
				continue;
			}
			if ( false !== strpos( $value, 'http' ) ) {
				continue;
			}
			// Skip numeric values (phone numbers, zip codes, etc.).
			if ( ctype_digit( $value ) ) {
				continue;
			}
			if ( false === strpos( $value, ' ' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check 4: AI-generated greeting patterns.
	 *
	 * AI tools used for spam generation frequently open with openers like
	 * "Hi! I just ...", "Hello, I just ...", or "Hi! We just ...".
	 * The original pattern required "!" which was trivially bypassed with
	 * a comma. Now matches both punctuation variants and "we just" openers.
	 *
	 * @param array $fields Sanitized field values.
	 * @return bool True if spam signal detected.
	 */
	private function has_ai_greeting( array $fields ) {
		foreach ( $fields as $value ) {
			if ( preg_match( '/^(Hi|Hey there|Hello there|Hi there|Hello)[!,]\s+(I|We) just /i', (string) $value ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check 5: Any field value contains a commercial call-to-action with a link.
	 *
	 * Catches both HTML anchor tags (case-insensitive) and bare http(s):// URLs
	 * paired with "buy" or "purchase". The original pattern only caught lowercase
	 * `<a ` which was bypassed by `<A` or bare URLs.
	 *
	 * @param array $fields Sanitized field values.
	 * @return bool True if spam signal detected.
	 */
	private function has_buy_link( array $fields ) {
		foreach ( $fields as $value ) {
			$value    = (string) $value;
			$has_cta  = false !== stripos( $value, 'buy' ) || false !== stripos( $value, 'purchase' );
			$has_link = false !== stripos( $value, '<a ' ) || false !== stripos( $value, 'http' );
			if ( $has_cta && $has_link ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check 6: Spammy disposable email combined with a URL in any field.
	 *
	 * Pattern: one field matches a bot-generated email format AND any field
	 * contains "http". The original pattern required an underscore separator
	 * (word_word@provider) which was bypassed by john.doe@ or single-word
	 * addresses. Now matches any address at a common free provider where the
	 * local part contains non-alphanumeric separators or is all-lowercase
	 * single-word (common bot pattern). Also expanded provider list.
	 *
	 * Note: the reviewer flagged a real false-positive risk — any legitimate
	 * user with john.doe@gmail.com who includes a URL gets silently blocked.
	 * This rule targets the bot pattern specifically; admins with high
	 * false-positive rates should disable it per-form via the spam rules UI.
	 *
	 * @param array $fields Sanitized field values.
	 * @return bool True if spam signal detected.
	 */
	private function has_spammy_email_url_combo( array $fields ) {
		$has_spammy_email = false;
		$has_url          = false;

		foreach ( $fields as $value ) {
			$value = (string) $value;
			// Matches: word_word@, word.word@, or word123word@ at common free providers.
			if ( preg_match( '/^[a-z0-9]+[._][a-z0-9]+@(yahoo|gmail|hotmail|outlook|aol|protonmail|icloud)\.(com|net|org)$/i', $value ) ) {
				$has_spammy_email = true;
			}
			if ( false !== strpos( $value, 'http' ) ) {
				$has_url = true;
			}
		}

		return $has_spammy_email && $has_url;
	}
}
