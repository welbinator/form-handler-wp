<?php
/**
 * GitHub Updater for Form Handler WP.
 *
 * Hooks into WordPress's plugin update system and checks the GitHub Releases
 * API for a newer version. When a newer release is found, WordPress shows the
 * standard "Update available" notice on the Plugins page and allows one-click
 * updates — exactly as if the plugin were hosted on WordPress.org.
 *
 * Results are cached for 12 hours to avoid hammering the GitHub API.
 *
 * @package Form_Handler_WP
 * @since   1.3.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check GitHub for a newer release and populate the WordPress update transient.
 *
 * Hooked onto `pre_set_site_transient_update_plugins`.
 *
 * @param object $transient The update_plugins site transient.
 * @return object
 */
function fhw_check_for_update( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$plugin_basename = FHW_PLUGIN_BASENAME;
	$current_version = $transient->checked[ $plugin_basename ] ?? null;

	if ( ! $current_version ) {
		return $transient;
	}

	$release = fhw_get_latest_github_release();
	if ( ! $release ) {
		return $transient;
	}

	if ( version_compare( $release['version'], $current_version, '<=' ) ) {
		return $transient;
	}

	$transient->response[ $plugin_basename ] = (object) array(
		'id'           => 'github.com/welbinator/form-handler-wp',
		'slug'         => dirname( $plugin_basename ),
		'plugin'       => $plugin_basename,
		'new_version'  => $release['version'],
		'url'          => 'https://github.com/welbinator/form-handler-wp',
		'package'      => $release['download_url'],
		'icons'        => array(),
		'banners'      => array(),
		'tested'       => '',
		'requires'     => '6.0',
		'requires_php' => '7.4',
	);

	return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', 'fhw_check_for_update' );

/**
 * Populate the plugin info popup ("View version X.X.X details" link).
 *
 * Hooked onto `plugins_api`.
 *
 * @param false|object|array $result The result — false if not set.
 * @param string             $action The API action being requested.
 * @param object             $args   Request arguments.
 * @return false|object
 */
function fhw_plugin_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action ) {
		return $result;
	}

	if ( ! isset( $args->slug ) || dirname( FHW_PLUGIN_BASENAME ) !== $args->slug ) {
		return $result;
	}

	$release = fhw_get_latest_github_release();
	if ( ! $release ) {
		return $result;
	}

	return (object) array(
		'name'          => 'Form Handler WP',
		'slug'          => dirname( FHW_PLUGIN_BASENAME ),
		'version'       => $release['version'],
		'author'        => '<a href="https://github.com/welbinator">welbinator</a>',
		'homepage'      => 'https://github.com/welbinator/form-handler-wp',
		'download_link' => $release['download_url'],
		'sections'      => array(
			'description' => 'Secure AJAX form handling with Brevo transactional email. Build your own forms; we handle the sending.',
			'changelog'   => nl2br( esc_html( $release['body'] ) ),
		),
		'last_updated'  => $release['published_at'],
		'requires'      => '6.0',
		'tested'        => get_bloginfo( 'version' ),
		'requires_php'  => '7.4',
	);
}
add_filter( 'plugins_api', 'fhw_plugin_info', 20, 3 );

/**
 * Fetch the latest non-prerelease GitHub release, with 12-hour caching.
 *
 * Prefers the explicitly-built zip asset attached to the release (the one
 * our release workflow uploads with the correct folder name). Falls back to
 * GitHub's auto-generated source zip only if no asset is found.
 *
 * @return array|false Associative array with keys: version, download_url, body, published_at.
 *                     Returns false on any failure.
 */
function fhw_get_latest_github_release() {
	$cache_key = 'fhw_github_latest_release';
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/welbinator/form-handler-wp/releases/latest',
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'               => 'application/vnd.github+json',
				'User-Agent'           => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
				'X-GitHub-Api-Version' => '2022-11-28',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return false;
	}

	if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return false;
	}

	$release = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
		return false;
	}

	// Skip pre-releases — only offer stable versions as updates.
	if ( ! empty( $release['prerelease'] ) ) {
		return false;
	}

	// Prefer the explicitly-uploaded zip asset (correct folder name).
	$download_url = '';
	if ( ! empty( $release['assets'] ) ) {
		foreach ( $release['assets'] as $asset ) {
			if (
				isset( $asset['content_type'], $asset['browser_download_url'] ) &&
				'application/zip' === $asset['content_type'] &&
				'' !== $asset['browser_download_url']
			) {
				$download_url = $asset['browser_download_url'];
				break;
			}
		}
	}

	// Fall back to GitHub's auto-generated source zip.
	if ( '' === $download_url ) {
		$tag          = rawurlencode( $release['tag_name'] );
		$download_url = 'https://github.com/welbinator/form-handler-wp/archive/refs/tags/' . $tag . '.zip';
	}

	$data = array(
		'version'      => ltrim( $release['tag_name'], 'v' ),
		'download_url' => esc_url_raw( $download_url ),
		'body'         => wp_strip_all_tags( $release['body'] ?? '' ),
		'published_at' => $release['published_at'] ?? '',
	);

	set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );

	return $data;
}

/**
 * Bust the release cache immediately after a successful plugin update.
 *
 * Ensures the next update check reflects the newly installed version
 * rather than serving stale cached data.
 *
 * @param \WP_Upgrader $upgrader Upgrader instance.
 * @param array        $options  Upgrade options.
 */
function fhw_bust_update_cache( $upgrader, $options ) {
	if (
		'update' === ( $options['action'] ?? '' ) &&
		'plugin' === ( $options['type'] ?? '' ) &&
		! empty( $options['plugins'] ) &&
		in_array( FHW_PLUGIN_BASENAME, (array) $options['plugins'], true )
	) {
		delete_transient( 'fhw_github_latest_release' );
	}
}
add_action( 'upgrader_process_complete', 'fhw_bust_update_cache', 10, 2 );
