<?php
/**
 * Admin settings: registers menus, tabs, and AJAX handlers for the settings page.
 *
 * @package Form_Handler_WP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FHW_Settings
 */
class FHW_Settings {

	/**
	 * Constructor: hook everything in.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_fhw_save_brevo_settings', array( $this, 'save_brevo_settings' ) );
		add_action( 'admin_post_fhw_add_form', array( $this, 'save_new_form' ) );
		add_action( 'admin_post_fhw_delete_form', array( $this, 'delete_form' ) );
		add_action( 'admin_post_fhw_clear_log', array( $this, 'clear_log' ) );
		add_action( 'admin_post_fhw_delete_submission', array( $this, 'delete_submission' ) );
		add_action( 'admin_post_fhw_clear_submissions', array( $this, 'clear_submissions' ) );
		add_action( 'wp_ajax_fhw_send_test_email', array( $this, 'ajax_send_test_email' ) );
	}

	/**
	 * Register top-level admin menu.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Form Handler WP', 'form-handler-wp' ),
			__( 'Form Handler WP', 'form-handler-wp' ),
			'manage_options',
			'form-handler-wp',
			array( $this, 'render_settings_page' ),
			'dashicons-email-alt',
			58
		);
	}

	/**
	 * Enqueue admin styles and scripts on our own page only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'toplevel_page_form-handler-wp' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'fhw-admin',
			FHW_PLUGIN_URL . 'assets/css/fhw-admin.css',
			array(),
			FHW_VERSION
		);

		wp_enqueue_script(
			'fhw-admin',
			FHW_PLUGIN_URL . 'assets/js/fhw-admin.js',
			array( 'jquery' ),
			FHW_VERSION,
			true
		);

		wp_localize_script(
			'fhw-admin',
			'fhwAdmin',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'testEmailNonce'  => wp_create_nonce( 'fhw_test_email' ),
				/* translators: status message shown in admin */
				'sending'         => __( 'Sending…', 'form-handler-wp' ),
				/* translators: status message shown in admin */
				'testSuccess'     => __( 'Test email sent successfully!', 'form-handler-wp' ),
				/* translators: status message shown in admin */
				'testFail'        => __( 'Test email failed. Check your API key and sender settings.', 'form-handler-wp' ),
				/* translators: confirmation prompt before deleting a form */
				'confirmDelete'   => __( 'Are you sure you want to delete this form handler? This cannot be undone.', 'form-handler-wp' ),
				/* translators: confirmation prompt before clearing the log */
				'confirmClearLog' => __( 'Are you sure you want to clear the entire email log?', 'form-handler-wp' ),
			)
		);
	}

	/**
	 * Render the settings page via the admin template.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'form-handler-wp' ) );
		}
		require_once FHW_PLUGIN_DIR . 'admin/settings-page.php';
	}

	/**
	 * Handle Brevo settings form POST.
	 */
	public function save_brevo_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'form-handler-wp' ) );
		}

		check_admin_referer( 'fhw_brevo_settings', 'fhw_brevo_nonce' );

		// API key — store AES-256-CBC encrypted.
		if ( isset( $_POST['fhw_brevo_api_key'] ) ) {
			$raw_key = sanitize_text_field( wp_unslash( $_POST['fhw_brevo_api_key'] ) );
			if ( '' !== $raw_key && '••••••••••••••••' !== $raw_key ) {
				$encrypted = FHW_Crypto::encrypt( $raw_key );
				if ( false !== $encrypted ) {
					update_option( 'fhw_brevo_api_key_enc', $encrypted );
				}
			}
		}

		if ( isset( $_POST['fhw_sender_email'] ) ) {
			update_option( 'fhw_sender_email', sanitize_email( wp_unslash( $_POST['fhw_sender_email'] ) ) );
		}

		if ( isset( $_POST['fhw_sender_name'] ) ) {
			update_option( 'fhw_sender_name', sanitize_text_field( wp_unslash( $_POST['fhw_sender_name'] ) ) );
		}

		$override = ! empty( $_POST['fhw_override_wp_mail'] ) ? '1' : '0';
		update_option( 'fhw_override_wp_mail', $override );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'form-handler-wp',
					'tab'     => 'brevo',
					'updated' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle Add New Form handler POST.
	 */
	public function save_new_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'form-handler-wp' ) );
		}

		check_admin_referer( 'fhw_add_form', 'fhw_add_form_nonce' );

		$registry = new FHW_Form_Registry();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized inside add_form().
		$result = $registry->add_form( $_POST );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'form-handler-wp',
						'tab'   => 'forms',
						'error' => rawurlencode( $result->get_error_message() ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => 'form-handler-wp',
					'tab'   => 'forms',
					'added' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle Delete Form POST.
	 */
	public function delete_form() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'form-handler-wp' ) );
		}

		check_admin_referer( 'fhw_delete_form', 'fhw_delete_nonce' );

		$action_name = isset( $_POST['fhw_action_name'] ) ? sanitize_key( wp_unslash( $_POST['fhw_action_name'] ) ) : '';

		if ( $action_name ) {
			$registry = new FHW_Form_Registry();
			$registry->delete_form( $action_name );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'form-handler-wp',
					'tab'     => 'forms',
					'deleted' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle Clear Log POST.
	 */
	public function clear_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'form-handler-wp' ) );
		}

		check_admin_referer( 'fhw_clear_log', 'fhw_clear_log_nonce' );

		$logger = new FHW_Logger();
		$logger->clear_log();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'form-handler-wp',
					'tab'     => 'log',
					'cleared' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle Delete Submission POST.
	 */
	public function delete_submission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'form-handler-wp' ) );
		}

		$id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
		check_admin_referer( 'fhw_delete_submission_' . $id, 'fhw_delete_submission_nonce' );

		if ( $id > 0 ) {
			$submissions = new FHW_Submissions();
			$submissions->delete_entry( $id );
		}

		$redirect_args = array(
			'page'    => 'form-handler-wp',
			'tab'     => 'submissions',
			'deleted' => '1',
		);

		$paged = isset( $_POST['paged'] ) ? absint( $_POST['paged'] ) : 1;
		if ( $paged > 1 ) {
			$redirect_args['paged'] = $paged;
		}

		$filter = isset( $_POST['action_name_filter'] ) ? sanitize_key( wp_unslash( $_POST['action_name_filter'] ) ) : '';
		if ( '' !== $filter ) {
			$redirect_args['action_name_filter'] = $filter;
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle Clear Submissions POST.
	 */
	public function clear_submissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'form-handler-wp' ) );
		}

		check_admin_referer( 'fhw_clear_submissions', 'fhw_clear_submissions_nonce' );

		$submissions = new FHW_Submissions();
		$submissions->clear_all();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'form-handler-wp',
					'tab'     => 'submissions',
					'cleared' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
