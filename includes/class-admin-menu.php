<?php
/**
 * Applies the menu layout to the WordPress admin sidebar.
 *
 * Everything the sidebar shows is decided before WordPress prints it: the order
 * with the menu_order filter, the names, hidden menus and More markers with the
 * add_menu_classes filter, and the More link and added separators as real menu
 * entries. The browser paints the final sidebar the first time, so nothing jumps
 * while loading.
 *
 * Hidden menus are only hidden with CSS. They stay in the menu, so their pages
 * keep working and no one's access changes.
 *
 * @package Tapio\CleanBackend
 */

namespace Tapio\CleanBackend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sidebar order, names, visibility and the More section.
 */
class Admin_Menu {

	/**
	 * CSS class of the entries under More.
	 *
	 * @var string
	 */
	const MORE_ITEM_CLASS = 'tapio-more-item';

	/**
	 * CSS class of hidden entries.
	 *
	 * @var string
	 */
	const HIDDEN_CLASS = 'tapio-hidden-item';

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
	 * @var array<string, array{title: string, bubble: string, icon: string, separator: bool}>
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
		add_action( 'admin_menu', array( $this, 'add_custom_separators' ), PHP_INT_MAX );
		add_filter( 'custom_menu_order', array( $this, 'enable_custom_order' ), PHP_INT_MAX );
		add_filter( 'menu_order', array( $this, 'order_menu' ), PHP_INT_MAX );
		add_filter( 'add_menu_classes', array( $this, 'decorate_menu' ), PHP_INT_MAX );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_visibility_style' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_editor_link' ), 90 );

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
	 * Adds the user's own separator lines as menu entries.
	 *
	 * @return void
	 */
	public function add_custom_separators() {
		global $menu;

		if ( ! is_array( $menu ) ) {
			return;
		}

		$settings = Settings::get();

		foreach ( Menu_Layout::custom_separators( $settings['menu'] ) as $index => $slug ) {
			$menu[ '999998.tapio' . $index ] = array( '', 'read', $slug, '', 'wp-menu-separator tapio-custom-separator' );
		}
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
	 * Applies custom names and marks the entries that are hidden or under More.
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

		$on_editor        = $this->is_editor_screen();
		$this->originals  = array();
		$toggle_key       = null;
		$has_visible_more = false;

		foreach ( $menu as $key => $item ) {
			if ( ! is_array( $item ) || ! isset( $item[2] ) ) {
				continue;
			}

			$slug = (string) $item[2];

			if ( self::TOGGLE_SLUG === $slug ) {
				$toggle_key = $key;
				continue;
			}

			$separator             = self::is_separator_item( $item );
			list( $text, $bubble ) = Menu_Layout::split_title( isset( $item[0] ) ? $item[0] : '' );

			if ( ! isset( $this->originals[ $slug ] ) ) {
				$this->originals[ $slug ] = array(
					'title'     => '' !== $text ? $text : $slug,
					'bubble'    => $bubble,
					'icon'      => isset( $item[6] ) ? (string) $item[6] : '',
					'separator' => $separator,
				);
			}

			if ( ! $separator ) {
				$name = Menu_Layout::name_for( $slug, $settings['menu']['names'] );
				if ( '' !== $name ) {
					$menu[ $key ][0] = esc_html( $name ) . ( '' !== $bubble ? ' ' . $bubble : '' );
				}
			}

			$hidden  = Menu_Layout::contains( $slug, $settings['menu']['hidden'] );
			$classes = array();

			if ( $this->with_more && isset( $in_more[ Menu_Layout::normalize_slug( $slug ) ] ) ) {
				$classes[]        = self::MORE_ITEM_CLASS;
				$has_visible_more = $has_visible_more || ( ! $separator && ! $hidden );
			}
			if ( $hidden ) {
				$classes[] = self::HIDDEN_CLASS;
			}
			if ( $on_editor ) {
				// Lets the editor update the sidebar next to it as changes are saved.
				$classes[] = 'tcb-key-' . Menu_Layout::key_for( $slug );
			}

			if ( $classes ) {
				$menu[ $key ][4] = trim( ( isset( $item[4] ) ? $item[4] : '' ) . ' ' . implode( ' ', $classes ) );
			}
		}

		if ( null !== $toggle_key ) {
			$menu = $this->tidy_around_toggle( $menu, $toggle_key, $has_visible_more );
		}

		return $menu;
	}

	/**
	 * Hides the More link when nothing visible is under it, and drops a separator right above it.
	 *
	 * @param array      $menu             Top-level menu.
	 * @param int|string $toggle_key       Array key of the More link.
	 * @param bool       $has_visible_more Whether any visible menu is under More.
	 * @return array
	 */
	private function tidy_around_toggle( array $menu, $toggle_key, $has_visible_more ) {
		$keys = array_keys( $menu );
		$at   = array_search( $toggle_key, $keys, true );

		while ( $at > 0 && self::is_separator_item( $menu[ $keys[ $at - 1 ] ] ) ) {
			unset( $menu[ $keys[ $at - 1 ] ] );
			array_splice( $keys, $at - 1, 1 );
			--$at;
		}

		if ( ! $has_visible_more ) {
			$menu[ $toggle_key ][4] .= ' tapio-more-empty';
		}

		return $menu;
	}

	/**
	 * Hides hidden entries and an empty More link. Applies without JavaScript too.
	 *
	 * @return void
	 */
	public function add_visibility_style() {
		wp_add_inline_style( 'admin-menu', '#adminmenu > li.tapio-hidden-item, #adminmenu > li.tapio-more-empty { display: none !important; }' );
	}

	/**
	 * Keeps the editor reachable from the admin bar when the Tools menu is hidden.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar.
	 * @return void
	 */
	public function add_editor_link( $wp_admin_bar ) {
		$settings = Settings::get();

		if ( ! current_user_can( 'manage_options' ) || ! Menu_Layout::contains( 'tools.php', $settings['menu']['hidden'] ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'     => 'tapio-clean-backend-editor-link',
				'parent' => 'site-name',
				'title'  => esc_html__( 'Edit sidebar menu', 'tapio-clean-backend' ),
				'href'   => admin_url( 'tools.php?page=' . Settings::PAGE ),
			)
		);
	}

	/**
	 * Whether the current screen is the sidebar editor.
	 *
	 * @return bool
	 */
	private function is_editor_screen() {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reads which screen is shown.
		return 'tools.php' === $pagenow && isset( $_GET['page'] ) && Settings::PAGE === sanitize_key( wp_unslash( $_GET['page'] ) );
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
				$custom = Menu_Layout::is_custom_separator( $slug );

				// The user's separators are always listed, also when WordPress dropped a doubled one.
				// Other menus removed after the layout was resolved are not.
				if ( ( ! $custom && ! isset( $this->originals[ $slug ] ) ) || isset( $listed[ $slug ] ) ) {
					continue;
				}

				$original = $custom
					? array(
						'title'     => '',
						'bubble'    => '',
						'icon'      => '',
						'separator' => true,
					)
					: $this->originals[ $slug ];

				$listed[ $slug ]        = true;
				$sections[ $section ][] = array_merge(
					$original,
					array(
						'slug'   => $slug,
						'key'    => Menu_Layout::key_for( $slug ),
						'custom' => $custom,
						'name'   => Menu_Layout::name_for( $slug, $settings['menu']['names'] ),
						'hidden' => Menu_Layout::contains( $slug, $settings['menu']['hidden'] ),
					)
				);
			}
		}

		// WordPress's own separators at either end are not drawn, so they are not offered.
		// Saving keeps them in the layout (see Menu_Layout::merge_unlisted()).
		$is_end_separator = static function ( $item ) {
			return $item['separator'] && ! $item['custom'];
		};
		while ( $sections['main'] && $is_end_separator( $sections['main'][0] ) ) {
			array_shift( $sections['main'] );
		}
		while ( $sections['main'] && $is_end_separator( $sections['main'][ count( $sections['main'] ) - 1 ] ) ) {
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
