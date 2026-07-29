<?php
/**
 * Admin bar More toggle (collapses plugin toolbar items).
 *
 * @package Tapio\CleanBackend
 */

namespace Tapio\CleanBackend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues admin bar More assets when the toolbar is shown.
 */
class Admin_Bar_More {

	/**
	 * CSS class on toolbar nodes moved under More.
	 *
	 * @var string
	 */
	const MORE_ITEM_CLASS = 'tapio-admin-bar-more-item';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Top-level admin bar node IDs that stay visible (without wp-admin-bar- prefix).
	 *
	 * @return string[]
	 */
	public function get_core_node_ids() {
		$ids = array(
			'wp-logo',
			'site-name',
			'updates',
			'comments',
			'new-content',
			'edit',
			'customize',
			'site-editor',
			'my-sites',
			'menu-toggle',
			'search',
			'command-palette',
			'tapio-clean-notifications',
			'tapio-clean-admin-more',
		);

		/**
		 * Admin bar node IDs kept outside More (no wp-admin-bar- prefix).
		 *
		 * @param string[] $ids Node IDs.
		 */
		return apply_filters( 'tapio_clean_backend_core_admin_bar_nodes', $ids );
	}

	/**
	 * Enqueues CSS/JS when the admin bar is visible.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! is_admin_bar_showing() || ! is_user_logged_in() ) {
			return;
		}

		wp_enqueue_style(
			'tapio-clean-backend-admin-bar-more',
			TAPIO_CLEAN_BACKEND_URL . 'assets/css/admin-bar-more.css',
			array( 'admin-bar', 'dashicons' ),
			TAPIO_CLEAN_BACKEND_VERSION
		);

		wp_enqueue_script(
			'tapio-clean-backend-admin-bar-more',
			TAPIO_CLEAN_BACKEND_URL . 'assets/js/admin-bar-more.js',
			array(),
			TAPIO_CLEAN_BACKEND_VERSION,
			true
		);

		wp_localize_script(
			'tapio-clean-backend-admin-bar-more',
			'tapioCleanBackendAdminBarMore',
			array(
				'coreNodeIds'  => array_values( $this->get_core_node_ids() ),
				'moreItemClass' => self::MORE_ITEM_CLASS,
				'moreNodeId'   => 'tapio-clean-admin-more',
				'storageKey'   => 'tapioCleanBackendAdminBarMoreOpen',
				'moreLabel'    => __( 'More', 'tapio-clean-backend' ),
			)
		);
	}
}
