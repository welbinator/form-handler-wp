<?php
/**
 * Plugin Name: Form Handler WP
 * Plugin URI:  https://github.com/welbinator/form-handler-wp
 * Description: A WordPress plugin for handling form submissions.
 * Version:     0.1.0
 * Author:      James Welbes
 * Author URI:  https://github.com/welbinator
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: form-handler-wp
 *
 * @package FormHandlerWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FORM_HANDLER_WP_VERSION', '0.1.0' );
define( 'FORM_HANDLER_WP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FORM_HANDLER_WP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// TODO: Bootstrap plugin classes here.
