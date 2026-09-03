<?php
/**
 * More accordion hub in the admin sidebar.
 *
 * @package Tapio\CleanBackend
 */

namespace Tapio\CleanBackend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues More accordion assets (toggle is injected via JS before Collapse Menu).
 */
class More_Menu {

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! is_admin() || is_network_admin() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueues More accordion CSS and JS.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'tapio-clean-backend-more',
			TAPIO_CLEAN_BACKEND_URL . 'assets/css/admin-more.css',
			array( 'admin-menu', 'dashicons' ),
			TAPIO_CLEAN_BACKEND_VERSION
		);

		wp_enqueue_script(
			'tapio-clean-backend-more',
			TAPIO_CLEAN_BACKEND_URL . 'assets/js/admin-more.js',
			array(),
			TAPIO_CLEAN_BACKEND_VERSION,
			true
		);

		wp_localize_script(
			'tapio-clean-backend-more',
			'tapioCleanBackendMore',
			array(
				'moreItemClass' => Menu_Classifier::MORE_ITEM_CLASS,
				'storageKey'    => 'tapioCleanBackendMoreOpen',
				'moreLabel'     => __( 'More', 'tapio-clean-backend' ),
			)
		);
	}
}
