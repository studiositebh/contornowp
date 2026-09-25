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
			var remove = wrapper.querySelector('[data-contorno-media-remove]');
			var input = wrapper.querySelector('[data-contorno-media-input]');
			var preview = wrapper.querySelector('[data-contorno-media-preview]');
			var placeholder = wrapper.querySelector('[data-contorno-media-placeholder]');

			if (!button || !input) {
				return;
			}

			function setEmpty() {
				input.value = '';
				if (preview) {
					preview.src = '';
					preview.hidden = true;
				}
				if (placeholder) {
					placeholder.hidden = false;
				}
				if (remove) {
					remove.hidden = true;
				}
				button.textContent = 'Selecionar imagem';
			}

			function setSelected(src) {
				if (preview) {
					preview.src = src;
					preview.hidden = false;
				}
				if (placeholder) {
					placeholder.hidden = true;
				}
				if (remove) {
					remove.hidden = false;
				}
				button.textContent = 'Trocar imagem';
			}

			button.addEventListener('click', function (event) {
				event.preventDefault();

				if (!window.wp || !window.wp.media) {
					return;
				}

				var frame = window.wp.media({
					title: 'Selecionar imagem',
					button: { text: 'Usar esta imagem' },
					multiple: false,
					library: { type: 'image' }
				});

				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();

					input.value = attachment.id;

					var src =
						attachment.sizes && attachment.sizes.medium
							? attachment.sizes.medium.url
							: attachment.url;
					setSelected(src);
				});

				frame.open();
			});

			if (remove) {
				remove.addEventListener('click', function (event) {
					event.preventDefault();
					setEmpty();
				});
			}
		});
	}

	/* ---------------------------------------------------------------
	 * Galeria: multi-selecao na Biblioteca de Midia + arrastar pra
	 * reordenar. O nome de cada input escondido e sempre o mesmo
	 * (campo[]), entao a ordem no DOM e a ordem que sera salva.
	 * ------------------------------------------------------------- */

	function bindGallery(scope) {
		var galleries = scope.querySelectorAll('[data-contorno-gallery]');

		Array.prototype.forEach.call(galleries, function (gallery) {
			if (gallery.dataset.contornoGalleryReady === '1') {
				return;
			}
			gallery.dataset.contornoGalleryReady = '1';

			var grid = gallery.querySelector('[data-contorno-gallery-items]');
			var add = gallery.querySelector('[data-contorno-gallery-add]');
			var template = gallery.querySelector('[data-contorno-gallery-template]');

			if (!grid || !add || !template) {
				return;
			}

			function bindItem(item) {
				var removeBtn = item.querySelector('[data-contorno-gallery-remove]');

				if (removeBtn && removeBtn.dataset.contornoBound !== '1') {
					removeBtn.dataset.contornoBound = '1';
					removeBtn.addEventListener('click', function (event) {
						event.preventDefault();
						item.remove();
					});
				}
			}

			Array.prototype.forEach.call(
				grid.querySelectorAll('[data-contorno-gallery-item]'),
				bindItem
			);

			add.addEventListener('click', function (event) {
				event.preventDefault();

				if (!window.wp || !window.wp.media) {
					return;
				}

				var frame = window.wp.media({
					title: 'Adicionar imagens',
					button: { text: 'Adicionar à galeria' },
					multiple: true,
					library: { type: 'image' }
				});

				frame.on('select', function () {
					var attachments = frame.state().get('selection').toArray();

					attachments.forEach(function (attachment) {
						var data = attachment.toJSON();
						var src =
							(data.sizes && data.sizes.thumbnail
								? data.sizes.thumbnail.url
								: data.url) || '';

						var html = template.innerHTML
							.split('__ID__')
							.join(String(data.id))
							.split('__URL__')
							.join(src);

						var holder = document.createElement('div');
						holder.innerHTML = html;

						var item = holder.firstElementChild;

						if (!item) {
							return;
						}

						grid.appendChild(item);
						bindItem(item);
					});
				});

				frame.open();
			});

			// Arrastar para reordenar — mesma tecnica do catalogo de atributos.
			var dragging = null;

			grid.addEventListener('dragstart', function (event) {
				var item = event.target.closest('[data-contorno-gallery-item]');

				if (!item) {
					return;
				}

				dragging = item;
				item.classList.add('is-dragging');

				if (event.dataTransfer) {
					event.dataTransfer.effectAllowed = 'move';
					event.dataTransfer.setData('text/plain', '');
				}
			});

			grid.addEventListener('dragover', function (event) {
				if (!dragging) {
					return;
				}

				event.preventDefault();

				var over = event.target.closest('[data-contorno-gallery-item]');

				if (!over || over === dragging) {
					return;
				}

				var box = over.getBoundingClientRect();
				var after = event.clientX > box.left + box.width / 2;

				grid.insertBefore(dragging, after ? over.nextSibling : over);
			});

			function stopDragging() {
				if (dragging) {
					dragging.classList.remove('is-dragging');
					dragging = null;
				}
			}

			grid.addEventListener('drop', function (event) {
				event.preventDefault();
				stopDragging();
			});

			grid.addEventListener('dragend', stopDragging);
		});
	}

	/* ---------------------------------------------------------------
	 * Mascaras: CEP, telefone/WhatsApp, coordenadas.
	 *
	 * So cosmetico — quem realmente garante o formato gravado e o
	 * saneamento no PHP (contorno_sanitize_field(), tipos 'cep',
	 * 'phone' e 'coordinate'). Colar com ou sem pontuacao funciona
	 * nos dois lados.
	 * ------------------------------------------------------------- */

	function maskCep(digits) {
		digits = digits.slice(0, 8);
		return digits.length > 5 ? digits.slice(0, 5) + '-' + digits.slice(5) : digits;
	}

	function bindCepMask(scope) {
		var inputs = scope.querySelectorAll('[data-contorno-cep]');

		Array.prototype.forEach.call(inputs, function (input) {
			if (input.dataset.contornoBound === '1') {
				return;
			}
			input.dataset.contornoBound = '1';

			input.addEventListener('input', function () {
				var digits = input.value.replace(/\D/g, '');
				input.value = maskCep(digits);
			});
		});
	}

	function maskPhone(digits) {
		digits = digits.slice(0, 11);

		if (digits.length === 0) {
			return '';
		}

		if (digits.length <= 2) {
			return '(' + digits;
		}

		var ddd = digits.slice(0, 2);
		var rest = digits.slice(2);

		// 11 digitos = celular (5+4); ate 10 = fixo (4+4). So vira 5+4 quando
		// o resto (sem DDD) chega a 9 digitos — antes disso pode ainda virar
		// um fixo de 10, entao mante o agrupamento 4+4.
		var splitAt = rest.length >= 9 ? 5 : 4;

		if (rest.length <= splitAt) {
			return '(' + ddd + ') ' + rest;
		}

		return '(' + ddd + ') ' + rest.slice(0, splitAt) + '-' + rest.slice(splitAt);
	}

	function bindPhoneMask(scope) {
		var inputs = scope.querySelectorAll('[data-contorno-phone]');

		Array.prototype.forEach.call(inputs, function (input) {
			if (input.dataset.contornoBound === '1') {
				return;
			}
			input.dataset.contornoBound = '1';

			input.addEventListener('input', function () {
				var digits = input.value.replace(/\D/g, '');
				input.value = maskPhone(digits);
			});
		});
	}

	/**
	 * Coordenadas nao tem mascara rigida (o formato final e decidido no
	 * PHP ao salvar) — so filtra, na digitacao, o que nunca poderia fazer
	 * parte de uma coordenada: letras e mais de um separador decimal.
	 */
	function bindCoordinateInput(scope) {
		var inputs = scope.querySelectorAll('[data-contorno-coordinate]');

		Array.prototype.forEach.call(inputs, function (input) {
			if (input.dataset.contornoBound === '1') {
				return;
			}
			input.dataset.contornoBound = '1';

			input.addEventListener('input', function () {
				var value = input.value.replace(/[^0-9,.\-]/g, '');
				var negative = value.charAt(0) === '-';
				value = value.replace(/-/g, '');
				var separator = value.match(/[,.]/);
				if (separator) {
					var index = value.indexOf(separator[0]);
					value =
						value.slice(0, index + 1) + value.slice(index + 1).replace(/[,.]/g, '');
				}
				input.value = (negative ? '-' : '') + value;
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
	 * Seletor de icone/imagem de um atributo.
	 *
	 * A biblioteca (~1800 icones Lucide, gerada localmente — nunca via CDN)
	 * so e buscada (uma vez, reaproveitada por todos os pickers da tela)
	 * quando o popover abre pela primeira vez; o grid so mostra os
	 * resultados da busca atual (no maximo 60), nunca a lista inteira.
	 */

	// Termos em portugues -> chave/termo em ingles, pra busca tambem achar
	// "wifi", "carro", "chuveiro" etc. mesmo a biblioteca sendo so em ingles.
	var ICON_PT_ALIASES = {"wifi":["wifi","wi-fi","internet"],"car":["carro","estacionamento","vaga"],"parking-circle":["estacionamento","vaga","carro"],"parking-square":["estacionamento","vaga","carro"],"bus":["onibus","transporte"],"bike":["bicicleta","bike","ciclismo"],"shower-head":["chuveiro","ducha","vestiario","banho"],"droplets":["agua","chuveiro","hidratacao"],"dumbbell":["musculacao","peso","halter","academia"],"heart-pulse":["coracao","cardio","saude","batimento"],"heart":["coracao","saude","favorito"],"accessibility":["acessibilidade","cadeirante","cadeira de rodas","pcd"],"person-standing":["pessoa","acessibilidade"],"coffee":["cafe","cafeteria","lanchonete"],"baby":["crianca","bebe","infantil"],"baby-carriage":["crianca","bebe","carrinho"],"hand":["massagem","mao","toque"],"wind":["ar condicionado","climatizacao","ventilacao","vento"],"snowflake":["ar condicionado","climatizacao","gelo","frio"],"air-vent":["ar condicionado","climatizacao","ventilacao"],"shield":["seguranca","protecao"],"shield-check":["seguranca","protecao","verificado"],"lock":["seguranca","cadeado","trava"],"camera":["seguranca","camera","cftv","monitoramento"],"key":["chave","acesso","armario"],"archive":["armario","guarda-volumes"],"package":["armario","guarda-volumes","caixa"],"waves":["piscina","natacao","agua"],"flame":["sauna","calor","fogo"],"thermometer":["temperatura","sauna","clima"],"users":["aula","coletiva","equipe","grupo","turma"],"user-round":["personal","pessoa","usuario"],"utensils":["nutricao","alimentacao","restaurante"],"apple":["nutricao","alimentacao","saude"],"clock":["horario","tempo","relogio"],"calendar":["agenda","horario","calendario"],"map-pin":["localizacao","endereco","mapa"],"map":["mapa","localizacao"],"music":["som","musica","sonorizacao"],"headphones":["som","fone","musica","audio"],"tv":["tv","televisao","tela"],"monitor":["tela","monitor","tv"],"thermometer-snowflake":["ar condicionado","climatizacao"],"sun":["sol","luz natural","claridade"],"moon":["noite","24 horas"],"sun-moon":["24 horas","dia e noite"],"leaf":["natureza","sustentabilidade","ecologico"],"trees":["natureza","area verde","externo"],"building":["predio","estrutura","unidade"],"building2":["predio","estrutura","unidade"],"home":["casa","inicio"],"door-open":["porta","entrada","acesso"],"shirt":["roupa","vestiario"],"footprints":["corrida","caminhada","passos"],"timer":["cronometro","tempo","treino"],"scan-face":["catraca","biometria","reconhecimento facial","acesso"],"fingerprint":["catraca","biometria","acesso","digital"],"credit-card":["pagamento","cartao"],"scale":["balanca","avaliacao","peso corporal"],"medal":["premio","conquista","resultado"],"trophy":["premio","conquista","resultado"],"crown":["premium","vip","exclusivo"],"gift":["presente","brinde","bonus"],"bell":["sino","notificacao","aviso"],"star":["estrela","destaque","avaliacao"],"check":["confirmado","incluso","ok"],"circle-parking":["estacionamento","vaga","carro"],"bath":["banheiro","banho"],"toilet":["banheiro","sanitario"],"refrigerator":["bebedouro","agua gelada","geladeira"],"cup-soda":["bebida","agua","hidratacao"],"activity":["funcional","atividade","cardio"],"gauge":["performance","medidor"],"route":["esteira","corrida","percurso"]};

	var iconLibraryPromise = null;

	function loadIconLibrary() {
		if (!iconLibraryPromise) {
			var url =
				(window.contornoAdminFields && window.contornoAdminFields.iconLibraryUrl) || '';

			iconLibraryPromise = url
				? fetch(url)
						.then(function (response) {
							return response.ok ? response.json() : [];
						})
						.catch(function () {
							return [];
						})
				: Promise.resolve([]);
		}

		return iconLibraryPromise;
	}

	function normalizeIconTerm(value) {
		return String(value || '')
			.toLowerCase()
			.normalize('NFD')
			.replace(/[̀-ͯ]/g, '')
			.trim();
	}

	// A busca tambem casa pelos aliases em portugues: "chuveiro" encontra
	// tanto o icone chamado literalmente "chuveiro" (se existir) quanto
	// "shower-head" (via o dicionario acima).
	function expandIconQuery(query) {
		var terms = [query];

		Object.keys(ICON_PT_ALIASES).forEach(function (key) {
			var aliases = ICON_PT_ALIASES[key];

			for (var i = 0; i < aliases.length; i++) {
				if (normalizeIconTerm(aliases[i]).indexOf(query) !== -1) {
					terms.push(key);
					terms.push(key.replace(/-/g, ' '));
					break;
				}
			}
		});

		return terms;
	}

	function searchIconLibrary(library, query) {
		var normalized = normalizeIconTerm(query);

		if ('' === normalized) {
			return [];
		}

		var terms = expandIconQuery(normalized);
		var results = [];

		for (var i = 0; i < library.length && results.length < 60; i++) {
			var icon = library[i];
			var haystack = normalizeIconTerm(
				icon.k + ' ' + icon.l + ' ' + (icon.t || []).join(' ')
			);

			for (var t = 0; t < terms.length; t++) {
				if (haystack.indexOf(terms[t]) !== -1) {
					results.push(icon);
					break;
				}
			}
		}

		return results;
	}

	// Grid inicial ao abrir o popover — antes de qualquer busca. Cerca de
	// 30 icones relevantes pra academia/localizacao, pra nunca abrir
	// mostrando so a busca vazia.
	var POPULAR_ICONS = [
		'wifi', 'car', 'parking-circle', 'bike', 'shower-head', 'dumbbell',
		'heart-pulse', 'accessibility', 'coffee', 'baby', 'hand', 'wind',
		'shield', 'camera', 'key', 'archive', 'waves', 'flame', 'users',
		'clock', 'calendar', 'map-pin', 'music', 'headphones', 'tv', 'sun',
		'leaf', 'building', 'home', 'footprints', 'scale', 'medal', 'star',
		'check', 'bath'
	];

	function renderIconResults(container, icons, emptyMessage, selectedKey) {
		if (!icons.length) {
			container.innerHTML = '<p class="description">' + emptyMessage + '</p>';
			return;
		}

		container.innerHTML = icons
			.map(function (icon) {
				return (
					'<button type="button" class="contorno-icon-picker__result' +
					(icon.k === selectedKey ? ' is-selected' : '') +
					'" data-icon-key="' +
					icon.k +
					'" title="' +
					icon.l +
					'">' +
					iconSvgMarkup(icon.s, 'contorno-icon-picker__result-svg') +
					'</button>'
				);
			})
			.join('');
	}

	function iconSvgMarkup(inner, classes) {
		return (
			'<svg class="' +
			classes +
			'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" ' +
			'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' +
			inner +
			'</svg>'
		);
	}

	function bindIconPickers(scope) {
		var pickers = scope.querySelectorAll('[data-contorno-icon-picker]');

		Array.prototype.forEach.call(pickers, function (picker) {
			if (picker.dataset.contornoIconReady === '1') {
				return;
			}
			picker.dataset.contornoIconReady = '1';

			var iconValue = picker.querySelector('[data-contorno-icon-value]');
			var typeValue = picker.querySelector('[data-contorno-icon-type-value]');
			var toggle = picker.querySelector('[data-contorno-icon-toggle]');
			var popover = picker.querySelector('[data-contorno-icon-popover]');
			var search = picker.querySelector('[data-contorno-icon-search]');
			var results = picker.querySelector('[data-contorno-icon-results]');
			var preview = picker.querySelector('[data-contorno-icon-preview]');
			var currentName = picker.querySelector('[data-contorno-icon-current-name]');
			var tabs = picker.querySelectorAll('[data-contorno-icon-tab]');
			var iconPanel = picker.querySelector('[data-contorno-icon-panel]');
			var imagePanel = picker.querySelector('[data-contorno-icon-image-panel]');

			function setTab(which) {
				if (typeValue) {
					typeValue.value = which;
				}

				Array.prototype.forEach.call(tabs, function (tab) {
					tab.classList.toggle(
						'is-active',
						tab.getAttribute('data-contorno-icon-tab') === which
					);
				});

				if (iconPanel) {
					iconPanel.hidden = 'icon' !== which;
				}
				if (imagePanel) {
					imagePanel.hidden = 'image' !== which;
				}
			}

			Array.prototype.forEach.call(tabs, function (tab) {
				tab.addEventListener('click', function (event) {
					event.preventDefault();
					setTab(tab.getAttribute('data-contorno-icon-tab'));
				});
			});

			if (toggle && popover) {
				toggle.addEventListener('click', function (event) {
					event.preventDefault();

					var willOpen = popover.hidden;
					popover.hidden = !willOpen;
					toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

					if (willOpen) {
						if (search) {
							search.focus();
						}

						// Grid inicial: mostra logo, nao so depois de digitar.
						if (results && !results.dataset.contornoFilled) {
							results.innerHTML = '<p class="description">Carregando…</p>';
							loadIconLibrary().then(function (library) {
								results.dataset.contornoFilled = '1';
								var byKey = {};
								library.forEach(function (icon) {
									byKey[icon.k] = icon;
								});
								var popular = POPULAR_ICONS.map(function (key) {
									return byKey[key];
								}).filter(Boolean);
								renderIconResults(results, popular, 'Nada encontrado.', iconValue ? iconValue.value : '');
							});
						}
					}
				});

				document.addEventListener('click', function (event) {
					if (!picker.contains(event.target)) {
						popover.hidden = true;
						toggle.setAttribute('aria-expanded', 'false');
					}
				});
			}

			if (search && results) {
				search.addEventListener('input', function () {
					var query = search.value;

					loadIconLibrary().then(function (library) {
						results.dataset.contornoFilled = '1';

						if ('' === normalizeIconTerm(query)) {
							var byKey = {};
							library.forEach(function (icon) {
								byKey[icon.k] = icon;
							});
							var popular = POPULAR_ICONS.map(function (key) {
								return byKey[key];
							}).filter(Boolean);
							renderIconResults(results, popular, 'Nada encontrado.', iconValue ? iconValue.value : '');
							return;
						}

						renderIconResults(results, searchIconLibrary(library, query), 'Nada encontrado.', iconValue ? iconValue.value : '');
					});
				});

				results.addEventListener('click', function (event) {
					var button = event.target.closest('[data-icon-key]');

					if (!button) {
						return;
					}

					event.preventDefault();

					var key = button.getAttribute('data-icon-key');

					if (iconValue) {
						iconValue.value = key;
					}
					if (currentName) {
						currentName.textContent = key;
					}
					if (preview) {
						var svg = button.querySelector('svg');
						preview.innerHTML = svg ? svg.outerHTML : '';
					}

					Array.prototype.forEach.call(
						results.querySelectorAll('.contorno-icon-picker__result.is-selected'),
						function (el) {
							el.classList.remove('is-selected');
						}
					);
					button.classList.add('is-selected');

					if (popover) {
						popover.hidden = true;
					}
					if (toggle) {
						toggle.setAttribute('aria-expanded', 'false');
					}
				});
			}
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

	/**
	 * Contador de caracteres abaixo de campos com maxlength (nome curto,
	 * selo, descricao curta, titulo/descricao SEO). O limite real e o
	 * atributo maxlength; isto so mantem o "N/limite" atualizado.
	 */
	function bindCharCounters(scope) {
		var inputs = scope.querySelectorAll('[data-contorno-counter]');

		Array.prototype.forEach.call(inputs, function (input) {
			if (input.dataset.contornoBound === '1') {
				return;
			}
			input.dataset.contornoBound = '1';

			var wrap = input.closest('.contorno-field');
			var label = wrap ? wrap.querySelector('[data-contorno-counter-label] span') : null;

			if (!label) {
				return;
			}

			input.addEventListener('input', function () {
				label.textContent = String(input.value.length);
			});
		});
	}

	/**
	 * Campos condicionais (ex.: os de pre-venda so aparecem quando Status =
	 * Pre-venda). So esconde/mostra — o campo continua no formulario e no
	 * POST, entao trocar o status de volta e pra frente nunca apaga o que
	 * ja estava preenchido.
	 */
	function bindConditionalFields(scope) {
		var conditionals = scope.querySelectorAll('[data-contorno-conditional-field]');

		if (!conditionals.length) {
			return;
		}

		var byControllingField = {};

		Array.prototype.forEach.call(conditionals, function (field) {
			var name = field.getAttribute('data-contorno-conditional-field');
			(byControllingField[name] = byControllingField[name] || []).push(field);
		});

		Object.keys(byControllingField).forEach(function (name) {
			var controller = scope.querySelector('[name="contorno[' + name + ']"]');

			if (!controller) {
				return;
			}

			function apply() {
				var current = controller.value;

				byControllingField[name].forEach(function (field) {
					var values = (field.getAttribute('data-contorno-conditional-values') || '').split(',');
					field.hidden = values.indexOf(current) === -1;
				});
			}

			if (controller.dataset.contornoBound !== '1') {
				controller.dataset.contornoBound = '1';
				controller.addEventListener('change', apply);
			}

			apply();
		});
	}

	/**
	 * Mascara monetaria BR (R$ 1.299,90) enquanto digita. So cosmetico —
	 * contorno_sanitize_field() (type=money) normaliza de verdade no
	 * backend, aceitando com ou sem "R$", com ou sem os pontos de milhar.
	 * "R$" nunca e o que fica gravado.
	 */
	function formatMoneyLive(raw) {
		// Mantem so digitos e a PRIMEIRA virgula digitada.
		var value = String(raw || '').replace(/[^\d,]/g, '');
		var comma = value.indexOf(',');

		if (comma !== -1) {
			value = value.slice(0, comma + 1) + value.slice(comma + 1).replace(/,/g, '');
		}

		var parts = value.split(',');
		var intPart = parts[0].replace(/^0+(?=\d)/, '');
		var centsPart = parts.length > 1 ? parts[1].slice(0, 2) : null;
		var withThousands = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
		var result = 'R$ ' + (withThousands || (centsPart !== null ? '0' : ''));

		if (centsPart !== null) {
			result += ',' + centsPart;
		}

		return 'R$ ' === result ? '' : result;
	}

	// No blur, sempre fecha em duas casas — "R$ 99" vira "R$ 99,00", "R$
	// 99,9" vira "R$ 99,90". Enquanto digita nao mexe nas casas (deixaria
	// de dar pra digitar o segundo centavo).
	function formatMoneyFinal(raw) {
		var digits = String(raw || '').replace(/[^\d,]/g, '');

		if ('' === digits) {
			return '';
		}

		var parts = digits.split(',');
		var intPart = parts[0].replace(/^0+(?=\d)/, '') || '0';
		var cents = parts.length > 1 ? (parts[1] + '00').slice(0, 2) : '00';
		var withThousands = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

		return 'R$ ' + withThousands + ',' + cents;
	}

	function bindMoneyMask(scope) {
		var inputs = scope.querySelectorAll('[data-contorno-money]');

		Array.prototype.forEach.call(inputs, function (input) {
			if (input.dataset.contornoBound === '1') {
				return;
			}
			input.dataset.contornoBound = '1';

			input.addEventListener('input', function () {
				input.value = formatMoneyLive(input.value);
			});

			input.addEventListener('blur', function () {
				input.value = formatMoneyFinal(input.value);
			});
		});
	}

	function init() {
		bindMediaPicker(document);
		bindGallery(document);
		bindRepeaters(document);
		bindAttributeSearch(document);
		bindIconPickers(document);
		bindSortable(document);
		bindCepMask(document);
		bindPhoneMask(document);
		bindCoordinateInput(document);
		bindCharCounters(document);
		bindConditionalFields(document);
		bindMoneyMask(document);
	}

	if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}
})();
