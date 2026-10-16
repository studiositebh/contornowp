/**
 * Grade nativa de aulas coletivas — filtros Manha/Tarde/Noite e, no celular,
 * abas por dia (lista de um dia por vez).
 *
 * Progressivo: sem JS a grade aparece inteira. O HTML vem pronto do PHP
 * (contorno_render_native_schedule); aqui so se mostra/esconde.
 */
(function () {
	'use strict';

	function init(root) {
		var chips = root.querySelectorAll('.contorno-schedule__chip');
		var tabs = root.querySelectorAll('.contorno-schedule__tab');
		var days = root.querySelectorAll('.contorno-schedule__day');
		var tabBar = root.querySelector('.contorno-schedule__tabs');
		var period = 'all';

		function applyPeriod() {
			days.forEach(function (day) {
				var visible = 0;

				day.querySelectorAll('.contorno-schedule__class').forEach(function (item) {
					var show = period === 'all' || item.dataset.period === period;
					item.hidden = !show;
					visible += show ? 1 : 0;
				});

				var empty = day.querySelector('.contorno-schedule__empty');
				if (empty) {
					empty.hidden = visible > 0;
				}
			});

			chips.forEach(function (chip) {
				chip.setAttribute('aria-pressed', chip.dataset.period === period ? 'true' : 'false');
			});
		}

		function selectDay(n) {
			tabs.forEach(function (tab) {
				tab.setAttribute('aria-pressed', tab.dataset.day === n ? 'true' : 'false');
			});
			days.forEach(function (day) {
				day.classList.toggle('is-active', day.dataset.day === n);
			});
		}

		chips.forEach(function (chip) {
			chip.addEventListener('click', function () {
				period = chip.dataset.period || 'all';
				applyPeriod();
			});
		});

		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () {
				selectDay(tab.dataset.day);
			});
		});

		var today = root.querySelector('.contorno-schedule__tab[data-today]');
		selectDay(today ? today.dataset.day : '1');

		if (tabBar) {
			tabBar.hidden = false;
		}

		root.classList.add('is-enhanced');
		applyPeriod();
	}

	function boot() {
		document.querySelectorAll('[data-contorno-schedule]').forEach(init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
