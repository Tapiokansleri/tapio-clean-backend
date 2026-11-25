<?php
/**
 * Applies the menu layout to the WordPress admin sidebar.
 *
 * Everything the sidebar shows is decided before WordPress prints it: the order
 * with the menu_order filter, the names and the More markers with the
 * add_menu_classes filter, and the More link as a real menu entry. The browser
 * paints the final sidebar the first time, so nothing jumps while loading.
 *
 * @package Tapio\CleanBackend
 */

namespace Tapio\CleanBackend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sidebar order, names and the More section.
 */
class Admin_Menu {

	/**
	 * CSS class of the menus under More.
	 *
	 * @var string
	 */
	const MORE_ITEM_CLASS = 'tapio-more-item';

	/**
	 * Menu slug of the More link. The hash keeps the link on the same page.
	 *
	 * @var string
	 */
	const TOGGLE_SLUG = '#tapio-clean-backend-more';

	/**
	 * HTML id of the More list item.
	 *
	 * @var string
	 */
	const TOGGLE_ID = 'menu-tapio-clean-backend-more';

	/**
	 * Whether the More section is switched on.
	 *
	 * @var bool
	 */
	private $with_more;

	/**
	 * Where each menu goes, resolved for this request.
	 *
	 * @var array{main: string[], more: string[]}|null
	 */
	private $layout = null;

	/**
	 * Menus with their own names and icons, keyed by slug, for the settings page.
	 *
	 * @var array<string, array{title: string, icon: string, separator: bool}>
	 */
	private $originals = array();

	/**
	 * Constructor.
	 *
	 * @param bool $with_more Whether the More section is switched on.
	 */
	public function __construct( $with_more ) {
		$this->with_more = (bool) $with_more;
	}

	/**
	 * Registers hooks. Network and user admin screens are left as they are.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! is_admin() || is_network_admin() || is_user_admin() ) {
			return;
		}

		// Last, so the order and names apply to every menu other plugins add or move.
		add_filter( 'custom_menu_order', array( $this, 'enable_custom_order' ), PHP_INT_MAX );
		add_filter( 'menu_order', array( $this, 'order_menu' ), PHP_INT_MAX );
		add_filter( 'add_menu_classes', array( $this, 'decorate_menu' ), PHP_INT_MAX );

		if ( $this->with_more ) {
			add_action( 'admin_menu', array( $this, 'add_more_link' ), PHP_INT_MAX );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'adminmenu', array( $this, 'print_setup_script' ) );
		}
	}

	/**
	 * Whether the plugin has an order to apply.
	 *
	 * With More switched off and nothing saved, the sidebar is left alone.
	 *
	 * @return bool
	 */
	private function has_order() {
		$settings = Settings::get();

		return $this->with_more || Menu_Layout::is_saved( $settings['menu'] ) || ! empty( $settings['menu_order'] );
	}

	/**
	 * Turns on WordPress custom menu ordering when there is an order to apply.
	 *
	 * @param bool $enabled Whether another plugin already turned it on.
	 * @return bool
	 */
	public function enable_custom_order( $enabled ) {
		return $this->has_order() ? true : $enabled;
	}

	/**
	 * Adds the More link as a menu entry, so it is printed with the sidebar.
	 *
	 * @return void
	 */
	public function add_more_link() {
		global $menu;

		if ( ! is_array( $menu ) ) {
			return;
		}

		$settings = Settings::get();
		$label    = '' !== $settings['menu']['more_label'] ? $settings['menu']['more_label'] : __( 'More', 'tapio-clean-backend' );

		$menu['999999.tapio'] = array(
			esc_html( $label ),
			'read',
			self::TOGGLE_SLUG,
			'',
			'menu-top tapio-more-toggle-item',
			self::TOGGLE_ID,
			'dashicons-plus-alt2',
		);
	}

	/**
	 * Sorts the sidebar: main menus, the More link, then the menus under More.
	 *
	 * @param mixed $order Menu slugs in sidebar order.
	 * @return mixed
	 */
	public function order_menu( $order ) {
		global $menu;

		if ( ! is_array( $order ) || ! is_array( $menu ) || ! $this->has_order() ) {
			return $order;
		}

		$separators = array();
		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) && self::is_separator_item( $item ) ) {
				$separators[ (string) $item[2] ] = true;
			}
		}

		$items = array();
		foreach ( $order as $slug ) {
			if ( self::TOGGLE_SLUG !== $slug ) {
				$items[] = array(
					'slug'      => (string) $slug,
					'separator' => isset( $separators[ (string) $slug ] ),
				);
			}
		}

		$this->layout = $this->resolve( $items );

		if ( ! $this->with_more ) {
			$settings = Settings::get();

			// Without More and without a saved layout, no menu moves to the end.
			return Menu_Layout::is_saved( $settings['menu'] )
				? array_merge( $this->layout['main'], $this->layout['more'] )
				: Menu_Layout::wordpress_order( $items, $settings['menu_order'] );
		}

		return array_merge( $this->layout['main'], array( self::TOGGLE_SLUG ), $this->layout['more'] );
	}

	/**
	 * Marks the menus under More and applies custom names.
	 *
	 * Runs when WordPress has sorted and filtered the menu, just before it is printed.
	 *
	 * @param mixed $menu Top-level menu.
	 * @return mixed
	 */
	public function decorate_menu( $menu ) {
		if ( ! is_array( $menu ) ) {
			return $menu;
		}

		$settings = Settings::get();

		if ( null === $this->layout ) {
			$this->layout = $this->resolve( self::items_from( $menu ) );
		}

		$in_more = array();
		foreach ( $this->layout['more'] as $slug ) {
			$in_more[ Menu_Layout::normalize_slug( $slug ) ] = true;
		}

		$this->originals = array();
		$toggle_key      = null;
		$has_more_items  = false;

		foreach ( $menu as $key => $item ) {
			if ( ! is_array( $item ) || ! isset( $item[2] ) ) {
				continue;
			}

			$slug = (string) $item[2];

			if ( self::TOGGLE_SLUG === $slug ) {
				$toggle_key = $key;
				continue;
			}

			$separator          = self::is_separator_item( $item );
			list( $text, $bubble ) = Menu_Layout::split_title( isset( $item[0] ) ? $item[0] : '' );

			if ( ! isset( $this->originals[ $slug ] ) ) {
				$this->originals[ $slug ] = array(
					'title'     => '' !== $text ? $text : $slug,
					'icon'      => isset( $item[6] ) ? (string) $item[6] : '',
					'separator' => $separator,
				);
			}

			if ( $separator ) {
				continue;
			}

			$name = Menu_Layout::name_for( $slug, $settings['menu']['names'] );
			if ( '' !== $name ) {
				$menu[ $key ][0] = esc_html( $name ) . ( '' !== $bubble ? ' ' . $bubble : '' );
			}

			if ( $this->with_more && isset( $in_more[ Menu_Layout::normalize_slug( $slug ) ] ) ) {
				$menu[ $key ][4] = trim( ( isset( $item[4] ) ? $item[4] : '' ) . ' ' . self::MORE_ITEM_CLASS );
				$has_more_items  = true;
			}
		}

		if ( null !== $toggle_key ) {
			$menu = $this->tidy_around_toggle( $menu, $toggle_key, $has_more_items );
		}

		return $menu;
	}

	/**
	 * Drops the More link when nothing is under it, and a separator right above it.
	 *
	 * @param array      $menu           Top-level menu.
	 * @param int|string $toggle_key     Array key of the More link.
	 * @param bool       $has_more_items Whether any menu is under More.
	 * @return array
	 */
	private function tidy_around_toggle( array $menu, $toggle_key, $has_more_items ) {
		$keys = array_keys( $menu );
		$at   = array_search( $toggle_key, $keys, true );

		while ( $at > 0 && self::is_separator_item( $menu[ $keys[ $at - 1 ] ] ) ) {
			unset( $menu[ $keys[ $at - 1 ] ] );
			array_splice( $keys, $at - 1, 1 );
			--$at;
		}

		if ( ! $has_more_items ) {
			unset( $menu[ $toggle_key ] );
		}

		return $menu;
	}

	/**
	 * Resolves the layout with the saved settings.
	 *
	 * @param array<int, array{slug: string, separator: bool}> $items Menus in order.
	 * @return array{main: string[], more: string[]}
	 */
	private function resolve( array $items ) {
		$settings = Settings::get();

		return Menu_Layout::resolve(
			$items,
			$settings['menu'],
			array(
				'keep_visible' => $settings['keep_visible'],
				'menu_order'   => $settings['menu_order'],
			)
		);
	}

	/**
	 * Slugs and separator flags of a menu array.
	 *
	 * @param array $menu Top-level menu.
	 * @return array<int, array{slug: string, separator: bool}>
	 */
	private static function items_from( array $menu ) {
		$items = array();
		foreach ( $menu as $item ) {
			if ( is_array( $item ) && isset( $item[2] ) && self::TOGGLE_SLUG !== $item[2] ) {
				$items[] = array(
					'slug'      => (string) $item[2],
					'separator' => self::is_separator_item( $item ),
				);
			}
		}

		return $items;
	}

	/**
	 * Whether a menu entry is a separator line.
	 *
	 * @param mixed $item Menu entry.
	 * @return bool
	 */
	private static function is_separator_item( $item ) {
		return is_array( $item ) && isset( $item[4] ) && false !== strpos( (string) $item[4], 'wp-menu-separator' );
	}

	/**
	 * The menus of the current user for the editor on the settings page.
	 *
	 * @return array{main: array<int, array>, more: array<int, array>}
	 */
	public function get_editor_items() {
		$sections = array(
			'main' => array(),
			'more' => array(),
		);

		if ( null === $this->layout ) {
			return $sections;
		}

		$settings = Settings::get();
		$listed   = array();

		foreach ( $sections as $section => $unused ) {
			foreach ( $this->layout[ $section ] as $slug ) {
				// Menus removed after the layout was resolved, such as doubled separators, are not listed.
				if ( ! isset( $this->originals[ $slug ] ) || isset( $listed[ $slug ] ) ) {
					continue;
				}

				$listed[ $slug ]        = true;
				$sections[ $section ][] = array_merge(
					$this->originals[ $slug ],
					array(
						'slug' => $slug,
						'name' => Menu_Layout::name_for( $slug, $settings['menu']['names'] ),
					)
				);
			}
		}

		// Separators at either end of a list are not drawn in the sidebar, so they are not offered.
		// Saving keeps them in the layout (see Menu_Layout::merge_unlisted()).
		while ( $sections['main'] && ! empty( $sections['main'][0]['separator'] ) ) {
			array_shift( $sections['main'] );
		}
		while ( $sections['main'] && ! empty( $sections['main'][ count( $sections['main'] ) - 1 ]['separator'] ) ) {
			array_pop( $sections['main'] );
		}

		return $sections;
	}

	/**
	 * Loads the More styles and script in the page head, before the sidebar.
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
			false
		);

		wp_localize_script(
			'tapio-clean-backend-more',
			'tapioCleanBackendMoreConfig',
			array(
				'itemClass'  => self::MORE_ITEM_CLASS,
				'toggleId'   => self::TOGGLE_ID,
				'storageKey' => 'tapioCleanBackendMoreOpen',
			)
		);
	}

	/**
	 * Sets the More state while the sidebar is being parsed, before it is painted.
	 *
	 * If the script file did not load, More is opened so every menu stays reachable.
	 *
	 * @return void
	 */
	public function print_setup_script() {
		wp_print_inline_script_tag(
			'if(window.tapioCleanBackendMore){window.tapioCleanBackendMore.setup();}else{var tapioMenu=document.getElementById("adminmenu");if(tapioMenu){tapioMenu.classList.add("tapio-more-open");}}'
		);
	}
}
