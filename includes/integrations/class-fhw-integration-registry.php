<?php
/**
 * Integration registry.
 *
 * Discovers, loads, and dispatches all registered integrations.
 *
 * @package Form_Handler_WP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FHW_Integration_Registry
 */
class FHW_Integration_Registry {

	/**
	 * Loaded integration instances, keyed by id.
	 *
	 * @var FHW_Integration[]
	 */
	private static $integrations = array();

	/**
	 * Whether the registry has been initialised.
	 *
	 * @var bool
	 */
	private static $initialised = false;

	/**
	 * Load all built-in integrations (and any registered via filter).
	 */
	public static function init(): void {
		if ( self::$initialised ) {
			return;
		}
		self::$initialised = true;

		// Built-ins.
		$classes = array(
			'FHW_Integration_Mailchimp',
			'FHW_Integration_ActiveCampaign',
		);

		/**
		 * Allow third-party integrations to register themselves.
		 *
		 * @param string[] $classes Fully-qualified class names that implement FHW_Integration.
		 */
		$classes = apply_filters( 'fhw_integration_classes', $classes );

		foreach ( $classes as $class ) {
			if ( class_exists( $class ) ) {
				$instance                                  = new $class();
				self::$integrations[ $instance->get_id() ] = $instance;
			}
		}
	}

	/**
	 * Return all registered integrations.
	 *
	 * @return FHW_Integration[]
	 */
	public static function all(): array {
		self::init();
		return self::$integrations;
	}

	/**
	 * Return a single integration by id, or null.
	 *
	 * @param string $id Integration id.
	 * @return FHW_Integration|null
	 */
	public static function get( string $id ): ?FHW_Integration {
		self::init();
		return self::$integrations[ $id ] ?? null;
	}

	/**
	 * Run all enabled integrations for a given form submission.
	 *
	 * @param array $form        Registered form config.
	 * @param array $post_fields Sanitized submitted field values.
	 */
	public static function run_all( array $form, array $post_fields ): void {
		self::init();
		foreach ( self::$integrations as $integration ) {
			$enabled_key = 'integration_' . $integration->get_id() . '_enabled';
			if ( '1' !== ( $form[ $enabled_key ] ?? '0' ) ) {
				continue;
			}
			if ( ! $integration->is_connected() ) {
				continue;
			}
			$integration->run( $form, $post_fields );
		}
	}
}
