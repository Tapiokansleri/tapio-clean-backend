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
		load_plugin_textdomain(
			'tapio-clean-backend',
			false,
			dirname( plugin_basename( TAPIO_CLEAN_BACKEND_FILE ) ) . '/languages'
		);

		$this->menu_classifier = new Menu_Classifier();
		$this->notices_capture = new Notices_Capture();

		$this->menu_classifier->register();
		$this->notices_capture->register();

		$admin_bar = new Admin_Bar_Notifications( $this->notices_capture );
		$admin_bar->register();

		$more_menu = new More_Menu( $this->menu_classifier );
		$more_menu->register();

		$admin_bar_more = new Admin_Bar_More();
		$admin_bar_more->register();
	}

	/**
	 * Returns the notices capture service.
	 *
	 * @return Notices_Capture
	 */
	public function notices_capture() {
		return $this->notices_capture;
	}

	/**
	 * Returns the menu classifier service.
	 *
	 * @return Menu_Classifier
	 */
	public function menu_classifier() {
		return $this->menu_classifier;
	}
}
