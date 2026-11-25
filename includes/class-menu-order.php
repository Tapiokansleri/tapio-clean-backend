<?php
/**
 * User-defined order for plugin and custom post type menus.
 *
 * @package Tapio\CleanBackend
 */

namespace Tapio\CleanBackend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reorders non-core top-level menus with the core menu_order filter.
 *
 * Core menus and separators keep their positions. Plugin and custom post type
 * menus trade places among the slots they already occupy, so the sidebar
 * keeps its familiar shape and only the order of those menus changes. The
 * More section follows the same order because it takes the menus in sidebar
 * order.
 */
class Menu_Order {

	/**
	 * Top-level menu order as WordPress built it, before this plugin reordered it.
	 *
	 * @var string[]|null
	 */
	private static $default_order = null;

	/**
	 * Registers hooks when the user has saved an order.
	 *
	 * @return void
	 */
	public function register() {
		if ( is_network_admin() || empty( Settings::get_menu_order() ) ) {
			return;
		}

		add_filter( 'custom_menu_order', '__return_true' );
		add_filter( 'menu_order', array( $this, 'apply_order' ), 999 );
	}

	/**
	 * Menu order before reordering, when this request reordered the menu.
	 *
	 * The settings page uses it to put the rows back in their default order.
	 *
	 * @return string[]|null
	 */
	public static function get_default_order() {
		return self::$default_order;
	}

	/**
	 * Puts the saved order on the non-core menus.
	 *
	 * Saved menus come first in the saved order. Menus the user has not
	 * sorted yet, such as a newly installed plugin, follow in their own order.
	 *
	 * @param mixed $order Top-level menu slugs in sidebar order.
	 * @return mixed
	 */
	public function apply_order( $order ) {
		if ( ! is_array( $order ) ) {
			return $order;
		}

		self::$default_order = array_values( $order );

		$saved      = Settings::get_menu_order();
		$classifier = new Menu_Classifier();
		$slots      = array();
		$extras     = array();

		foreach ( $order as $position => $slug ) {
			if ( $classifier->is_core_menu( (string) $slug ) ) {
				continue;
			}

			$slots[]  = $position;
			$extras[] = (string) $slug;
		}

		if ( count( $extras ) < 2 ) {
			return $order;
		}

		// Each sidebar slot is used once, by position. Slugs can repeat in $menu, so work with the positions in $extras,
		// never with the slugs alone: a list of unique slugs would be shorter than the slots and leave them empty.
		$used   = array();
		$sorted = array();
		foreach ( $saved as $saved_slug ) {
			foreach ( $extras as $i => $slug ) {
				if ( ! isset( $used[ $i ] ) && Menu_Classifier::slugs_match( $slug, $saved_slug ) ) {
					$used[ $i ] = true;
					$sorted[]   = $slug;
				}
			}
		}

		foreach ( $extras as $i => $slug ) {
			if ( ! isset( $used[ $i ] ) ) {
				$sorted[] = $slug;
			}
		}

		foreach ( $slots as $index => $position ) {
			$order[ $position ] = $sorted[ $index ];
		}

		return $order;
	}
}
