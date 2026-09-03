<?php
/**
 * Captures admin notices via output buffering.
 *
 * @package Tapio\CleanBackend
 */

namespace Tapio\CleanBackend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Buffers admin_notices through all_admin_notices and stores the HTML.
 */
class Notices_Capture {

	/**
	 * Captured notice HTML for the current request.
	 *
	 * @var string
	 */
	private $html = '';

	/**
	 * Whether buffering is active.
	 *
	 * @var bool
	 */
	private $buffering = false;

	/**
	 * Output buffer nesting level opened by this class.
	 *
	 * @var int
	 */
	private $buffer_level = 0;

	/**
	 * Whether capture completed successfully.
	 *
	 * @var bool
	 */
	private $captured = false;

	/**
	 * Whether captured HTML was delivered to the admin footer payload.
	 *
	 * @var bool
	 */
	private $delivered = false;

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! $this->should_capture() ) {
			return;
		}

		// Buffer both notice phases so echoed markup never reaches #wpbody-content.
		add_action( 'admin_notices', array( $this, 'start_buffer' ), -999999 );
		add_action( 'user_admin_notices', array( $this, 'start_buffer' ), -999999 );
		add_action( 'network_admin_notices', array( $this, 'start_buffer' ), -999999 );
		add_action( 'all_admin_notices', array( $this, 'end_buffer' ), PHP_INT_MAX );

		add_action( 'shutdown', array( $this, 'flush_undelivered' ), 0 );

		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Adds a body class used to hide leftover notices until JS relocates them.
	 *
	 * @param string $classes Admin body classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		$classes .= ' tapio-clean-hide-notices';
		return $classes;
	}

	/**
	 * Whether notice capture should run on this request.
	 *
	 * @return bool
	 */
	private function should_capture() {
		if ( ! is_admin() ) {
			return false;
		}

		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		return true;
	}

	/**
	 * Starts output buffering before admin notices print.
	 *
	 * @return void
	 */
	public function start_buffer() {
		if ( $this->buffering ) {
			return;
		}

		ob_start();
		$this->buffer_level = ob_get_level();
		$this->buffering    = true;
	}

	/**
	 * Ends buffering after all_admin_notices and stores the HTML.
	 *
	 * Closes only our buffer level (and nested buffers opened inside it).
	 * Never closes buffers that existed before we started.
	 *
	 * @return void
	 */
	public function end_buffer() {
		if ( ! $this->buffering ) {
			return;
		}

		$html = '';

		while ( ob_get_level() > $this->buffer_level ) {
			$nested = ob_get_clean();
			if ( is_string( $nested ) && '' !== $nested ) {
				$html = $nested . $html;
			}
		}

		if ( $this->buffer_level > 0 && ob_get_level() === $this->buffer_level ) {
			$captured = ob_get_clean();
			if ( is_string( $captured ) ) {
				$html = $captured . $html;
			}
		}

		$this->buffering    = false;
		$this->buffer_level = 0;
		$this->html         = trim( $html );
		$this->captured     = true;
	}

	/**
	 * Ensures buffering is closed (e.g. before footer output).
	 *
	 * @return void
	 */
	public function finalize_capture() {
		$this->end_buffer();
	}

	/**
	 * Marks captured HTML as delivered to the admin bar payload.
	 *
	 * @return void
	 */
	public function mark_delivered() {
		$this->delivered = true;
	}

	/**
	 * On shutdown, flush captured notices that never reached admin_footer
	 * (redirects, wp_die) so they are not lost.
	 *
	 * @return void
	 */
	public function flush_undelivered() {
		$this->end_buffer();

		if ( $this->delivered || '' === $this->html ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin notice HTML from plugins/core.
		echo $this->html;
		$this->delivered = true;
	}

	/**
	 * Returns captured notice HTML.
	 *
	 * @return string
	 */
	public function get_html() {
		return $this->html;
	}

	/**
	 * Whether there is any notice content.
	 *
	 * @return bool
	 */
	public function has_notices() {
		return '' !== $this->html;
	}
}
