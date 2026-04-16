<?php

declare(strict_types=1);

/**
 * Acceptance tests for fhw-forms.js custom DOM events.
 *
 * ## How to run
 *
 * 1. Start chromedriver on the host:
 *      chromedriver --port=9515 --allowed-ips= --allowed-origins=* &
 *
 * 2. Run the suite:
 *      ddev exec "cd wp-content/plugins/form-handler-wp && vendor/bin/codecept run Acceptance -v"
 *
 *    Or use the helper script from the DDEV project root:
 *      ./run-acceptance.sh
 *
 * ## One-time DDEV setup (already done, documented here for reference)
 *
 * The following was run once to configure the test environment:
 *
 *   wp config set FHW_TEST_MODE true --raw --path=/var/www/html
 *   wp option update fhw_brevo_api_key_enc '<encrypted-test-key>' --path=/var/www/html
 *   wp option update fhw_brevo_from_email test@example.com --path=/var/www/html
 *   wp option update fhw_brevo_from_name Test --path=/var/www/html
 *
 * The mu-plugin at wp-content/mu-plugins/fhw-test-stub.php stubs Brevo HTTP calls
 * when FHW_TEST_MODE is defined, so no real API key is needed.
 *
 * @package Form_Handler_WP
 */
class FormEventsCest
{
	private const ACTION    = 'fhw_test_form';
	private const PAGE_SLUG = 'fhw-test-page';

	/** WordPress admin credentials (matches DDEV test install). */
	private const WP_USER = 'james';
	private const WP_PASS = 'pepsidude';

	/** JS snippet that attaches event listeners and writes to #fhw-event-log. */
	private const ATTACH_LISTENERS_JS = '
		var log = document.getElementById("fhw-event-log");
		if ( log && ! window.__fhwListenersAttached ) {
			window.__fhwListenersAttached = true;
			["fhw:submit","fhw:success","fhw:error"].forEach(function(name) {
				document.addEventListener(name, function(e) {
					var extra = "";
					if ( name === "fhw:submit" ) {
						// e.target is the form; find the submit button inside the form
						var form = e.target;
						var btn = form ? form.querySelector("[type=submit]") : null;
						extra = " btn:" + (btn ? btn.textContent.trim() : "none");
					}
					if (log) log.textContent += name + " fired - action: " + e.detail.action + extra + "\n";
				});
			});
		}
	';

	/** JS that dispatches a submit event on the form. */
	private const SUBMIT_FORM_JS = 'document.querySelector("[data-fhw-form]").dispatchEvent(new Event("submit", {bubbles:true,cancelable:true}));';

	// -----------------------------------------------------------------------
	// Lifecycle
	// -----------------------------------------------------------------------

	public function _before( AcceptanceTester $I ): void
	{
		$this->provisionTestData();
		$this->loginToAdmin( $I );
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
		$I->executeJS( self::ATTACH_LISTENERS_JS );

		$I->fillField( '[name="name"]', 'Test User' );
		$I->fillField( '[name="email"]', 'test@example.com' );
		$I->fillField( '[name="message"]', 'Hello from Codeception acceptance test.' );
		$I->executeJS( self::SUBMIT_FORM_JS );

		$I->waitForText( 'fhw:submit', 15, '#fhw-event-log' );
		$I->waitForText( 'fhw:success', 15, '#fhw-event-log' );

		$I->waitForElementVisible( '[data-fhw-status]', 5 );
		$I->seeElement( '.fhw-status--success' );
	}

	/**
	 * A bad nonce triggers fhw:submit then fhw:error.
	 */
	public function errorSubmitFiresEvents( AcceptanceTester $I ): void
	{
		$I->amOnPage( '/' . self::PAGE_SLUG . '/' );
		$I->waitForElement( '[data-fhw-form="' . self::ACTION . '"]', 10 );
		$I->executeJS( self::ATTACH_LISTENERS_JS );

		// Poison the nonce so the server rejects the request.
		$I->executeJS( 'document.getElementById("fhw-nonce-' . self::ACTION . '").value = "bad_nonce";' );

		$I->fillField( '[name="name"]', 'Error Test' );
		$I->fillField( '[name="email"]', 'error@example.com' );
		$I->fillField( '[name="message"]', 'Nonce poisoned — expecting fhw:error.' );
		$I->executeJS( self::SUBMIT_FORM_JS );

		$I->waitForText( 'fhw:submit', 10, '#fhw-event-log' );
		$I->waitForText( 'fhw:error', 10, '#fhw-event-log' );

		$I->waitForElementVisible( '[data-fhw-status]', 5 );
		$I->seeElement( '.fhw-status--error' );
	}

	/**
	 * The submit button is re-enabled after submission completes.
	 */
	public function submitButtonReEnablesAfterSubmission( AcceptanceTester $I ): void
	{
		$I->amOnPage( '/' . self::PAGE_SLUG . '/' );
		$I->waitForElement( '[type="submit"]', 10 );
		$I->executeJS( self::ATTACH_LISTENERS_JS );

		$I->fillField( '[name="name"]', 'Button Test' );
		$I->fillField( '[name="email"]', 'button@example.com' );
		$I->fillField( '[name="message"]', 'Checking button state.' );
		$I->executeJS( self::SUBMIT_FORM_JS );

		// Wait for the request to complete (either success or error event).
		$I->waitForText( 'fhw:success', 10, '#fhw-event-log' );

		// Button should be re-enabled after completion.
		$buttonDisabled = (bool) $I->executeJS( 'return document.querySelector("[type=submit]").disabled;' );
		$I->comment( $buttonDisabled ? 'Button is disabled (FAIL)' : 'Button is enabled (PASS)' );
		$I->seeInPageSource( '<button type="submit"' );
		// Verify not disabled via JS assertion written to event log.
		$I->executeJS( '
			var btn = document.querySelector("[type=submit]");
			var log = document.getElementById("fhw-event-log");
			if (log) log.textContent += (btn.disabled ? "button:disabled" : "button:enabled") + "\n";
		' );
		$I->see( 'button:enabled', '#fhw-event-log' );
	}

	/**
	 * The status element receives focus after a successful submission.
	 */
	public function statusElementReceivesFocus( AcceptanceTester $I ): void
	{
		$I->amOnPage( '/' . self::PAGE_SLUG . '/' );
		$I->waitForElement( '[data-fhw-form="' . self::ACTION . '"]', 10 );
		$I->executeJS( self::ATTACH_LISTENERS_JS );

		$I->fillField( '[name="name"]', 'Focus Test' );
		$I->fillField( '[name="email"]', 'focus@example.com' );
		$I->fillField( '[name="message"]', 'Checking focus management.' );
		$I->executeJS( self::SUBMIT_FORM_JS );

		$I->waitForElementVisible( '[data-fhw-status]', 10 );
		$I->waitForText( 'fhw:success', 5, '#fhw-event-log' );

		// Verify focus via JS, write result to event log for assertion.
		$I->executeJS( '
			var log = document.getElementById("fhw-event-log");
			var focused = document.activeElement && document.activeElement.hasAttribute("data-fhw-status");
			if (log) log.textContent += (focused ? "focus:status" : "focus:other") + "\n";
		' );
		$I->see( 'focus:status', '#fhw-event-log' );
	}

	/**
	 * data-fhw-loading-text is shown on the button during submission.
	 */
	public function loadingTextShownOnButton( AcceptanceTester $I ): void
	{
		$I->amOnPage( '/' . self::PAGE_SLUG . '/' );
		$I->waitForElement( '[type="submit"]', 10 );
		$I->executeJS( self::ATTACH_LISTENERS_JS );

		$I->fillField( '[name="name"]', 'Loading Text Test' );
		$I->fillField( '[name="email"]', 'loading@example.com' );
		$I->fillField( '[name="message"]', 'Checking loading text.' );
		$I->executeJS( self::SUBMIT_FORM_JS );

		// The ATTACH_LISTENERS_JS captures btn.textContent in the fhw:submit log entry.
		// setSubmitting() runs before fhw:submit fires, so the text should be "Sending...".
		$I->waitForText( 'fhw:submit', 10, '#fhw-event-log' );
		$I->see( 'btn:Sending', '#fhw-event-log' );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Provision the test form handler and test page via WP-CLI (idempotent).
	 */
	private function provisionTestData(): void
	{
		$wpPath = '/var/www/html';
		$action = self::ACTION;
		$slug   = self::PAGE_SLUG;

		// Register the form handler if not already registered.
		$forms = (string) shell_exec( "wp option get fhw_registered_forms --path={$wpPath} 2>/dev/null" );
		if ( ! str_contains( $forms, $action ) ) {
			$formData = json_encode(
				array(
					array(
						'action_name'                => $action,
						'to_emails'                  => 'test@example.com',
						'subject_tpl'                => 'Test submission from {name}',
						'reply_to_field'             => 'email',
						'success_message'            => 'Thank you! Your message has been sent.',
						'html_email'                 => '0',
						'autoreply_enabled'          => '0',
						'autoreply_to_field'         => '',
						'autoreply_subject'          => '',
						'autoreply_message'          => '',
						'honeypot_field'             => '',
						'rate_limit'                 => 0,
						'spam_filter'                => '0',
						'field_schema'               => array(),
						'spam_rule_no_user_agent'    => '0',
						'spam_rule_all_digits'       => '0',
						'spam_rule_no_spaces'        => '0',
						'spam_rule_ai_greeting'      => '0',
						'spam_rule_buy_link'         => '0',
						'spam_rule_spammy_email_url' => '0',
					),
				)
			);
			shell_exec( "wp option update fhw_registered_forms " . escapeshellarg( (string) $formData ) . " --path={$wpPath} 2>/dev/null" );
		}

		// Create the test page if it doesn't exist.
		$existingId = trim( (string) shell_exec( "wp post list --post_type=page --name={$slug} --field=ID --path={$wpPath} 2>/dev/null" ) );
		if ( empty( $existingId ) ) {
			shell_exec(
				'wp post create --post_type=page --post_status=publish'
				. ' --post_title=' . escapeshellarg( 'FHW Test Page' )
				. ' --post_name=' . escapeshellarg( $slug )
				. ' --post_content=' . escapeshellarg( $this->buildTestPageContent() )
				. " --path={$wpPath} 2>/dev/null"
			);
		}
	}

	/**
	 * Log in to WordPress admin.
	 */
	private function loginToAdmin( AcceptanceTester $I ): void
	{
		$I->amOnPage( '/wp-login.php' );
		$url = $I->grabFromCurrentUrl();
		if ( str_contains( $url, 'wp-admin' ) || str_contains( $url, 'dashboard' ) ) {
			return;
		}
		$I->waitForElement( '#user_login', 5 );
		$I->fillField( '#user_login', self::WP_USER );
		$I->fillField( '#user_pass', self::WP_PASS );
		$I->click( '#wp-submit' );
		$I->waitForElement( 'body.wp-admin', 10 );
	}

	/**
	 * Build the HTML for the test page — form + event log div.
	 * The inline script is a fallback; tests also attach listeners via executeJS.
	 */
	private function buildTestPageContent(): string
	{
		$action = self::ACTION;
		return <<<HTML
<form data-fhw-form="{$action}" data-fhw-reset="false">
  <p><label for="fhw-name">Name</label><br><input type="text" id="fhw-name" name="name" required /></p>
  <p><label for="fhw-email">Email</label><br><input type="email" id="fhw-email" name="email" required /></p>
  <p><label for="fhw-message">Message</label><br><textarea id="fhw-message" name="message" required></textarea></p>
  <p><button type="submit" data-fhw-loading-text="Sending...">Send</button></p>
  <div data-fhw-status></div>
</form>
<div id="fhw-event-log" style="margin-top:20px;padding:10px;background:#f0f0f0;font-family:monospace;white-space:pre;min-height:20px;"></div>
HTML;
	}
}
