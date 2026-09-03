/**
 * Collapses plugin/CPT menus under a More toggle in #adminmenu.
 */
(function () {
	'use strict';

	var config = window.tapioCleanBackendMore || {};
	var moreItemClass = config.moreItemClass || 'tapio-more-item';
	var storageKey = config.storageKey || 'tapioCleanBackendMoreOpen';
	var moreLabel = config.moreLabel || 'More';
	var openClass = 'tapio-more-is-open';
	var readyClass = 'tapio-more-ready';
	var enhancedClass = 'tapio-more-enhanced';
	var itemsId = 'tapio-more-items';

	function isOpen() {
		try {
			return window.localStorage.getItem(storageKey) === '1';
		} catch (e) {
			return false;
		}
	}

	function setOpen(open) {
		try {
			window.localStorage.setItem(storageKey, open ? '1' : '0');
		} catch (e) {
			// Ignore.
		}
	}

	function getCollapseMenu(adminmenu) {
		return adminmenu.querySelector('#collapse-menu');
	}

	function applyOpenState(adminmenu, open) {
		adminmenu.classList.toggle(openClass, open);
		var toggle = adminmenu.querySelector('.tapio-more-toggle');
		if (toggle) {
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		}
	}

	function toggleOpen(adminmenu) {
		var open = !adminmenu.classList.contains(openClass);
		applyOpenState(adminmenu, open);
		setOpen(open);
		orderMoreItems(adminmenu);
	}

	function ensureToggle(adminmenu) {
		var existing = adminmenu.querySelector('.tapio-more-toggle-item');
		if (existing) {
			return existing;
		}

		var toggleLi = document.createElement('li');
		toggleLi.className = 'menu-top tapio-more-toggle-item';

		var toggleLink = document.createElement('a');
		toggleLink.href = '#';
		toggleLink.className = 'menu-top tapio-more-toggle';
		toggleLink.setAttribute('role', 'button');
		toggleLink.setAttribute('aria-expanded', 'false');

		var icon = document.createElement('div');
		icon.className = 'wp-menu-image dashicons-before dashicons-plus-alt2';
		icon.setAttribute('aria-hidden', 'true');
		icon.appendChild(document.createElement('br'));

		var name = document.createElement('div');
		name.className = 'wp-menu-name';
		name.textContent = moreLabel;

		toggleLink.appendChild(icon);
		toggleLink.appendChild(name);
		toggleLi.appendChild(toggleLink);

		var collapse = getCollapseMenu(adminmenu);
		if (collapse) {
			adminmenu.insertBefore(toggleLi, collapse);
		} else {
			adminmenu.appendChild(toggleLi);
		}

		toggleLink.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopImmediatePropagation();
			toggleOpen(adminmenu);
		});

		toggleLink.addEventListener('keydown', function (event) {
			if (event.key !== 'Enter' && event.key !== ' ') {
				return;
			}
			event.preventDefault();
			event.stopImmediatePropagation();
			toggleOpen(adminmenu);
		});

		return toggleLi;
	}

	function orderMoreItems(adminmenu) {
		var toggle = adminmenu.querySelector('.tapio-more-toggle-item');
		if (!toggle) {
			return;
		}

		var collapse = getCollapseMenu(adminmenu);
		if (!collapse) {
			return;
		}

		var items = Array.prototype.slice.call(
			adminmenu.querySelectorAll(':scope > li.' + moreItemClass)
		);

		items.forEach(function (li) {
			li.style.display = '';
			li.style.maxHeight = '';
		});

		// More stays fixed directly above "Collapse menu"; plugin items expand below it.
		adminmenu.insertBefore(toggle, collapse);
		items.forEach(function (li) {
			adminmenu.insertBefore(li, collapse);
		});

		var toggleLink = toggle.querySelector('.tapio-more-toggle');
		if (toggleLink) {
			if (items.length) {
				if (!items[0].id) {
					items[0].id = itemsId;
				}
				toggleLink.setAttribute('aria-controls', items[0].id);
			} else {
				toggleLink.removeAttribute('aria-controls');
			}
		}
	}

	function enableTransitions(adminmenu) {
		window.requestAnimationFrame(function () {
			adminmenu.classList.add(readyClass);
		});
	}

	function init() {
		var adminmenu = document.getElementById('adminmenu');
		if (!adminmenu) {
			return;
		}

		ensureToggle(adminmenu);
		orderMoreItems(adminmenu);
		adminmenu.classList.add(enhancedClass);

		var hasCurrent = !!adminmenu.querySelector(
			'li.' + moreItemClass + '.current, li.' + moreItemClass + '.wp-has-current-submenu, li.' + moreItemClass + '.opensub'
		);

		applyOpenState(adminmenu, hasCurrent || isOpen());
		enableTransitions(adminmenu);

		if (window.jQuery) {
			window.jQuery(window).trigger('wp-window-resized');
		}
	}

	function boot() {
		init();
		window.setTimeout(init, 250);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
