/**
 * Lightbox das galerias — porte de UnitGalleryLightbox / CTNGalleryLightbox.
 *
 * Teclado: Esc fecha, setas navegam. Foco devolvido ao item de origem.
 */
(function () {
	'use strict';

	var overlay = null;
	var image = null;
	var sources = [];
	var index = 0;
	var origin = null;

	function build() {
		if (overlay) {
			return;
		}

		overlay = document.createElement('div');
		overlay.className = 'contorno-lightbox';
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-modal', 'true');
		overlay.setAttribute('aria-label', 'Galeria');

		image = document.createElement('img');
		image.alt = '';

		var close = document.createElement('button');
		close.type = 'button';
		close.className = 'contorno-lightbox__close';
		close.innerHTML = '&times;';
		close.setAttribute('aria-label', 'Fechar');
		close.addEventListener('click', hide);

		var prev = document.createElement('button');
		prev.type = 'button';
		prev.className = 'contorno-lightbox__btn contorno-lightbox__btn--prev';
		prev.innerHTML = '&#8249;';
		prev.setAttribute('aria-label', 'Imagem anterior');
		prev.addEventListener('click', function (event) {
			event.stopPropagation();
			step(-1);
		});

		var next = document.createElement('button');
		next.type = 'button';
		next.className = 'contorno-lightbox__btn contorno-lightbox__btn--next';
		next.innerHTML = '&#8250;';
		next.setAttribute('aria-label', 'Proxima imagem');
		next.addEventListener('click', function (event) {
			event.stopPropagation();
			step(1);
		});

		overlay.appendChild(image);
		overlay.appendChild(close);
		overlay.appendChild(prev);
		overlay.appendChild(next);

		overlay.addEventListener('click', function (event) {
			if (event.target === overlay || event.target === image) {
				hide();
			}
		});

		overlay.hidden = true;
		document.body.appendChild(overlay);
	}

	function render() {
		if (image && sources[index]) {
			image.src = sources[index];
		}
	}

	function step(delta) {
		if (!sources.length) {
			return;
		}
		index = (index + delta + sources.length) % sources.length;
		render();
	}

	function show(list, start, trigger) {
		build();
		sources = list;
		index = start;
		origin = trigger || null;
		render();
		overlay.hidden = false;
		document.body.style.overflow = 'hidden';
	}

	function hide() {
		if (!overlay) {
			return;
		}
		overlay.hidden = true;
		document.body.style.overflow = '';

		if (origin && typeof origin.focus === 'function') {
			origin.focus();
		}
		origin = null;
	}

	document.addEventListener('keydown', function (event) {
		if (!overlay || overlay.hidden) {
			return;
		}

		if (event.key === 'Escape') {
			hide();
		} else if (event.key === 'ArrowLeft') {
			step(-1);
		} else if (event.key === 'ArrowRight') {
			step(1);
		}
	});

	function setup(gallery) {
		if (gallery.dataset.contornoLightboxReady === '1') {
			return;
		}
		gallery.dataset.contornoLightboxReady = '1';

		var items = Array.prototype.slice.call(
			gallery.querySelectorAll('[data-contorno-lightbox-item]')
		);

		var list = items.map(function (item) {
			return item.dataset.src;
		});

		items.forEach(function (item, position) {
			item.addEventListener('click', function () {
				show(list, position, item);
			});
		});
	}

	function init() {
		Array.prototype.forEach.call(
			document.querySelectorAll('[data-contorno-lightbox]'),
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
