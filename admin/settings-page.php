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
	<?php if ( isset( $_GET['form_updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Form handler updated.', 'form-handler-wp' ); ?></p></div>
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
	<?php if ( isset( $_GET['error'] ) && 'duplicate_action' === sanitize_key( $_GET['error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'That action name is already in use by another form.', 'form-handler-wp' ); ?></p></div>
	<?php elseif ( isset( $_GET['error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ) ); ?></p></div> <?php // phpcs:ignore WordPress.Security.NonceVerification ?>
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
			<div class="fhw-log-table-wrap">
			<div class="fhw-log-table-wrap"><table class="fhw-forms-table">
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
								<a href="
								<?php
								echo esc_url(
									add_query_arg(
										array(
											'page' => 'form-handler-wp',
											'tab'  => 'forms',
											'edit' => $form['action_name'],
										),
										admin_url( 'admin.php' )
									)
								);
								?>
											"
									class="button button-small">
									<?php esc_html_e( 'Edit', 'form-handler-wp' ); ?>
								</a>
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
			</div><!-- .fhw-log-table-wrap -->
		<?php endif; ?>
	</div>

	<?php
	// Edit mode detection.
	$editing_action = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$editing_form   = '' !== $editing_action ? $registry->get_form( $editing_action ) : false;
	$is_editing     = false !== $editing_form;
	?>

	<div class="fhw-card">
		<h2><?php echo $is_editing ? esc_html__( 'Edit Form Handler', 'form-handler-wp' ) : esc_html__( 'Add New Form Handler', 'form-handler-wp' ); ?></h2>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php if ( $is_editing ) : ?>
				<input type="hidden" name="action" value="fhw_update_form" />
				<input type="hidden" name="original_action" value="<?php echo esc_attr( $editing_action ); ?>" />
				<?php wp_nonce_field( 'fhw_update_form', 'fhw_update_form_nonce' ); ?>
			<?php else : ?>
				<input type="hidden" name="action" value="fhw_add_form" />
				<?php wp_nonce_field( 'fhw_add_form', 'fhw_add_form_nonce' ); ?>
			<?php endif; ?>

			<table class="form-table fhw-form-table">
				<tr>
					<th scope="row"><label for="fhw_action_name"><?php esc_html_e( 'Action Name', 'form-handler-wp' ); ?> <span style="color:#d63638;">*</span></label></th>
					<td>
						<input type="text" id="fhw_action_name" name="action_name"
							pattern="[a-z0-9_]+" placeholder="contact_form_submit" class="regular-text" required
							value="<?php echo $is_editing ? esc_attr( $editing_form['action_name'] ) : ''; ?>" />
						<span class="description"><?php esc_html_e( 'Unique slug (lowercase, numbers, underscores). Used as the WordPress AJAX action.', 'form-handler-wp' ); ?></span>
						<?php if ( $is_editing ) : ?>
							<span class="description" style="color:#d63638;display:block;margin-top:4px;"><?php esc_html_e( 'Changing this will break any forms on your site using the old value.', 'form-handler-wp' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fhw_to_emails"><?php esc_html_e( 'Recipient Email(s)', 'form-handler-wp' ); ?> <span style="color:#d63638;">*</span></label></th>
					<td>
						<input type="text" id="fhw_to_emails" name="to_emails"
							placeholder="you@example.com, other@example.com" class="regular-text" required
							value="<?php echo $is_editing ? esc_attr( $editing_form['to_emails'] ) : ''; ?>" />
						<span class="description"><?php esc_html_e( 'Comma-separated list of recipient addresses.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fhw_subject_tpl"><?php esc_html_e( 'Subject Template', 'form-handler-wp' ); ?> <span style="color:#d63638;">*</span></label></th>
					<td>
						<input type="text" id="fhw_subject_tpl" name="subject_tpl"
							placeholder="New message from {name} — {site_name}" class="regular-text" required
							value="<?php echo $is_editing ? esc_attr( $editing_form['subject_tpl'] ) : ''; ?>" />
						<span class="description"><?php esc_html_e( 'Use {field_name} for any submitted field, or {site_name} for your site name.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fhw_reply_to_field"><?php esc_html_e( 'Reply-To Field Name', 'form-handler-wp' ); ?></label></th>
					<td>
						<input type="text" id="fhw_reply_to_field" name="reply_to_field"
							placeholder="email" class="regular-text"
							value="<?php echo $is_editing ? esc_attr( $editing_form['reply_to_field'] ) : ''; ?>" />
						<span class="description"><?php esc_html_e( 'POST field name whose value will be used as Reply-To (must be an email field).', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Field Schema', 'form-handler-wp' ); ?></th>
					<td>
						<div class="fhw-field-schema-wrap">
							<div id="fhw-field-rows" class="fhw-field-rows">
								<?php
								$schema_rows = ( $is_editing && ! empty( $editing_form['field_schema'] ) ) ? $editing_form['field_schema'] : array(
									array(
										'field_name' => '',
										'field_type' => 'text',
									),
								);
								$field_types = array( 'text', 'email', 'textarea', 'url', 'number', 'checkbox' );
								foreach ( $schema_rows as $row_idx => $schema_row ) :
									$row_name = sanitize_key( $schema_row['field_name'] ?? '' );
									$row_type = sanitize_key( $schema_row['field_type'] ?? 'text' );
									?>
								<div class="fhw-field-row">
									<input type="text" name="field_schema[<?php echo esc_attr( (string) $row_idx ); ?>][field_name]" placeholder="field_name" pattern="[a-z0-9_]+" value="<?php echo esc_attr( $row_name ); ?>" />
									<select name="field_schema[<?php echo esc_attr( (string) $row_idx ); ?>][field_type]">
										<?php foreach ( $field_types as $ft ) : ?>
											<option value="<?php echo esc_attr( $ft ); ?>"<?php selected( $row_type, $ft ); ?>><?php echo esc_html( $ft ); ?></option>
										<?php endforeach; ?>
									</select>
									<button type="button" class="fhw-remove-field" aria-label="<?php esc_attr_e( 'Remove field', 'form-handler-wp' ); ?>">&times;</button>
								</div>
								<?php endforeach; ?>
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
						<textarea id="fhw_success_message" name="success_message" rows="3" class="large-text"><?php echo $is_editing ? esc_textarea( $editing_form['success_message'] ) : ''; ?></textarea>
						<span class="description"><?php esc_html_e( 'Shown to the user after successful submission. HTML allowed.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'HTML Email', 'form-handler-wp' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="html_email" value="1"
								<?php checked( $is_editing && '1' === $editing_form['html_email'] ); ?> />
							<?php esc_html_e( 'Send email as HTML', 'form-handler-wp' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-Reply to Submitter', 'form-handler-wp' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="autoreply_enabled" value="1" id="fhw_autoreply_enabled"
								<?php checked( $is_editing && '1' === $editing_form['autoreply_enabled'] ); ?> />
							<?php esc_html_e( 'Send a confirmation email to the person who filled out the form', 'form-handler-wp' ); ?>
						</label>
					</td>
				</tr>
				<tr class="fhw-autoreply-row"<?php echo ( $is_editing && '1' === $editing_form['autoreply_enabled'] ) ? ' style="display:table-row;"' : ''; ?>>
					<th scope="row"><label for="fhw_autoreply_to_field"><?php esc_html_e( 'Submitter Email Field', 'form-handler-wp' ); ?> <span style="color:#d63638;">*</span></label></th>
					<td>
						<input type="text" id="fhw_autoreply_to_field" name="autoreply_to_field"
							placeholder="email" class="regular-text"
							value="<?php echo $is_editing ? esc_attr( $editing_form['autoreply_to_field'] ) : ''; ?>" />
						<span class="description"><?php esc_html_e( 'The field name that contains the submitter\'s email address.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr class="fhw-autoreply-row"<?php echo ( $is_editing && '1' === $editing_form['autoreply_enabled'] ) ? ' style="display:table-row;"' : ''; ?>>
					<th scope="row"><label for="fhw_autoreply_subject"><?php esc_html_e( 'Confirmation Subject', 'form-handler-wp' ); ?></label></th>
					<td>
						<input type="text" id="fhw_autoreply_subject" name="autoreply_subject"
							placeholder="<?php esc_attr_e( 'Thanks for contacting {site_name}!', 'form-handler-wp' ); ?>"
							class="regular-text"
							value="<?php echo $is_editing ? esc_attr( $editing_form['autoreply_subject'] ) : ''; ?>" />
						<span class="description"><?php esc_html_e( 'Supports {field_name} and {site_name} placeholders. Leave blank for a generic default.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr class="fhw-autoreply-row"<?php echo ( $is_editing && '1' === $editing_form['autoreply_enabled'] ) ? ' style="display:table-row;"' : ''; ?>>
					<th scope="row"><label for="fhw_autoreply_message"><?php esc_html_e( 'Confirmation Message', 'form-handler-wp' ); ?></label></th>
					<td>
						<textarea id="fhw_autoreply_message" name="autoreply_message" rows="4" class="large-text"><?php echo $is_editing ? esc_textarea( $editing_form['autoreply_message'] ) : ''; ?></textarea>
						<span class="description"><?php esc_html_e( 'Supports {field_name} and {site_name} placeholders. HTML allowed. Leave blank for a generic thank-you message.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fhw_honeypot_field"><?php esc_html_e( 'Honeypot Field Name', 'form-handler-wp' ); ?></label></th>
					<td>
						<input type="text" id="fhw_honeypot_field" name="honeypot_field"
							placeholder="website" class="regular-text"
							value="<?php echo $is_editing ? esc_attr( $editing_form['honeypot_field'] ) : ''; ?>" />
						<span class="description"><?php esc_html_e( 'Hidden field name. If filled by a bot, submission is silently discarded.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fhw_rate_limit"><?php esc_html_e( 'Rate Limit', 'form-handler-wp' ); ?></label></th>
					<td>
						<input type="number" id="fhw_rate_limit" name="rate_limit"
							value="<?php echo $is_editing ? esc_attr( (string) $editing_form['rate_limit'] ) : '0'; ?>" min="0" max="999" style="width:80px;" />
						<span class="description"><?php esc_html_e( 'Max submissions per IP per hour. Set to 0 to disable.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr class="form-field">
					<th scope="row">
						<label for="fhw_spam_filter"><?php esc_html_e( 'Spam Filter', 'form-handler-wp' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="fhw_spam_filter" name="spam_filter" value="1"
							<?php checked( ! $is_editing || '1' === ( $editing_form['spam_filter'] ?? '1' ) ); ?> />
							<?php esc_html_e( 'Enable spam filtering for this form', 'form-handler-wp' ); ?>
						</label>
						<span class="description"><?php esc_html_e( 'Blocks common spam patterns. Individual rules can be configured below.', 'form-handler-wp' ); ?></span>
					</td>
				</tr>
				<tr class="form-field fhw-spam-rules-row"<?php echo ( ! $is_editing || '1' === ( $editing_form['spam_filter'] ?? '1' ) ) ? ' style="display:table-row;"' : ''; ?>>
					<th scope="row"><?php esc_html_e( 'Spam Rules', 'form-handler-wp' ); ?></th>
					<td>
						<fieldset>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox" name="spam_rule_no_user_agent" value="1"
								<?php checked( ! $is_editing || '1' === ( $editing_form['spam_rule_no_user_agent'] ?? '1' ) ); ?> />
								<?php esc_html_e( 'Block requests with no browser user-agent', 'form-handler-wp' ); ?>
								<span style="display:block;color:#646970;font-size:12px;"><?php esc_html_e( 'Almost all bots omit this. Safe to keep on.', 'form-handler-wp' ); ?></span>
							</label>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox" name="spam_rule_all_digits" value="1"
								<?php checked( ! $is_editing || '1' === ( $editing_form['spam_rule_all_digits'] ?? '1' ) ); ?> />
								<?php esc_html_e( 'Block submissions where a field contains only digits (over 10 chars)', 'form-handler-wp' ); ?>
								<span style="display:block;color:#646970;font-size:12px;"><?php esc_html_e( 'Turn off if you expect long numeric inputs (e.g. account numbers).', 'form-handler-wp' ); ?></span>
							</label>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox" name="spam_rule_no_spaces" value="1"
								<?php checked( ! $is_editing || '1' === ( $editing_form['spam_rule_no_spaces'] ?? '1' ) ); ?> />
								<?php esc_html_e( 'Block single-word messages (no spaces, over 10 chars)', 'form-handler-wp' ); ?>
								<span style="display:block;color:#646970;font-size:12px;"><?php esc_html_e( 'Turn off if you expect very short or single-word responses.', 'form-handler-wp' ); ?></span>
							</label>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox" name="spam_rule_ai_greeting" value="1"
								<?php checked( ! $is_editing || '1' === ( $editing_form['spam_rule_ai_greeting'] ?? '1' ) ); ?> />
								<?php esc_html_e( 'Block AI-generated greeting openers (&#8220;Hi! I just&#8230;&#8221;, &#8220;Hello there! I just&#8230;&#8221;)', 'form-handler-wp' ); ?>
								<span style="display:block;color:#646970;font-size:12px;"><?php esc_html_e( 'Rarely triggers on real humans. Safe to keep on.', 'form-handler-wp' ); ?></span>
							</label>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox" name="spam_rule_buy_link" value="1"
								<?php checked( ! $is_editing || '1' === ( $editing_form['spam_rule_buy_link'] ?? '1' ) ); ?> />
								<?php esc_html_e( 'Block messages containing &#8220;buy&#8221; and a hyperlink', 'form-handler-wp' ); ?>
								<span style="display:block;color:#646970;font-size:12px;"><?php esc_html_e( 'Turn off if your form legitimately accepts messages with product links.', 'form-handler-wp' ); ?></span>
							</label>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox" name="spam_rule_spammy_email_url" value="1"
								<?php checked( ! $is_editing || '1' === ( $editing_form['spam_rule_spammy_email_url'] ?? '1' ) ); ?> />
								<?php esc_html_e( 'Block firstname_lastname@gmail/yahoo/hotmail.com emails combined with a URL in any field', 'form-handler-wp' ); ?>
								<span style="display:block;color:#646970;font-size:12px;"><?php echo wp_kses( __( '<strong>Turn off if your form has a &#8220;website&#8221; or &#8220;URL&#8221; field</strong> &#8212; this combo can trigger on real users.', 'form-handler-wp' ), array( 'strong' => array() ) ); ?></span>
							</label>
						</fieldset>
					</td>
				</tr>
			</table>
			</div><!-- /.fhw-log-table-wrap -->

			<?php
			if ( $is_editing ) {
				submit_button( __( 'Update Form', 'form-handler-wp' ), 'primary', 'submit', false );
				echo ' <a href="' . esc_url( admin_url( 'admin.php?page=form-handler-wp&tab=forms' ) ) . '" class="button" style="margin-left:8px;">' . esc_html__( 'Cancel', 'form-handler-wp' ) . '</a>';
			} else {
				submit_button( __( 'Add Form Handler', 'form-handler-wp' ) );
			}
			?>
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

			<div class="fhw-log-table-wrap">
			<div class="fhw-log-table-wrap"><table class="fhw-log-table fhw-submissions-table">
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

						// Build data attributes for the modal.
						$modal_fields = array();
						foreach ( $decoded_fields as $f_key => $f_val ) {
							$modal_fields[] = array(
								'key' => (string) $f_key,
								'val' => (string) $f_val,
							);
						}
						?>
						<tr class="fhw-sub-row"
							data-id="<?php echo esc_attr( (string) $entry_id ); ?>"
							data-date="<?php echo esc_attr( $sub_entry['submitted_at'] ); ?>"
							data-form="<?php echo esc_attr( $sub_entry['action_name'] ); ?>"
							data-status="<?php echo esc_attr( $sub_entry['email_status'] ); ?>"
							data-spam-reason="<?php echo esc_attr( $sub_entry['spam_reason'] ?? '' ); ?>"
							data-fields="<?php echo esc_attr( wp_json_encode( $modal_fields ) ); ?>"
							style="cursor:pointer;">
							<td><?php echo esc_html( $sub_entry['submitted_at'] ); ?></td>
							<td><code><?php echo esc_html( $sub_entry['action_name'] ); ?></code></td>
							<td><?php echo wp_kses( $summary, array() ); ?></td>
							<td>
								<span class="fhw-log-<?php echo esc_attr( $sub_entry['email_status'] ); ?>">
									<?php echo esc_html( $sub_entry['email_status'] ); ?>
								</span>
								<?php if ( 'spam' === $sub_entry['email_status'] && ! empty( $sub_entry['spam_reason'] ) ) : ?>
									<br><small class="fhw-spam-reason"><?php echo esc_html( $sub_entry['spam_reason'] ); ?></small>
								<?php endif; ?>
							</td>
							<td>
								<form method="post"
									action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
									class="fhw-delete-sub-form"
									style="display:inline;">
									<input type="hidden" name="action" value="fhw_delete_submission" />
									<input type="hidden" name="submission_id" value="<?php echo esc_attr( (string) $entry_id ); ?>" />
									<input type="hidden" name="paged" value="<?php echo esc_attr( (string) $sub_paged ); ?>" />
									<input type="hidden" name="action_name_filter" value="<?php echo esc_attr( $sub_filter ); ?>" />
									<?php wp_nonce_field( 'fhw_delete_submission_' . $entry_id, 'fhw_delete_submission_nonce' ); ?>
									<button type="submit" class="button button-small button-link-delete fhw-delete-sub-btn">
										<?php esc_html_e( 'Delete', 'form-handler-wp' ); ?>
									</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div><!-- .fhw-log-table-wrap -->

			<?php // Modal overlay. ?>
			<div id="fhw-sub-modal" role="dialog" aria-modal="true" aria-labelledby="fhw-modal-title" style="display:none;">
				<div id="fhw-sub-modal-backdrop"></div>
				<div id="fhw-sub-modal-box">
					<div id="fhw-sub-modal-header">
						<h2 id="fhw-modal-title"><?php esc_html_e( 'Submission Detail', 'form-handler-wp' ); ?></h2>
						<button type="button" id="fhw-sub-modal-close" aria-label="<?php esc_attr_e( 'Close', 'form-handler-wp' ); ?>">&times;</button>
					</div>
					<div id="fhw-sub-modal-meta">
						<span id="fhw-modal-form"></span>
						<span id="fhw-modal-date"></span>
						<span id="fhw-modal-status"></span>
					</div>
					<table id="fhw-sub-modal-fields">
						<tbody></tbody>
					</table>
					</div><!-- /.fhw-log-table-wrap -->
			</div><!-- .fhw-log-table-wrap -->
					<div id="fhw-sub-modal-footer">
						<button type="button" id="fhw-modal-delete-btn" class="button button-link-delete">
							<?php esc_html_e( 'Delete Submission', 'form-handler-wp' ); ?>
						</button>
						<button type="button" id="fhw-sub-modal-close-footer" class="button">
							<?php esc_html_e( 'Close', 'form-handler-wp' ); ?>
						</button>
					</div>
				</div>
			</div>

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

	<?php // Modal + submissions JS. ?>
	<script>
	( function() {
		var modal    = document.getElementById( 'fhw-sub-modal' );
		var backdrop = document.getElementById( 'fhw-sub-modal-backdrop' );
		var closeBtn = document.getElementById( 'fhw-sub-modal-close' );
		var closeFtr = document.getElementById( 'fhw-sub-modal-close-footer' );
		var delBtn   = document.getElementById( 'fhw-modal-delete-btn' );
		var tbody    = document.querySelector( '#fhw-sub-modal-fields tbody' );
		var currentDeleteForm = null;

		if ( ! modal ) { return; }

		function openModal( row ) {
			var fields = [];
			try { fields = JSON.parse( row.dataset.fields || '[]' ); } catch(e) {}

			document.getElementById( 'fhw-modal-form' ).textContent   = row.dataset.form   || '';
			document.getElementById( 'fhw-modal-date' ).textContent   = row.dataset.date   || '';

			var statusEl   = document.getElementById( 'fhw-modal-status' );
			var status     = row.dataset.status || '';
			var spamReason = row.dataset.spamReason || '';
			statusEl.textContent = status;
			statusEl.className   = 'fhw-log-' + status;
			if ( 'spam' === status && spamReason ) {
				var reasonEl = document.createElement( 'small' );
				reasonEl.className   = 'fhw-spam-reason';
				reasonEl.textContent = ' (' + spamReason + ')';
				statusEl.appendChild( reasonEl );
			}

			// Populate fields table.
			tbody.innerHTML = '';
			if ( fields.length ) {
				fields.forEach( function( f ) {
					var tr = document.createElement( 'tr' );
					var th = document.createElement( 'th' );
					var td = document.createElement( 'td' );
					th.textContent = f.key;
					td.textContent = f.val;
					tr.appendChild( th );
					tr.appendChild( td );
					tbody.appendChild( tr );
				} );
			} else {
				var tr = document.createElement( 'tr' );
				var td = document.createElement( 'td' );
				td.setAttribute( 'colspan', '2' );
				td.textContent = '<?php echo esc_js( __( '(no fields)', 'form-handler-wp' ) ); ?>';
				tr.appendChild( td );
				tbody.appendChild( tr );
			}

			// Wire delete button to the hidden form in this row.
			currentDeleteForm = row.querySelector( '.fhw-delete-sub-form' );

			modal.style.display = 'flex';
			document.body.classList.add( 'fhw-modal-open' );
			closeBtn.focus();
		}

		function closeModal() {
			modal.style.display = 'none';
			document.body.classList.remove( 'fhw-modal-open' );
			currentDeleteForm = null;
		}

		// Open on row click (but not on the delete button itself).
		document.addEventListener( 'click', function( e ) {
			var row = e.target.closest( '.fhw-sub-row' );
			if ( ! row ) { return; }
			if ( e.target.closest( '.fhw-delete-sub-form' ) ) { return; }
			openModal( row );
		} );

		// Close on backdrop / close buttons.
		backdrop.addEventListener( 'click', closeModal );
		closeBtn.addEventListener( 'click', closeModal );
		closeFtr.addEventListener( 'click', closeModal );

		// Escape key closes.
		document.addEventListener( 'keydown', function( e ) {
			if ( 'Escape' === e.key && modal.style.display !== 'none' ) {
				closeModal();
			}
		} );

		// Delete button inside modal submits the row's delete form.
		delBtn.addEventListener( 'click', function() {
			if ( ! currentDeleteForm ) { return; }
			if ( ! confirm( '<?php echo esc_js( __( 'Delete this submission?', 'form-handler-wp' ) ); ?>' ) ) { return; }
			currentDeleteForm.submit();
		} );
	}() );
	</script>

<?php elseif ( 'log' === $current_tab ) : ?>

	<div class="fhw-card">
		<h2><?php esc_html_e( 'Email Log', 'form-handler-wp' ); ?></h2>

		<?php if ( empty( $log ) ) : ?>
			<p class="fhw-empty-state"><?php esc_html_e( 'No emails logged yet.', 'form-handler-wp' ); ?></p>
		<?php else : ?>
			<div class="fhw-log-table-wrap">
			<div class="fhw-log-table-wrap"><table class="fhw-log-table">
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
			</div><!-- .fhw-log-table-wrap -->

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
		<div class="fhw-log-table-wrap">
		<div class="fhw-log-table-wrap"><table class="fhw-forms-table">
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
				<tr>
					<td><code class="fhw-action-code">data-fhw-loading-text="..."</code> <?php esc_html_e( '(on submit button)', 'form-handler-wp' ); ?></td>
					<td><?php esc_html_e( 'Text shown on the submit button while the request is in flight. Defaults to &#8220;Sending&#8230;&#8221;. The button is disabled automatically during submission.', 'form-handler-wp' ); ?></td>
				</tr>
			</tbody>
		</table>
		</div><!-- .fhw-log-table-wrap -->
	</div>

	<div class="fhw-card">
		<h2><?php esc_html_e( 'Custom Events &amp; Extensibility', 'form-handler-wp' ); ?></h2>
		<p><?php esc_html_e( 'The plugin fires custom DOM events on the form element after each submission. Use these to run your own JavaScript &#8212; analytics tracking, redirects, modals, conditional logic &#8212; without touching the plugin.', 'form-handler-wp' ); ?></p>
		<div class="fhw-log-table-wrap">
		<div class="fhw-log-table-wrap"><table class="fhw-forms-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Event', 'form-handler-wp' ); ?></th>
					<th><?php esc_html_e( 'When it fires', 'form-handler-wp' ); ?></th>
					<th><?php esc_html_e( 'event.detail', 'form-handler-wp' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code class="fhw-action-code">fhw:submit</code></td>
					<td><?php esc_html_e( 'Immediately when the form is submitted (before the AJAX request).', 'form-handler-wp' ); ?></td>
					<td><code class="fhw-action-code">{ action }</code></td>
				</tr>
				<tr>
					<td><code class="fhw-action-code">fhw:success</code></td>
					<td><?php esc_html_e( 'After a successful submission and email send.', 'form-handler-wp' ); ?></td>
					<td><code class="fhw-action-code">{ action, message, response }</code></td>
				</tr>
				<tr>
					<td><code class="fhw-action-code">fhw:error</code></td>
					<td><?php esc_html_e( 'After a failed submission (server error or network failure).', 'form-handler-wp' ); ?></td>
					<td><code class="fhw-action-code">{ action, message, response }</code></td>
				</tr>
			</tbody>
		</table>
		</div><!-- .fhw-log-table-wrap -->
		<p style="margin-top:16px;"><?php esc_html_e( 'All events bubble, so you can listen on document to catch any form on the page.', 'form-handler-wp' ); ?></p>
		<p><?php esc_html_e( 'Example &#8212; fire a Google Analytics event on success:', 'form-handler-wp' ); ?></p>
		<pre style="background:#f6f7f7;padding:16px;border-radius:4px;overflow:auto;font-size:13px;">document.querySelector( &#39;[data-fhw-form=&quot;contact_form&quot;]&#39; )
	.addEventListener( &#39;fhw:success&#39;, function( e ) {
	gtag( &#39;event&#39;, &#39;form_submit&#39;, { form_id: e.detail.action } );
	} );</pre>
		<p><?php esc_html_e( 'Example &#8212; redirect to a thank-you page after success:', 'form-handler-wp' ); ?></p>
		<pre style="background:#f6f7f7;padding:16px;border-radius:4px;overflow:auto;font-size:13px;">document.querySelector( &#39;[data-fhw-form=&quot;contact_form&quot;]&#39; )
	.addEventListener( &#39;fhw:success&#39;, function() {
	window.location.href = &#39;/thank-you/&#39;;
	} );</pre>
		<p><?php esc_html_e( 'Example &#8212; show a custom modal overlay on success:', 'form-handler-wp' ); ?></p>
		<pre style="background:#f6f7f7;padding:16px;border-radius:4px;overflow:auto;font-size:13px;">document.querySelector( &#39;[data-fhw-form=&quot;contact_form&quot;]&#39; )
	.addEventListener( &#39;fhw:success&#39;, function( e ) {
	document.getElementById( &#39;my-modal&#39; ).style.display = &#39;flex&#39;;
	document.getElementById( &#39;my-modal-msg&#39; ).textContent = e.detail.message;
	} );</pre>
	</div>

	<div class="fhw-card">
		<h2><?php esc_html_e( 'Accessibility', 'form-handler-wp' ); ?></h2>
		<p><?php esc_html_e( 'The plugin handles the basics automatically:', 'form-handler-wp' ); ?></p>
		<ul style="list-style:disc;margin-left:20px;line-height:1.8;">
			<li><?php echo wp_kses( __( 'The status element has <code>role="alert"</code> and <code>aria-live="polite"</code> &#8212; screen readers will announce success and error messages without needing extra markup.', 'form-handler-wp' ), array( 'code' => array() ) ); ?></li>
			<li><?php esc_html_e( 'After submission, keyboard focus is moved to the status element so screen reader users land directly on the result.', 'form-handler-wp' ); ?></li>
			<li><?php esc_html_e( 'The submit button is disabled while the request is in flight to prevent duplicate submissions.', 'form-handler-wp' ); ?></li>
		</ul>
		<p><?php esc_html_e( 'For full WCAG 2.1 AA compliance you should also:', 'form-handler-wp' ); ?></p>
		<ul style="list-style:disc;margin-left:20px;line-height:1.8;">
			<li><?php esc_html_e( 'Associate every input with a visible label using the for/id pattern or aria-label.', 'form-handler-wp' ); ?></li>
			<li><?php esc_html_e( 'Use the fhw:error event to add aria-describedby or aria-invalid attributes to specific fields with validation errors.', 'form-handler-wp' ); ?></li>
			<li><?php esc_html_e( 'Ensure sufficient colour contrast on the success/error status messages.', 'form-handler-wp' ); ?></li>
		</ul>
	</div>

	<div class="fhw-card">
		<h2><?php esc_html_e( 'Custom Validation', 'form-handler-wp' ); ?></h2>
		<p><?php esc_html_e( 'Native HTML5 validation (required, type="email", pattern, minlength, etc.) runs before the plugin intercepts the submit &#8212; no extra work needed for basic validation.', 'form-handler-wp' ); ?></p>
		<p><?php esc_html_e( 'For custom validation logic (e.g. matching two fields, conditional rules), use the fhw:submit event to run checks before the AJAX fires. Since the event is not cancellable, the recommended pattern is to prevent the native submit yourself:', 'form-handler-wp' ); ?></p>
		<pre style="background:#f6f7f7;padding:16px;border-radius:4px;overflow:auto;font-size:13px;">var myForm = document.querySelector( &#39;[data-fhw-form=&quot;contact_form&quot;]&#39; );

myForm.addEventListener( &#39;submit&#39;, function( e ) {
	var email    = myForm.querySelector( &#39;[name=&quot;email&quot;]&#39; ).value;
	var confirm  = myForm.querySelector( &#39;[name=&quot;email_confirm&quot;]&#39; ).value;
	if ( email !== confirm ) {
	e.stopImmediatePropagation(); // Prevent plugin from handling submit.
	document.getElementById( &#39;email-error&#39; ).textContent = &#39;Emails do not match.&#39;;
	}
}, true ); // &lt;-- useCapture: true runs before the plugin&#39;s listener</pre>
	</div>

	<div class="fhw-card">
		<h2><?php esc_html_e( 'Secure API Key (Recommended)', 'form-handler-wp' ); ?></h2>
		<p><?php esc_html_e( 'For better security, define your Brevo API key as a constant in wp-config.php instead of storing it in the database:', 'form-handler-wp' ); ?></p>
		<pre style="background:#f6f7f7;padding:16px;border-radius:4px;overflow:auto;font-size:13px;">define( 'FHW_BREVO_API_KEY', 'your-brevo-v3-api-key' );</pre>
		<p><?php esc_html_e( 'When this constant is present the API key field on the Brevo Settings tab is disabled and the constant value is used automatically.', 'form-handler-wp' ); ?></p>
	</div>

	<div class="fhw-card">
		<h2><?php esc_html_e( 'Spam Filtering', 'form-handler-wp' ); ?></h2>
		<p><?php esc_html_e( 'Spam filtering is enabled per form and is on by default. It runs a series of lightweight checks against each submission.', 'form-handler-wp' ); ?></p>
		<ul style="list-style:disc;margin-left:20px;line-height:1.8;">
			<li><?php esc_html_e( 'There are 6 individual rules, each of which can be toggled on or off when adding or editing a form handler.', 'form-handler-wp' ); ?></li>
			<li><?php esc_html_e( 'Blocked submissions are still recorded in the Submissions tab with the status &#8220;spam&#8221; &#8212; check there if real users report their submissions are not going through.', 'form-handler-wp' ); ?></li>
			<li><?php echo wp_kses( __( 'The <strong>spammy email + URL</strong> rule is the most likely to cause false positives. If your form has a &#8220;website&#8221; or &#8220;URL&#8221; field, consider turning it off.', 'form-handler-wp' ), array( 'strong' => array() ) ); ?></li>
		</ul>
	</div>

	<div class="fhw-card">
		<h2><?php esc_html_e( 'Honeypot Protection', 'form-handler-wp' ); ?></h2>
		<p><?php esc_html_e( 'A honeypot is a hidden form field that real users never see or fill in &#8212; but bots do. If the field has a value when the form is submitted, the submission is silently discarded.', 'form-handler-wp' ); ?></p>
		<p><?php esc_html_e( 'To set it up:', 'form-handler-wp' ); ?></p>
		<ol style="list-style:decimal;margin-left:20px;line-height:1.8;">
			<li><?php esc_html_e( 'Add a hidden field to your HTML form using a plausible-looking field name.', 'form-handler-wp' ); ?></li>
			<li><?php esc_html_e( 'Hide it from real users with inline CSS (display:none) and accessibility attributes.', 'form-handler-wp' ); ?></li>
			<li><?php esc_html_e( 'In the form config, set &#8220;Honeypot Field Name&#8221; to match the field name you chose.', 'form-handler-wp' ); ?></li>
		</ol>
		<p><?php esc_html_e( 'Example:', 'form-handler-wp' ); ?></p>
		<pre style="background:#f6f7f7;padding:16px;border-radius:4px;overflow:auto;font-size:13px;">&lt;input type=&quot;text&quot; name=&quot;website&quot; style=&quot;display:none;&quot; tabindex=&quot;-1&quot; autocomplete=&quot;off&quot; /&gt;</pre>
		<p>
			<?php
			echo wp_kses(
				__( 'Then set <strong>Honeypot Field</strong> to <code>website</code> in the form config.', 'form-handler-wp' ),
				array(
					'strong' => array(),
					'code'   => array(),
				)
			);
			?>
		</p>
	</div>

<?php endif; ?>

</div>
