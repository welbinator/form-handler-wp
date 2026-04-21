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
		// Cached release is not newer — delete the transient so the next WP
		// update check always fetches fresh data from GitHub rather than
		// serving stale cached "no update" results for up to 12 hours.
		delete_transient( 'fhw_github_latest_release' );
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
	$sha256_url   = '';
	if ( ! empty( $release['assets'] ) ) {
		foreach ( $release['assets'] as $asset ) {
			if ( ! isset( $asset['browser_download_url'] ) || '' === $asset['browser_download_url'] ) {
				continue;
			}
			if ( isset( $asset['content_type'] ) && 'application/zip' === $asset['content_type'] && '' === $download_url ) {
				$download_url = $asset['browser_download_url'];
			}
			if ( isset( $asset['name'] ) && str_ends_with( $asset['name'], '.sha256' ) && '' === $sha256_url ) {
				$sha256_url = $asset['browser_download_url'];
			}
		}
	}

	// Require a sha256 asset — no hash means no update (fail closed).
	if ( '' === $sha256_url ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Form Handler WP] Update blocked: no .sha256 asset found for release ' . $release['tag_name'] . '.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return false;
	}

	// Fall back to GitHub's auto-generated source zip.
	if ( '' === $download_url ) {
		$tag          = rawurlencode( $release['tag_name'] );
		$download_url = 'https://github.com/welbinator/form-handler-wp/archive/refs/tags/' . $tag . '.zip';
	}

	$data = array(
		'version'      => ltrim( $release['tag_name'], 'v' ),
		'download_url' => esc_url_raw( $download_url ),
		'sha256_url'   => esc_url_raw( $sha256_url ),
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

/**
 * Verify the SHA-256 checksum of the downloaded package before installation.
 *
 * Hooked onto `upgrader_pre_install`. If the hash does not match the
 * published .sha256 asset, the install is aborted with a WP_Error.
 *
 * @param bool|WP_Error $response   Installation response (pass-through).
 * @param array         $hook_extra Extra data passed by the upgrader.
 * @return bool|WP_Error
 */
function fhw_verify_package_integrity( $response, $hook_extra ) {
	// Only act on our own plugin update.
	if (
		empty( $hook_extra['plugin'] ) ||
		FHW_PLUGIN_BASENAME !== $hook_extra['plugin']
	) {
		return $response;
	}

	// Bail early if a previous step already errored.
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$release = fhw_get_latest_github_release();
	if ( ! $release || empty( $release['sha256_url'] ) ) {
		return new WP_Error(
			'fhw_no_checksum',
			__( 'Form Handler WP update aborted: no integrity checksum available for this release.', 'form-handler-wp' )
		);
	}

	// Fetch the expected hash from the .sha256 asset.
	$hash_response = wp_remote_get(
		$release['sha256_url'],
		array(
			'timeout'    => 10,
			'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
		)
	);

	if ( is_wp_error( $hash_response ) || 200 !== (int) wp_remote_retrieve_response_code( $hash_response ) ) {
		return new WP_Error(
			'fhw_checksum_fetch_failed',
			__( 'Form Handler WP update aborted: could not retrieve integrity checksum.', 'form-handler-wp' )
		);
	}

	$expected_hash = trim( wp_remote_retrieve_body( $hash_response ) );
	if ( ! preg_match( '/^[a-f0-9]{64}$/', $expected_hash ) ) {
		return new WP_Error(
			'fhw_checksum_invalid',
			__( 'Form Handler WP update aborted: integrity checksum is malformed.', 'form-handler-wp' )
		);
	}

	// Locate the downloaded package file.
	global $wp_filesystem;
	if ( empty( $wp_filesystem ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
	}

	// WordPress stores the downloaded package path in the upgrader skin.
	// The most reliable way to get it is via the upgrader's stored result.
	// We hash the package file directly from the temp location WordPress used.
	$package_path = '';
	if ( isset( $GLOBALS['fhw_upgrader_package_path'] ) ) {
		$package_path = $GLOBALS['fhw_upgrader_package_path'];
	}

	if ( '' === $package_path || ! file_exists( $package_path ) ) {
		return new WP_Error(
			'fhw_package_not_found',
			__( 'Form Handler WP update aborted: downloaded package could not be located for integrity check.', 'form-handler-wp' )
		);
	}

	$actual_hash = hash_file( 'sha256', $package_path );
	if ( $actual_hash !== $expected_hash ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Form Handler WP] Integrity check FAILED. Expected: ' . $expected_hash . ' Got: ' . $actual_hash ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return new WP_Error(
			'fhw_checksum_mismatch',
			__( 'Form Handler WP update aborted: integrity check failed. The package may have been tampered with.', 'form-handler-wp' )
		);
	}

	return $response;
}
add_filter( 'upgrader_pre_install', 'fhw_verify_package_integrity', 10, 2 );

/**
 * Capture the downloaded package path before installation begins.
 *
 * WordPress doesn't expose the temp file path to upgrader_pre_install,
 * so we hook upgrader_source_selection (which fires just before pre_install)
 * to grab it.
 *
 * @param string $source        Extracted source directory.
 * @param string $remote_source Temp path of the downloaded zip.
 * @param object $upgrader      WP_Upgrader instance.
 * @param array  $hook_extra    Extra hook data.
 * @return string
 */
function fhw_capture_package_path( $source, $remote_source, $upgrader, $hook_extra ) {
	if (
		! empty( $hook_extra['plugin'] ) &&
		FHW_PLUGIN_BASENAME === $hook_extra['plugin']
	) {
		$GLOBALS['fhw_upgrader_package_path'] = $remote_source;
	}
	return $source;
}
add_filter( 'upgrader_source_selection', 'fhw_capture_package_path', 9, 4 );
