/**
 * Comportamento do tema: sombra do header no scroll e menu mobile.
 */
(function () {
	'use strict';

	function initHeader() {
		var header = document.querySelector('[data-contorno-header]');

		if (!header) {
			return;
		}

		function sync() {
			header.classList.toggle('is-scrolled', window.scrollY > 8);
		}

		window.addEventListener('scroll', sync, { passive: true });
		sync();
	}

	function initMobileMenu() {
		var toggle = document.querySelector('[data-contorno-menu-toggle]');
		var menu = document.querySelector('[data-contorno-mobile-menu]');

		if (!toggle || !menu) {
			return;
		}

		toggle.addEventListener('click', function () {
			var open = toggle.getAttribute('aria-expanded') === 'true';

			toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
			menu.hidden = open;
		});

		// Fecha ao navegar para uma ancora da propria pagina.
		menu.addEventListener('click', function (event) {
			if (event.target.closest('a')) {
				toggle.setAttribute('aria-expanded', 'false');
				menu.hidden = true;
			}
		});
	}

	function init() {
		initHeader();
		initMobileMenu();
	}

	if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}
})();
