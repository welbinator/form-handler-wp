<?php
/**
 * Admin settings page template.
 *
 * Rendered by FHW_Settings::render_settings_page().
 * $this is an FHW_Settings instance.
 *
 * @package Form_Handler_WP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'brevo'; // phpcs:ignore WordPress.Security.NonceVerification

$registry = new FHW_Form_Registry();
$forms    = $registry->get_forms();
$logger   = new FHW_Logger();
$log      = $logger->get_entries( 50 );

// Stored settings.
$api_key_enc  = get_option( 'fhw_brevo_api_key_enc', '' );
$api_key_set  = defined( 'FHW_BREVO_API_KEY' ) || '' !== $api_key_enc;
$sender_email = get_option( 'fhw_sender_email', get_option( 'admin_email' ) );
$sender_name  = get_option( 'fhw_sender_name', get_bloginfo( 'name' ) );
$override     = get_option( 'fhw_override_wp_mail', '0' );
?>
<div class="wrap fhw-wrap">
	<h1><?php esc_html_e( 'Form Handler WP', 'form-handler-wp' ); ?></h1>

	<?php // Notices. ?>
	<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'form-handler-wp' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['added'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Form handler added.', 'form-handler-wp' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Form handler deleted.', 'form-handler-wp' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Email log cleared.', 'form-handler-wp' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['submissions_deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Submission deleted.', 'form-handler-wp' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['submissions_cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'All submissions cleared.', 'form-handler-wp' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( urldecode( sanitize_text_field( $_GET['error'] ) ) ); ?></p></div> <?php // phpcs:ignore WordPress.Security.NonceVerification ?>
	<?php endif; ?>

	<?php // Tab nav. ?>
	<nav class="fhw-tab-nav">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=form-handler-wp&tab=brevo' ) ); ?>"
			class="<?php echo 'brevo' === $current_tab ? 'fhw-tab-active' : ''; ?>">
			<?php esc_html_e( 'Brevo Settings', 'form-handler-wp' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=form-handler-wp&tab=forms' ) ); ?>"
			class="<?php echo 'forms' === $current_tab ? 'fhw-tab-active' : ''; ?>">
			<?php esc_html_e( 'Registered Forms', 'form-handler-wp' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=form-handler-wp&tab=submissions' ) ); ?>"
			class="<?php echo 'submissions' === $current_tab ? 'fhw-tab-active' : ''; ?>">
			<?php esc_html_e( 'Submissions', 'form-handler-wp' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=form-handler-wp&tab=log' ) ); ?>"
			class="<?php echo 'log' === $current_tab ? 'fhw-tab-active' : ''; ?>">
			<?php esc_html_e( 'Email Log', 'form-handler-wp' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=form-handler-wp&tab=help' ) ); ?>"
			class="<?php echo 'help' === $current_tab ? 'fhw-tab-active' : ''; ?>">
			<?php esc_html_e( 'How to Use', 'form-handler-wp' ); ?>
		</a>
	</nav>

<?php if ( 'brevo' === $current_tab ) : ?>

	<div class="fhw-card">
		<h2><?php esc_html_e( 'Brevo API Settings', 'form-handler-wp' ); ?></h2>

		<?php if ( defined( 'FHW_BREVO_API_KEY' ) ) : ?>
			<div class="notice notice-info inline"><p>
				<?php esc_html_e( 'API key is defined via the FHW_BREVO_API_KEY constant in wp-config.php. The field below is disabled.', 'form-handler-wp' ); ?>
			</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fhw_save_brevo_settings" />
			<?php wp_nonce_field( 'fhw_brevo_settings', 'fhw_brevo_nonce' ); ?>

			<table class="form-table fhw-form-table">
				<tr>
					<th scope="row"><label for="fhw_brevo_api_key"><?php esc_html_e( 'Brevo API Key (v3)', 'form-handler-wp' ); ?></label></th>
					<td>
						<div class="fhw-api-key-row">
							<input type="password"
								id="fhw_brevo_api_key"
								name="fhw_brevo_api_key"
								value="<?php echo $api_key_set ? '••••••••••••••••' : ''; ?>"
								<?php disabled( defined( 'FHW_BREVO_API_KEY' ) ); ?>
								autocomplete="off"
								class="regular-text" />
						</div>
						<span class="description">
							<?php esc_html_e( 'For better security, define FHW_BREVO_API_KEY in wp-config.php instead.', 'form-handler-wp' ); ?>
						</span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fhw_sender_email"><?php esc_html_e( 'Default Sender Email', 'form-handler-wp' ); ?></label></th>
					<td>
						<input type="email" id="fhw_sender_email" name="fhw_sender_email"
							value="<?php echo esc_attr( $sender_email ); ?>" class="regular-text" />
						<span class="description"><?php esc_html_e( 'Must be a verified sender in Brevo.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fhw_sender_name"><?php esc_html_e( 'Default Sender Name', 'form-handler-wp' ); ?></label></th>
					<td>
						<input type="text" id="fhw_sender_name" name="fhw_sender_name"
							value="<?php echo esc_attr( $sender_name ); ?>" class="regular-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Override wp_mail()', 'form-handler-wp' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="fhw_override_wp_mail" value="1" <?php checked( '1', $override ); ?> />
							<?php esc_html_e( 'Route all wp_mail() calls through Brevo', 'form-handler-wp' ); ?>
						</label>
						<span class="description"><?php esc_html_e( 'Affects all plugins and themes that use wp_mail().', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Send Test Email', 'form-handler-wp' ); ?></th>
					<td>
						<div class="fhw-test-email-row">
							<input type="email" id="fhw-test-email-address"
								placeholder="<?php esc_attr_e( 'recipient@example.com', 'form-handler-wp' ); ?>" />
							<button type="button" id="fhw-test-email-btn" class="button button-secondary"
								<?php disabled( ! $api_key_set ); ?>>
								<?php esc_html_e( 'Send Test', 'form-handler-wp' ); ?>
							</button>
							<span id="fhw-test-result"></span>
						</div>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Settings', 'form-handler-wp' ) ); ?>
		</form>
	</div>

<?php elseif ( 'forms' === $current_tab ) : ?>

	<div class="fhw-card">
		<h2><?php esc_html_e( 'Registered Form Handlers', 'form-handler-wp' ); ?></h2>

		<?php if ( empty( $forms ) ) : ?>
			<p class="fhw-empty-state"><?php esc_html_e( 'No form handlers registered yet. Add one below.', 'form-handler-wp' ); ?></p>
		<?php else : ?>
			<table class="fhw-forms-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Action Name', 'form-handler-wp' ); ?></th>
						<th><?php esc_html_e( 'Recipient(s)', 'form-handler-wp' ); ?></th>
						<th><?php esc_html_e( 'Subject Template', 'form-handler-wp' ); ?></th>
						<th><?php esc_html_e( 'Status', 'form-handler-wp' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'form-handler-wp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $forms as $form ) : ?>
						<tr>
							<td><code class="fhw-action-code"><?php echo esc_html( $form['action_name'] ); ?></code></td>
							<td><?php echo esc_html( $form['to_emails'] ); ?></td>
							<td><?php echo esc_html( $form['subject_tpl'] ); ?></td>
							<td>
								<span class="fhw-status-badge fhw-status-<?php echo esc_attr( $form['status'] ?? 'active' ); ?>">
									<?php echo esc_html( $form['status'] ?? 'active' ); ?>
								</span>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
									class="fhw-delete-form" style="display:inline;">
									<input type="hidden" name="action" value="fhw_delete_form" />
									<input type="hidden" name="fhw_action_name" value="<?php echo esc_attr( $form['action_name'] ); ?>" />
									<?php wp_nonce_field( 'fhw_delete_form', 'fhw_delete_nonce' ); ?>
									<button type="submit" class="button button-small button-link-delete">
										<?php esc_html_e( 'Delete', 'form-handler-wp' ); ?>
									</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="fhw-card">
		<h2><?php esc_html_e( 'Add New Form Handler', 'form-handler-wp' ); ?></h2>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="fhw_add_form" />
			<?php wp_nonce_field( 'fhw_add_form', 'fhw_add_form_nonce' ); ?>

			<table class="form-table fhw-form-table">
				<tr>
					<th scope="row"><label for="fhw_action_name"><?php esc_html_e( 'Action Name', 'form-handler-wp' ); ?> <span style="color:#d63638;">*</span></label></th>
					<td>
						<input type="text" id="fhw_action_name" name="action_name"
							pattern="[a-z0-9_]+" placeholder="contact_form_submit" class="regular-text" required />
						<span class="description"><?php esc_html_e( 'Unique slug (lowercase, numbers, underscores). Used as the WordPress AJAX action.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fhw_to_emails"><?php esc_html_e( 'Recipient Email(s)', 'form-handler-wp' ); ?> <span style="color:#d63638;">*</span></label></th>
					<td>
						<input type="text" id="fhw_to_emails" name="to_emails"
							placeholder="you@example.com, other@example.com" class="regular-text" required />
						<span class="description"><?php esc_html_e( 'Comma-separated list of recipient addresses.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fhw_subject_tpl"><?php esc_html_e( 'Subject Template', 'form-handler-wp' ); ?> <span style="color:#d63638;">*</span></label></th>
					<td>
						<input type="text" id="fhw_subject_tpl" name="subject_tpl"
							placeholder="New message from {name} — {site_name}" class="regular-text" required />
						<span class="description"><?php esc_html_e( 'Use {field_name} for any submitted field, or {site_name} for your site name.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fhw_reply_to_field"><?php esc_html_e( 'Reply-To Field Name', 'form-handler-wp' ); ?></label></th>
					<td>
						<input type="text" id="fhw_reply_to_field" name="reply_to_field"
							placeholder="email" class="regular-text" />
						<span class="description"><?php esc_html_e( 'POST field name whose value will be used as Reply-To (must be an email field).', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Field Schema', 'form-handler-wp' ); ?></th>
					<td>
						<div class="fhw-field-schema-wrap">
							<div id="fhw-field-rows" class="fhw-field-rows">
								<div class="fhw-field-row">
									<input type="text" name="field_schema[0][field_name]" placeholder="field_name" pattern="[a-z0-9_]+" />
									<select name="field_schema[0][field_type]">
										<option value="text">text</option>
										<option value="email">email</option>
										<option value="textarea">textarea</option>
										<option value="url">url</option>
										<option value="number">number</option>
										<option value="checkbox">checkbox</option>
									</select>
									<button type="button" class="fhw-remove-field" aria-label="<?php esc_attr_e( 'Remove field', 'form-handler-wp' ); ?>">&times;</button>
								</div>
							</div>
							<button type="button" id="fhw-add-field-btn" class="button button-secondary">
								+ <?php esc_html_e( 'Add Field', 'form-handler-wp' ); ?>
							</button>
							<span class="description" style="display:block;margin-top:6px;">
								<?php esc_html_e( 'Define fields for automatic sanitization. Leave empty to accept any POST data (sanitized as text).', 'form-handler-wp' ); ?>
							</span>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fhw_success_message"><?php esc_html_e( 'Success Message', 'form-handler-wp' ); ?></label></th>
					<td>
						<textarea id="fhw_success_message" name="success_message" rows="3" class="large-text"><?php echo esc_textarea( '' ); ?></textarea>
						<span class="description"><?php esc_html_e( 'Shown to the user after successful submission. HTML allowed.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'HTML Email', 'form-handler-wp' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="html_email" value="1" />
							<?php esc_html_e( 'Send email as HTML', 'form-handler-wp' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-Reply to Submitter', 'form-handler-wp' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="autoreply_enabled" value="1" id="fhw_autoreply_enabled" />
							<?php esc_html_e( 'Send a confirmation email to the person who filled out the form', 'form-handler-wp' ); ?>
						</label>
					</td>
				</tr>
				<tr class="fhw-autoreply-row">
					<th scope="row"><label for="fhw_autoreply_to_field"><?php esc_html_e( 'Submitter Email Field', 'form-handler-wp' ); ?> <span style="color:#d63638;">*</span></label></th>
					<td>
						<input type="text" id="fhw_autoreply_to_field" name="autoreply_to_field"
							placeholder="email" class="regular-text" />
						<span class="description"><?php esc_html_e( 'The field name that contains the submitter\'s email address.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr class="fhw-autoreply-row">
					<th scope="row"><label for="fhw_autoreply_subject"><?php esc_html_e( 'Confirmation Subject', 'form-handler-wp' ); ?></label></th>
					<td>
						<input type="text" id="fhw_autoreply_subject" name="autoreply_subject"
							placeholder="<?php esc_attr_e( 'Thanks for contacting {site_name}!', 'form-handler-wp' ); ?>"
							class="regular-text" />
						<span class="description"><?php esc_html_e( 'Supports {field_name} and {site_name} placeholders. Leave blank for a generic default.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr class="fhw-autoreply-row">
					<th scope="row"><label for="fhw_autoreply_message"><?php esc_html_e( 'Confirmation Message', 'form-handler-wp' ); ?></label></th>
					<td>
						<textarea id="fhw_autoreply_message" name="autoreply_message" rows="4" class="large-text"></textarea>
						<span class="description"><?php esc_html_e( 'Supports {field_name} and {site_name} placeholders. HTML allowed. Leave blank for a generic thank-you message.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fhw_honeypot_field"><?php esc_html_e( 'Honeypot Field Name', 'form-handler-wp' ); ?></label></th>
					<td>
						<input type="text" id="fhw_honeypot_field" name="honeypot_field"
							placeholder="website" class="regular-text" />
						<span class="description"><?php esc_html_e( 'Hidden field name. If filled by a bot, submission is silently discarded.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fhw_rate_limit"><?php esc_html_e( 'Rate Limit', 'form-handler-wp' ); ?></label></th>
					<td>
						<input type="number" id="fhw_rate_limit" name="rate_limit"
							value="0" min="0" max="999" style="width:80px;" />
						<span class="description"><?php esc_html_e( 'Max submissions per IP per hour. Set to 0 to disable.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Add Form Handler', 'form-handler-wp' ) ); ?>
		</form>
	</div>

<?php elseif ( 'submissions' === $current_tab ) : ?>

	<?php
	// Submissions tab data.
	$submissions_obj = new FHW_Submissions();
	$sub_per_page    = 25;
	$sub_paged       = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
	$sub_paged       = max( 1, $sub_paged );
	$sub_filter      = isset( $_GET['action_name_filter'] ) ? sanitize_key( wp_unslash( $_GET['action_name_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$sub_offset      = ( $sub_paged - 1 ) * $sub_per_page;
	$sub_total       = $submissions_obj->get_count( $sub_filter );
	$sub_total_pages = $sub_total > 0 ? (int) ceil( $sub_total / $sub_per_page ) : 1;
	$sub_entries     = $submissions_obj->get_entries( $sub_per_page, $sub_offset, $sub_filter );
	$sub_from        = $sub_total > 0 ? $sub_offset + 1 : 0;
	$sub_to          = min( $sub_offset + $sub_per_page, $sub_total );
	?>

	<div class="fhw-card">
		<h2><?php esc_html_e( 'Form Submissions', 'form-handler-wp' ); ?></h2>

		<?php if ( isset( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Submission deleted.', 'form-handler-wp' ); ?></p></div>
		<?php endif; ?>

		<?php // Filter by form action. ?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-bottom:16px;">
			<input type="hidden" name="page" value="form-handler-wp" />
			<input type="hidden" name="tab" value="submissions" />
			<label for="fhw-sub-filter"><?php esc_html_e( 'Filter by form:', 'form-handler-wp' ); ?></label>
			<select id="fhw-sub-filter" name="action_name_filter" onchange="this.form.submit()">
				<option value=""><?php esc_html_e( '— All Forms —', 'form-handler-wp' ); ?></option>
				<?php foreach ( $forms as $form_item ) : ?>
					<option value="<?php echo esc_attr( $form_item['action_name'] ); ?>"
						<?php selected( $sub_filter, $form_item['action_name'] ); ?>>
						<?php echo esc_html( $form_item['action_name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</form>

		<?php if ( empty( $sub_entries ) ) : ?>
			<p class="fhw-empty-state"><?php esc_html_e( 'No submissions found.', 'form-handler-wp' ); ?></p>
		<?php else : ?>

			<p style="margin-bottom:8px;">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: first entry number, 2: last entry number, 3: total count */
						__( 'Showing %1$d-%2$d of %3$d', 'form-handler-wp' ),
						$sub_from,
						$sub_to,
						$sub_total
					)
				);
				?>
			</p>

			<table class="fhw-log-table fhw-submissions-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date/Time', 'form-handler-wp' ); ?></th>
						<th><?php esc_html_e( 'Form', 'form-handler-wp' ); ?></th>
						<th><?php esc_html_e( 'Fields', 'form-handler-wp' ); ?></th>
						<th><?php esc_html_e( 'Email Status', 'form-handler-wp' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'form-handler-wp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sub_entries as $sub_entry ) : ?>
						<?php
						$entry_id       = absint( $sub_entry['id'] );
						$decoded_fields = json_decode( $sub_entry['fields'], true );
						if ( ! is_array( $decoded_fields ) ) {
							$decoded_fields = array();
						}

						// Build summary: first 2 values, truncated to 50 chars.
						$summary_parts = array();
						$summary_count = 0;
						foreach ( $decoded_fields as $f_key => $f_val ) {
							if ( $summary_count >= 2 ) {
								break;
							}
							$display_val = (string) $f_val;
							if ( strlen( $display_val ) > 50 ) {
								$display_val = substr( $display_val, 0, 50 ) . '...';
							}
							$summary_parts[] = esc_html( $f_key ) . ': ' . esc_html( $display_val );
							++$summary_count;
						}
						$summary = implode( ' | ', $summary_parts );
						?>
						<tr>
							<td><?php echo esc_html( $sub_entry['submitted_at'] ); ?></td>
							<td><code><?php echo esc_html( $sub_entry['action_name'] ); ?></code></td>
							<td>
								<?php echo wp_kses( $summary, array() ); ?>
								<button type="button"
									class="button button-small fhw-sub-view-toggle"
									data-target="fhw-sub-detail-<?php echo esc_attr( (string) $entry_id ); ?>"
									aria-expanded="false"
									style="margin-left:6px;">
									<?php esc_html_e( 'View', 'form-handler-wp' ); ?>
								</button>
							</td>
							<td>
								<span class="fhw-log-<?php echo esc_attr( $sub_entry['email_status'] ); ?>">
									<?php echo esc_html( $sub_entry['email_status'] ); ?>
								</span>
							</td>
							<td>
								<form method="post"
									action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
									style="display:inline;">
									<input type="hidden" name="action" value="fhw_delete_submission" />
									<input type="hidden" name="submission_id" value="<?php echo esc_attr( (string) $entry_id ); ?>" />
									<input type="hidden" name="paged" value="<?php echo esc_attr( (string) $sub_paged ); ?>" />
									<input type="hidden" name="action_name_filter" value="<?php echo esc_attr( $sub_filter ); ?>" />
									<?php wp_nonce_field( 'fhw_delete_submission_' . $entry_id, 'fhw_delete_submission_nonce' ); ?>
									<button type="submit" class="button button-small button-link-delete"
										onclick="return confirm('<?php echo esc_js( __( 'Delete this submission?', 'form-handler-wp' ) ); ?>')">
										<?php esc_html_e( 'Delete', 'form-handler-wp' ); ?>
									</button>
								</form>
							</td>
						</tr>
						<tr id="fhw-sub-detail-<?php echo esc_attr( (string) $entry_id ); ?>" style="display:none;">
							<td colspan="5">
								<table class="fhw-sub-fields-table" style="width:100%;border-collapse:collapse;">
									<?php if ( empty( $decoded_fields ) ) : ?>
										<tr><td><?php esc_html_e( '(no fields)', 'form-handler-wp' ); ?></td></tr>
									<?php else : ?>
										<?php foreach ( $decoded_fields as $field_key => $field_val ) : ?>
											<tr>
												<th style="text-align:left;padding:4px 8px;background:#f5f5f5;width:160px;"><?php echo esc_html( $field_key ); ?></th>
												<td style="padding:4px 8px;"><?php echo esc_html( (string) $field_val ); ?></td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</table>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $sub_total_pages > 1 ) : ?>
				<div class="fhw-pagination" style="margin-top:12px;">
					<?php if ( $sub_paged > 1 ) : ?>
						<a href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									'page'               => 'form-handler-wp',
									'tab'                => 'submissions',
									'paged'              => $sub_paged - 1,
									'action_name_filter' => $sub_filter,
								),
								admin_url( 'admin.php' )
							)
						);
						?>
									" class="button">&laquo; <?php esc_html_e( 'Previous', 'form-handler-wp' ); ?></a>
					<?php endif; ?>
					<?php if ( $sub_paged < $sub_total_pages ) : ?>
						<a href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									'page'               => 'form-handler-wp',
									'tab'                => 'submissions',
									'paged'              => $sub_paged + 1,
									'action_name_filter' => $sub_filter,
								),
								admin_url( 'admin.php' )
							)
						);
						?>
									" class="button"><?php esc_html_e( 'Next', 'form-handler-wp' ); ?> &raquo;</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px;">
				<input type="hidden" name="action" value="fhw_clear_submissions" />
				<?php wp_nonce_field( 'fhw_clear_submissions', 'fhw_clear_submissions_nonce' ); ?>
				<button type="submit" class="button button-link-delete"
					onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to clear ALL submissions? This cannot be undone.', 'form-handler-wp' ) ); ?>')">
					<?php esc_html_e( 'Clear All Submissions', 'form-handler-wp' ); ?>
				</button>
			</form>

		<?php endif; ?>
	</div>

	<?php // Inline script for View toggle. ?>
	<script>
	( function() {
		document.addEventListener( 'click', function( e ) {
			if ( ! e.target.classList.contains( 'fhw-sub-view-toggle' ) ) {
				return;
			}
			var btn    = e.target;
			var target = document.getElementById( btn.dataset.target );
			if ( ! target ) {
				return;
			}
			var expanded = 'true' === btn.getAttribute( 'aria-expanded' );
			btn.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
			target.style.display = expanded ? 'none' : '';
			btn.textContent = expanded ?
				'<?php echo esc_js( __( 'View', 'form-handler-wp' ) ); ?>' :
				'<?php echo esc_js( __( 'Hide', 'form-handler-wp' ) ); ?>';
		} );
	}() );
	</script>

<?php elseif ( 'log' === $current_tab ) : ?>

	<div class="fhw-card">
		<h2><?php esc_html_e( 'Email Log', 'form-handler-wp' ); ?></h2>

		<?php if ( empty( $log ) ) : ?>
			<p class="fhw-empty-state"><?php esc_html_e( 'No emails logged yet.', 'form-handler-wp' ); ?></p>
		<?php else : ?>
			<table class="fhw-log-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'form-handler-wp' ); ?></th>
						<th><?php esc_html_e( 'Recipient', 'form-handler-wp' ); ?></th>
						<th><?php esc_html_e( 'Subject', 'form-handler-wp' ); ?></th>
						<th><?php esc_html_e( 'Status', 'form-handler-wp' ); ?></th>
						<th><?php esc_html_e( 'Message ID', 'form-handler-wp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $log as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( $entry['sent_at'] ); ?></td>
							<td><?php echo esc_html( $entry['recipient'] ); ?></td>
							<td><?php echo esc_html( $entry['subject'] ); ?></td>
							<td>
								<span class="fhw-log-<?php echo esc_attr( $entry['status'] ); ?>">
									<?php echo esc_html( $entry['status'] ); ?>
								</span>
								<?php if ( ! empty( $entry['error_msg'] ) ) : ?>
									<br><span class="fhw-log-error-msg"><?php echo esc_html( $entry['error_msg'] ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $entry['message_id'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				id="fhw-clear-log-form" style="margin-top:16px;">
				<input type="hidden" name="action" value="fhw_clear_log" />
				<?php wp_nonce_field( 'fhw_clear_log', 'fhw_clear_log_nonce' ); ?>
				<?php submit_button( __( 'Clear Log', 'form-handler-wp' ), 'delete', 'submit', false ); ?>
			</form>
		<?php endif; ?>
	</div>

<?php endif; ?>

<?php if ( 'help' === $current_tab ) : ?>

	<div class="fhw-card">
		<h2><?php esc_html_e( 'Quick Start', 'form-handler-wp' ); ?></h2>
		<ol style="line-height:2;max-width:700px;">
			<li><?php esc_html_e( 'Go to Brevo Settings and enter your Brevo v3 API key and a verified sender email.', 'form-handler-wp' ); ?></li>
			<li><?php esc_html_e( 'Go to Registered Forms and click "Add New Form Handler". Give it a unique action name (e.g. contact_form_submit).', 'form-handler-wp' ); ?></li>
			<li><?php esc_html_e( 'Build your HTML form anywhere on your site and add the data-fhw-form attribute with your action name — no JavaScript needed.', 'form-handler-wp' ); ?></li>
		</ol>
	</div>

	<div class="fhw-card">
		<h2><?php esc_html_e( 'Building Your Form', 'form-handler-wp' ); ?></h2>
		<p><?php esc_html_e( 'Add the data-fhw-form attribute to any HTML form. The value must match an action name you registered in Registered Forms.', 'form-handler-wp' ); ?></p>
		<pre style="background:#f6f7f7;padding:16px;border-radius:4px;overflow:auto;font-size:13px;">&lt;form data-fhw-form=&quot;contact_form_submit&quot;&gt;
	&lt;input type=&quot;text&quot;  name=&quot;name&quot;  placeholder=&quot;Your Name&quot; /&gt;
	&lt;input type=&quot;email&quot; name=&quot;email&quot; placeholder=&quot;Email Address&quot; /&gt;
	&lt;button type=&quot;submit&quot;&gt;Send&lt;/button&gt;
&lt;/form&gt;</pre>
		<p><?php esc_html_e( 'The plugin automatically handles the nonce, AJAX submission, and success/error messages. No JavaScript required from you.', 'form-handler-wp' ); ?></p>
	</div>

	<div class="fhw-card">
		<h2><?php esc_html_e( 'One Action Name Per Form', 'form-handler-wp' ); ?></h2>
		<p><?php esc_html_e( 'Each form must use a unique action name that matches a registered form handler. The action name connects your HTML form to its plugin config (recipient email, subject, auto-reply settings, etc.).', 'form-handler-wp' ); ?></p>
		<p><?php esc_html_e( 'Example — two forms, two action names:', 'form-handler-wp' ); ?></p>
		<pre style="background:#f6f7f7;padding:16px;border-radius:4px;overflow:auto;font-size:13px;">&lt;!-- Contact form --&gt;
&lt;form data-fhw-form=&quot;contact_form_submit&quot;&gt; ... &lt;/form&gt;

&lt;!-- Newsletter signup --&gt;
&lt;form data-fhw-form=&quot;newsletter_signup&quot;&gt; ... &lt;/form&gt;</pre>
	</div>

	<div class="fhw-card">
		<h2><?php esc_html_e( 'Optional Form Attributes', 'form-handler-wp' ); ?></h2>
		<table class="fhw-forms-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Attribute', 'form-handler-wp' ); ?></th>
					<th><?php esc_html_e( 'Description', 'form-handler-wp' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code class="fhw-action-code">data-fhw-success="..."</code></td>
					<td><?php esc_html_e( 'Override the success message shown after submission.', 'form-handler-wp' ); ?></td>
				</tr>
				<tr>
					<td><code class="fhw-action-code">data-fhw-error="..."</code></td>
					<td><?php esc_html_e( 'Override the generic error message.', 'form-handler-wp' ); ?></td>
				</tr>
				<tr>
					<td><code class="fhw-action-code">data-fhw-reset="false"</code></td>
					<td><?php esc_html_e( 'Prevent the form from clearing after successful submission.', 'form-handler-wp' ); ?></td>
				</tr>
				<tr>
					<td><code class="fhw-action-code">&lt;div data-fhw-status&gt;&lt;/div&gt;</code></td>
					<td><?php esc_html_e( 'Place inside your form to control exactly where success/error messages appear.', 'form-handler-wp' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="fhw-card">
		<h2><?php esc_html_e( 'Secure API Key (Recommended)', 'form-handler-wp' ); ?></h2>
		<p><?php esc_html_e( 'For better security, define your Brevo API key as a constant in wp-config.php instead of storing it in the database:', 'form-handler-wp' ); ?></p>
		<pre style="background:#f6f7f7;padding:16px;border-radius:4px;overflow:auto;font-size:13px;">define( 'FHW_BREVO_API_KEY', 'your-brevo-v3-api-key' );</pre>
		<p><?php esc_html_e( 'When this constant is present the API key field on the Brevo Settings tab is disabled and the constant value is used automatically.', 'form-handler-wp' ); ?></p>
	</div>

<?php endif; ?>

</div>
