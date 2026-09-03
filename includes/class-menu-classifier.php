<?php
/**
 * Classifies top-level admin menus into core vs More.
 *
 * @package Tapio\CleanBackend
 */

namespace Tapio\CleanBackend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Marks non-core top-level menus for relocation into the More accordion.
 */
class Menu_Classifier {

	/**
	 * CSS class applied to menus that should move into More.
	 *
	 * @var string
	 */
	const MORE_ITEM_CLASS = 'tapio-more-item';

	/**
	 * Slugs of menus relocated into More.
	 *
	 * @var string[]
	 */
	private $moved_slugs = array();

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		if ( is_network_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'classify_menus' ), 999999 );
	}

	/**
	 * Core top-level menu slugs that stay in the main sidebar.
	 *
	 * @return string[]
	 */
	public function get_core_slugs() {
		$slugs = array(
			'index.php',
			'edit.php',
			'upload.php',
			'link-manager.php',
			'edit-comments.php',
			'themes.php',
			'plugins.php',
			'users.php',
			'profile.php',
			'tools.php',
			'options-general.php',
			'edit.php?post_type=page',
		);

		/**
		 * Filters the allowlist of top-level menu slugs kept in the main sidebar.
		 *
		 * @param string[] $slugs Core menu slugs.
		 */
		return apply_filters( 'tapio_clean_backend_core_menu_slugs', $slugs );
	}

	/**
	 * Whether a menu slug should stay in the main sidebar.
	 *
	 * @param string $slug Menu file / slug from $menu[n][2].
	 * @return bool
	 */
	public function is_core_menu( $slug ) {
		$slug = (string) $slug;

		if ( false !== strpos( $slug, 'separator' ) ) {
			return true;
		}

		if ( in_array( $slug, $this->get_core_slugs(), true ) ) {
			return true;
		}

		// Builtin Posts menu is edit.php (already allowlisted); pages above.
		if ( 0 === strpos( $slug, 'edit.php?post_type=' ) ) {
			$query = array();
			parse_str( (string) wp_parse_url( $slug, PHP_URL_QUERY ), $query );
			$post_type = isset( $query['post_type'] ) ? $query['post_type'] : '';

			if ( in_array( $post_type, array( 'post', 'page' ), true ) ) {
				return true;
			}

			return false;
		}

		return false;
	}

	/**
	 * Tags non-core top-level menus and records moved slugs.
	 *
	 * @return void
	 */
	public function classify_menus() {
		global $menu;

		if ( ! is_array( $menu ) ) {
			return;
		}

		$this->moved_slugs = array();

		foreach ( $menu as $index => $item ) {
			if ( ! is_array( $item ) || ! isset( $item[2] ) ) {
				continue;
			}

			$slug = (string) $item[2];

			if ( $this->is_core_menu( $slug ) ) {
				continue;
			}

			$classes = isset( $item[4] ) ? (string) $item[4] : '';
			if ( false === strpos( $classes, self::MORE_ITEM_CLASS ) ) {
				$classes           = trim( $classes . ' ' . self::MORE_ITEM_CLASS );
				$menu[ $index ][4] = $classes;
			}

			$this->moved_slugs[] = $slug;
		}

		$this->moved_slugs = array_values( array_unique( $this->moved_slugs ) );
	}

	/**
	 * Returns slugs that were marked for More.
	 *
	 * @return string[]
	 */
	public function get_moved_slugs() {
		return $this->moved_slugs;
	}

	/**
	 * Whether a parent file belongs to a relocated menu.
	 *
	 * @param string $parent_file Current parent file.
	 * @return bool
	 */
	public function is_moved_parent( $parent_file ) {
		$parent_file = (string) $parent_file;

		if ( in_array( $parent_file, $this->moved_slugs, true ) ) {
			return true;
		}

		// Match query-string variants (amp encoding differences).
		$normalized = html_entity_decode( $parent_file, ENT_QUOTES );
		foreach ( $this->moved_slugs as $slug ) {
			if ( html_entity_decode( $slug, ENT_QUOTES ) === $normalized ) {
				return true;
			}
		}

		return false;
	}
}
