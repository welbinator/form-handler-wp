<?php
/**
 * Unit tests for FHW_Spam_Checker.
 *
 * Each test targets a specific heuristic check to verify the checker
 * correctly identifies spam patterns and avoids false positives.
 *
 * Run with: ddev exec vendor/bin/codecept run Unit
 *
 * @package Form_Handler_WP\Tests\Unit
 */

namespace Tests\Unit;

use Codeception\Test\Unit;

/**
 * Class FHWSpamCheckerTest
 */
class FHWSpamCheckerTest extends Unit {

	/**
	 * Spam checker instance under test.
	 *
	 * @var \FHW_Spam_Checker
	 */
	private $checker;

	/**
	 * Set up a fresh checker instance before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->checker = new \FHW_Spam_Checker();
	}

	// -----------------------------------------------------------------------
	// Clean submission
	// -----------------------------------------------------------------------

	/**
	 * A normal, clean submission should return false (not spam).
	 */
	public function testCleanSubmissionReturnsFalse(): void {
		$fields = array(
			'name'    => 'Jane Doe',
			'email'   => 'jane@example.com',
			'message' => 'Hello! I am interested in your services. Please get back to me.',
		);

		$result = $this->checker->is_spam( $fields, 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36' );

		$this->assertFalse( $result, 'Clean submission should not be flagged as spam' );
	}

	// -----------------------------------------------------------------------
	// Check 1: No user-agent
	// -----------------------------------------------------------------------

	/**
	 * An empty user-agent string should be flagged as spam.
	 */
	public function testEmptyUserAgentFlagsSpam(): void {
		$fields = array(
			'name'    => 'Jane Doe',
			'email'   => 'jane@example.com',
			'message' => 'Hello, I would like more information.',
		);

		$result = $this->checker->is_spam( $fields, '' );

		$this->assertSame( 'no_user_agent', $result, 'Empty user-agent should return no_user_agent reason' );
	}

	/**
	 * A whitespace-only user-agent string should also be flagged.
	 */
	public function testWhitespaceUserAgentFlagsSpam(): void {
		$fields = array(
			'name'    => 'Jane',
			'message' => 'Some message here.',
		);

		$result = $this->checker->is_spam( $fields, '   ' );

		$this->assertSame( 'no_user_agent', $result );
	}

	// -----------------------------------------------------------------------
	// Check 2: All-digit field value
	// -----------------------------------------------------------------------

	/**
	 * A field value that is entirely digits and longer than 10 chars should
	 * be flagged as spam.
	 */
	public function testAllDigitFieldFlagsSpam(): void {
		$fields = array(
			'name'    => 'Spambot',
			'message' => '12345678901234',
		);

		$result = $this->checker->is_spam( $fields, 'Mozilla/5.0' );

		$this->assertSame( 'all_digits', $result, 'All-digit field > 10 chars should return all_digits reason' );
	}

	/**
	 * A short all-digit value (≤ 10 chars, e.g. a zip code "52401") should
	 * NOT be flagged — this is the critical false-positive edge case.
	 */
	public function testShortAllDigitValueIsNotSpam(): void {
		$fields = array(
			'name'    => 'Jane Doe',
			'zip'     => '52401',
			'message' => 'Please contact me about your services.',
		);

		$result = $this->checker->is_spam( $fields, 'Mozilla/5.0 (Windows NT 10.0)' );

		$this->assertFalse( $result, 'Zip code (5-digit all-digits) should NOT be flagged as spam' );
	}

	/**
	 * An all-digit value exactly 10 chars should not be flagged (boundary).
	 */
	public function testTenDigitValueIsNotSpam(): void {
		$fields = array(
			'phone'   => '5551234567',
			'message' => 'Hi there, please call me back about this.',
		);

		$result = $this->checker->is_spam( $fields, 'Mozilla/5.0' );

		$this->assertFalse( $result, '10-digit value should not be flagged (boundary: must be > 10 to trigger)' );
	}

	// -----------------------------------------------------------------------
	// Check 3: No spaces in longest field
	// -----------------------------------------------------------------------

	/**
	 * A field value with no spaces that exceeds 10 characters should be
	 * flagged as spam.
	 */
	public function testNoSpacesInLongFieldFlagsSpam(): void {
		$fields = array(
			'name'    => 'Bot Name',
			'message' => 'BuyChea pViagr aOnline',
			'website' => 'https://spam-example-link.com/path/to/spam',
		);

		$result = $this->checker->is_spam( $fields, 'Mozilla/5.0' );

		$this->assertSame( 'no_spaces', $result, 'Long no-space field should return no_spaces reason' );
	}

	/**
	 * A short value with no spaces (≤ 10 chars) should NOT be flagged.
	 */
	public function testShortNoSpaceValueIsNotSpam(): void {
		$fields = array(
			'name'    => 'Jane',
			'message' => 'Hello there, how are you doing today in Cedar Rapids?',
		);

		$result = $this->checker->is_spam( $fields, 'Mozilla/5.0' );

		$this->assertFalse( $result, 'Short no-space value should not be flagged' );
	}

	// -----------------------------------------------------------------------
	// Check 4: AI greeting patterns
	// -----------------------------------------------------------------------

	/**
	 * A message starting with "Hi! I just " should be flagged.
	 */
	public function testAiGreetingHiFlagsSpam(): void {
		$fields = array(
			'name'    => 'Some Person',
			'email'   => 'some@example.com',
			'message' => 'Hi! I just came across your website and wanted to reach out.',
		);

		$result = $this->checker->is_spam( $fields, 'Mozilla/5.0' );

		$this->assertSame( 'ai_greeting', $result, '"Hi! I just" should return ai_greeting reason' );
	}

	/**
	 * A message starting with "Hello there! I just " should be flagged.
	 */
	public function testAiGreetingHelloThereFlagsSpam(): void {
		$fields = array(
			'message' => 'Hello there! I just noticed your site and think we could partner.',
		);

		$result = $this->checker->is_spam( $fields, 'Mozilla/5.0' );

		$this->assertSame( 'ai_greeting', $result );
	}

	/**
	 * A message starting with "Hey there! I just " should be flagged.
	 */
	public function testAiGreetingHeyThereFlagsSpam(): void {
		$fields = array(
			'message' => 'Hey there! I just read your blog post.',
		);

		$result = $this->checker->is_spam( $fields, 'Mozilla/5.0' );

		$this->assertSame( 'ai_greeting', $result );
	}

	/**
	 * AI greeting check should be case-insensitive.
	 */
	public function testAiGreetingIsCaseInsensitive(): void {
		$fields = array(
			'message' => 'HI! I just wanted to say hello.',
		);

		$result = $this->checker->is_spam( $fields, 'Mozilla/5.0' );

		$this->assertSame( 'ai_greeting', $result, 'AI greeting check must be case-insensitive' );
	}

	/**
	 * A normal "Hi, " greeting (without "I just") should NOT be flagged.
	 */
	public function testNormalHiGreetingIsNotSpam(): void {
		$fields = array(
			'name'    => 'Bob',
			'message' => 'Hi, I wanted to ask about your pricing.',
		);

		$result = $this->checker->is_spam( $fields, 'Mozilla/5.0 (Macintosh)' );

		$this->assertFalse( $result, 'Normal "Hi," greeting should not be flagged' );
	}

	// -----------------------------------------------------------------------
	// Check 5: Buy + hyperlink
	// -----------------------------------------------------------------------

	/**
	 * A field containing both "buy" and an <a  tag should be flagged.
	 */
	public function testBuyWithHyperlinkFlagsSpam(): void {
		$fields = array(
			'message' => 'You should buy our product <a href="https://spam.example.com">click here</a>.',
		);

		$result = $this->checker->is_spam( $fields, 'Mozilla/5.0' );

		$this->assertSame( 'buy_link', $result, 'Field with buy + <a should return buy_link reason' );
	}

	/**
	 * "buy" alone (no hyperlink) should NOT be flagged.
	 */
	public function testBuyAloneIsNotSpam(): void {
		$fields = array(
			'message' => 'I would like to buy your premium plan. Please send pricing.',
		);

		$result = $this->checker->is_spam( $fields, 'Mozilla/5.0 (iPhone)' );

		$this->assertFalse( $result, '"buy" without a hyperlink should not be flagged' );
	}

	/**
	 * "Buy" check should be case-insensitive.
	 */
	public function testBuyLinkIsCaseInsensitive(): void {
		$fields = array(
			'message' => 'BUY NOW <a href="http://spam.test">here</a>',
		);

		$result = $this->checker->is_spam( $fields, 'Mozilla/5.0' );

		$this->assertSame( 'buy_link', $result );
	}

	// -----------------------------------------------------------------------
	// Check 6: Spammy email + URL combo
	// -----------------------------------------------------------------------

	/**
	 * A "word_word@gmail.com" email combined with a URL in another field
	 * should be flagged.
	 */
	public function testSpammyEmailWithUrlFlagsSpam(): void {
		$fields = array(
			'name'    => 'John Doe',
			'email'   => 'john_doe@gmail.com',
			'message' => 'Please visit our site for more information about our services.',
			'website' => 'http://spam-site.example.com',
		);

		$result = $this->checker->is_spam( $fields, 'Mozilla/5.0' );

		$this->assertSame( 'spammy_email_url', $result, 'word_word@gmail.com + URL should return spammy_email_url reason' );
	}

	/**
	 * A "word_word@yahoo.com" pattern should also be caught.
	 */
	public function testSpammyYahooEmailWithUrlFlagsSpam(): void {
		$fields = array(
			'email'   => 'buy_cheap@yahoo.com',
			'message' => 'Check https://example.com for more.',
		);

		$result = $this->checker->is_spam( $fields, 'Mozilla/5.0' );

		$this->assertSame( 'spammy_email_url', $result );
	}

	/**
	 * A normal email (no underscore) + URL should NOT be flagged.
	 */
	public function testNormalEmailWithUrlIsNotSpam(): void {
		$fields = array(
			'email'   => 'janedoe@gmail.com',
			'website' => 'https://myportfolio.com',
			'message' => 'Please review my portfolio linked above.',
		);

		$result = $this->checker->is_spam( $fields, 'Mozilla/5.0 (Linux)' );

		$this->assertFalse( $result, 'Normal email without underscore pattern should not be flagged' );
	}

	/**
	 * Spammy email WITHOUT a URL in any other field should NOT be flagged.
	 */
	public function testSpammyEmailAloneIsNotSpam(): void {
		$fields = array(
			'email'   => 'john_doe@hotmail.com',
			'message' => 'Just checking in, no links here.',
		);

		$result = $this->checker->is_spam( $fields, 'Mozilla/5.0' );

		$this->assertFalse( $result, 'Spammy email pattern alone (no URL) should not be flagged' );
	}
}
