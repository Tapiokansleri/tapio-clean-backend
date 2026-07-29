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

		add_action( 'shutdown', array( $this, 'end_buffer' ), 0 );

		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_action( 'admin_head', array( $this, 'print_hide_css' ), 1 );
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
	 * Prints early CSS so notices never flash in the content area.
	 *
	 * @return void
	 */
	public function print_hide_css() {
		echo '<style id="tapio-clean-hide-notices-css">'
			. 'body.wp-admin #wpbody-content > .notice,'
			. 'body.wp-admin #wpbody-content > .update-nag,'
			. 'body.wp-admin #wpbody-content > .updated,'
			. 'body.wp-admin #wpbody-content > .error,'
			. 'body.wp-admin #wpbody-content > .wrap > .notice,'
			. 'body.wp-admin #wpbody-content > .wrap > .update-nag,'
			. 'body.wp-admin #wpbody-content > .wrap > .updated,'
			. 'body.wp-admin #wpbody-content > .wrap > .error,'
			. 'body.wp-admin #wpbody-content > .e-notice,'
			. 'body.wp-admin #wpbody-content > .wrap > .e-notice'
			. '{display:none!important;}'
			. '</style>';
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
	 * @return void
	 */
	public function end_buffer() {
		if ( ! $this->buffering ) {
			return;
		}

		$html = '';

		/*
		 * Fold nested buffers opened by notice callbacks back into ours, then
		 * capture our level. If a callback already closed our buffer, fall back
		 * to collecting whatever nested content remains (CSS/JS hide leftovers).
		 */
		while ( ob_get_level() > $this->buffer_level ) {
			$nested = ob_get_clean();
			if ( ! is_string( $nested ) || '' === $nested ) {
				continue;
			}

			if ( ob_get_level() >= $this->buffer_level && $this->buffer_level > 0 ) {
				echo $nested; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Re-inject into our capture buffer only.
			} else {
				$html .= $nested;
			}
		}

		if ( $this->buffer_level > 0 && ob_get_level() >= $this->buffer_level ) {
			$captured = ob_get_clean();
			if ( is_string( $captured ) ) {
				$html .= $captured;
			}
		}

		$this->buffering    = false;
		$this->buffer_level = 0;
		$this->html         = trim( $html );
		$this->captured     = true;
	}

	/**
	 * Whether notices were captured for this request.
	 *
	 * @return bool
	 */
	public function was_captured() {
		return $this->captured;
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

	/**
	 * Counts top-level notice elements in the captured HTML.
	 *
	 * @return int
	 */
	public function get_count() {
		if ( '' === $this->html ) {
			return 0;
		}

		$count = preg_match_all(
			'/<div\b[^>]*\bclass=(["\'])[^"\']*\b(?:notice|update-nag|e-notice)\b[^"\']*\1[^>]*>/i',
			$this->html
		);

		return is_int( $count ) ? $count : 0;
	}
}
