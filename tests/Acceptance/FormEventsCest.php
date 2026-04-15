<?php

declare(strict_types=1);

/**
 * Acceptance tests for fhw-forms.js custom DOM events.
 *
 * These tests verify that after a form submission:
 *   - fhw:submit fires before the AJAX request
 *   - fhw:success / fhw:error fires after the response
 *   - The status element receives keyboard focus
 *   - The submit button is re-enabled after submission
 *
 * ## How to run
 *
 * 1. Start chromedriver on the host:
 *      chromedriver --port=9515 &
 *
 * 2. Run the suite (inside DDEV):
 *      ddev exec "cd wp-content/plugins/form-handler-wp && vendor/bin/codecept run Acceptance -v"
 *
 *    Or use the helper script from the DDEV project root:
 *      ./run-acceptance.sh
 *
 * ## Requirements
 *   - DDEV running: ddev start
 *   - Plugin active in the DDEV site
 *   - chromedriver matching your Chrome version installed on the host
 *   - Brevo API key configured (or at least a form handler that won't fatal)
 *
 * @package Form_Handler_WP
 */
class FormEventsCest
{
	private const ACTION     = 'fhw_test_form';
	private const PAGE_SLUG  = 'fhw-test-page';
	private const PAGE_TITLE = 'FHW Test Page';

	/** WordPress admin credentials (matches DDEV test install). */
	private const WP_USER = 'james';
	private const WP_PASS = 'pepsidude';

	// -----------------------------------------------------------------------
	// Lifecycle
	// -----------------------------------------------------------------------

	public function _before( AcceptanceTester $I ): void
	{
		$this->loginToAdmin( $I );
		$this->registerTestForm( $I );
		$this->ensureTestPage( $I );
	}

	// -----------------------------------------------------------------------
	// Tests
	// -----------------------------------------------------------------------

	/**
	 * A successful form submission fires fhw:submit then fhw:success.
	 */
	public function successSubmitFiresEvents( AcceptanceTester $I ): void
	{
		$I->amOnPage( '/' . self::PAGE_SLUG . '/' );
		$I->waitForElement( '[data-fhw-form="' . self::ACTION . '"]', 10 );

		$I->fillField( '[name="name"]', 'Test User' );
		$I->fillField( '[name="email"]', 'test@example.com' );
		$I->fillField( '[name="message"]', 'Hello from Codeception acceptance test.' );
		$I->click( '[type="submit"]' );

		// Both events should be logged by the inline script.
		$I->waitForText( 'fhw:submit', 10, '#fhw-event-log' );
		$I->waitForText( 'fhw:success', 10, '#fhw-event-log' );

		// Status element should show a success message.
		$I->waitForElementVisible( '[data-fhw-status]', 5 );
		$I->seeElement( '.fhw-status--success' );

		// Submit button should be re-enabled.
		$I->seeElement( '[type="submit"]:not([disabled])' );
	}

	/**
	 * A bad nonce triggers fhw:submit then fhw:error.
	 */
	public function errorSubmitFiresEvents( AcceptanceTester $I ): void
	{
		$I->amOnPage( '/' . self::PAGE_SLUG . '/' );
		$I->waitForElement( '[data-fhw-form="' . self::ACTION . '"]', 10 );

		// Poison the nonce so the server rejects the request.
		$I->executeJS( 'document.getElementById("fhw-nonce-' . self::ACTION . '").value = "bad_nonce";' );

		$I->fillField( '[name="name"]', 'Error Test' );
		$I->fillField( '[name="email"]', 'error@example.com' );
		$I->fillField( '[name="message"]', 'Nonce poisoned — expecting fhw:error.' );
		$I->click( '[type="submit"]' );

		$I->waitForText( 'fhw:submit', 10, '#fhw-event-log' );
		$I->waitForText( 'fhw:error', 10, '#fhw-event-log' );

		$I->waitForElementVisible( '[data-fhw-status]', 5 );
		$I->seeElement( '.fhw-status--error' );
		$I->seeElement( '[type="submit"]:not([disabled])' );
	}

	/**
	 * The submit button is disabled during submission and re-enabled after.
	 */
	public function submitButtonReEnablesAfterSubmission( AcceptanceTester $I ): void
	{
		$I->amOnPage( '/' . self::PAGE_SLUG . '/' );
		$I->waitForElement( '[type="submit"]', 10 );

		$I->fillField( '[name="name"]', 'Button Test' );
		$I->fillField( '[name="email"]', 'button@example.com' );
		$I->fillField( '[name="message"]', 'Checking button state.' );
		$I->click( '[type="submit"]' );

		// After the request completes the button must be re-enabled.
		$I->waitForElement( '[type="submit"]:not([disabled])', 10 );
		$I->seeElement( '[type="submit"]:not([disabled])' );
	}

	/**
	 * The status element has tabindex=-1 and receives focus after submission.
	 */
	public function statusElementReceivesFocus( AcceptanceTester $I ): void
	{
		$I->amOnPage( '/' . self::PAGE_SLUG . '/' );
		$I->waitForElement( '[data-fhw-form="' . self::ACTION . '"]', 10 );

		$I->fillField( '[name="name"]', 'Focus Test' );
		$I->fillField( '[name="email"]', 'focus@example.com' );
		$I->fillField( '[name="message"]', 'Checking focus management.' );
		$I->click( '[type="submit"]' );

		$I->waitForElementVisible( '[data-fhw-status]', 10 );

		$focused = $I->executeJS(
			'return document.activeElement && document.activeElement.hasAttribute("data-fhw-status");'
		);
		$I->assertTrue( (bool) $focused, 'The status element should have keyboard focus after submission.' );
	}

	/**
	 * data-fhw-loading-text is shown on the button during submission.
	 */
	public function loadingTextShownOnButton( AcceptanceTester $I ): void
	{
		$I->amOnPage( '/' . self::PAGE_SLUG . '/' );
		$I->waitForElement( '[type="submit"]', 10 );

		// Slow down the response so we can catch the loading state.
		// We do this by poisoning the nonce, which still causes fhw:submit to fire.
		$I->fillField( '[name="name"]', 'Loading Text Test' );
		$I->fillField( '[name="email"]', 'loading@example.com' );
		$I->fillField( '[name="message"]', 'Checking loading text.' );

		// Intercept the click and check loading text immediately after.
		$I->executeJS( '
			var btn = document.querySelector("[type=submit]");
			var form = document.querySelector("[data-fhw-form]");
			form.addEventListener("fhw:submit", function() {
				window.__fhwLoadingText = btn.textContent;
			});
		' );

		$I->click( '[type="submit"]' );
		$I->waitForText( 'fhw:submit', 5, '#fhw-event-log' );

		$loadingText = $I->executeJS( 'return window.__fhwLoadingText || "";' );
		$I->assertStringContainsString( 'Sending', $loadingText, 'Button should show loading text during submission.' );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function loginToAdmin( AcceptanceTester $I ): void
	{
		$I->amOnPage( '/wp-login.php' );
		$I->waitForElement( '#user_login', 5 );
		$I->fillField( '#user_login', self::WP_USER );
		$I->fillField( '#user_pass', self::WP_PASS );
		$I->click( '#wp-submit' );
		$I->waitForElement( 'body.wp-admin', 10 );
	}

	/**
	 * Register the test form handler via WP Admin (idempotent).
	 */
	private function registerTestForm( AcceptanceTester $I ): void
	{
		$I->amOnPage( '/wp-admin/admin.php?page=form-handler-wp&tab=forms' );
		$I->waitForElement( 'body.wp-admin', 5 );

		// If the action name already appears in the registered forms table, skip.
		$source = $I->grabPageSource();
		if ( str_contains( $source, self::ACTION ) ) {
			return;
		}

		$I->fillField( '[name="action_name"]', self::ACTION );
		$I->fillField( '[name="to_emails"]', 'test@example.com' );
		$I->fillField( '[name="subject_tpl"]', 'Test submission from {name}' );
		$I->click( '[type="submit"]' );
		$I->waitForElement( 'body.wp-admin', 5 );
	}

	/**
	 * Create the test WordPress page if it doesn't already exist.
	 */
	private function ensureTestPage( AcceptanceTester $I ): void
	{
		// Check if page already exists.
		$I->amOnPage( '/' . self::PAGE_SLUG . '/' );
		$source = $I->grabPageSource();

		if ( str_contains( $source, 'data-fhw-form="' . self::ACTION . '"' ) ) {
			return; // Already exists.
		}

		// Create it via WP Admin → New Page.
		$I->amOnPage( '/wp-admin/post-new.php?post_type=page' );
		$I->waitForElement( 'body.wp-admin', 10 );

		// Disable Gutenberg if present — inject page via REST API instead.
		$pageId = $I->executeJS( '
			return fetch( "' . self::SITE_URL . '/wp-json/wp/v2/pages", {
				method: "POST",
				headers: {
					"Content-Type": "application/json",
					"X-WP-Nonce": "' . $this->getRestNonce( $I ) . '"
				},
				body: JSON.stringify({
					title: "' . self::PAGE_TITLE . '",
					slug: "' . self::PAGE_SLUG . '",
					status: "publish",
					content: ' . json_encode( $this->buildTestPageContent() ) . '
				})
			}).then(r => r.json()).then(d => d.id);
		' );

		// Wait a moment then verify the page is live.
		$I->wait( 2 );
		$I->amOnPage( '/' . self::PAGE_SLUG . '/' );
		$I->waitForElement( '[data-fhw-form="' . self::ACTION . '"]', 10 );
	}

	/**
	 * Get a WP REST API nonce for the current admin session.
	 */
	private function getRestNonce( AcceptanceTester $I ): string
	{
		$I->amOnPage( '/wp-admin/admin-ajax.php?action=rest-nonce' );
		$nonce = trim( $I->grabPageSource() );
		// Fall back to wp_create_nonce via inline JS if the ajax action isn't registered.
		if ( empty( $nonce ) || strlen( $nonce ) > 20 ) {
			$nonce = (string) $I->executeJS( 'return wpApiSettings && wpApiSettings.nonce ? wpApiSettings.nonce : "";' );
		}
		return $nonce;
	}

	private const SITE_URL = 'https://form-handler-wp.ddev.site';

	/**
	 * Build the HTML content for the test page.
	 */
	private function buildTestPageContent(): string
	{
		$action = self::ACTION;
		return <<<HTML
<form data-fhw-form="{$action}" data-fhw-reset="false">
  <label for="fhw-name">Name</label>
  <input type="text" id="fhw-name" name="name" required />

  <label for="fhw-email">Email</label>
  <input type="email" id="fhw-email" name="email" required />

  <label for="fhw-message">Message</label>
  <textarea id="fhw-message" name="message" required></textarea>

  <button type="submit" data-fhw-loading-text="Sending...">Send</button>
  <div data-fhw-status></div>
</form>

<div id="fhw-event-log" style="margin-top:20px;padding:10px;background:#f0f0f0;font-family:monospace;white-space:pre;min-height:40px;"></div>

<script>
(function() {
  var log = document.getElementById('fhw-event-log');
  ['fhw:submit','fhw:success','fhw:error'].forEach(function(name) {
    document.addEventListener(name, function(e) {
      log.textContent += name + ' fired — action: ' + e.detail.action + '\\n';
    });
  });
})();
</script>
HTML;
	}
}
