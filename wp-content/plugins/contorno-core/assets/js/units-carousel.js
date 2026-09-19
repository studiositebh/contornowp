/**
 * Carrossel mobile — unidades (UMA por vez) e planos (1 ou 2 por tela).
 *
 * O swipe e o snapping sao nativos (scroll-snap no CSS). Este script cuida
 * das setas, dos indicadores e da sincronia com o filtro de busca: slides
 * escondidos pelo filtro saem da contagem de indicadores.
 *
 * Slides: [data-contorno-carousel-slide] ou .contorno-units__slide (legado).
 * Quando o trilho nao rola (desktop em grade, poucos itens), o root recebe
 * .is-static e o CSS esconde os controles.
 */
(function () {
	'use strict';

	function setupCarousel(root) {
		if (root.dataset.contornoCarouselReady === '1') {
			return;
		}
		root.dataset.contornoCarouselReady = '1';

		var track = root.querySelector('[data-contorno-carousel-track]');
		var dotsWrap = root.querySelector('[data-contorno-carousel-dots]');
		var prev = root.querySelector('[data-contorno-carousel-prev]');
		var next = root.querySelector('[data-contorno-carousel-next]');

		if (!track) {
			return;
		}

		var dotClass = (dotsWrap && dotsWrap.dataset.dotClass) || 'contorno-units__dot';
		var dotLabel = (dotsWrap && dotsWrap.dataset.dotLabel) || 'Ir para unidade';

		function visibleSlides() {
			return Array.prototype.filter.call(
				track.querySelectorAll('[data-contorno-carousel-slide], .contorno-units__slide'),
				function (slide) {
					return !slide.hidden;
				}
			);
		}

		function currentIndex() {
			var slides = visibleSlides();
			var left = track.scrollLeft;
			var best = 0;
			var bestDistance = Infinity;

			slides.forEach(function (slide, index) {
				var distance = Math.abs(slide.offsetLeft - track.offsetLeft - left);
				if (distance < bestDistance) {
					bestDistance = distance;
					best = index;
				}
			});

			return best;
		}

		function scrollToIndex(index) {
			var slides = visibleSlides();
			if (!slides.length) {
				return;
			}
			var clamped = Math.max(0, Math.min(index, slides.length - 1));
			track.scrollTo({
				left: slides[clamped].offsetLeft - track.offsetLeft,
				behavior: 'smooth'
			});
		}

		function renderDots() {
			if (!dotsWrap) {
				return;
			}

			var slides = visibleSlides();
			var active = currentIndex();

			dotsWrap.textContent = '';

			if (slides.length < 2) {
				return;
			}

			slides.forEach(function (slide, index) {
				var dot = document.createElement('button');
				dot.type = 'button';
				dot.className = dotClass + (index === active ? ' is-active' : '');
				dot.setAttribute('role', 'tab');
				dot.setAttribute('aria-selected', index === active ? 'true' : 'false');
				dot.setAttribute('aria-label', dotLabel + ' ' + (index + 1));
				dot.addEventListener('click', function () {
					scrollToIndex(index);
				});
				dotsWrap.appendChild(dot);
			});
		}

		function syncArrows() {
			var slides = visibleSlides();
			var active = currentIndex();

			if (prev) {
				prev.disabled = active <= 0;
			}
			if (next) {
				next.disabled = active >= slides.length - 1;
			}
		}

		function sync() {
			root.classList.toggle('is-static', track.scrollWidth <= track.clientWidth + 1);
			renderDots();
			syncArrows();
		}

		if (prev) {
			prev.addEventListener('click', function () {
				scrollToIndex(currentIndex() - 1);
			});
		}

		if (next) {
			next.addEventListener('click', function () {
				scrollToIndex(currentIndex() + 1);
			});
		}

		var scrollTimer = null;
		track.addEventListener('scroll', function () {
			window.clearTimeout(scrollTimer);
			scrollTimer = window.setTimeout(sync, 90);
		});

		// Apos busca/filtro: voltar ao primeiro slide valido.
		root.addEventListener('contorno:units-filtered', function () {
			track.scrollTo({ left: 0, behavior: 'auto' });
			sync();
		});

		window.addEventListener('resize', sync);
		sync();
	}

	function init() {
		Array.prototype.forEach.call(
			document.querySelectorAll('[data-contorno-carousel]'),
			setupCarousel
		);
	}

	if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}

	document.addEventListener('contorno:refresh', init);
})();
