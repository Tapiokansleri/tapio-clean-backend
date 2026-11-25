<?php
/**
 * Removes the single settings option on uninstall, on every site of a network.
 *
 * @package Tapio\CleanBackend
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! is_multisite() ) {
	delete_option( 'tapio_clean_backend_settings' );
	return;
}

$tapio_clean_backend_sites = get_sites(
	array(
		'fields' => 'ids',
		'number' => 0,
	)
);

foreach ( $tapio_clean_backend_sites as $tapio_clean_backend_site ) {
	delete_blog_option( $tapio_clean_backend_site, 'tapio_clean_backend_settings' );
}
