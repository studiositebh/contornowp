/**
 * UI dos campos estruturados no painel.
 *
 * - Seletor de imagem pela Biblioteca de Midia (wp.media), para que trocar
 *   uma foto nunca dependa de FTP, Git ou alteracao de codigo.
 * - Repeater: adicionar/remover linhas de planos, marcas, equipamentos,
 *   horarios e numeros.
 */
(function () {
	'use strict';

	/* ---------------------------------------------------------------
	 * Biblioteca de Midia
	 * ------------------------------------------------------------- */

	function bindMediaPicker(scope) {
		var wrappers = scope.querySelectorAll('[data-contorno-media]');

		Array.prototype.forEach.call(wrappers, function (wrapper) {
			if (wrapper.dataset.contornoMediaReady === '1') {
				return;
			}
			wrapper.dataset.contornoMediaReady = '1';

			var button = wrapper.querySelector('[data-contorno-media-pick]');
			var input = wrapper.querySelector('[data-contorno-media-input]');
			var preview = wrapper.querySelector('[data-contorno-media-preview]');

			if (!button || !input) {
				return;
			}

			button.addEventListener('click', function (event) {
				event.preventDefault();

				if (!window.wp || !window.wp.media) {
					return;
				}

				var frame = window.wp.media({
					title: 'Selecionar imagem',
					button: { text: 'Usar esta imagem' },
					multiple: false
				});

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();

					input.value = attachment.id;

					if (preview) {
						var src =
							attachment.sizes && attachment.sizes.medium
								? attachment.sizes.medium.url
								: attachment.url;
						preview.src = src;
						preview.hidden = false;
					}
				});

				frame.open();
			});
		});
	}

	/* ---------------------------------------------------------------
	 * Repeater
	 * ------------------------------------------------------------- */

	function nextIndex(rowsWrap) {
		return rowsWrap.querySelectorAll('[data-contorno-repeater-row]').length;
	}

	function bindRemove(row) {
		var remove = row.querySelector('[data-contorno-repeater-remove]');

		if (!remove || remove.dataset.contornoBound === '1') {
			return;
		}
		remove.dataset.contornoBound = '1';

		remove.addEventListener('click', function (event) {
			event.preventDefault();
			row.remove();
		});
	}

	function bindRepeaters(scope) {
		var repeaters = scope.querySelectorAll('[data-contorno-repeater]');

		Array.prototype.forEach.call(repeaters, function (repeater) {
			if (repeater.dataset.contornoRepeaterReady === '1') {
				return;
			}
			repeater.dataset.contornoRepeaterReady = '1';

			var rowsWrap = repeater.querySelector('[data-contorno-repeater-rows]');
			var template = repeater.querySelector('[data-contorno-repeater-template]');
			var add = repeater.querySelector('[data-contorno-repeater-add]');

			if (!rowsWrap || !template || !add) {
				return;
			}

			Array.prototype.forEach.call(
				rowsWrap.querySelectorAll('[data-contorno-repeater-row]'),
				bindRemove
			);

			add.addEventListener('click', function (event) {
				event.preventDefault();

				var html = template.innerHTML.split('__INDEX__').join(String(nextIndex(rowsWrap)));
				var holder = document.createElement('div');
				holder.innerHTML = html;

				var row = holder.firstElementChild;

				if (!row) {
					return;
				}

				rowsWrap.appendChild(row);
				bindRemove(row);
				bindMediaPicker(row);
			});
		});
	}

	function init() {
		bindMediaPicker(document);
		bindRepeaters(document);
	}

	if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}
})();
