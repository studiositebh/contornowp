/**
 * Animacao de entrada por scroll — porte de <Reveal /> do React.
 *
 * Adiciona .is-inview quando o elemento entra no viewport. O CSS neutraliza
 * as transicoes quando prefers-reduced-motion esta ativo.
 */
(function () {
	'use strict';

	function activate(el) {
		el.classList.add('is-inview');
	}

	function init() {
		var nodes = document.querySelectorAll('[data-contorno-reveal]:not(.is-inview)');

		if (!nodes.length) {
			return;
		}

		if (!('IntersectionObserver' in window)) {
			Array.prototype.forEach.call(nodes, activate);
			return;
		}

		var observer = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						activate(entry.target);
						observer.unobserve(entry.target);
					}
				});
			},
			{ rootMargin: '0px 0px -12% 0px', threshold: 0.05 }
		);

		Array.prototype.forEach.call(nodes, function (node) {
			observer.observe(node);
		});
	}

	if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}

	// Conteudo inserido depois (ex.: preview do builder).
	document.addEventListener('contorno:refresh', init);
})();
