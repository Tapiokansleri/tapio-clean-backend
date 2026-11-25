<?php
/**
 * Plugin Name: Tapio Clean Backend
 * Plugin URI:  https://github.com/Tapiokansleri/tapio-clean-backend
 * Description: Arrange and rename the admin sidebar, keep less used menus under a More link, and read admin notices in an admin bar Notifications panel.
 * Version:     1.2.0
 * Author:      Tapio Kauranen
 * Author URI:  https://tapiokauranen.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tapio-clean-backend
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 *
 * @package Tapio\CleanBackend
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TAPIO_CLEAN_BACKEND_VERSION', '1.2.0' );
define( 'TAPIO_CLEAN_BACKEND_FILE', __FILE__ );
define( 'TAPIO_CLEAN_BACKEND_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAPIO_CLEAN_BACKEND_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoloads plugin classes from the includes directory.
 *
 * Maps Tapio\CleanBackend\Menu_Layout to includes/class-menu-layout.php.
 *
 * @param string $class_name Fully qualified class name.
 * @return void
 */
function tapio_clean_backend_autoload( $class_name ) {
	$prefix = 'Tapio\\CleanBackend\\';

	if ( 0 !== strpos( $class_name, $prefix ) ) {
		return;
	}

	$relative = substr( $class_name, strlen( $prefix ) );
	$slug     = strtolower( str_replace( '_', '-', $relative ) );
	$path     = TAPIO_CLEAN_BACKEND_DIR . 'includes/class-' . $slug . '.php';

	if ( is_readable( $path ) ) {
		require_once $path;
	}
}
spl_autoload_register( 'tapio_clean_backend_autoload' );

add_action(
	'plugins_loaded',
	static function () {
		if ( ! apply_filters( 'tapio_clean_backend_enabled', true ) ) {
			return;
		}

		Tapio\CleanBackend\Plugin::instance()->boot();
	}
);
