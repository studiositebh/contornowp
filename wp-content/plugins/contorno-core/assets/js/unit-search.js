/**
 * Busca de unidades no cliente — porte de filterUnits() do React.
 *
 * Casa por nome, cidade, bairro, endereco e tipo, e tambem por CEP quando o
 * termo tem 5 ou mais digitos. Um campo dentro de uma listagem filtra a
 * propria listagem; um campo de hero (data-target="hero") leva para
 * /unidades ja com o termo aplicado.
 */
(function () {
	'use strict';

	// Faixa de sinais diacriticos combinantes (NFD).
	var DIACRITICS = /[̀-ͯ]/g;

	function normalize(value) {
		return String(value || '')
			.normalize('NFD')
			.replace(DIACRITICS, '')
			.toLowerCase()
			.replace(/\s+/g, ' ')
			.trim();
	}

	function applyFilter(container, query) {
		var q = normalize(query);
		var digits = q.replace(/\D/g, '');
		var items = container.querySelectorAll('[data-contorno-unit]');
		var visible = 0;

		Array.prototype.forEach.call(items, function (item) {
			var haystack = item.dataset.haystack || '';
			var postal = (item.dataset.postal || '').replace(/\D/g, '');

			var match =
				!q ||
				haystack.indexOf(q) !== -1 ||
				(digits.length >= 5 && postal !== '' && postal.indexOf(digits) !== -1);

			item.hidden = !match;

			if (match) {
				visible += 1;
			}
		});

		var empty = container.querySelector('[data-contorno-units-empty]');
		if (empty) {
			empty.hidden = visible > 0;
		}

		var carousel = container.querySelector('[data-contorno-carousel]');
		if (carousel) {
			carousel.dispatchEvent(new CustomEvent('contorno:units-filtered'));
		}
	}

	function setup(root) {
		if (root.dataset.contornoSearchReady === '1') {
			return;
		}
		root.dataset.contornoSearchReady = '1';

		var input = root.querySelector('[data-contorno-unit-search-input]');
		var clear = root.querySelector('[data-contorno-unit-search-clear]');

		if (!input) {
			return;
		}

		var container = root.closest('[data-contorno-units]');
		var isRemote = root.dataset.target === 'hero' || !container;

		function run() {
			if (clear) {
				clear.hidden = input.value === '';
			}

			if (!isRemote) {
				applyFilter(container, input.value);
			}
		}

		input.addEventListener('input', run);

		input.addEventListener('keydown', function (event) {
			if (event.key !== 'Enter') {
				return;
			}

			event.preventDefault();

			if (!isRemote) {
				return;
			}

			var archive = input.dataset.archive || '/unidades/';
			var term = input.value.trim();

			window.location.href = term
				? archive + (archive.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(term)
				: archive;
		});

		if (clear) {
			clear.addEventListener('click', function () {
				input.value = '';
				run();
				input.focus();
			});
		}

		// Termo vindo da URL (?q=) aplicado na chegada.
		if (!isRemote && window.URLSearchParams) {
			var initial = new URLSearchParams(window.location.search).get('q');
			if (initial) {
				input.value = initial;
				run();
			}
		}
	}

	function init() {
		Array.prototype.forEach.call(
			document.querySelectorAll('[data-contorno-unit-search]'),
			setup
		);
	}

	if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}

	document.addEventListener('contorno:refresh', init);
})();
