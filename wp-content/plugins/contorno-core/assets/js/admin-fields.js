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

	/**
	 * Filtro da grade de atributos da unidade: esconde o que nao casa com o
	 * texto digitado. So mexe na exibicao — nenhum checkbox e alterado.
	 */
	function bindAttributeSearch(scope) {
		var boxes = scope.querySelectorAll('[data-contorno-attributes]');

		Array.prototype.forEach.call(boxes, function (box) {
			var input = box.querySelector('[data-contorno-attributes-search]');
			var items = box.querySelectorAll('[data-contorno-attributes-item]');

			if (!input) {
				return;
			}

			input.addEventListener('input', function () {
				var needle = input.value
					.toLowerCase()
					.normalize('NFD')
					.replace(/[^a-z0-9]+/g, '');

				Array.prototype.forEach.call(items, function (item) {
					var haystack = item.getAttribute('data-search') || '';
					item.hidden = needle !== '' && haystack.indexOf(needle) === -1;
				});
			});
		});
	}

	/**
	 * Seletor visual de icone do catalogo: abre a grade, marca o radio e
	 * atualiza a previa. O valor e sempre uma chave da allowlist do PHP.
	 */
	function bindIconPickers(scope) {
		var pickers = scope.querySelectorAll('[data-contorno-icon-picker]');

		Array.prototype.forEach.call(pickers, function (picker) {
			var toggle = picker.querySelector('[data-contorno-icon-toggle]');
			var grid = picker.querySelector('.contorno-icon-picker__grid');
			var preview = picker.querySelector('[data-contorno-icon-preview]');

			if (!toggle || !grid) {
				return;
			}

			toggle.addEventListener('click', function () {
				var open = grid.hidden;
				grid.hidden = !open;
				toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			});

			grid.addEventListener('change', function (event) {
				var input = event.target;

				if (!input || input.type !== 'radio') {
					return;
				}

				var svg = input.parentNode.querySelector('svg');

				if (svg && preview) {
					preview.innerHTML = svg.outerHTML;
				}

				grid.hidden = true;
				toggle.setAttribute('aria-expanded', 'false');
			});

			document.addEventListener('click', function (event) {
				if (!picker.contains(event.target)) {
					grid.hidden = true;
					toggle.setAttribute('aria-expanded', 'false');
				}
			});
		});
	}

	/**
	 * Ordenacao do catalogo por arrastar e soltar.
	 *
	 * A ordem e a posicao da linha: depois de mover, os campos ocultos sao
	 * renumerados de 10 em 10 e o formulario e salvo normalmente. Tambem
	 * responde a setas do teclado, para nao depender do mouse.
	 */
	function bindSortable(scope) {
		var bodies = scope.querySelectorAll('[data-contorno-sortable]');

		Array.prototype.forEach.call(bodies, function (body) {
			var dragging = null;

			function renumber() {
				var rows = body.querySelectorAll('[data-contorno-sortable-row]');

				Array.prototype.forEach.call(rows, function (row, index) {
					var field = row.querySelector('[data-contorno-sort-order]');

					if (field) {
						field.value = String((index + 1) * 10);
					}
				});
			}

			function move(row, delta) {
				var sibling = delta < 0 ? row.previousElementSibling : row.nextElementSibling;

				if (!sibling || !sibling.hasAttribute('data-contorno-sortable-row')) {
					return;
				}

				if (delta < 0) {
					body.insertBefore(row, sibling);
				} else {
					body.insertBefore(sibling, row);
				}

				renumber();

				var handle = row.querySelector('[data-contorno-sort-handle]');

				if (handle) {
					handle.focus();
				}
			}

			body.addEventListener('dragstart', function (event) {
				var row = event.target.closest('[data-contorno-sortable-row]');

				if (!row) {
					return;
				}

				dragging = row;
				row.classList.add('is-dragging');

				if (event.dataTransfer) {
					event.dataTransfer.effectAllowed = 'move';
					// Firefox so inicia o arrasto se houver dados.
					event.dataTransfer.setData('text/plain', '');
				}
			});

			body.addEventListener('dragover', function (event) {
				if (!dragging) {
					return;
				}

				event.preventDefault();

				var over = event.target.closest('[data-contorno-sortable-row]');

				if (!over || over === dragging) {
					return;
				}

				var box = over.getBoundingClientRect();
				var after = event.clientY > box.top + box.height / 2;

				body.insertBefore(dragging, after ? over.nextSibling : over);
			});

			body.addEventListener('drop', function (event) {
				if (!dragging) {
					return;
				}

				event.preventDefault();
				dragging.classList.remove('is-dragging');
				dragging = null;
				renumber();
			});

			body.addEventListener('dragend', function () {
				if (!dragging) {
					return;
				}

				dragging.classList.remove('is-dragging');
				dragging = null;
				renumber();
			});

			body.addEventListener('keydown', function (event) {
				if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') {
					return;
				}

				var handle = event.target.closest('[data-contorno-sort-handle]');

				if (!handle) {
					return;
				}

				event.preventDefault();
				move(handle.closest('[data-contorno-sortable-row]'), event.key === 'ArrowUp' ? -1 : 1);
			});
		});
	}

	function init() {
		bindMediaPicker(document);
		bindRepeaters(document);
		bindAttributeSearch(document);
		bindIconPickers(document);
		bindSortable(document);
	}

	if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}
})();
