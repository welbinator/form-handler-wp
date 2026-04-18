# Changelog

All notable changes to Form Handler WP are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versions follow [Semantic Versioning](https://semver.org/).

---

## [1.3.4] — 2026-04-17

### Fixed
- **Plugin Checker compliance** — resolved all issues flagged by the WordPress Plugin Checker
  - `tests/_bootstrap.php` and `phpstan-bootstrap.php` now include proper direct file access guards
  - `FHW_Submissions::get_entries()` and `get_count()` now use `wp_cache_get()`/`wp_cache_set()` to satisfy `WordPress.DB.DirectDatabaseQuery.NoCaching`
  - Cache is busted via `wp_cache_flush_group()` after every write (insert, delete, truncate)

---

## [1.3.3] — 2026-04-17

### Fixed
- **GitHub updater cache bug** — when the cached release matched the installed version (no update available), the result was stored for 12 hours, preventing detection of a new release published within that window. The transient is now deleted immediately when no update is found, so the next WordPress update check always fetches fresh data from GitHub.

---

## [1.3.2] — 2026-04-17

### Improved
- **Registered Forms tab** — "Add New Form Handler" form is now hidden by default and revealed by clicking an "Add New Form Handler" button in the card header. Keeps the page clean when you just want to view or manage existing forms.

---

## [1.3.1] — 2026-04-17

### Added
- **GitHub auto-updates** — Plugin now checks GitHub Releases for newer versions. WordPress will show the standard "Update available" notice and allow one-click updates directly from the Plugins page, just like a WordPress.org plugin.
  - Checks GitHub API every 12 hours (cached)
  - Prefers the explicitly-built zip asset (correct folder name) over GitHub's auto-generated source zip
  - Skips pre-releases — only stable tags are offered as updates
  - Cache is busted immediately after a successful update

---

## [1.3.0] — 2026-04-17

### Added
- **Integrations system** — Extensible integration framework; new integrations are a single class
- **Mailchimp integration** — Subscribe form submitters to a Mailchimp audience on successful submission
  - Field mapping: email, first name, last name, phone, full address (addr1/addr2/city/state/zip/country)
  - Comma-separated tags (auto-created in Mailchimp if they don't exist)
  - Opt-in field gate — only subscribe if a named checkbox field equals "1"
  - Audience dropdown populated live from the Mailchimp API
  - API key stored encrypted via AES-256
- **ActiveCampaign integration** — Subscribe form submitters to an ActiveCampaign list on successful submission
  - Same field mapping, tags, and opt-in gate as Mailchimp
  - List dropdown populated live from the ActiveCampaign API
  - API key stored encrypted
- **Integrations settings tab** — Connect services globally (API keys); shows connected/not-connected status per integration
- **Per-form Integrations card** — Appears in Add/Edit form when any integration is connected; shows/hides on toggle

### Security
- Datacenter slug from Mailchimp API key validated against strict regex before URL construction (prevents hostname injection)
- Mailchimp list ID validated as hex string before URL path use
- ActiveCampaign API URL enforced as HTTPS in all request paths (prevents credential leakage over HTTP)
- Tag lengths capped at API limits (Mailchimp: 100 chars, ActiveCampaign: 255 chars)
- Country codes validated as 2-letter alpha before sending to Mailchimp
- `FHW_Crypto::decrypt()` called once per function (eliminates double-invocation)
- Error logging gated behind `WP_DEBUG` to prevent production log pollution
- Snyk and SonarCloud security scanning added to CI pipeline

### Fixed
- `Send Test Email` button was broken since v1.1.0 (method was registered as AJAX hook but never implemented)
- `no_spaces` spam rule was triggering false positives on email addresses and phone numbers
  - Now skips values containing `@` (emails), `http` (URLs), and all-digit values (phone numbers)
  - Now checks each field individually instead of only the longest field
- Unused `$short_circuit` parameter renamed to `$_short_circuit` (intentionally unused filter param)
- `FHW_Settings` instantiation warning resolved

### Improved
- **Responsive admin UI** — Settings page fully usable on mobile/narrow screens
  - Tab nav scrolls horizontally (no wrapping)
  - All data tables horizontally scrollable
  - Form fields stack (label above input) on small screens
  - Submission modal centers on mobile with max-height
- **Icon buttons** — Edit and Delete in the Registered Forms table now use dashicon pencil/trash icons
- **"Message ID" → "ID"** in Email Log table header
- JS modernised: `var` → `let`/`const`, `getAttribute` → `.dataset`, optional chaining, `window` → `globalThis`, deprecated `initCustomEvent` removed
- Submission modal converted to native `<dialog>` element for better screen reader support
- WCAG AA contrast ratio fixes in status badges and form status messages
- Test file repeated literals extracted to class constants

---

## [1.2.4] — 2026-04-16

### Added
- **Spam reason tracking** — spam-blocked submissions now record which rule triggered the block (e.g. `no_user_agent`, `all_digits`, `buy_link`)
- Spam rule key shown in the Submissions tab below the amber spam badge
- Spam rule key also shown in the submission detail modal

### Fixed
- Spam submissions were being saved with status `sent` instead of `spam` due to missing enum value in `FHW_Submissions::save()`
- Plugin data (options, DB tables) was being wiped when any copy of the plugin was deleted — `uninstall.php` is now a no-op to preserve data on reinstall/upgrade

---

## [1.2.3] — 2026-04-16

### Added
- **Spam reason tracking** — spam-blocked submissions now record which rule triggered the block (e.g. `no_user_agent`, `all_digits`, `buy_link`)
- Spam rule key shown in the Submissions tab below the amber spam badge
- Spam rule key also shown in the submission detail modal

### Fixed
- Spam submissions were being saved with status `sent` instead of `spam` due to missing enum value in `FHW_Submissions::save()`

---

## [1.2.3] — v1.2.3

### Added
- **Custom DOM events** — `fhw:submit`, `fhw:success`, and `fhw:error` are now fired on the form element after each submission, allowing developers to hook in analytics, redirects, modals, or custom validation without touching the plugin
- **Focus management** — keyboard focus is moved to the status element after submission so screen reader users land directly on the result
- **`data-fhw-loading-text` attribute** on the submit button — customise the button text shown while the request is in flight (default: “Sending…”)
- **Help tab — Custom Events & Extensibility section** with code examples for analytics tracking, redirects, modals, and custom validation
- **Help tab — Accessibility section** documenting what the plugin handles automatically and what developers need to add for full WCAG 2.1 AA compliance
- **Help tab — Custom Validation section** with pattern for intercepting submit before the plugin handles it

### Added
- **Edit registered forms** — Each form in the Registered Forms table now has an Edit button that opens a pre-populated form with all existing config values (action name, recipient emails, subject, spam rules, autoreply settings, field schema, etc.)
- Edit form shows a warning if you change the action name (it will break existing HTML forms using the old value)
- Autoreply and spam rule sections expand automatically on the edit form if they were already enabled
- Cancel button returns to the forms list without saving

### Fixed
- Field schema rows now correctly pre-populate when editing a registered form

---

## [1.2.1] — 2026-04-15

### Added
- **Edit registered forms** — Each form in the Registered Forms table now has an Edit button that opens a pre-populated form with all existing config values (action name, recipient emails, subject, spam rules, autoreply settings, field schema, etc.)
- Edit form shows a warning if you change the action name (it will break existing HTML forms using the old value)
- Autoreply and spam rule sections expand automatically on the edit form if they were already enabled
- Cancel button returns to the forms list without saving
- README.md and CHANGELOG.md added to the repository

### Fixed
- Field schema rows now correctly pre-populate when editing a registered form

---

## [1.2.0] — 2026-04-15

### Added
- **Spam filtering** — Heuristic spam detection runs on every submission before the email is sent
- Six individually toggleable rules per form (all enabled by default):
  - Block requests with no browser user-agent
  - Block all-digit field values over 10 characters (zip codes ≤ 10 chars are exempt)
  - Block single-word messages with no spaces (over 10 chars)
  - Block AI-generated greeting openers ("Hi! I just…", "Hello there! I just…", etc.)
  - Block messages containing "buy" and an HTML hyperlink
  - Block `firstname_lastname@gmail/yahoo/hotmail.com` emails combined with a URL in any field
- Blocked submissions are saved to the Submissions tab with status `spam` (amber badge)
- Master spam filter toggle per form — disable entirely when not needed
- Help tab now documents the spam filter rules, false positive risks, and the honeypot field

### Notes
- The spammy email + URL rule is most likely to produce false positives on forms with a "website" field — disable that rule for those forms

---

## [1.1.0] — 2026-04-15

### Added
- **Form Submissions dashboard** — New "Submissions" tab in the plugin settings
- Every form submission (successful or failed) is saved to a new `fhw_submissions` database table
- Submission detail modal — click any row to view full field data, form name, date/time, and email status
- Filter submissions by form action name
- Paginated table (25 per page) with Previous/Next navigation
- Per-row delete and "Clear All Submissions" (with confirmation)
- Delete button available inside the detail modal

### Security
- IP addresses stored as SHA-256 hashes only — never raw
- Field values stored as JSON with a 30-field cap and 5,000-character-per-value limit (matching the handler's existing caps)
- All delete/clear actions require `manage_options` capability and nonce verification

### Technical
- New `FHW_Submissions` class (`includes/class-fhw-submissions.php`)
- Table auto-creates on plugin activation and upgrades automatically for existing installs via `admin_init` hook
- Table dropped on plugin uninstall

---

## [1.0.9] — 2026-04-15

### Security
- **Fixed IP spoofing in rate limiter** — `get_client_ip()` previously trusted `HTTP_X_FORWARDED_FOR` and other proxy headers, which any client can trivially fake to bypass rate limiting. Now only `REMOTE_ADDR` is used by default (the only unspoofable value). Sites behind a trusted reverse proxy can opt in to proxy header support by defining `FHW_TRUSTED_PROXY=true` in `wp-config.php`
- **Capped unbounded POST field sweep** — submissions with no field schema previously swept all POST keys into the email with no limit. Now capped at 30 fields and 5,000 characters per value
- **Fixed magic quotes / wp_unslash** — success messages, autoreply messages, subjects, and recipient emails were being saved with backslash-escaped quotes (e.g. `We\'ll`) due to WordPress's `wp_magic_quotes()`. Added `wp_unslash()` before all sanitization calls in `sanitize_form_data()`

---

## [1.0.8] — 2026-04-15

### Security
- **Replaced base64 API key storage with AES-256-CBC encryption** — `base64_encode()` is not encryption; anyone with database read access could instantly decode the key. Brevo API keys are now encrypted using `FHW_Crypto::encrypt()` (AES-256-CBC via OpenSSL) before being stored in `wp_options`
- On first admin load after upgrade, existing base64-stored keys are automatically migrated to the new encrypted format
- For maximum security, define `FHW_BREVO_API_KEY` as a constant in `wp-config.php` — the plugin will use the constant directly and disable the database field entirely

### Added
- New `FHW_Crypto` class (`includes/class-fhw-crypto.php`) — AES-256-CBC encryption/decryption with key derived from `FHW_ENCRYPTION_KEY` constant (if defined) or WordPress secret salts as fallback
- Codeception 5 + lucatume/wp-browser unit test suite
- Tests cover crypto operations, migration from base64, salt fallback, and edge cases

### Fixed
- PHPStan, PHPCS, and CI pipeline issues

---

## [1.0.7] — 2026-04-14

### Fixed
- All PHPStan level-5 errors resolved
- Security hardening on nonce verification endpoint
- Added `Update URI` header to plugin file to prevent unintended auto-updates from WordPress.org

---

## [1.0.6] — 2026-04-14

### Fixed
- Reverted page URL changes introduced in earlier versions that caused regressions
- Added `Update URI` header
- Release workflow fixes (versioned zip names, `GITHUB_TOKEN` permissions, workflow dispatch)

---

## [1.0.5] — Early development

### Added
- Support for multiple page URLs per form handler (one per line)

---

## [1.0.4] — Early development

### Added
- Page URL field per form — JavaScript assets enqueued only on matching pages (performance improvement)

---

## [1.0.3] — Early development

### Added
- "How to Use" tab with full developer documentation in the plugin settings

---

## [1.0.2] — Early development

### Added
- Auto-intercept for `data-fhw-form` elements — no custom JavaScript required from the site developer
- Pure HTML/CSS/JS form snippet for page builder compatibility

---

## [1.0.1] — Early development

### Fixed
- Added `sib-plugin` and `User-Agent` headers to Brevo API requests to fix authentication failures

---

## [1.0.0] — Initial release

### Added
- Plugin scaffold with full admin UI
- AJAX form submission handling via `data-fhw-form` attribute
- Brevo transactional email integration
- Form handler registration (action name, recipient emails, subject template)
- Field schema builder for typed field sanitization
- Auto-reply confirmation email to form submitters
- Honeypot spam protection field
- Per-form rate limiting (submissions per IP per hour)
- HTML and plain-text email modes
- Success/error message customization
- Email log tab (recipient, subject, send status, error details)
- `wp_mail()` override option (route all WordPress emails through Brevo)
- Help/Quick Start tab
- Plugin uninstall cleanup (drops custom tables, deletes options)
- PHPCS (WordPress Coding Standards) and PHPStan (level 5) enforced via GitHub Actions
