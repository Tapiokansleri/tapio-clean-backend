<?php
/**
 * Removes the single settings option on uninstall.
 *
 * @package Tapio\CleanBackend
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'tapio_clean_backend_settings' );
