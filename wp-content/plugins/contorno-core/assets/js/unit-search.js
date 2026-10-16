/**
 * Busca de unidades no cliente — porte de filterUnits() do React.
 *
 * Casa por nome, cidade, bairro, endereco e tipo, e tambem por CEP quando o
 * termo tem 5 ou mais digitos. Um campo dentro de uma listagem filtra a
 * propria listagem; um campo de hero (data-target="hero") leva para
 * /unidades ja com o termo aplicado.
 *
 * No catalogo (/unidades), um CEP completo (8 digitos) vira busca por
 * proximidade no servidor: o campo mostra o seletor de raio e a navegacao
 * leva ?q=<cep>&raio=<km>.
 *
 * "Usar minha localizacao" (GPS): complementa a busca, nao a substitui.
 * navigator.geolocation -> Haversine contra as coordenadas publicas que o
 * proprio card entrega em data-lat/data-lng -> reordena a grade e mostra a
 * distancia. Tudo no navegador: a posicao do visitante vive so numa variavel
 * local — nao vai para URL, requisicao, storage, log nem analytics.
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

	// Mesma regra de contorno_cep_digits(): so digitos/pontuacao e 8 digitos.
	function cepDigits(value) {
		var term = String(value || '').trim();

		if (term === '' || !/^[\d.\-\s]+$/.test(term)) {
			return '';
		}

		var digits = term.replace(/\D/g, '');

		return digits.length === 8 ? digits : '';
	}

	/* ------------------------------------------------------------------
	 * Geolocalizacao: funcoes puras (sem DOM)
	 * ---------------------------------------------------------------- */

	var EARTH_KM = 6371;
	var PAGE_SIZE = 60; // maior per_page aceito pelo catalogo.
	var MAX_PAGES = 10;
	var GEO_OPTIONS = { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 };
	// Rede de seguranca: se o aviso de permissao ficar aberto sem resposta, o
	// botao volta a funcionar (o timeout nativo so conta depois da permissao).
	var GEO_GUARD_MS = 20000;

	function toRad(deg) {
		return (deg * Math.PI) / 180;
	}

	// Distancia em km em linha reta (mesma formula de contorno_distance_km()).
	function haversineKm(lat1, lng1, lat2, lng2) {
		var dLat = toRad(lat2 - lat1);
		var dLng = toRad(lng2 - lng1);
		var a =
			Math.pow(Math.sin(dLat / 2), 2) +
			Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.pow(Math.sin(dLng / 2), 2);

		return EARTH_KM * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
	}

	// Numero finito dentro de +-max; qualquer outra coisa vira null (nada e inventado).
	function parseCoord(value, max) {
		if (value === null || value === undefined || String(value).trim() === '') {
			return null;
		}

		var n = Number(value);

		return isFinite(n) && Math.abs(n) <= max ? n : null;
	}

	// {lat, lng} valido ou null; 0,0 e o "sem coordenada" do cadastro.
	function unitCoords(el) {
		var lat = parseCoord(el.getAttribute('data-lat'), 90);
		var lng = parseCoord(el.getAttribute('data-lng'), 180);

		if (lat === null || lng === null || (lat === 0 && lng === 0)) {
			return null;
		}

		return { lat: lat, lng: lng };
	}

	function groupThousands(intPart) {
		return intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
	}

	// "650 m" (arredondado a 10 m), "1,8 km", "48 km".
	function formatDistance(km) {
		if (typeof km !== 'number' || !isFinite(km) || km < 0) {
			return '';
		}

		var meters = Math.round(km * 100) * 10;

		if (meters < 1000) {
			return meters + ' m';
		}

		var fixed = km.toFixed(km < 10 ? 1 : 0).split('.');

		return groupThousands(fixed[0]) + (fixed[1] ? ',' + fixed[1] : '') + ' km';
	}

	/**
	 * Ordena entradas {coords, index}: com coordenadas por distancia crescente
	 * (empate: ordem atual); sem coordenadas ficam ao final, sem distancia.
	 */
	function sortByDistance(entries, lat, lng) {
		var ranked = entries.map(function (entry) {
			return {
				entry: entry,
				distance: entry.coords ? haversineKm(lat, lng, entry.coords.lat, entry.coords.lng) : null
			};
		});

		ranked.sort(function (a, b) {
			if (a.distance === null && b.distance === null) {
				return a.entry.index - b.entry.index;
			}
			if (a.distance === null) {
				return 1;
			}
			if (b.distance === null) {
				return -1;
			}

			return a.distance - b.distance || a.entry.index - b.entry.index;
		});

		return ranked;
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

	/* ------------------------------------------------------------------
	 * Geolocalizacao: interface
	 * ---------------------------------------------------------------- */

	var DEFAULT_TEXT = {
		idle: 'Usar minha localização',
		idleAria: 'Usar minha localização para ordenar as unidades da mais próxima para a mais distante',
		loading: 'Obtendo sua localização...',
		active: 'Localização atual ativada',
		activeAria: 'Localização atual ativada. Unidades ordenadas por distância',
		activeNote: 'Mostrando as unidades da mais próxima para a mais distante.',
		noCoords: 'Algumas unidades não têm localização cadastrada e aparecem ao final.',
		countOne: '1 unidade ordenada por distância',
		countMany: '%d unidades ordenadas por distância',
		distance: '%s de você',
		denied: 'Não foi possível acessar sua localização. Você pode buscar por CEP, bairro ou cidade.',
		unavailable: 'Não conseguimos identificar sua localização agora. Tente buscar manualmente.',
		timeout: 'A localização demorou para responder. Tente novamente ou busque manualmente.',
		unsupported: 'Seu navegador não permite usar a localização. Você pode buscar por CEP, bairro ou cidade.',
		loadError: 'Não conseguimos carregar todas as unidades agora. Tente buscar manualmente.'
	};

	function readText(geo) {
		var text = {};
		var custom = {};

		try {
			custom = JSON.parse(geo.getAttribute('data-i18n') || '{}') || {};
		} catch (e) {
			custom = {};
		}

		Object.keys(DEFAULT_TEXT).forEach(function (key) {
			text[key] = typeof custom[key] === 'string' && custom[key] !== '' ? custom[key] : DEFAULT_TEXT[key];
		});

		return text;
	}

	var GEO_HASH = '#perto-de-mim';
	var ASKED_KEY = 'contornoGeoAsked'; // so a flag "ja perguntei" — nunca a posicao.
	var modalAccept = null;

	function wasAsked() {
		try {
			return window.sessionStorage.getItem(ASKED_KEY) === '1';
		} catch (e) {
			return false;
		}
	}

	function markAsked() {
		try {
			window.sessionStorage.setItem(ASKED_KEY, '1');
		} catch (e) {
			// Sem storage o modal pode reaparecer em outra pagina; nada mais depende disso.
		}
	}

	function wireModal(modal) {
		if (modal.dataset.contornoModalReady === '1') {
			return;
		}
		modal.dataset.contornoModalReady = '1';

		modal.addEventListener('click', function (event) {
			if (event.target === modal) {
				modal.close('dismiss'); // clique no fundo escurecido.
			}
		});

		Array.prototype.forEach.call(modal.querySelectorAll('[data-contorno-geo-modal-close]'), function (btn) {
			btn.addEventListener('click', function () {
				var choice = btn.getAttribute('data-contorno-geo-modal-close');
				var accept = modalAccept;

				modalAccept = null;
				modal.close(choice);

				// Direto no clique (nao no evento "close", assincrono): mantem o gesto do usuario.
				if (choice === 'accept' && accept) {
					accept();
				}
			});
		});
	}

	function setupGeo(root, input, container, hooks) {
		var geo = root.nextElementSibling;

		if (!geo || !geo.hasAttribute('data-contorno-unit-geo')) {
			return null;
		}

		var button = geo.querySelector('[data-contorno-unit-geo-btn]');
		var label = geo.querySelector('[data-contorno-unit-geo-label]');
		var off = geo.querySelector('[data-contorno-unit-geo-off]');
		var status = geo.querySelector('[data-contorno-unit-geo-status]');

		if (!button || !label || !status) {
			return null;
		}

		var text = readText(geo);
		var isCatalog = root.dataset.target === 'catalog';
		// Sem listagem na pagina (busca do hero da Home): a permissao e pedida aqui e
		// a ordenacao acontece em /unidades, aberta com #perto-de-mim (sem posicao na URL).
		var redirectMode = !container;
		var grid = container ? container.querySelector('.contorno-units__grid') : null;
		var track = container ? container.querySelector('[data-contorno-carousel-track]') : null;

		var state ={ active: false, busy: false, token: 0 };
		var snapshot = null; // estado original da listagem, para "voltar a busca manual".
		var inserted = []; // blocos criados pelo JS (listagem que chegou vazia).
		var createdLists = false;
		var fetchedPool = null; // todas as unidades do catalogo (uma busca, sem posicao).

		geo.hidden = false;

		function say(message, isError) {
			status.textContent = message;
			status.classList.toggle('is-error', Boolean(isError));
		}

		function paint() {
			button.disabled = state.busy;
			button.setAttribute('aria-busy', state.busy ? 'true' : 'false');
			button.setAttribute('aria-pressed', state.active ? 'true' : 'false');
			button.classList.toggle('is-active', state.active);
			button.classList.toggle('is-loading', state.busy);

			if (state.busy) {
				label.textContent = text.loading;
				button.setAttribute('aria-label', text.loading);
			} else if (state.active) {
				label.textContent = text.active;
				button.setAttribute('aria-label', text.activeAria);
			} else {
				label.textContent = text.idle;
				button.setAttribute('aria-label', text.idleAria);
			}

			if (off) {
				off.hidden = !state.active || state.busy;
			}
		}

		// Desabilitar o botao durante o carregamento derruba o foco do teclado.
		function keepFocus() {
			if (document.activeElement === document.body) {
				button.focus();
			}
		}

		function fail(message) {
			state.busy = false;
			paint();
			say(message, true);
			keepFocus();
		}

		/* ---- entradas da listagem (celula da grade + slide do carrossel) ---- */

		function collect(scope) {
			var byId = {};
			var order = [];

			function add(el, kind) {
				var id = el.getAttribute('data-unit-id');

				if (!id) {
					return;
				}
				if (!byId[id]) {
					byId[id] = { id: id, grid: null, slide: null, coords: unitCoords(el), index: order.length };
					order.push(byId[id]);
				}
				byId[id][kind] = el;
			}

			Array.prototype.forEach.call(scope.querySelectorAll('.contorno-units__cell[data-unit-id]'), function (el) {
				add(el, 'grid');
			});
			Array.prototype.forEach.call(scope.querySelectorAll('.contorno-units__slide[data-unit-id]'), function (el) {
				add(el, 'slide');
			});

			return order;
		}

		// /unidades/page/2/ -> /unidades/ ; sem busca, raio nem pagina propria.
		function listUrl(page) {
			var url = new URL(window.location.href);

			url.pathname = url.pathname.replace(/\/page\/\d+\/?$/, '/');
			['q', 'raio', 'unidades_page', 'per_page'].forEach(function (key) {
				url.searchParams.delete(key);
			});
			url.searchParams.set('per_page', String(PAGE_SIZE));
			if (page > 1) {
				url.searchParams.set('unidades_page', String(page));
			}
			url.hash = '';

			return url.toString();
		}

		function fetchPage(page) {
			return fetch(listUrl(page), { credentials: 'same-origin' }).then(function (response) {
				if (!response.ok) {
					throw new Error('http');
				}

				return response.text();
			}).then(function (html) {
				var doc = new DOMParser().parseFromString(html, 'text/html');
				var remote = doc.querySelector('[data-contorno-units]');

				if (!remote) {
					throw new Error('markup');
				}

				// Conteudo e do proprio site, mas nada executavel entra na pagina.
				Array.prototype.forEach.call(remote.querySelectorAll('script'), function (node) {
					node.parentNode.removeChild(node);
				});

				return remote;
			});
		}

		// Catalogo: so a pagina atual esta no DOM; busca o restante (sem posicao).
		function loadFetchedPool() {
			if (fetchedPool) {
				return Promise.resolve(fetchedPool);
			}

			var cells = [];
			var slides = [];
			var blocks = null;

			function take(remote) {
				Array.prototype.forEach.call(remote.querySelectorAll('.contorno-units__cell[data-unit-id]'), function (el) {
					cells.push(document.importNode(el, true));
				});
				Array.prototype.forEach.call(remote.querySelectorAll('.contorno-units__slide[data-unit-id]'), function (el) {
					slides.push(document.importNode(el, true));
				});
			}

			return fetchPage(1).then(function (remote) {
				var total = parseInt(remote.getAttribute('data-total'), 10) || 0;
				var pages = Math.min(MAX_PAGES, Math.max(1, Math.ceil(total / PAGE_SIZE)));
				var chain = Promise.resolve();

				blocks = {
					carousel: remote.querySelector('.contorno-units__carousel'),
					grid: remote.querySelector('.contorno-units__grid'),
					empty: remote.querySelector('[data-contorno-units-empty]')
				};
				take(remote);

				for (var page = 2; page <= pages; page += 1) {
					chain = chain.then(
						(function (n) {
							return function () {
								return fetchPage(n).then(take);
							};
						})(page)
					);
				}

				return chain;
			}).then(function () {
				var holder = document.createElement('div');

				cells.forEach(function (el) {
					holder.appendChild(el);
				});
				slides.forEach(function (el) {
					holder.appendChild(el);
				});

				fetchedPool = { entries: collect(holder), blocks: blocks };

				return fetchedPool;
			});
		}

		// A listagem chegou sem grade (busca sem resultado): traz os blocos vazios do catalogo.
		function ensureLists(blocks) {
			if (grid && track) {
				return;
			}

			var anchor = container.querySelector('.contorno-units__empty');

			function put(node) {
				if (!node) {
					return null;
				}

				var imported = document.importNode(node, true);

				if (anchor && anchor.parentNode) {
					anchor.parentNode.insertBefore(imported, anchor.nextSibling);
					anchor = imported;
				} else {
					container.appendChild(imported);
				}
				inserted.push(imported);

				return imported;
			}

			var emptyOriginal = container.querySelector('.contorno-units__empty:not([data-contorno-units-empty])');
			if (emptyOriginal) {
				emptyOriginal.hidden = true;
				inserted.push({ restoreHidden: emptyOriginal });
			}

			if (blocks.empty) {
				put(blocks.empty);
			}
			put(blocks.carousel);
			put(blocks.grid);

			grid = container.querySelector('.contorno-units__grid');
			track = container.querySelector('[data-contorno-carousel-track]');
			createdLists = true;
		}

		/* ---- aplicar / desfazer ---- */

		function toggleChrome(hide) {
			['.contorno-units__pagination', '.contorno-units__notice', '.contorno-units__per-page'].forEach(function (selector) {
				Array.prototype.forEach.call(container.querySelectorAll(selector), function (el) {
					el.hidden = hide;
				});
			});

			var radiusWrap = root.querySelector('[data-contorno-unit-search-radius-wrap]');
			if (radiusWrap && hide) {
				radiusWrap.hidden = true;
			}
		}

		function addDistance(card, km) {
			var body = card && card.querySelector('.unit-card__body');

			if (!body) {
				return;
			}

			Array.prototype.forEach.call(body.querySelectorAll('.unit-card__distance'), function (el) {
				if (el.hasAttribute('data-gps-distance')) {
					el.parentNode.removeChild(el);
				} else {
					el.hidden = true;
				}
			});

			if (km === null) {
				return;
			}

			var line = document.createElement('p');
			line.className = 'unit-card__distance';
			line.setAttribute('data-gps-distance', '');
			line.textContent = text.distance.replace('%s', formatDistance(km));

			var location = body.querySelector('.unit-card__location');
			if (location && location.parentNode === body) {
				body.insertBefore(line, location.nextSibling);
			} else {
				var title = body.querySelector('.unit-card__title');
				body.insertBefore(line, title ? title.nextSibling : body.firstChild);
			}
		}

		function updateCount() {
			var counter = container.querySelector('.contorno-units__count');

			if (!counter || !state.active || !grid) {
				return;
			}

			var shown = grid.querySelectorAll('.contorno-units__cell:not([hidden])').length;

			counter.textContent = shown === 1 ? text.countOne : text.countMany.replace('%d', String(shown));
		}

		function render(entries, lat, lng) {
			if (!snapshot) {
				var counter = container.querySelector('.contorno-units__count');

				snapshot = {
					grid: Array.prototype.slice.call(grid.children),
					track: track ? Array.prototype.slice.call(track.children) : [],
					count: counter ? counter.textContent : null,
					term: input.value
				};
			}

			var ranked = sortByDistance(entries, lat, lng);
			var missing = false;

			grid.textContent = '';
			if (track) {
				track.textContent = '';
			}

			ranked.forEach(function (row) {
				var entry = row.entry;

				if (row.distance === null) {
					missing = true;
				}

				[entry.grid, entry.slide].forEach(function (card) {
					addDistance(card, row.distance);
				});

				if (entry.grid) {
					entry.grid.hidden = false;
					grid.appendChild(entry.grid);
				}
				if (entry.slide && track) {
					entry.slide.hidden = false;
					track.appendChild(entry.slide);
				}
			});

			toggleChrome(true);
			input.value = '';
			hooks.refresh();

			var empty = container.querySelector('[data-contorno-units-empty]');
			if (empty) {
				empty.hidden = true;
			}

			state.active = true;
			updateCount();
			hooks.afterRender();

			return missing;
		}

		function restore() {
			if (!snapshot) {
				return;
			}

			Array.prototype.forEach.call(container.querySelectorAll('[data-gps-distance]'), function (el) {
				el.parentNode.removeChild(el);
			});
			Array.prototype.forEach.call(container.querySelectorAll('.unit-card__distance[hidden]'), function (el) {
				el.hidden = false;
			});

			inserted.forEach(function (item) {
				if (item.restoreHidden) {
					item.restoreHidden.hidden = false;
				} else if (item.parentNode) {
					item.parentNode.removeChild(item);
				}
			});
			inserted = [];

			// Se a listagem nasceu sem grade, os blocos criados saem e as referencias voltam a ser nulas.
			if (createdLists) {
				createdLists = false;
				grid = null;
				track = null;
			} else {
				grid.textContent = '';
				snapshot.grid.forEach(function (el) {
					el.hidden = false;
					grid.appendChild(el);
				});
				if (track) {
					track.textContent = '';
					snapshot.track.forEach(function (el) {
						el.hidden = false;
						track.appendChild(el);
					});
				}
			}

			var counter = container.querySelector('.contorno-units__count');
			if (counter && snapshot.count !== null) {
				counter.textContent = snapshot.count;
			}

			toggleChrome(false);
			input.value = snapshot.term;
			snapshot = null;
		}

		function deactivate() {
			state.token += 1;
			state.active = false;
			state.busy = false;
			restore();
			paint();
			say('', false);
			hooks.refresh();
			hooks.afterRender();
			input.focus();
		}

		/* ---- fluxo principal ---- */

		function onPosition(position, token) {
			if (token !== state.token) {
				return;
			}

			var lat = position && position.coords ? Number(position.coords.latitude) : NaN;
			var lng = position && position.coords ? Number(position.coords.longitude) : NaN;

			if (!isFinite(lat) || !isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) {
				fail(text.unavailable);
				return;
			}

			if (redirectMode) {
				window.location.href = (input.dataset.archive || '/unidades/') + GEO_HASH;
				return;
			}

			var needsFetch =isCatalog && (container.getAttribute('data-all-rendered') !== '1' || !grid);
			var ready = needsFetch
				? loadFetchedPool().then(function (pool) {
						ensureLists(pool.blocks);
						return pool.entries;
					})
				: Promise.resolve(collect(container));

			ready.then(function (entries) {
				if (token !== state.token) {
					return;
				}

				if (!grid) {
					throw new Error('no-list');
				}

				var missing = render(entries, lat, lng);

				state.busy = false;
				paint();
				say(text.activeNote + (missing ? ' ' + text.noCoords : ''), false);
				keepFocus();
			}).catch(function () {
				if (token === state.token) {
					fail(text.loadError);
				}
			});
		}

		function onError(error, token) {
			if (token !== state.token) {
				return;
			}

			var code = error && typeof error.code === 'number' ? error.code : 2;

			fail(code === 1 ? text.denied : code === 3 ? text.timeout : text.unavailable);
		}

		function start() {
			if (state.busy || state.active) {
				return;
			}

			markAsked();

			if (!('geolocation' in navigator) || !navigator.geolocation) {
				say(text.unsupported, true);
				return;
			}

			state.token += 1;

			var token = state.token;

			state.busy = true;
			paint();
			say(text.loading, false);

			window.setTimeout(function () {
				if (state.busy && token === state.token) {
					state.token += 1;
					fail(text.timeout);
				}
			}, GEO_GUARD_MS);

			try {
				navigator.geolocation.getCurrentPosition(
					function (position) {
						onPosition(position, token);
					},
					function (error) {
						onError(error, token);
					},
					GEO_OPTIONS
				);
			} catch (e) {
				fail(text.unavailable);
			}
		}

		button.addEventListener('click', start);

		if (off) {
			off.addEventListener('click', deactivate);
		}

		// Voltar pelo historico (bfcache) com o botao preso em "Obtendo...".
		window.addEventListener('pageshow', function (event) {
			if (event.persisted && state.busy) {
				state.token += 1;
				state.busy = false;
				paint();
				say('', false);
			}
		});

		/*
		 * Modal de contexto ao COMECAR a buscar (foco no campo): uma vez por
		 * sessao e so quando o navegador ainda nao decidiu a permissao. Nunca
		 * ao abrir o site, nunca repetido apos "Agora nao" ou negativa.
		 */
		var modal = document.querySelector('[data-contorno-geo-modal]');

		function maybePrompt() {
			if (
				!modal ||
				typeof modal.showModal !== 'function' ||
				modal.open ||
				state.busy ||
				state.active ||
				wasAsked() ||
				!navigator.geolocation
			) {
				return;
			}

			markAsked();

			function open() {
				wireModal(modal);
				modalAccept = start;
				modal.returnValue = '';
				modal.showModal();
			}

			if (navigator.permissions && navigator.permissions.query) {
				navigator.permissions.query({ name: 'geolocation' }).then(
					function (permission) {
						if (permission.state === 'prompt') {
							open();
						}
					},
					open
				);
			} else {
				open();
			}
		}

		input.addEventListener('focus', maybePrompt);

		paint();

		// Chegada de /unidades#perto-de-mim: a pessoa ja pediu a localizacao na Home.
		if (!redirectMode && window.location.hash === GEO_HASH) {
			if (window.history && window.history.replaceState) {
				window.history.replaceState(null, '', window.location.pathname + window.location.search);
			}
			start();
		}

		return {
			isActive: function () {
				return state.active;
			},
			updateCount: updateCount
		};
	}

	function setup(root) {
		if (root.dataset.contornoSearchReady === '1') {
			return;
		}
		root.dataset.contornoSearchReady = '1';

		var input = root.querySelector('[data-contorno-unit-search-input]');
		var clear = root.querySelector('[data-contorno-unit-search-clear]');
		var radiusWrap = root.querySelector('[data-contorno-unit-search-radius-wrap]');
		var radius = root.querySelector('[data-contorno-unit-search-radius]');

		if (!input) {
			return;
		}

		var container = root.closest('[data-contorno-units]');
		var isRemote = root.dataset.target === 'hero' || root.dataset.target === 'catalog' || !container;
		var gps = null;

		function gpsActive() {
			return gps !== null && gps.isActive();
		}

		// URL de /unidades com o termo e, quando for CEP, o raio escolhido.
		function buildUrl(term) {
			var archive = input.dataset.archive || '/unidades/';

			if (!term) {
				return archive;
			}

			var params = ['q=' + encodeURIComponent(term)];

			if (radius && cepDigits(term) && radius.value !== radius.dataset.default) {
				params.push('raio=' + encodeURIComponent(radius.value));
			}

			return archive + (archive.indexOf('?') === -1 ? '?' : '&') + params.join('&');
		}

		function go() {
			window.location.href = buildUrl(input.value.trim());
		}

		function run() {
			if (clear) {
				clear.hidden = input.value === '';
			}

			if (radiusWrap) {
				radiusWrap.hidden = cepDigits(input.value) === '';
			}

			// Com GPS ligado a busca por texto filtra a lista ja ordenada, sem recarregar.
			if (!isRemote || gpsActive()) {
				applyFilter(container, input.value);

				if (gpsActive()) {
					gps.updateCount();
				}
			}
		}

		input.addEventListener('input', run);

		input.addEventListener('keydown', function (event) {
			if (event.key !== 'Enter') {
				return;
			}

			event.preventDefault();

			// Com GPS ligado, so um CEP completo precisa do servidor (geocodificacao).
			if (isRemote && (!gpsActive() || cepDigits(input.value))) {
				go();
			}
		});

		if (radius) {
			radius.addEventListener('change', function () {
				if (cepDigits(input.value)) {
					go();
				}
			});
		}

		if (clear) {
			clear.addEventListener('click', function () {
				input.value = '';
				run();
				input.focus();

				if (root.dataset.target === 'catalog' && !gpsActive()) {
					var archive = input.dataset.archive || '/unidades/';
					var url = new URL(window.location.href);
					url.searchParams.delete('q');
					url.searchParams.delete('raio');
					url.searchParams.delete('unidades_page');
					window.location.href = url.pathname + (url.search ? url.search : '') || archive;
				}
			});
		}

		// Botão do cartão do hero: leva para /unidades com o termo aplicado.
		var submit = root.querySelector('[data-contorno-unit-search-submit]');
		if (submit) {
			submit.addEventListener('click', go);
		}

		gps = setupGeo(root, input, container, {
			refresh: run,
			afterRender: function () {
				var carousel = container.querySelector('[data-contorno-carousel]');

				if (carousel) {
					carousel.dispatchEvent(new CustomEvent('contorno:units-filtered'));
				}

				document.dispatchEvent(new CustomEvent('contorno:refresh'));
			}
		});

		// Termo vindo da URL (?q=) aplicado na chegada.
		if (window.URLSearchParams) {
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

	// Funcoes puras expostas para os testes (scripts/test-unit-geo.js).
	window.ContornoUnitGeo = {
		haversineKm: haversineKm,
		formatDistance: formatDistance,
		sortByDistance: sortByDistance,
		parseCoord: parseCoord
	};

	if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}

	document.addEventListener('contorno:refresh', init);
})();
