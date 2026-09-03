=== Tapio Clean Backend ===
Contributors: tapiokansleri
Tags: admin, admin menu, admin notices, dashboard, admin bar
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Nothing is removed. Tapio Clean Backend only rearranges the admin menus and notices that take up too much space.

== Description ==

The WordPress admin gets crowded fast. Every plugin adds a top-level menu item, and every plugin wants to tell you something above the page you are actually trying to read. Tapio Clean Backend tidies that up **without taking anything away from you**.

= Nothing is removed =

This is the whole idea, so it is worth being explicit:

* **No menu is deleted or blocked.** Plugin menus are moved into a collapsible **More** section at the bottom of the sidebar. Every item, every submenu and every link still works exactly as before.
* **No notice is suppressed.** Admin notices are relocated into a **Notifications** panel in the admin bar. You still see all of them, including the important ones, and you can still act on them and dismiss them.
* **No capabilities are changed.** The plugin never hides anything based on user role. What an administrator could reach before, they can still reach.
* **No content or other plugins are touched.** The only thing this plugin stores is one small option for its own two on/off switches. It does not modify any other plugin's settings.
* **No lock-in.** Deactivate it and your admin instantly looks exactly like it did before. Uninstalling deletes the one option.

In short: the same admin, arranged so that the things you use most are not buried under the things you rarely use.

= Sidebar: the More section =

Core WordPress menus stay exactly where you expect them: Dashboard, Posts, Media, Pages, Comments, Appearance, Plugins, Users, Tools and Settings.

Plugin menus and custom post type menus are gathered into a single collapsed **More** control placed just above “Collapse menu”. Click it to expand the full list. The open or closed state is remembered in your own browser, so the admin opens the way you left it.

If you navigate to a screen that lives inside More, the section opens automatically and highlights the correct item, so you never lose your place.

= Admin bar: the Notifications panel =

Admin notices normally stack up between your page heading and the content you came to work on. Tapio Clean Backend collects them and shows them in a **Notifications** panel in the admin bar, with a badge showing how many are waiting.

The notices keep their original styling cues (success, warning, error, info), their action buttons, and their dismiss controls. Dismissing a notice in the panel behaves the same as dismissing it on the page.

= Settings =

Open **Tools > Tapio Clean Backend** to turn each feature on or off independently:

* **Sidebar More** — collapse plugin menus under More.
* **Notifications** — move admin notices into the admin bar.

Both are on by default. Turning a switch off restores that part of the admin to standard WordPress.

= Safe by design =

* One small options row for the two switches. No custom tables and no post meta.
* No external requests, no tracking, no analytics, no phoning home.
* If JavaScript is unavailable or fails, the admin falls back to completely standard WordPress: all menus visible, all notices in their normal position.
* Network Admin is deliberately left untouched.

= Translations =

Ships with a Finnish translation (`fi`). Other locales are welcome via translate.wordpress.org.

= Developers =

Two filters are available:

* `tapio_clean_backend_core_menu_slugs` — the allowlist of top-level menus that stay in the main sidebar.
* `tapio_clean_backend_enabled` — return `false` to turn the plugin off at runtime, for example on a specific screen or for a specific role.

== Installation ==

1. Upload the `tapio-clean-backend` folder to `/wp-content/plugins/`, or install the plugin through **Plugins > Add New**.
2. Activate **Tapio Clean Backend** through the **Plugins** screen.
3. Optionally open **Tools > Tapio Clean Backend** to turn the sidebar More section or the Notifications panel off independently. Both are on by default.

To go back to the default admin, turn both switches off or deactivate the plugin.

== Frequently Asked Questions ==

= Does this hide or remove my plugin menus? =

No. Nothing is removed. The menus are moved into a collapsible **More** section and remain fully functional. Click **More** to see them all.

= Does it hide admin notices I need to see? =

No. Notices are relocated to the **Notifications** panel in the admin bar rather than being discarded. The badge tells you how many are waiting, so nothing goes unnoticed.

= Which menus stay in the main sidebar? =

The WordPress core menus: Dashboard, Posts, Media, Pages, Comments, Appearance, Plugins, Users, Profile, Tools and Settings. Everything else is grouped under **More**.

= How do I keep a specific plugin menu in the main sidebar? =

Add its menu slug to the allowlist with the `tapio_clean_backend_core_menu_slugs` filter. Use the same value WordPress uses for the menu file:

`
add_filter( 'tapio_clean_backend_core_menu_slugs', function ( $slugs ) {
	$slugs[] = 'edit.php?post_type=product';
	$slugs[] = 'woocommerce';
	return $slugs;
} );
`

= How do I disable the plugin without deactivating it? =

`
add_filter( 'tapio_clean_backend_enabled', '__return_false' );
`

= Where are the settings? =

**Tools > Tapio Clean Backend.** There you can turn Sidebar More and Notifications on or off independently.

= Does the plugin store anything in my database? =

One option, `tapio_clean_backend_settings`, for those two switches. No tables and no post meta. Whether you left the **More** section open is stored only in your own browser. Uninstalling the plugin deletes the option.

= What happens if JavaScript is disabled or blocked? =

The admin falls back to standard WordPress behaviour: every menu is visible in the sidebar and every notice appears in its normal place. The More section and the Notifications panel are progressive enhancements, so a failure never locks you out of anything.

= Does it work on multisite? =

Yes. On regular site admin screens it works as described. Network Admin screens are intentionally skipped so core network menus stay exactly where they are.

= What happens when I deactivate the plugin? =

Your admin returns to its default appearance immediately. Uninstalling also deletes the settings option.

== Changelog ==

= 1.0.0 =
* Initial WordPress.org release.
* Groups plugin and custom post type menus into a collapsible More section, without removing anything.
* Relocates admin notices into an admin bar Notifications panel with a badge count.
* Accessible More toggle and keyboard-reachable Notifications panel.
* Graceful fallback to standard WordPress when JavaScript is unavailable.
* Tools settings page to turn Sidebar More and Notifications on or off independently.
* Finnish translation.
