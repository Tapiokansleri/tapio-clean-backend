/**
 * Collapses plugin admin bar items under a More toggle.
 */
(function () {
	'use strict';

	var config = window.tapioCleanBackendAdminBarMore || {};
	var coreIds = config.coreNodeIds || [];
	var moreItemClass = config.moreItemClass || 'tapio-admin-bar-more-item';
	var moreNodeId = config.moreNodeId || 'tapio-clean-admin-more';
	var storageKey = config.storageKey || 'tapioCleanBackendAdminBarMoreOpen';
	var moreLabel = config.moreLabel || 'More';
	var openClass = 'tapio-admin-bar-more-is-open';
	var readyClass = 'tapio-admin-bar-more-ready';

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

	function nodeIdFromLi(li) {
		if (!li || !li.id || li.id.indexOf('wp-admin-bar-') !== 0) {
			return '';
		}
		return li.id.slice('wp-admin-bar-'.length);
	}

	function isCore(id) {
		return coreIds.indexOf(id) !== -1;
	}

	function getRootList() {
		var root = document.getElementById('wp-admin-bar-root-default');
		return root ? root : null;
	}

	function applyOpenState(adminbar, open) {
		adminbar.classList.toggle(openClass, open);
		var toggle = document.getElementById('wp-admin-bar-' + moreNodeId);
		if (!toggle) {
			return;
		}
		var link = toggle.querySelector('a.ab-item');
		if (link) {
			link.setAttribute('aria-expanded', open ? 'true' : 'false');
		}
	}

	function ensureToggle(root, insertBeforeNode) {
		var existing = document.getElementById('wp-admin-bar-' + moreNodeId);
		if (existing) {
			return existing;
		}

		var li = document.createElement('li');
		li.id = 'wp-admin-bar-' + moreNodeId;
		li.className = 'tapio-admin-bar-more-toggle-root';

		var link = document.createElement('a');
		link.className = 'ab-item tapio-admin-bar-more-toggle';
		link.href = '#';
		link.setAttribute('aria-expanded', 'false');

		var icon = document.createElement('span');
		icon.className = 'ab-icon dashicons dashicons-plus-alt2';
		icon.setAttribute('aria-hidden', 'true');

		var label = document.createElement('span');
		label.className = 'ab-label';
		label.textContent = moreLabel;

		link.appendChild(icon);
		link.appendChild(label);
		li.appendChild(link);

		if (insertBeforeNode && insertBeforeNode.parentNode === root) {
			root.insertBefore(li, insertBeforeNode);
		} else {
			root.appendChild(li);
		}

		link.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopImmediatePropagation();
			var adminbar = document.getElementById('wpadminbar');
			if (!adminbar) {
				return;
			}
			var open = !adminbar.classList.contains(openClass);
			applyOpenState(adminbar, open);
			setOpen(open);
			orderItems(root);
		});

		return li;
	}

	function orderItems(root) {
		var toggle = document.getElementById('wp-admin-bar-' + moreNodeId);
		if (!toggle) {
			return;
		}

		var extras = Array.prototype.slice.call(
			root.querySelectorAll(':scope > li.' + moreItemClass)
		);

		if (!extras.length) {
			return;
		}

		extras.forEach(function (li) {
			li.style.display = '';
		});

		root.insertBefore(toggle, extras[0]);

		for (var i = extras.length - 1; i >= 0; i--) {
			root.insertBefore(extras[i], toggle.nextSibling);
		}
	}

	function init() {
		var adminbar = document.getElementById('wpadminbar');
		var root = getRootList();
		if (!adminbar || !root) {
			return;
		}

		var children = Array.prototype.slice.call(root.children);
		var firstExtra = null;
		var hasActiveExtra = false;

		children.forEach(function (li) {
			var id = nodeIdFromLi(li);
			if (!id || isCore(id) || id === moreNodeId) {
				return;
			}

			li.classList.add(moreItemClass);
			if (!firstExtra) {
				firstExtra = li;
			}
			if (
				li.classList.contains('hover') ||
				li.querySelector('.current') ||
				li.querySelector('a.current')
			) {
				hasActiveExtra = true;
			}
		});

		if (!firstExtra) {
			return;
		}

		ensureToggle(root, firstExtra);
		orderItems(root);

		applyOpenState(adminbar, hasActiveExtra || isOpen());

		window.requestAnimationFrame(function () {
			adminbar.classList.add(readyClass);
		});
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
