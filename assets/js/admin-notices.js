/**
 * Relocates admin notices into the admin bar Notifications panel.
 *
 * Runs after wp-admin common.js (which moves .notice nodes after the page
 * heading). Re-entry must not wipe notices already placed in the panel.
 */
(function ($) {
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

	var emptyLabel = 'No notifications';
	var didInitial = false;
	var failed = false;

	function classTokens(el) {
		return String(el.className || '')
			.split(/\s+/)
			.map(function (c) {
				return c.trim();
			})
			.filter(Boolean);
	}

	function hasToken(el, token) {
		return classTokens(el).indexOf(token) !== -1;
	}

	function collectNodes(selector) {
		return Array.prototype.slice.call(document.querySelectorAll(selector));
	}

	function fingerprint(el) {
		var text = (el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 200);
		return classTokens(el).sort().join('.') + '|' + text;
	}

	function revealNoticesInPlace() {
		document.body.classList.remove('tapio-clean-hide-notices');

		var source = document.getElementById('tapio-clean-notices-source');
		var target = document.getElementById('wpbody-content');
		if (source && target && source.children.length) {
			var wrap = target.querySelector('.wrap');
			var anchor = wrap || target;
			while (source.firstChild) {
				anchor.insertBefore(source.firstChild, anchor.firstChild);
			}
		}
		removeSource();
	}

	function isNoticeLike(el) {
		if (!el || el.nodeType !== 1 || el.tagName === 'SCRIPT' || el.tagName === 'STYLE') {
			return false;
		}
		if (el.closest('#wpadminbar, #tapio-clean-notices-panel, #tapio-clean-notices-source, #screen-meta, #screen-meta-links')) {
			return false;
		}
		if (el.closest('.wp-editor-container, .block-editor, .interface-interface-skeleton')) {
			return false;
		}

		return (
			hasToken(el, 'notice') ||
			hasToken(el, 'update-nag') ||
			hasToken(el, 'updated') ||
			hasToken(el, 'error') ||
			hasToken(el, 'e-notice')
		);
	}

	function usableNotice(el) {
		if (!isNoticeLike(el)) {
			return false;
		}

		var text = (el.textContent || '').replace(/\s+/g, ' ').trim();
		if (text.length < 2) {
			return false;
		}

		// Skip chrome / view-switcher leftovers that are not real admin notices.
		if (/^switch to the (list|grid) view$/i.test(text)) {
			return false;
		}

		return true;
	}

	function prepareNotice(el) {
		// Prevent wp-admin common.js from yanking the node back under the heading.
		el.classList.add('inline');
		el.classList.add('tapio-clean-notice-card');
		el.style.cssText = '';
		el.removeAttribute('hidden');

		// Flatten accidental nested notice wrappers.
		Array.prototype.slice.call(el.querySelectorAll('.notice, .update-nag, .updated, .error, .e-notice')).forEach(function (nested) {
			if (nested === el) {
				return;
			}
			while (nested.firstChild) {
				el.insertBefore(nested.firstChild, nested);
			}
			if (nested.parentNode) {
				nested.parentNode.removeChild(nested);
			}
		});
	}

	function gatherNewNotices() {
		var nodes = [];
		var seen = {};

		function pushUnique(el) {
			if (!usableNotice(el)) {
				if (el && el.parentNode && !el.closest('#wpadminbar') && isNoticeLike(el) && !usableNotice(el)) {
					el.parentNode.removeChild(el);
				}
				return;
			}
			var key = fingerprint(el);
			if (seen[key]) {
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
			var label = source.getAttribute('data-empty');
			if (label) {
				emptyLabel = label;
			}
			Array.prototype.slice.call(source.children).forEach(pushUnique);
			// Captured HTML sometimes wraps notices; pull notice roots from deeper.
			if (!nodes.length) {
				collectNodes('#tapio-clean-notices-source .notice, #tapio-clean-notices-source .update-nag, #tapio-clean-notices-source .updated, #tapio-clean-notices-source .error, #tapio-clean-notices-source .e-notice').forEach(function (el) {
					if (!el.parentElement || !isNoticeLike(el.parentElement)) {
						pushUnique(el);
					}
				});
			}
		}

		collectNodes(CONTENT_SELECTORS).forEach(pushUnique);

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

	function panelNoticeCount(panel) {
		return panel.querySelectorAll(':scope > .tapio-clean-notice-card').length;
	}

	function renderEmpty(panel) {
		panel.innerHTML = '';
		var empty = document.createElement('p');
		empty.className = 'tapio-clean-notices-empty';
		empty.textContent = emptyLabel;
		panel.appendChild(empty);
	}

	function appendNotices(panel, notices) {
		if (!notices.length) {
			return 0;
		}

		var empty = panel.querySelector('.tapio-clean-notices-empty');
		if (empty) {
			empty.parentNode.removeChild(empty);
		}

		notices.forEach(function (el) {
			prepareNotice(el);
			panel.appendChild(el);
		});

		return notices.length;
	}

	/**
	 * Remove dismissed cards instantly — core fadeTo/slideUp looks awful in this tray.
	 */
	function bindInstantDismiss(panel) {
		if (panel.getAttribute('data-tapio-dismiss-bound') === '1') {
			return;
		}
		panel.setAttribute('data-tapio-dismiss-bound', '1');

		panel.addEventListener(
			'click',
			function (event) {
				var dismiss = event.target.closest('.notice-dismiss, .e-notice__dismiss');
				if (!dismiss || !panel.contains(dismiss)) {
					return;
				}

				var card = dismiss.closest('.tapio-clean-notice-card');
				if (!card || !panel.contains(card)) {
					return;
				}

				event.preventDefault();
				event.stopImmediatePropagation();
				card.remove();

				if (!panelNoticeCount(panel)) {
					renderEmpty(panel);
				}
				updateBadge(panelNoticeCount(panel));
			},
			true
		);
	}

	function removeSource() {
		var source = document.getElementById('tapio-clean-notices-source');
		if (source && source.parentNode) {
			source.parentNode.removeChild(source);
		}
	}

	function init(isLatePass) {
		if (failed) {
			return;
		}

		try {
			var panel = document.getElementById('tapio-clean-notices-panel');
			if (!panel) {
				revealNoticesInPlace();
				failed = true;
				return;
			}

			bindInstantDismiss(panel);

			var notices = gatherNewNotices();

			if (!didInitial) {
				didInitial = true;
				if (notices.length) {
					panel.innerHTML = '';
					appendNotices(panel, notices);
				} else {
					renderEmpty(panel);
				}
				removeSource();
			} else if (notices.length) {
				appendNotices(panel, notices);
			} else if (!isLatePass && !panelNoticeCount(panel)) {
				renderEmpty(panel);
			}

			updateBadge(panelNoticeCount(panel));
		} catch (e) {
			failed = true;
			revealNoticesInPlace();
		}
	}

	function boot() {
		init(false);
		window.setTimeout(function () {
			init(true);
		}, 300);
		window.setTimeout(function () {
			init(true);
		}, 1200);
	}

	$(boot);
})(jQuery);
