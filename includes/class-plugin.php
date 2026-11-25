<?php
/**
 * Plugin wiring.
 *
 * @package Tapio\CleanBackend
 */

namespace Tapio\CleanBackend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers every hook the plugin needs.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Returns the shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$settings = Settings::get();

		// Names and a saved order apply even when the More section is off.
		$admin_menu = new Admin_Menu( $settings['collapse_sidebar'] );
		$admin_menu->register();

		$settings_page = new Settings( $admin_menu );
		$settings_page->register();

		if ( $settings['relocate_notices'] ) {
			$notices_capture = new Notices_Capture();
			$notices_capture->register();

			$admin_bar = new Admin_Bar_Notifications( $notices_capture );
			$admin_bar->register();
		}
	}
}
