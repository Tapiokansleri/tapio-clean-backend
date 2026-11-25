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
	 * Whether a menu slug is a WordPress core menu that always stays in the main sidebar.
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
	 * Whether the user chose to keep a menu in the main sidebar.
	 *
	 * @param string   $slug Menu file / slug from $menu[n][2].
	 * @param string[] $kept Slugs ticked on the settings page.
	 * @return bool
	 */
	public function is_kept_menu( $slug, $kept ) {
		foreach ( $kept as $kept_slug ) {
			if ( self::slugs_match( $slug, $kept_slug ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether two menu slugs point to the same menu.
	 *
	 * Query-string slugs can arrive with &amp; or with a plain ampersand.
	 *
	 * @param string $a Menu slug.
	 * @param string $b Menu slug.
	 * @return bool
	 */
	public static function slugs_match( $a, $b ) {
		$a = (string) $a;
		$b = (string) $b;

		return $a === $b || html_entity_decode( $a, ENT_QUOTES ) === html_entity_decode( $b, ENT_QUOTES );
	}

	/**
	 * Top-level menus that can go under More, in sidebar order.
	 *
	 * Used by the settings page, so it lists every non-core menu whether or not
	 * the user has chosen to keep it in the main sidebar.
	 *
	 * @return array<string, array{title: string, icon: string}> Menu slug => title and icon.
	 */
	public function get_candidate_menus() {
		global $menu;

		$candidates = array();

		if ( ! is_array( $menu ) ) {
			return $candidates;
		}

		foreach ( $menu as $item ) {
			if ( ! is_array( $item ) || ! isset( $item[2] ) ) {
				continue;
			}

			$slug = (string) $item[2];

			if ( '' === $slug || $this->is_core_menu( $slug ) || isset( $candidates[ $slug ] ) ) {
				continue;
			}

			$title = $this->clean_title( isset( $item[0] ) ? $item[0] : '' );

			$candidates[ $slug ] = array(
				'title' => '' !== $title ? $title : $slug,
				'icon'  => isset( $item[6] ) ? (string) $item[6] : '',
			);
		}

		return $candidates;
	}

	/**
	 * Menu title without update and notification count bubbles.
	 *
	 * @param string $title Raw menu title from $menu[n][0].
	 * @return string
	 */
	private function clean_title( $title ) {
		$title = preg_replace(
			'#<span[^>]*class="[^"]*(count|update|awaiting|badge|pending|notification)[^"]*"[^>]*>.*?</span>#is',
			'',
			(string) $title
		);

		return trim( wp_strip_all_tags( (string) $title ) );
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
		$kept              = Settings::get_kept_slugs();

		foreach ( $menu as $index => $item ) {
			if ( ! is_array( $item ) || ! isset( $item[2] ) ) {
				continue;
			}

			$slug = (string) $item[2];

			if ( $this->is_core_menu( $slug ) || $this->is_kept_menu( $slug, $kept ) ) {
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
