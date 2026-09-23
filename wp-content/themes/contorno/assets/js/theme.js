/**
 * Comportamento do tema: fundo do header no scroll e menu mobile.
 */
(function () {
	'use strict';

	function initHeader() {
		var header = document.querySelector('[data-contorno-header]');

		if (!header) {
			return;
		}

		var adminBar = document.getElementById('wpadminbar');

		function sync() {
			header.classList.toggle('is-scrolled', window.scrollY > 8);
			if (adminBar) {
				header.style.setProperty('--contorno-admin-offset', Math.max(0, adminBar.getBoundingClientRect().bottom) + 'px');
			}
		}

		window.addEventListener('scroll', sync, { passive: true });
		window.addEventListener('resize', sync, { passive: true });
		window.addEventListener('pageshow', sync);
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

	/*
	 * Ancoras internas (#estrutura, #planos...). A navegacao nativa por hash
	 * nem sempre rola em navegadores moveis com scroll-behavior:smooth; aqui o
	 * destino e rolado explicitamente, respeitando o scroll-margin-top do CSS
	 * (header fixo) e a preferencia de movimento reduzido.
	 */
	function initAnchors() {
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		function targetFor(hash) {
			if (!hash || hash.length < 2) {
				return null;
			}

			try {
				return document.getElementById(decodeURIComponent(hash.slice(1)));
			} catch (error) {
				return null;
			}
		}

		document.addEventListener('click', function (event) {
			var link = event.target.closest('a[href^="#"]');

			if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) {
				return;
			}

			var target = targetFor(link.getAttribute('href'));

			if (!target) {
				return;
			}

			event.preventDefault();
			target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });

			// Mantem o comportamento de foco da ancora nativa (skip link, leitores de tela).
			if (!target.hasAttribute('tabindex') && !/^(A|BUTTON|INPUT|SELECT|TEXTAREA)$/.test(target.tagName)) {
				target.setAttribute('tabindex', '-1');
			}
			target.focus({ preventScroll: true });

			if (window.history && window.history.pushState) {
				window.history.pushState(null, '', link.getAttribute('href'));
			}
		});

		// Chegada com hash (ex.: /ctn/castelo/#estrutura): rola depois do layout.
		var initial = targetFor(window.location.hash);
		if (initial) {
			window.addEventListener('load', function () {
				initial.scrollIntoView({ behavior: 'auto', block: 'start' });
			});
		}
	}

	function init() {
		initHeader();
		initMobileMenu();
		initAnchors();
	}

	if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}
})();
