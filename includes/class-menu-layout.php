<?php
/**
 * Decides where every top-level admin menu goes and what it is called.
 *
 * The class only works with the values it is given, so the rules can be tested
 * without an admin screen. Admin_Menu applies the result to WordPress.
 *
 * @package Tapio\CleanBackend
 */

namespace Tapio\CleanBackend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu layout rules.
 */
class Menu_Layout {

	/**
	 * Longest custom menu name, in characters.
	 *
	 * @var int
	 */
	const MAX_NAME_LENGTH = 60;

	/**
	 * Slugs of the separator lines the user adds. The "separator" prefix makes
	 * WordPress treat them like its own separators.
	 *
	 * @var string
	 */
	const CUSTOM_SEPARATOR_PATTERN = '/^separator-tapio-[a-z0-9]{4,16}$/';

	/**
	 * An empty saved layout: nothing chosen yet.
	 *
	 * @return array{main: string[], more: string[], hidden: string[], names: array<string, string>, more_label: string}
	 */
	public static function empty_layout() {
		return array(
			'main'       => array(),
			'more'       => array(),
			'hidden'     => array(),
			'names'      => array(),
			'more_label' => '',
		);
	}

	/**
	 * Whether a slug is a separator the user added.
	 *
	 * @param string $slug Menu slug.
	 * @return bool
	 */
	public static function is_custom_separator( $slug ) {
		return (bool) preg_match( self::CUSTOM_SEPARATOR_PATTERN, (string) $slug );
	}

	/**
	 * The separators the user added, wherever they are in a saved layout.
	 *
	 * @param array $saved Saved layout.
	 * @return string[]
	 */
	public static function custom_separators( array $saved ) {
		$slugs = array_merge(
			isset( $saved['main'] ) ? (array) $saved['main'] : array(),
			isset( $saved['more'] ) ? (array) $saved['more'] : array()
		);

		return array_values( array_filter( $slugs, array( __CLASS__, 'is_custom_separator' ) ) );
	}

	/**
	 * A key for a menu that is safe in a CSS class name.
	 *
	 * @param string $slug Menu slug.
	 * @return string
	 */
	public static function key_for( $slug ) {
		return self::is_custom_separator( $slug ) ? (string) $slug : 'm' . substr( md5( self::normalize_slug( $slug ) ), 0, 12 );
	}

	/**
	 * Whether a menu is in a list of slugs.
	 *
	 * @param string   $slug  Menu slug.
	 * @param string[] $slugs List.
	 * @return bool
	 */
	public static function contains( $slug, array $slugs ) {
		return in_array( self::normalize_slug( $slug ), array_map( array( __CLASS__, 'normalize_slug' ), $slugs ), true );
	}

	/**
	 * Whether the user has saved their own order or placement.
	 *
	 * @param array $saved Saved layout.
	 * @return bool
	 */
	public static function is_saved( array $saved ) {
		return ! empty( $saved['main'] ) || ! empty( $saved['more'] );
	}

	/**
	 * Top-level menus that start in the main sidebar before the user chooses otherwise.
	 *
	 * @return string[]
	 */
	public static function core_slugs() {
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
		 * Filters the top-level menus that start in the main sidebar.
		 *
		 * A layout saved on the settings page takes precedence over this list.
		 *
		 * @param string[] $slugs Core menu slugs.
		 */
		return (array) apply_filters( 'tapio_clean_backend_core_menu_slugs', $slugs );
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
		return self::normalize_slug( $a ) === self::normalize_slug( $b );
	}

	/**
	 * Slug in the form used for comparisons and array keys.
	 *
	 * @param string $slug Menu slug.
	 * @return string
	 */
	public static function normalize_slug( $slug ) {
		return html_entity_decode( (string) $slug, ENT_QUOTES );
	}

	/**
	 * Splits the menus into the main sidebar and More, each in display order.
	 *
	 * With a saved layout, saved menus and separators keep their saved section
	 * and order. A menu the layout does not know yet (a newly activated plugin,
	 * for example) goes to More, or, if it is a WordPress core menu, into the
	 * main sidebar after the menu it normally follows. So does a new separator.
	 *
	 * Without a saved layout, core menus stay in the main sidebar and the rest go
	 * to More, taking into account the choices stored by versions 1.1.x.
	 *
	 * @param array<int, array{slug: string, separator: bool}> $items  Menus in the order WordPress built them.
	 * @param array                                            $saved  Saved layout (see empty_layout()).
	 * @param array{keep_visible?: string[], menu_order?: string[]} $legacy Settings saved by version 1.1.x.
	 * @return array{main: string[], more: string[]}
	 */
	public static function resolve( array $items, array $saved, array $legacy = array() ) {
		$present   = array(); // Normalized slug => slug as WordPress has it.
		$separator = array(); // Normalized slug => true.

		foreach ( $items as $item ) {
			$key = self::normalize_slug( $item['slug'] );
			if ( '' === $key || isset( $present[ $key ] ) ) {
				continue;
			}

			$present[ $key ] = (string) $item['slug'];
			if ( ! empty( $item['separator'] ) ) {
				$separator[ $key ] = true;
			}
		}

		if ( ! self::is_saved( $saved ) ) {
			return self::default_layout( $present, $separator, $legacy );
		}

		$wanted = array(
			'main' => array_map( array( __CLASS__, 'normalize_slug' ), isset( $saved['main'] ) ? (array) $saved['main'] : array() ),
			'more' => array_map( array( __CLASS__, 'normalize_slug' ), isset( $saved['more'] ) ? (array) $saved['more'] : array() ),
		);

		// People who cannot list users get Profile where others have Users.
		if ( isset( $present['profile.php'] ) && ! isset( $present['users.php'] )
			&& ! in_array( 'profile.php', array_merge( $wanted['main'], $wanted['more'] ), true )
		) {
			foreach ( $wanted as $section => $keys ) {
				$at = array_search( 'users.php', $keys, true );
				if ( false !== $at ) {
					$wanted[ $section ][ $at ] = 'profile.php';
				}
			}
		}

		$layout = array(
			'main' => array(),
			'more' => array(),
		);
		$placed = array();

		foreach ( $wanted as $section => $keys ) {
			foreach ( $keys as $key ) {
				if ( ! isset( $present[ $key ] ) || isset( $placed[ $key ] ) ) {
					continue;
				}

				$layout[ $section ][] = $key;
				$placed[ $key ]       = true;
			}
		}

		$core     = self::core_keys();
		$previous = null; // The nearest earlier menu, in WordPress order, that is in the main sidebar.

		foreach ( array_keys( $present ) as $key ) {
			if ( ! isset( $placed[ $key ] ) ) {
				if ( isset( $separator[ $key ] ) || isset( $core[ $key ] ) ) {
					$at = null === $previous ? 0 : array_search( $previous, $layout['main'], true ) + 1;
					array_splice( $layout['main'], $at, 0, array( $key ) );
				} else {
					$layout['more'][] = $key;
				}
				$placed[ $key ] = true;
			}

			if ( in_array( $key, $layout['main'], true ) ) {
				$previous = $key;
			}
		}

		return self::to_original_slugs( $layout, $present );
	}

	/**
	 * Layout before the user has saved one.
	 *
	 * @param array<string, string> $present   Normalized slug => slug.
	 * @param array<string, bool>   $separator Separator keys.
	 * @param array                 $legacy    Settings saved by version 1.1.x.
	 * @return array{main: string[], more: string[]}
	 */
	private static function default_layout( array $present, array $separator, array $legacy ) {
		$core = self::core_keys();
		$kept = array();
		foreach ( isset( $legacy['keep_visible'] ) ? (array) $legacy['keep_visible'] : array() as $slug ) {
			$kept[ self::normalize_slug( $slug ) ] = true;
		}

		$keys = self::apply_legacy_order(
			array_keys( $present ),
			$separator,
			isset( $legacy['menu_order'] ) ? (array) $legacy['menu_order'] : array()
		);

		$layout = array(
			'main' => array(),
			'more' => array(),
		);
		foreach ( $keys as $key ) {
			$section              = ( isset( $separator[ $key ] ) || isset( $core[ $key ] ) || isset( $kept[ $key ] ) ) ? 'main' : 'more';
			$layout[ $section ][] = $key;
		}

		return self::to_original_slugs( $layout, $present );
	}

	/**
	 * One list, for a sidebar without the More link and no saved layout.
	 *
	 * WordPress's order is kept; only the order saved by version 1.1.x applies.
	 *
	 * @param array<int, array{slug: string, separator: bool}> $items        Menus in WordPress order.
	 * @param string[]                                         $legacy_order Order saved by version 1.1.x.
	 * @return string[]
	 */
	public static function wordpress_order( array $items, array $legacy_order ) {
		$present   = array();
		$separator = array();
		foreach ( $items as $item ) {
			$key = self::normalize_slug( $item['slug'] );
			if ( '' !== $key && ! isset( $present[ $key ] ) ) {
				$present[ $key ] = (string) $item['slug'];
				if ( ! empty( $item['separator'] ) ) {
					$separator[ $key ] = true;
				}
			}
		}

		$order = array();
		foreach ( self::apply_legacy_order( array_keys( $present ), $separator, $legacy_order ) as $key ) {
			$order[] = $present[ $key ];
		}

		return $order;
	}

	/**
	 * Version 1.1.x ordered plugin menus among the places they already had.
	 *
	 * @param string[]            $keys         Normalized slugs in WordPress order.
	 * @param array<string, bool> $separator    Separator keys.
	 * @param string[]            $legacy_order Saved order.
	 * @return string[]
	 */
	private static function apply_legacy_order( array $keys, array $separator, array $legacy_order ) {
		if ( ! $legacy_order ) {
			return $keys;
		}

		$core   = self::core_keys();
		$slots  = array();
		$extras = array();
		foreach ( $keys as $position => $key ) {
			if ( ! isset( $core[ $key ] ) && ! isset( $separator[ $key ] ) ) {
				$slots[]  = $position;
				$extras[] = $key;
			}
		}

		$sorted = array_unique( array_intersect( array_map( array( __CLASS__, 'normalize_slug' ), $legacy_order ), $extras ) );
		$sorted = array_values( array_merge( $sorted, array_diff( $extras, $sorted ) ) );

		foreach ( $slots as $index => $position ) {
			$keys[ $position ] = $sorted[ $index ];
		}

		return $keys;
	}

	/**
	 * Core menu slugs as lookup keys.
	 *
	 * @return array<string, int>
	 */
	private static function core_keys() {
		return array_flip( array_map( array( __CLASS__, 'normalize_slug' ), self::core_slugs() ) );
	}

	/**
	 * Turns normalized keys back into the slugs WordPress uses.
	 *
	 * @param array{main: string[], more: string[]} $layout  Layout of normalized keys.
	 * @param array<string, string>                 $present Normalized slug => slug.
	 * @return array{main: string[], more: string[]}
	 */
	private static function to_original_slugs( array $layout, array $present ) {
		foreach ( $layout as $section => $keys ) {
			foreach ( $keys as $i => $key ) {
				$layout[ $section ][ $i ] = $present[ $key ];
			}
		}

		return $layout;
	}

	/**
	 * Splits a menu title into its text and a trailing count bubble.
	 *
	 * WordPress and plugins put counts such as pending comments in a span after
	 * the name. Renaming a menu must keep that span. A span is a bubble when its
	 * class says so, or when everything from it onwards is just a number.
	 *
	 * @param string $title Menu title from $menu[n][0].
	 * @return array{0: string, 1: string} Plain text name and the bubble markup ('' if none).
	 */
	public static function split_title( $title ) {
		$title = (string) $title;

		if ( preg_match_all( '/<span\b[^>]*>/i', $title, $spans, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $spans[0] as $span ) {
				list( $tag, $offset ) = $span;

				$rest     = substr( $title, $offset );
				$is_count = (bool) preg_match( '/^\d+$/', self::plain_text( $rest ) );
				$is_class = (bool) preg_match( '/\bclass\s*=\s*(["\'])(?:(?!\1).)*?(?:count|update|awaiting|badge|pending|notification|bubble)/i', $tag );

				if ( $is_count || $is_class ) {
					$text = self::plain_text( substr( $title, 0, $offset ) );
					if ( '' !== $text ) {
						return array( $text, $rest );
					}
				}
			}
		}

		return array( self::plain_text( $title ), '' );
	}

	/**
	 * Text of a title without markup or HTML entities.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	private static function plain_text( $html ) {
		return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Cleans a layout that came from the settings form or the database.
	 *
	 * @param mixed $layout Raw layout.
	 * @return array{main: string[], more: string[], names: array<string, string>, more_label: string}
	 */
	public static function clean( $layout ) {
		$clean = self::empty_layout();
		if ( ! is_array( $layout ) ) {
			return $clean;
		}

		$clean['main'] = self::clean_slugs( isset( $layout['main'] ) ? $layout['main'] : array() );
		$clean['more'] = array_values(
			array_diff( self::clean_slugs( isset( $layout['more'] ) ? $layout['more'] : array() ), $clean['main'] )
		);

		$clean['hidden'] = self::clean_slugs( isset( $layout['hidden'] ) ? $layout['hidden'] : array() );

		if ( isset( $layout['names'] ) && is_array( $layout['names'] ) ) {
			foreach ( $layout['names'] as $slug => $name ) {
				$slug = self::clean_slugs( array( (string) $slug ) );
				$name = self::clean_name( $name );
				if ( $slug && '' !== $name ) {
					$clean['names'][ $slug[0] ] = $name;
				}
			}
		}

		$clean['more_label'] = self::clean_name( isset( $layout['more_label'] ) ? $layout['more_label'] : '' );

		return $clean;
	}

	/**
	 * Keeps plausible menu slugs from a list.
	 *
	 * Menu slugs are file names, admin URLs such as admin.php?page=x, or names a
	 * plugin made up, so only markup and control characters are rejected. Slugs
	 * are compared with the menu and escaped on output, never used as paths.
	 *
	 * @param mixed $slugs Raw list.
	 * @return string[]
	 */
	public static function clean_slugs( $slugs ) {
		if ( ! is_array( $slugs ) ) {
			return array();
		}

		$clean = array();
		foreach ( $slugs as $slug ) {
			if ( ! is_string( $slug ) || '' === $slug || strlen( $slug ) > 200 || preg_match( '/[<>\x00-\x1F\x7F]/', $slug ) ) {
				continue;
			}

			$clean[] = $slug;
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * A custom name as plain text, at most MAX_NAME_LENGTH characters.
	 *
	 * @param mixed $name Raw name.
	 * @return string
	 */
	public static function clean_name( $name ) {
		if ( ! is_string( $name ) ) {
			return '';
		}

		$name = sanitize_text_field( $name );

		return trim( function_exists( 'mb_substr' ) ? mb_substr( $name, 0, self::MAX_NAME_LENGTH ) : substr( $name, 0, self::MAX_NAME_LENGTH ) );
	}

	/**
	 * The custom name saved for a menu.
	 *
	 * @param string                $slug  Menu slug.
	 * @param array<string, string> $names Saved names.
	 * @return string Empty when the menu keeps its own name.
	 */
	public static function name_for( $slug, array $names ) {
		if ( isset( $names[ $slug ] ) ) {
			return $names[ $slug ];
		}

		foreach ( $names as $saved_slug => $name ) {
			if ( self::slugs_match( (string) $saved_slug, $slug ) ) {
				return $name;
			}
		}

		return '';
	}

	/**
	 * Keeps what the editor could not show when its layout is saved.
	 *
	 * Menus of a deactivated plugin, or menus the saving user cannot see, are not
	 * in the posted layout. Their section, name and visibility are carried over,
	 * and each one stays after the menu it followed. The editor always lists the
	 * separators the user added, so a missing one was removed.
	 *
	 * @param array $posted   Clean layout from the editor.
	 * @param array $previous Clean layout saved before.
	 * @return array{main: string[], more: string[], hidden: string[], names: array<string, string>, more_label: string}
	 */
	public static function merge_unlisted( array $posted, array $previous ) {
		$listed = self::listed_keys( $posted );

		foreach ( array( 'main', 'more' ) as $section ) {
			$anchor = null; // The previous menu of this section that is also in the new one.

			foreach ( $previous[ $section ] as $slug ) {
				$key  = self::normalize_slug( $slug );
				$keys = array_map( array( __CLASS__, 'normalize_slug' ), $posted[ $section ] );

				if ( ! isset( $listed[ $key ] ) && self::is_custom_separator( $slug ) ) {
					continue;
				}

				if ( isset( $listed[ $key ] ) ) {
					if ( in_array( $key, $keys, true ) ) {
						$anchor = $key;
					}
					continue;
				}

				$at = null === $anchor ? 0 : array_search( $anchor, $keys, true ) + 1;
				array_splice( $posted[ $section ], $at, 0, array( $slug ) );
				$anchor = $key;
			}
		}

		$posted['names']  = self::merge_names( $posted['names'], $previous['names'], $listed );
		$posted['hidden'] = self::merge_hidden( $posted['hidden'], $previous['hidden'], $listed );

		return $posted;
	}

	/**
	 * Saves names and visibility without touching the saved order and placement.
	 *
	 * @param array $posted   Clean layout from the editor.
	 * @param array $previous Clean layout saved before.
	 * @return array{main: string[], more: string[], hidden: string[], names: array<string, string>, more_label: string}
	 */
	public static function apply_details( array $posted, array $previous ) {
		$listed = self::listed_keys( $posted );

		$previous['names']      = self::merge_names( $posted['names'], $previous['names'], $listed );
		$previous['hidden']     = self::merge_hidden( $posted['hidden'], $previous['hidden'], $listed );
		$previous['more_label'] = $posted['more_label'];

		return $previous;
	}

	/**
	 * Posted hidden menus, plus hidden menus the editor did not list.
	 *
	 * @param string[]            $posted   Hidden menus from the editor.
	 * @param string[]            $previous Saved hidden menus.
	 * @param array<string, bool> $listed   Menus the editor listed.
	 * @return string[]
	 */
	private static function merge_hidden( array $posted, array $previous, array $listed ) {
		foreach ( $previous as $slug ) {
			if ( ! isset( $listed[ self::normalize_slug( $slug ) ] ) && ! self::is_custom_separator( $slug ) ) {
				$posted[] = $slug;
			}
		}

		return array_values( array_unique( $posted ) );
	}

	/**
	 * Lookup keys of the menus the editor listed.
	 *
	 * @param array $posted Clean layout from the editor.
	 * @return array<string, bool>
	 */
	private static function listed_keys( array $posted ) {
		$listed = array();
		foreach ( array_merge( $posted['main'], $posted['more'] ) as $slug ) {
			$listed[ self::normalize_slug( $slug ) ] = true;
		}

		return $listed;
	}

	/**
	 * Posted names, plus saved names of menus the editor did not list.
	 *
	 * @param array<string, string> $posted   Names from the editor.
	 * @param array<string, string> $previous Saved names.
	 * @param array<string, bool>   $listed   Menus the editor listed.
	 * @return array<string, string>
	 */
	private static function merge_names( array $posted, array $previous, array $listed ) {
		foreach ( $previous as $slug => $name ) {
			if ( ! isset( $listed[ self::normalize_slug( (string) $slug ) ] ) ) {
				$posted[ $slug ] = $name;
			}
		}

		return $posted;
	}
}
