=== Tapio Clean Backend ===
Contributors: tapiokansleri
Tags: admin, admin menu, admin menu editor, admin notices, admin bar
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Arrange and rename the admin sidebar, keep less used menus under a More link, and read admin notices in one place. Nothing is removed.

== Description ==

The WordPress admin gets crowded fast. Every plugin adds a top-level menu item, and every plugin wants to tell you something above the page you are actually trying to read. Tapio Clean Backend tidies that up **without taking anything away from you**.

= Nothing is removed =

* **No menu is deleted or blocked.** Menus are only reordered, renamed or placed under a **More** link. Every item, every submenu and every link still works.
* **No notice is thrown away.** Admin notices are moved into a **Notifications** panel in the admin bar, with a badge showing how many are waiting.
* **No capabilities are changed.** Each user still sees exactly the menus their role allows.
* **No content or other plugins are touched.** The plugin stores one option: its two switches, the sidebar order and your menu names.
* **No lock-in.** Deactivate it and the admin looks exactly as before. Uninstalling deletes the option.

= Your sidebar, your way =

On **Tools > Tapio Clean Backend** you see the sidebar as two lists, **Sidebar** and **More**:

* **Drag** any menu, WordPress core menus included, to change its order or to move it between the lists. Separator lines can be moved within the sidebar.
* **Click a name** to rename the menu. Leave the name empty to get the original back. Counts such as pending comments stay next to the new name.
* **Rename the More link** by clicking the title of the More list.
* **Restore default menu** brings back the starting layout and the original names.

Before you save your own layout, WordPress core menus are in the sidebar and plugin and custom post type menus are under More. A menu that appears later, for example from a newly activated plugin, goes to the end of More; a core menu goes into the sidebar next to its usual neighbour. Menus of a deactivated plugin keep their place and name.

The layout is shared by all users. Keyboard users can move menus from a drag handle with the arrow keys.

= No flashing sidebar =

The order, the names and the More link are prepared on the server, and the menus under More are hidden before the sidebar is first painted. The page does not show the old sidebar while loading. The open or closed state of More is remembered in your browser, and More opens by itself on screens that are inside it.

= Admin bar: the Notifications panel =

Admin notices normally stack up above the content you came to work on. The plugin moves them into a **Notifications** panel in the admin bar. Their buttons and dismiss controls keep working, including dismissals a plugin saves.

Some things stay on the page on purpose: hidden notices that WordPress shows later (such as "Connection lost" in the editor), notices marked as inline, and messages that appear after the page has loaded, such as the result of an action.

= Safe by design =

* No custom tables and no post meta.
* No external requests, no tracking, no analytics.
* Without JavaScript the admin stays standard: every menu is visible and notices stay on the page.
* Network Admin screens are left as they are.

= Translations =

Ships with a Finnish translation (`fi`).

= Developers =

* `tapio_clean_backend_core_menu_slugs` — the menus that start in the sidebar before a layout is saved, and where a new core menu goes. A saved layout takes precedence.
* `tapio_clean_backend_enabled` — return `false` to turn the plugin off at runtime, for example for a specific role.

== Installation ==

1. Upload the `tapio-clean-backend` folder to `/wp-content/plugins/`, or install the plugin through **Plugins > Add New**.
2. Activate **Tapio Clean Backend** on the **Plugins** screen.
3. Open **Tools > Tapio Clean Backend** to arrange the sidebar, or to turn the More link or the Notifications panel off.

== Frequently Asked Questions ==

= Does this hide or remove my plugin menus? =

No. Menus under **More** are one click away and work exactly as before.

= Can I move WordPress core menus under More? =

Yes. Any top-level menu can go in either list.

= Can I rename submenu items? =

No. Renaming and ordering apply to top-level menus.

= Does it hide admin notices I need to see? =

No. Notices go to the **Notifications** panel, and the badge tells you how many are there. Warnings that WordPress keeps hidden until they are needed stay on the page.

= I used version 1.1. What happens to my choices? =

The menus you kept in the sidebar and your menu order still apply. They are replaced by the new layout when you first change it in the editor.

= Does the plugin store anything in my database? =

One option, `tapio_clean_backend_settings`. Whether you left **More** open is stored only in your own browser. Uninstalling the plugin deletes the option, on every site of a network.

= What happens when I deactivate the plugin? =

Your admin returns to its default appearance immediately.

== Changelog ==

= 1.2.0 =
* New sidebar editor: drag any top-level menu, WordPress core menus included, between the sidebar and More and into any order.
* Rename any menu, and the More link, by clicking its name.
* Fix: the sidebar no longer flashes in its old layout while a page loads. The final menu is printed by the server.
* Fix: hidden editor warnings such as "Connection lost" were moved into the Notifications panel, where they were not seen. Hidden and inline notices now stay on the page.
* Fix: dismissing a notice in the panel now also runs the plugin's own dismiss action, so dismissed notices do not come back.
* Fix: notices that appear after the page has loaded stay visible on the page.
* Fix: content that plugins print with their notices, such as styles and hidden templates, is no longer deleted, and notices are no longer removed as duplicates.
* Fix: without JavaScript, notices are shown instead of disappearing.
* Uninstalling removes the option on every site of a network.

= 1.1.1 =
* Fix: a "Undefined array key" warning and a PHP "array_flip()" warning in the admin menu when the saved menu order met a menu that appears twice (the slots of the sidebar are now filled by position).
* Fix: the Notifications panel: tall notices (for example WPML's) no longer shrink and run over the next one, small svg icons keep their size (they were stretched to the width of the panel), a notice's layout stays inside its card, and the round "collapse" buttons of WPML notices are hidden (they sat on top of the text), and the round icon some plugins draw in front of a notice is hidden.

= 1.1.0 =
* Keep in the sidebar: tick plugin and custom post type menus on the settings page to keep them in the main sidebar instead of More.
* Drag to reorder plugin and custom post type menus, in the sidebar and under More. Keyboard users can move rows with the arrow keys.

= 1.0.0 =
* Initial WordPress.org release.
* Groups plugin and custom post type menus into a collapsible More section, without removing anything.
* Relocates admin notices into an admin bar Notifications panel with a badge count.
* Accessible More toggle and keyboard-reachable Notifications panel.
* Graceful fallback to standard WordPress when JavaScript is unavailable.
* Tools settings page to turn Sidebar More and Notifications on or off independently.
* Finnish translation.
