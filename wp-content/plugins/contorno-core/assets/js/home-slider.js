/**
 * CONTORNO — Slider da Home.
 *
 * Slides ficam empilhados (CSS) e trocam so por opacity — este script apenas
 * alterna a classe is-active, sem nenhuma biblioteca externa. Autoplay pausa
 * no hover/foco e ao sair da aba. Sem JS, o primeiro slide (is-active vindo
 * do PHP) continua visivel, parado.
 */
(function () {
	'use strict';

	function setupSlider(root) {
		if (root.dataset.contornoHomeSliderReady === '1') {
			return;
		}
		root.dataset.contornoHomeSliderReady = '1';

		var slides = Array.prototype.slice.call(
			root.querySelectorAll('[data-contorno-home-slider-slide]')
		);
		var dots = Array.prototype.slice.call(
			root.querySelectorAll('[data-contorno-home-slider-dot]')
		);

		if (slides.length < 2) {
			return;
		}

		var interval = parseInt(root.dataset.interval, 10) || 6000;
		var index = 0;
		var timer = null;

		function goTo(next) {
			index = (next + slides.length) % slides.length;

			slides.forEach(function (slide, i) {
				slide.classList.toggle('is-active', i === index);
				slide.setAttribute('aria-hidden', i === index ? 'false' : 'true');
			});

			dots.forEach(function (dot, i) {
				dot.classList.toggle('is-active', i === index);
			});
		}

		function start() {
			stop();
			timer = window.setInterval(function () {
				goTo(index + 1);
			}, interval);
		}

		function stop() {
			if (timer) {
				window.clearInterval(timer);
				timer = null;
			}
		}

		dots.forEach(function (dot, i) {
			dot.addEventListener('click', function () {
				goTo(i);
				start();
			});
		});

		root.addEventListener('mouseenter', stop);
		root.addEventListener('mouseleave', start);
		root.addEventListener('focusin', stop);
		root.addEventListener('focusout', start);

		document.addEventListener('visibilitychange', function () {
			if (document.hidden) {
				stop();
			} else {
				start();
			}
		});

		start();
	}

	function init() {
		Array.prototype.forEach.call(
			document.querySelectorAll('[data-contorno-home-slider]'),
			setupSlider
		);
	}

	if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}
})();
