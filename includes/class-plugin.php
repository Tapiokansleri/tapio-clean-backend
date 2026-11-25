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
	 * Menu classifier.
	 *
	 * @var Menu_Classifier
	 */
	private $menu_classifier;

	/**
	 * Notices capture.
	 *
	 * @var Notices_Capture
	 */
	private $notices_capture;

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
		$settings = new Settings();
		$settings->register();

		// The saved order applies whether or not the More section is on.
		$menu_order = new Menu_Order();
		$menu_order->register();

		if ( Settings::is_sidebar_enabled() ) {
			$this->menu_classifier = new Menu_Classifier();
			$this->menu_classifier->register();

			$more_menu = new More_Menu();
			$more_menu->register();
		}

		if ( Settings::is_notices_enabled() ) {
			$this->notices_capture = new Notices_Capture();
			$this->notices_capture->register();

			$admin_bar = new Admin_Bar_Notifications( $this->notices_capture );
			$admin_bar->register();
		}
	}
}
