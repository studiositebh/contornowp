/**
 * Checkout nativo — etapas, validação e pagamento.
 *
 * O QUE ESTE ARQUIVO NUNCA FAZ
 * ----------------------------
 * Não lê, não guarda e não envia número de cartão, CVV ou validade digitados.
 * Não existe input de cartão no HTML da página: o formulário do cartão é
 * montado pelo componente oficial do EVO Pay, dentro do container
 * [data-ck-card-mount], e o que chega até aqui é o TOKEN que ele devolve.
 * Antes de sair, o payload passa por pickCard(), uma lista fechada de campos —
 * se o componente devolver algo além disso, o extra fica de fora.
 *
 * Também não conhece preço: o resumo é preenchido com o que /checkout/open
 * devolveu, e nenhum valor sai daqui de volta para o servidor. Trocar o texto
 * do resumo no inspetor muda o texto na tela e nada mais; quem cobra é a EVO,
 * com o valor que ela mesma resolveu.
 *
 * INDISPONIBILIDADE (antes chamada de "fallback")
 * -------------------------------------------------
 * A jornada NUNCA sai do site. Quando a API diz, explicitamente, que
 * nenhuma venda foi criada (`fallback: true` na resposta), a tela mostra
 * o aviso de indisponibilidade + "Falar com a unidade" (CFG.contactUrl,
 * WhatsApp da própria unidade) — nunca um redirect para domínio externo.
 * Depois de uma resposta ambígua o servidor responde `indeterminado` e
 * NÃO manda fallback — mostrar esse caminho cobraria duas vezes.
 *
 * TOKENIZAÇÃO — contrato do componente oficial
 * --------------------------------------------
 * Lido do próprio evo-pay.js (w12evopay.com/evocartao) e do checkout da EVO
 * que o usa, em 09/10/2026:
 *  - <evo-cartao> é um custom element (Angular Elements). A configuração
 *    entra pela PROPRIEDADE `gateway` = { tipoGateway, dadosGateway } —
 *    objeto, não atributo.
 *  - Com tokenização no navegador (tipo 9 = Pagar.me), o cartão vai do
 *    navegador direto para api.pagar.me; nada passa por este arquivo.
 *  - O resultado sai no evento `eventoTokenGerado`, disparado NO ELEMENTO,
 *    com event.detail = { token, tokenFranquia, nmIgualCartao,
 *    mesVencimento, anoVencimento, ultimoDigitosCartao, bandeira,
 *    numeroParcelas, tipo, idCartaoCliente }. Erros em `eventoOcorreuErro`
 *    (event.detail = mensagem).
 *  - O checkout da EVO grava token e tokenTemporario com o MESMO valor e
 *    tokenFranquia à parte; aqui viram token, temporaryToken e branchToken
 *    do cardData da venda, montado no servidor (evoCard()).
 */
(function () {
	'use strict';

	var CFG = window.contornoCheckout || null;
	if (!CFG) return;

	var T = CFG.i18n || {};

	/** Campos de cartão aceitos — espelha Contorno_Evo_Checkout::CARD_FIELDS. */
	var CARD_FIELDS = [
		'token',
		'temporaryToken',
		'branchToken',
		'truncatedCardNumber',
		'brand',
		'cardHolderName',
		'cardExpirationYear',
		'cardExpirationMonth'
	];

	/** Eventos do <evo-cartao> (outputs do componente). */
	var EVENT_TOKEN = 'eventoTokenGerado';
	var EVENT_ERROR = 'eventoOcorreuErro';

	/* ------------------------------------------------------------------
	 * Utilidades
	 * ---------------------------------------------------------------- */

	function digits(value) {
		return String(value || '').replace(/\D/g, '');
	}

	function money(value) {
		var number = Number(value || 0);
		try {
			return number.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
		} catch (e) {
			return 'R$ ' + number.toFixed(2).replace('.', ',');
		}
	}

	/**
	 * Número nacional (DDD + telefone), sem DDI.
	 *
	 * O autocomplete do Chrome entrega "+55 31 97524-8061" ou
	 * "5531975248061". Com 12 ou 13 dígitos começando por 55, os dois
	 * primeiros são DDI. Com 10 ou 11, "55" é o DDD do RS e fica.
	 * Normaliza ANTES de mascarar ou limitar — cortar primeiro transformava
	 * o DDI em DDD e descartava os últimos dígitos.
	 */
	function normalizePhone(value) {
		var d = digits(value);
		if (d.indexOf('55') === 0 && (d.length === 12 || d.length === 13)) d = d.slice(2);
		return d;
	}

	/** DDD válido (sem zero) e celular de 11 dígitos começando por 9. */
	function validPhone(value) {
		var d = normalizePhone(value);
		if (d.length !== 10 && d.length !== 11) return false;
		if (!/^[1-9][1-9]/.test(d)) return false;
		return d.length === 10 || d.charAt(2) === '9';
	}

	function maskPhone(value) {
		var d = normalizePhone(value);
		if (!d) return '';
		// Mais de 11 dígitos não é telefone brasileiro: mostra como veio para
		// a pessoa ver e corrigir, em vez de cortar em silêncio.
		if (d.length > 11) return d;
		if (d.length <= 2) return '(' + d;
		if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
		if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
		return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
	}

	/** Reaplica normalização + máscara no campo, mantendo o cursor ao digitar. */
	function syncPhone(field, preserveCursor) {
		if (!field) return;

		var cursor = typeof field.selectionStart === 'number' ? field.selectionStart : null;
		var before = field.value.length;
		var masked = maskPhone(field.value);

		if (masked === field.value) return;
		field.value = masked;

		if (preserveCursor && cursor !== null && field.setSelectionRange) {
			var next = Math.max(0, cursor + (masked.length - before));
			field.setSelectionRange(next, next);
		}
	}

	function maskCpf(value) {
		var d = digits(value).slice(0, 11);
		if (d.length <= 3) return d;
		if (d.length <= 6) return d.slice(0, 3) + '.' + d.slice(3);
		if (d.length <= 9) return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6);
		return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6, 9) + '-' + d.slice(9);
	}

	function maskCep(value) {
		var d = digits(value).slice(0, 8);
		return d.length <= 5 ? d : d.slice(0, 5) + '-' + d.slice(5);
	}

	/**
	 * Dígito verificador do CPF. Mesma regra do servidor: um dígito trocado
	 * criaria cadastro novo para quem já existe na EVO.
	 */
	function validCpf(value) {
		var cpf = digits(value);
		if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;

		for (var position = 9; position < 11; position++) {
			var sum = 0;
			for (var index = 0; index < position; index++) {
				sum += parseInt(cpf.charAt(index), 10) * (position + 1 - index);
			}
			var digit = ((10 * sum) % 11) % 10;
			if (parseInt(cpf.charAt(position), 10) !== digit) return false;
		}

		return true;
	}

	/* ------------------------------------------------------------------
	 * Controlador
	 * ---------------------------------------------------------------- */

	function Checkout(root) {
		this.root = root;
		this.token = '';
		this.summary = null;
		this.gateway = null;
		this.card = null;
		this.busy = false;
		this.withAddress = root.getAttribute('data-address') === '1';
		// 1ª etapa = lead (nome, WhatsApp, e-mail), salvo na EVO antes do
		// resto. O plano fica no resumo ao lado; não há etapa de "confirmar".
		this.order = ['data'].concat(this.withAddress ? ['address'] : [], ['payment', 'done']);
		this.current = 'data';

		this.alert = root.querySelector('[data-ck-alert]');
		this.progress = root.querySelector('[data-ck-progress]');
	}

	Checkout.prototype.$ = function (selector) {
		return this.root.querySelector(selector);
	};

	Checkout.prototype.$$ = function (selector) {
		return Array.prototype.slice.call(this.root.querySelectorAll(selector));
	};

	/* ---------------- estado visual ---------------- */

	Checkout.prototype.say = function (message, kind) {
		if (!this.alert) return;
		this.alert.textContent = message || '';
		this.alert.hidden = !message;
		this.alert.className = 'contorno-ck__alert' + (message ? ' is-' + (kind || 'error') : '');
	};

	Checkout.prototype.go = function (step) {
		var self = this;
		this.current = step;

		this.$$('[data-ck-panel]').forEach(function (panel) {
			var is = panel.getAttribute('data-ck-panel') === step;
			panel.hidden = !is;
			panel.classList.toggle('is-current', is);
		});

		this.$$('[data-ck-step-marker]').forEach(function (marker) {
			var key = marker.getAttribute('data-ck-step-marker');
			var at = self.order.indexOf(key);
			var now = self.order.indexOf(step);
			marker.classList.toggle('is-current', at === now);
			marker.classList.toggle('is-done', at > -1 && at < now);
		});

		if (this.progress && T.stepOf) {
			var index = this.order.indexOf(step) + 1;
			this.progress.textContent = T.stepOf
				.replace('%1$d', String(index))
				.replace('%2$d', String(this.order.length));
		}

		// Etapa nova começa no topo: em telefone, o formulário longo deixaria
		// o botão fora da vista.
		// Desconta o header fixo do tema (e a barra do admin): sem isso a
		// trilha de etapas ficava escondida atrás dele.
		var header = document.querySelector('[data-contorno-header]');
		var covered = header && /fixed|sticky/.test(getComputedStyle(header).position) ? Math.max(0, header.getBoundingClientRect().bottom) : 0;
		var top = this.root.getBoundingClientRect().top + window.pageYOffset - covered - 16;
		window.scrollTo({ top: top < 0 ? 0 : top, behavior: 'smooth' });
	};

	Checkout.prototype.back = function () {
		var index = this.order.indexOf(this.current);
		if (index > 0) {
			this.say('');
			this.go(this.order[index - 1]);
		}
	};

	/* ---------------- resumo ---------------- */

	Checkout.prototype.paint = function (summary) {
		var self = this;
		this.summary = summary || {};

		function set(key, text) {
			var node = self.$('[data-ck-sum="' + key + '"]');
			var row = self.$('[data-ck-sum-row="' + key + '"]');
			if (node) node.textContent = text;
			if (row) row.hidden = !text;
		}

		set('unit', this.summary.unit || '');
		set('plan', this.summary.plan || '');

		var first = Number(this.summary.firstValue || 0);
		var full = Number(this.summary.value || 0);

		set('firstValue', first > 0 ? money(first) : '');
		// Só mostra "valor recorrente" quando ele é diferente do que a pessoa
		// paga agora. Repetir o mesmo número em duas linhas não informa nada.
		set('recurrent', full > 0 && Math.abs(full - first) > 0.004 ? money(full) : '');

		var condition = '';
		if (Number(this.summary.promoValue || 0) > 0) {
			if (Number(this.summary.promoMonths || 0) > 0) {
				condition = money(first) + ' nos primeiros ' + this.summary.promoMonths + ' mês(es), depois ' + money(full);
			} else if (Number(this.summary.promoDays || 0) > 0) {
				condition = money(first) + ' nos primeiros ' + this.summary.promoDays + ' dia(s), depois ' + money(full);
			}
		}
		set('condition', condition);

		var fidelity = '';
		if (Number(this.summary.duration || 0) > 0) {
			var type = String(this.summary.durationType || '').toLowerCase();
			var unit = type.indexOf('dia') > -1 || type.indexOf('day') > -1 ? 'dia(s)' : 'mês(es)';
			fidelity = this.summary.duration + ' ' + unit;
		} else if (Number(this.summary.minStay || 0) > 0) {
			fidelity = this.summary.minStay + ' mês(es)';
		}
		set('fidelity', fidelity);

		// A EVO informa SE existe taxa de matrícula, mas não o valor nesta
		// rota. Exibir "a confirmar" é honesto; exibir um número seria invenção.
		set('enrollment', this.summary.enrollmentRequired ? 'Sim — valor confirmado na unidade' : '');

		var max = Number(this.summary.maxInstallments || 1);
		set('installments', max > 1 ? 'Em até ' + max + 'x no cartão' : 'À vista');

		this.fillInstallments(max);
	};

	Checkout.prototype.fillInstallments = function (max) {
		var row = this.$('[data-ck-installments]');
		var select = this.$('#contorno-ck-installments');
		if (!row || !select) return;

		select.innerHTML = '';
		for (var n = 1; n <= Math.max(1, max); n++) {
			var option = document.createElement('option');
			option.value = String(n);
			option.textContent = n === 1 ? '1x (à vista)' : n + 'x';
			select.appendChild(option);
		}

		row.hidden = max <= 1;
	};

	/* ---------------- rede ---------------- */

	Checkout.prototype.call = function (route, body) {
		var payload = body || {};
		payload.nonce = CFG.nonce;
		// Honeypot: sempre vazio no fluxo legítimo.
		payload.website = '';

		return fetch(CFG.routes[route], {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload)
		}).then(function (response) {
			return response.json().catch(function () {
				return { ok: false, error: 'generic', message: T.generic };
			});
		}).catch(function () {
			// Rede caiu do lado do navegador. Nenhuma garantia sobre o que o
			// servidor fez, então tratamos como indeterminado, sem fallback.
			return { ok: false, error: 'rede', message: T.generic };
		});
	};

	// Nunca navega para fora do site. So mostra o aviso — mensagem publica
	// ja vem sanitizada do servidor (Contorno_Evo_Checkout::public_message)
	// — e, se a unidade tiver WhatsApp cadastrado, um jeito de continuar
	// por humano.
	Checkout.prototype.fallbackTo = function (message) {
		if (!this.alert) return;

		var text = message || T.unavailable || T.generic;
		this.alert.innerHTML = '';
		this.alert.hidden = false;
		this.alert.className = 'contorno-ck__alert is-warning';

		var p = document.createElement('p');
		p.textContent = text;
		this.alert.appendChild(p);

		if (CFG.contactUrl) {
			var link = document.createElement('a');
			link.href = CFG.contactUrl;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			link.className = 'contorno-ck__alert-contact';
			link.textContent = T.contactUnit || 'Falar com a unidade';
			this.alert.appendChild(link);
		}
	};

	/* ---------------- etapa 1 ---------------- */

	Checkout.prototype.start = function () {
		var self = this;
		var loading = this.$('[data-ck-loading]');
		// "Continuar" só depois da sessão aberta: sem token não há onde
		// registrar o lead.
		var next = this.$('[data-ck-next="data"]');

		this.call('open', { slug: CFG.slug, plan: CFG.plan }).then(function (data) {
			if (loading) loading.hidden = true;

			if (!data.ok) {
				// Nada foi criado nesta etapa: o checkout da academia é destino
				// seguro e é onde a pessoa consegue concluir.
				if (data.fallback) {
					self.fallbackTo(data.message);
					return;
				}
				self.say(data.message || T.generic, 'error');
				return;
			}

			self.token = data.token;
			self.gateway = data.gateway || {};
			self.paint(data.summary);

			if (next) next.disabled = false;
		});
	};

	/* ---------------- validação ---------------- */

	Checkout.prototype.showError = function (name, message) {
		var box = this.$('[data-error-for="' + name + '"]');
		var input = this.root.querySelector('[name="' + name + '"]');

		if (box) {
			box.textContent = message || '';
			box.hidden = !message;
		}
		if (input) {
			if (message) input.setAttribute('aria-invalid', 'true');
			else input.removeAttribute('aria-invalid');
		}
	};

	Checkout.prototype.clearErrors = function (panel) {
		var scope = panel ? this.$('[data-ck-panel="' + panel + '"]') : this.root;
		if (!scope) return;

		Array.prototype.forEach.call(scope.querySelectorAll('[data-error-for]'), function (box) {
			box.textContent = '';
			box.hidden = true;
		});
		Array.prototype.forEach.call(scope.querySelectorAll('[aria-invalid]'), function (input) {
			input.removeAttribute('aria-invalid');
		});
	};

	Checkout.prototype.value = function (name) {
		var input = this.root.querySelector('[name="' + name + '"]');
		return input ? String(input.value || '').trim() : '';
	};

	/** 1ª etapa (lead): nome completo, WhatsApp, e-mail e consentimento. */
	Checkout.prototype.validateData = function () {
		var errors = {};

		// Nome + sobrenome: a EVO exige os dois no prospect.
		var name = this.value('fullName').replace(/\s+/g, ' ').split(' ');
		if (name.length < 2 || name[0].length < 2 || !name[1]) errors.fullName = T.fullName || T.required;

		// Última proteção para autofill que não disparou nenhum evento.
		syncPhone(this.root.querySelector('[name="phone"]'), false);
		if (!validPhone(this.value('phone'))) errors.phone = T.phone;

		if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(this.value('email'))) errors.email = T.email;

		var consent = this.root.querySelector('[name="consent"]');
		if (consent && !consent.checked) errors.consent = T.consent || T.required;

		return errors;
	};

	/** Etapa de pagamento: CPF e termos (o cartão é do componente). */
	Checkout.prototype.validatePayment = function () {
		var errors = {};

		if (!validCpf(this.value('document'))) errors.document = T.cpf;

		var terms = this.root.querySelector('[name="acceptedTerms"]');
		if (terms && !terms.checked) errors.acceptedTerms = T.terms;

		return errors;
	};

	Checkout.prototype.validateAddress = function () {
		var errors = {};

		if (digits(this.value('zipCode')).length !== 8) errors.zipCode = T.required;
		if (this.value('address').length < 3) errors.address = T.required;
		if (this.value('number').length < 1) errors.number = T.required;
		if (this.value('neighborhood').length < 2) errors.neighborhood = T.required;
		if (this.value('city').length < 2) errors.city = T.required;
		if (this.value('state').length !== 2) errors.state = T.required;

		return errors;
	};

	Checkout.prototype.report = function (panel, errors) {
		var self = this;
		var keys = Object.keys(errors);

		this.clearErrors(panel);
		keys.forEach(function (key) {
			self.showError(key, errors[key]);
		});

		if (keys.length) {
			var first = this.root.querySelector('[name="' + keys[0] + '"]');
			if (first && first.focus) first.focus();
		}

		return keys.length === 0;
	};

	/* ---------------- etapa 2 ---------------- */

	Checkout.prototype.submitData = function (button) {
		var self = this;

		if (!this.report('data', this.validateData())) return;

		var label = button ? button.querySelector('[data-ck-label]') : null;
		var previous = label ? label.textContent : '';

		if (button) button.disabled = true;
		if (label) label.textContent = T.saving || T.validating || previous;

		var restore = function () {
			if (button) button.disabled = false;
			if (label) label.textContent = previous;
		};

		// O lead vai para a EVO AGORA: se a pessoa desistir no pagamento, a
		// unidade já tem o contato. Aluno ou prospect existente é
		// reaproveitado pelo servidor, sem cadastro duplicado.
		this.call('lead', {
			token: this.token,
			fullName: this.value('fullName'),
			// Só o número nacional; o servidor envia à EVO com ddi 55 uma vez.
			phone: normalizePhone(this.value('phone')),
			email: this.value('email'),
			consent: true
		}).then(function (data) {
			restore();

			if (!data.ok) {
				if (data.fallback) {
					self.fallbackTo(data.message);
					return;
				}
				self.say(data.message || T.generic, 'error');
				return;
			}

			self.say('');
			self.go(self.withAddress ? 'address' : 'payment');
			if (!self.withAddress) self.mountCard();
		});
	};

	/* ---------------- etapa de cartão ---------------- */

	/** Só os campos previstos passam. O resto é descartado aqui mesmo. */
	function pickCard(source) {
		var out = {};
		if (!source || typeof source !== 'object') return out;

		CARD_FIELDS.forEach(function (field) {
			if (source[field] !== undefined && source[field] !== null && String(source[field]) !== '') {
				out[field] = source[field];
			}
		});

		return out;
	}

	/**
	 * event.detail do <evo-cartao> -> campos de CardDataViewModel.
	 *
	 * Só mapeia o que o componente devolve; número completo e CVV nem
	 * existem no detail (ultimoDigitosCartao já vem "****1234").
	 */
	function evoCard(detail) {
		if (!detail || typeof detail !== 'object') return {};

		var year = parseInt(detail.anoVencimento, 10);
		// "MM/AA" no componente: ano de 2 dígitos vira 20AA.
		if (year > 0 && year < 100) year += 2000;

		return pickCard({
			token: detail.token,
			temporaryToken: detail.token,
			branchToken: detail.tokenFranquia,
			truncatedCardNumber: detail.ultimoDigitosCartao,
			brand: detail.bandeira,
			cardHolderName: detail.nmIgualCartao,
			cardExpirationMonth: parseInt(detail.mesVencimento, 10) || undefined,
			cardExpirationYear: year || undefined
		});
	}

	/**
	 * Token chegou do <evo-cartao>. Só vale se NÓS pedimos (pay() ->
	 * 'salvar'); evento sem pedido pendente é ignorado. Com token, a venda
	 * segue na hora — o token do adquirente é de uso único e curto.
	 */
	Checkout.prototype.acceptToken = function (payload) {
		if (!this.awaitingToken) return;

		var card = pickCard(payload);

		if (!card.token && !card.temporaryToken) {
			this.tokenFailed(T.cardMissing || T.generic);
			return;
		}

		this.awaitingToken = false;
		clearTimeout(this.tokenTimer);
		this.card = card;
		this.showError('card', '');
		this.submit();
	};

	/** Tokenização falhou ou expirou: nada foi enviado; a pessoa corrige e tenta de novo. */
	Checkout.prototype.tokenFailed = function (message) {
		this.awaitingToken = false;
		clearTimeout(this.tokenTimer);
		this.card = null;
		this.idle();
		this.showError('card', message || T.generic);
	};

	/** Botão de volta ao estado inicial (sem venda em andamento). */
	Checkout.prototype.idle = function () {
		var button = this.$('[data-ck-pay]');
		var label = this.$('[data-ck-pay-label]');
		var spinner = this.$('[data-ck-pay-spinner]');

		this.busy = false;
		if (button) button.disabled = !this.cardElement;
		if (spinner) spinner.hidden = true;
		if (label) label.textContent = T.payLabel || 'Finalizar matrícula';
	};

	Checkout.prototype.mountCard = function () {
		var self = this;
		var mount = this.$('[data-ck-card-mount]');
		var empty = this.$('[data-ck-card-empty]');

		if (!mount || mount.getAttribute('data-ready') === '1') return;

		var url = this.gateway && this.gateway.scriptUrl;
		var config = this.gateway && this.gateway.component;

		if (!url || !config) {
			// Sem o componente oficial não existe caminho seguro de cartão
			// nesta página — e construir um formulário próprio postaria PAN
			// para o WordPress. Continua no checkout da academia.
			if (empty) empty.textContent = T.cardMissing || T.generic;
			this.fallbackTo(T.cardMissing);
			return;
		}

		mount.setAttribute('data-ready', '1');

		var script = document.createElement('script');
		script.src = url;
		script.async = true;
		script.onerror = function () {
			if (empty) empty.textContent = T.cardMissing || T.generic;
			self.fallbackTo(T.cardMissing);
		};
		script.onload = function () {
			var element = document.createElement('evo-cartao');

			// Propriedades, não atributos: `gateway` é objeto. Só dado público
			// do gateway chega aqui — o servidor já reduziu dadosGateway a uma
			// lista fechada (publicKey, publicKeyFranquia, origemFranquia).
			element.gateway = config;
			element.flTokenizaBackend = false;
			element.exibirTipo = !!self.gateway.showCardType;
			element.cultura = 'pt-BR';
			element.classes = 'form-no-style';

			// Bandeiras e rótulos vindos da EVO (guia "Integração EVO Pay").
			// Vazios: o componente usa a lista e os textos padrão dele.
			var flags = self.gateway.bandeiras;
			if (Array.isArray(flags) && flags.length) element.bandeiras = flags;
			var texts = self.gateway.traducoes;
			if (texts && typeof texts === 'object' && Object.keys(texts).length) element.traducoes = texts;

			// O componente dispara no PRÓPRIO elemento.
			element.addEventListener(EVENT_TOKEN, function (event) {
				self.acceptToken(evoCard(event && event.detail));
			});
			element.addEventListener(EVENT_ERROR, function (event) {
				var detail = event && event.detail;
				self.tokenFailed(typeof detail === 'string' && detail ? detail : (T.cardMissing || T.generic));
			});

			mount.appendChild(element);
			self.cardElement = element;
			if (empty) empty.textContent = '';

			// O componente não tem botão próprio: quem dispara a tokenização é
			// o nosso "Finalizar matrícula" (pay -> 'salvar').
			var pay = self.$('[data-ck-pay]');
			if (pay) pay.disabled = false;
		};

		document.head.appendChild(script);
	};

	/* ---------------- pagamento ---------------- */

	/** Tempo máximo para o adquirente devolver o token. */
	var TOKEN_TIMEOUT_MS = 30000;

	/**
	 * Clique em "Finalizar matrícula".
	 *
	 * 1. pede ao <evo-cartao> que tokenize ('salvar', como no guia oficial
	 *    "Integração EVO Pay"): o cartão vai do navegador ao adquirente;
	 * 2. acceptToken() recebe o token e chama submit(), que cria a venda.
	 *
	 * Token novo a CADA tentativa: o da Pagar.me é de uso único e expira em
	 * poucos minutos; reaproveitar o de uma tentativa recusada falharia.
	 */
	Checkout.prototype.pay = function () {
		var self = this;
		var button = this.$('[data-ck-pay]');
		var label = this.$('[data-ck-pay-label]');
		var spinner = this.$('[data-ck-pay-spinner]');

		// Duplo clique não passa daqui; o servidor tem a própria trava.
		if (this.busy) return;

		if (!this.cardElement) {
			this.showError('card', T.cardMissing || T.generic);
			return;
		}

		// CPF e termos antes do cartão: token só é gerado se a venda pode sair.
		if (!this.report('payment', this.validatePayment())) return;

		if (this.withAddress && !this.report('address', this.validateAddress())) {
			this.go('address');
			return;
		}

		this.busy = true;
		this.card = null;
		if (button) button.disabled = true;
		if (spinner) spinner.hidden = false;
		if (label) label.textContent = T.verifyingIdentity || T.validating || '';
		this.showError('card', '');

		// 1º a identidade (CPF) no servidor; só depois o cartão. Cadastro de
		// outra unidade, ambíguo ou não verificável para AQUI — o cartão nem
		// é tokenizado para uma venda que não pode acontecer.
		var person = {
			document: digits(this.value('document')),
			birthday: this.value('birthday'),
			acceptedTerms: true,
			fullName: this.value('fullName'),
			email: this.value('email'),
			phone: normalizePhone(this.value('phone'))
		};

		this.call('verify', { token: this.token, person: person }).then(function (data) {
			if (!data.ok) {
				self.idle();
				if (data.fallback) {
					self.fallbackTo(data.message);
					return;
				}
				self.say(data.message || T.generic, 'error');
				return;
			}

			self.tokenize();
		});
	};

	/** Pede o token ao <evo-cartao> (só depois da identidade verificada). */
	Checkout.prototype.tokenize = function () {
		var self = this;
		var label = this.$('[data-ck-pay-label]');

		this.awaitingToken = true;
		if (label) label.textContent = T.validatingCard || T.processing || '';

		clearTimeout(this.tokenTimer);
		this.tokenTimer = setTimeout(function () {
			if (self.awaitingToken) self.tokenFailed(T.cardTimeout || T.generic);
		}, TOKEN_TIMEOUT_MS);

		this.cardElement.dispatchEvent(new CustomEvent('salvar'));
	};

	/** Envia a venda com o token recém-gerado. Só chamado por acceptToken(). */
	Checkout.prototype.submit = function () {
		var self = this;
		var button = this.$('[data-ck-pay]');
		var label = this.$('[data-ck-pay-label]');
		var spinner = this.$('[data-ck-pay-spinner]');

		if (!this.card) {
			this.tokenFailed(T.cardMissing || T.generic);
			return;
		}

		if (label && T.processing) label.textContent = T.processing;
		this.say(T.dontClose || '', 'info');

		// Nome, e-mail e WhatsApp já foram para a EVO no lead (1ª etapa); o
		// servidor completa o mesmo cadastro com o CPF.
		var person = {
			document: digits(this.value('document')),
			birthday: this.value('birthday'),
			acceptedTerms: true
		};

		if (this.withAddress) {
			person.zipCode = digits(this.value('zipCode'));
			person.address = this.value('address');
			person.number = this.value('number');
			person.complement = this.value('complement');
			person.neighborhood = this.value('neighborhood');
			person.city = this.value('city');
			person.state = this.value('state').toUpperCase();
		}

		var select = this.$('#contorno-ck-installments');

		this.call('pay', {
			token: this.token,
			person: person,
			card: this.card,
			installments: select ? parseInt(select.value, 10) || 1 : 1
		}).then(function (data) {
			if (spinner) spinner.hidden = true;

			if (data.ok && data.redirect) {
				self.say('');
				self.go('done');
				window.location.assign(data.redirect);
				return;
			}

			self.busy = false;
			if (label) label.textContent = T.payLabel || 'Finalizar matrícula';

			// Preço mudou: o resumo ao lado é repintado e a pessoa vê o valor
			// novo antes de clicar de novo. Não reenviamos sozinhos.
			if (data.error === 'preco_mudou') {
				if (data.summary) self.paint(data.summary);
				self.say(data.message, 'warning');
				return;
			}

			// Indeterminado: a venda pode existir. Botão fica travado e NÃO
			// oferecemos o checkout externo.
			if (data.error === 'indeterminado' || data.error === 'rede') {
				self.say(data.message || T.generic, 'warning');
				self.busy = true;
				return;
			}

			if (data.error === 'em_andamento') {
				self.say(data.message, 'info');
				self.busy = true;
				return;
			}

			if (data.fallback) {
				self.fallbackTo(data.message);
				return;
			}

			// Recusa comum de cartão: a pessoa corrige no componente e clica
			// de novo — o próximo clique gera token novo. O usado é descartado.
			self.card = null;
			self.idle();
			self.say(data.message || T.generic, 'error');
			self.showError('card', '');
		});
	};

	/* ---------------- ligação ---------------- */

	Checkout.prototype.bind = function () {
		var self = this;

		this.$$('[data-contorno-mask]').forEach(function (input) {
			var kind = input.getAttribute('data-contorno-mask');

			if (kind === 'phone') {
				// Digitação e colagem.
				input.addEventListener('input', function () { syncPhone(input, true); });
				// Autofill que só se conclui em change/blur, sem input confiável.
				input.addEventListener('change', function () { syncPhone(input, false); });
				input.addEventListener('blur', function () { syncPhone(input, false); });
				// Autofill do Chrome preenche nome/telefone/e-mail de uma vez e
				// às vezes depois do foco: revalida logo em seguida.
				input.addEventListener('focus', function () {
					setTimeout(function () { syncPhone(input, false); }, 0);
					setTimeout(function () { syncPhone(input, false); }, 120);
				});
				return;
			}

			input.addEventListener('input', function () {
				var atEnd = input.selectionStart === input.value.length;

				if (kind === 'cpf') input.value = maskCpf(input.value);
				else if (kind === 'cep') input.value = maskCep(input.value);

				if (atEnd) input.setSelectionRange(input.value.length, input.value.length);
			});
		});

		this.$$('[data-ck-next]').forEach(function (button) {
			button.addEventListener('click', function () {
				var from = button.getAttribute('data-ck-next');

				if (from === 'data') {
					self.submitData(button);
					return;
				}

				if (from === 'address') {
					if (!self.report('address', self.validateAddress())) return;
					self.go('payment');
					self.mountCard();
				}
			});
		});

		this.$$('[data-ck-back]').forEach(function (button) {
			button.addEventListener('click', function () {
				self.back();
			});
		});

		var pay = this.$('[data-ck-pay]');
		if (pay) {
			pay.addEventListener('click', function () {
				self.pay();
			});
		}

		// Sem aceite dos termos o botão fica com aparência de desabilitado,
		// mas clicável: pay() explica o motivo junto da caixa.
		var terms = this.root.querySelector('[name="acceptedTerms"]');
		if (terms) {
			terms.addEventListener('change', function () {
				self.syncTerms();
				if (terms.checked) self.showError('acceptedTerms', '');
			});
			this.syncTerms();
		}

		// CPF: erro discreto ao sair do campo; some assim que fica válido.
		var cpf = this.root.querySelector('[name="document"]');
		if (cpf) {
			cpf.addEventListener('blur', function () {
				var value = digits(cpf.value);
				self.showError('document', value && !validCpf(value) ? T.cpf : '');
			});
			cpf.addEventListener('input', function () {
				if (validCpf(cpf.value)) self.showError('document', '');
			});
		}
	};

	Checkout.prototype.syncTerms = function () {
		var terms = this.root.querySelector('[name="acceptedTerms"]');
		var pay = this.$('[data-ck-pay]');
		if (pay && terms) pay.setAttribute('aria-disabled', terms.checked ? 'false' : 'true');
	};

	function init() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-contorno-checkout]'), function (root) {
			if (root.dataset.ckReady === '1') return;
			root.dataset.ckReady = '1';

			var checkout = new Checkout(root);
			checkout.bind();
			checkout.go('data');
			checkout.start();
		});
	}

	if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}
})();
