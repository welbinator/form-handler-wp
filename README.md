# Form Handler WP

A lightweight WordPress plugin for handling AJAX form submissions and routing them through Brevo (Sendinblue) transactional email — no page reloads, no server-side form HTML required.

---

## Features

- **Zero-JS form setup** — add `data-fhw-form="your_action"` to any `<form>` tag and the plugin handles the rest
- **Brevo transactional email** — sends notifications via the Brevo API with full `Reply-To` support
- **Auto-reply emails** — optional confirmation email sent to the person who submitted the form
- **Form submissions dashboard** — every submission saved to the database with full field data, filterable and paginated
- **Spam filtering** — six heuristic rules, individually toggleable per form
- **Honeypot protection** — invisible field that bots fill in; humans don't
- **Per-form rate limiting** — cap submissions per IP per hour
- **Field schema** — define expected fields and their types for automatic sanitization
- **Encrypted API key storage** — Brevo API key stored with AES-256-CBC encryption, never plain text
- **IP privacy** — client IPs stored as SHA-256 hashes only, never raw
- **WordPress Coding Standards compliant** — PHPCS + PHPStan level 5 enforced in CI

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- A [Brevo](https://www.brevo.com/) account with a transactional email API key
- A verified sender address in Brevo

---

## Installation

1. Download the latest release zip from [GitHub Releases](https://github.com/welbinator/form-handler-wp/releases)
2. In WordPress admin: **Plugins → Add New → Upload Plugin**
3. Upload the zip and activate
4. Go to **Settings → Form Handler WP** to configure

---

## Quick Start

### 1. Add your Brevo API key

Go to **Settings → Form Handler WP → Brevo Settings** and paste your API key. Set your sender name and email address (must be verified in Brevo).

> **Tip:** For maximum security, define the key as a constant in `wp-config.php` instead of storing it in the database:
> ```php
> define( 'FHW_BREVO_API_KEY', 'your-api-key-here' );
> ```

### 2. Register a form handler

Go to the **Forms** tab and click **Add New Form Handler**. Fill in:

| Field | Description |
|-------|-------------|
| Action Name | Unique slug (e.g. `contact_form`). Must match your HTML form's `data-fhw-form` attribute |
| Recipient Email(s) | Where notification emails are sent (comma-separated) |
| Subject Template | Use `{field_name}` placeholders, e.g. `New message from {name}` |
| Reply-To Field | The form field that contains the submitter's email |
| Field Schema | Optional — define expected fields and their types for typed sanitization |
| Success Message | Shown to the user after a successful submission |
| Honeypot Field | Name of a hidden field — submissions that fill it are silently dropped |
| Rate Limit | Max submissions per IP per hour (0 = unlimited) |
| Spam Filter | Enable/disable heuristic spam detection with per-rule toggles |
| Auto-Reply | Send a confirmation email back to the submitter |

### 3. Add the form to your page

```html
<form data-fhw-form="contact_form">
  <input type="text" name="name" placeholder="Your name" required />
  <input type="email" name="email" placeholder="Your email" required />
  <textarea name="message" placeholder="Your message" required></textarea>
  <button type="submit">Send</button>
  <div class="fhw-success" style="display:none;"></div>
  <div class="fhw-error" style="display:none;"></div>
</form>
```

That's it. The plugin auto-intercepts the form submit, sends the data via AJAX, and displays your success/error message without a page reload.

---

## Spam Filtering

Each form has a master spam filter toggle and six individually toggleable rules:

| Rule | What it catches |
|------|-----------------|
| No user agent | Automated POST requests with no browser user-agent header |
| All digits | Field values that are long strings of only digits (phone spam, etc.) |
| No spaces | Single-word field values over 10 characters (keyword spam) |
| AI greeting | Messages starting with "Hi! I just…" or "Hello there! I just…" |
| Buy + link | Messages containing "buy" combined with an HTML anchor tag |
| Spammy email + URL | `firstname_lastname@gmail/yahoo/hotmail.com` combined with a URL in any field |

Spam submissions are saved to the Submissions dashboard with an amber **spam** badge — they are not emailed to you.

> **Note on false positives:** The spammy email + URL rule is the most likely to catch legitimate submissions on forms that include a "Website" field. Disable that rule for those forms.

---

## Security

- **API key encryption** — stored with AES-256-CBC via OpenSSL; define `FHW_ENCRYPTION_KEY` in `wp-config.php` to use your own key instead of WordPress salt derivation
- **IP hashing** — client IPs are hashed with SHA-256 before storage; raw IPs are never written to the database
- **Nonce verification** — all admin actions require valid WordPress nonces
- **Capability checks** — all destructive actions require `manage_options`
- **Prepared statements** — all database queries use `$wpdb->prepare()` or typed `$wpdb->insert/delete`
- **Input sanitization** — all POST data runs through appropriate WordPress sanitization functions; `wp_unslash()` applied before sanitization
- **Rate limiting** — uses `REMOTE_ADDR` only; proxy header support opt-in via `define( 'FHW_TRUSTED_PROXY', true )` in `wp-config.php`
- **POST field cap** — max 30 fields and 5,000 characters per value accepted per submission

---

## Configuration Constants

Define these in `wp-config.php` for enhanced security:

```php
// Use your own encryption key (recommended)
define( 'FHW_ENCRYPTION_KEY', 'a-long-random-secret-string' );

// Store Brevo API key outside the database entirely
define( 'FHW_BREVO_API_KEY', 'your-brevo-api-key' );

// Enable proxy header support for rate limiting (only if behind a trusted reverse proxy)
define( 'FHW_TRUSTED_PROXY', true );
```

---

## Database Tables

The plugin creates two custom tables (prefixed with your WordPress table prefix):

| Table | Purpose |
|-------|---------|
| `fhw_email_log` | Record of every email send attempt (recipient, subject, status, error) |
| `fhw_submissions` | Full form submission data (hashed IP, field values as JSON, spam status) |

Both tables are dropped on plugin uninstall.

---

## Development

### Requirements

- [DDEV](https://ddev.com/) (or any local WordPress environment)
- PHP 8.0+
- Composer

### Setup

```bash
git clone https://github.com/welbinator/form-handler-wp.git
cd form-handler-wp
composer install
```

### Linting

```bash
# PHPCS (WordPress Coding Standards)
vendor/bin/phpcs

# PHPStan (level 5)
vendor/bin/phpstan analyse --memory-limit=1G
```

### Tests

```bash
# Codeception unit tests
vendor/bin/codecept run Unit
```

### CI

GitHub Actions runs PHPCS, PHPStan, and Codeception on every push and pull request.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for full version history.

---

## License

GPLv2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
