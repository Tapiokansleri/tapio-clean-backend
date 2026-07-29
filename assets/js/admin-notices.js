/**
 * Relocates admin notices into the admin bar Notifications panel.
 */
(function () {
	'use strict';

	var CONTENT_SELECTORS = [
		'#wpbody-content > .notice',
		'#wpbody-content > .update-nag',
		'#wpbody-content > .updated',
		'#wpbody-content > .error',
		'#wpbody-content > .e-notice',
		'#wpbody-content > .wrap > .notice',
		'#wpbody-content > .wrap > .update-nag',
		'#wpbody-content > .wrap > .updated',
		'#wpbody-content > .wrap > .error',
		'#wpbody-content > .wrap > .e-notice'
	].join(',');

	function fingerprint(el) {
		return (el.className || '') + '|' + (el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 240);
	}

	function collectNodes(selector) {
		return Array.prototype.slice.call(document.querySelectorAll(selector));
	}

	function isNoticeLike(el) {
		if (!el || el.nodeType !== 1) {
			return false;
		}
		if (el.closest('#wpadminbar, #tapio-clean-notices-panel, #tapio-clean-notices-source')) {
			return false;
		}
		var cls = ' ' + (el.className || '') + ' ';
		return (
			cls.indexOf(' notice ') !== -1 ||
			cls.indexOf(' update-nag ') !== -1 ||
			cls.indexOf(' updated ') !== -1 ||
			cls.indexOf(' e-notice ') !== -1 ||
			/(?:^|\s)error(?:\s|$)/.test(cls.trim()) ||
			/notice/i.test(el.className || '')
		);
	}

	function gatherNotices() {
		var nodes = [];
		var seen = {};

		function pushUnique(el) {
			if (!isNoticeLike(el)) {
				return;
			}
			var key = fingerprint(el);
			if (seen[key]) {
				// Duplicate of something already collected — remove leftover from content.
				if (el.parentNode && !el.closest('#wpadminbar')) {
					el.parentNode.removeChild(el);
				}
				return;
			}
			seen[key] = true;
			nodes.push(el);
		}

		var source = document.getElementById('tapio-clean-notices-source');
		if (source) {
			Array.prototype.slice.call(source.children).forEach(pushUnique);
			if (!nodes.length && source.innerHTML.replace(/\s/g, '') !== '') {
				Array.prototype.slice.call(source.querySelectorAll(':scope > *')).forEach(pushUnique);
			}
		}

		collectNodes(CONTENT_SELECTORS).forEach(pushUnique);

		// Catch custom top-level banners that only use a *notice* class fragment.
		collectNodes('#wpbody-content > div, #wpbody-content > .wrap > div').forEach(function (el) {
			if (isNoticeLike(el)) {
				pushUnique(el);
			}
		});

		return nodes;
	}

	function updateBadge(count) {
		var badge = document.querySelector('#wp-admin-bar-tapio-clean-notifications .tapio-clean-notice-badge');
		if (!badge) {
			return;
		}
		if (count > 0) {
			badge.hidden = false;
			badge.textContent = String(count);
		} else {
			badge.hidden = true;
			badge.textContent = '';
		}
	}

	function init() {
		var panel = document.getElementById('tapio-clean-notices-panel');
		if (!panel) {
			return;
		}

		var source = document.getElementById('tapio-clean-notices-source');
		var emptyLabel = (source && source.getAttribute('data-empty')) || 'No notifications';
		var notices = gatherNotices();

		panel.innerHTML = '';

		if (notices.length) {
			notices.forEach(function (el) {
				el.style.display = '';
				el.style.visibility = '';
				panel.appendChild(el);
			});
		} else {
			var empty = document.createElement('p');
			empty.className = 'tapio-clean-notices-empty';
			empty.textContent = emptyLabel;
			panel.appendChild(empty);
		}

		if (source && source.parentNode) {
			source.parentNode.removeChild(source);
		}

		var count = notices.length;
		if (source && source.getAttribute('data-count')) {
			var parsed = parseInt(source.getAttribute('data-count'), 10);
			if (!isNaN(parsed) && parsed > count) {
				count = parsed;
			}
		}

		updateBadge(count);
	}

	function boot() {
		init();
		// Late-injected notices (some plugins print after DOMContentLoaded).
		window.setTimeout(init, 300);
		window.setTimeout(init, 1200);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
