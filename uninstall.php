<?php
/**
 * Uninstall Form Handler WP.
 *
 * Intentionally does nothing. Plugin data (options, DB tables) is preserved
 * on uninstall so that reinstalling or upgrading the plugin does not lose
 * any settings, form configurations, or submission history.
 *
 * If you want to fully remove all plugin data, use the "Remove all data"
 * option in the plugin settings before deleting.
 *
 * @package Form_Handler_WP
 */

// Only run via WordPress uninstall — never directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
