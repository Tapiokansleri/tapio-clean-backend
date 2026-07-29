<?php
/**
 * Admin bar Notifications hub.
 *
 * @package Tapio\CleanBackend
 */

namespace Tapio\CleanBackend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Notifications admin bar item and injects captured notices.
 *
 * Admin bar renders before admin_notices, so panel content and badge count
 * are filled from admin_footer via JavaScript after capture completes.
 */
class Admin_Bar_Notifications {

	/**
	 * Admin bar node ID.
	 *
	 * @var string
	 */
	const NODE_ID = 'tapio-clean-notifications';

	/**
	 * Notices capture service.
	 *
	 * @var Notices_Capture
	 */
	private $capture;

	/**
	 * Constructor.
	 *
	 * @param Notices_Capture $capture Notices capture service.
	 */
	public function __construct( Notices_Capture $capture ) {
		$this->capture = $capture;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_node' ), 80 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_footer', array( $this, 'print_notices_payload' ), 5 );
	}

	/**
	 * Adds the Notifications node (content filled later).
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 * @return void
	 */
	public function add_admin_bar_node( $wp_admin_bar ) {
		if ( ! current_user_can( 'read' ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => self::NODE_ID,
				'title' => sprintf(
					'<span class="ab-icon dashicons dashicons-bell" aria-hidden="true"></span><span class="ab-label">%s</span><span class="tapio-clean-notice-badge" hidden></span>',
					esc_html__( 'Notifications', 'tapio-clean-backend' )
				),
				'href'  => false,
				'meta'  => array(
					'title' => __( 'Notifications', 'tapio-clean-backend' ),
					'class' => 'tapio-clean-notifications-root',
				),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => self::NODE_ID . '-panel',
				'parent' => self::NODE_ID,
				'title'  => '<div id="tapio-clean-notices-panel" class="tapio-clean-notices-panel"><p class="tapio-clean-notices-empty">' . esc_html__( 'No notifications', 'tapio-clean-backend' ) . '</p></div>',
				'href'   => false,
				'meta'   => array(
					'class' => 'tapio-clean-notifications-panel-wrap',
				),
			)
		);
	}

	/**
	 * Enqueues notice panel styles and script.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'tapio-clean-backend-notices',
			TAPIO_CLEAN_BACKEND_URL . 'assets/css/admin-notices.css',
			array( 'dashicons', 'admin-bar' ),
			TAPIO_CLEAN_BACKEND_VERSION
		);

		wp_enqueue_script(
			'tapio-clean-backend-notices',
			TAPIO_CLEAN_BACKEND_URL . 'assets/js/admin-notices.js',
			array(),
			TAPIO_CLEAN_BACKEND_VERSION,
			true
		);
	}

	/**
	 * Prints captured notices for the admin bar script to consume.
	 *
	 * @return void
	 */
	public function print_notices_payload() {
		if ( ! current_user_can( 'read' ) ) {
			return;
		}

		$this->capture->finalize_capture();

		$html  = $this->capture->get_html();
		$count = $this->capture->get_count();

		echo '<div id="tapio-clean-notices-source" class="tapio-clean-notices-source" hidden data-count="' . esc_attr( (string) $count ) . '" data-empty="' . esc_attr__( 'No notifications', 'tapio-clean-backend' ) . '">';
		if ( '' !== $html ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice HTML from plugins/core, previously printed on admin screens.
			echo $html;
		}
		echo '</div>';
	}
}
