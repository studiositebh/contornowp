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
 * FALLBACK
 * --------
 * O checkout externo só é oferecido quando a API diz, explicitamente, que
 * nenhuma venda foi criada (`fallback: true`). Depois de uma resposta
 * ambígua o servidor responde `indeterminado` e NÃO manda fallback — mandar
 * cobraria duas vezes.
 *
 * TOKENIZAÇÃO — o que falta homologar
 * -----------------------------------
 * A especificação pública da EVO não documenta o script do EVO Pay nem o nome
 * do evento de "token gerado". Em vez de fixar um chute, escutamos os nomes
 * plausíveis E expomos window.contornoCheckoutCardToken(payload) para o
 * componente chamar. Na homologação confirma-se qual dos dois caminhos a EVO
 * usa e o outro pode sair.
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

	var TOKEN_EVENTS = ['evo-cartao-token', 'evoCardToken', 'tokenGenerated', 'evo-pay-token'];

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

	function maskPhone(value) {
		var d = digits(value).slice(0, 11);
		if (!d) return '';
		if (d.length <= 2) return '(' + d;
		if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
		if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
		return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
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
		this.order = ['plan', 'data'].concat(this.withAddress ? ['address'] : [], ['payment', 'done']);
		this.current = 'plan';

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
		var top = this.root.getBoundingClientRect().top + window.pageYOffset - 24;
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

	Checkout.prototype.fallbackTo = function (message) {
		var url = CFG.fallback;
		this.say(message || T.generic, 'warning');

		if (url) {
			window.location.assign(url);
		}
	};

	/* ---------------- etapa 1 ---------------- */

	Checkout.prototype.start = function () {
		var self = this;
		var loading = this.$('[data-ck-loading]');
		var actions = this.$('[data-ck-plan-actions]');

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

			if (actions) actions.hidden = false;
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

	Checkout.prototype.validateData = function () {
		var errors = {};

		if (this.value('firstName').length < 2) errors.firstName = T.required;
		if (this.value('lastName').length < 2) errors.lastName = T.required;
		if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(this.value('email'))) errors.email = T.email;

		var phone = digits(this.value('phone'));
		if (phone.length < 10 || phone.length > 11) errors.phone = T.phone;

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
		if (label && T.validating) label.textContent = T.validating;

		var restore = function () {
			if (button) button.disabled = false;
			if (label) label.textContent = previous;
		};

		// Identificar antes de pagar: se a pessoa já é aluna ou já é prospect,
		// a venda usa o cadastro dela em vez de criar outro.
		this.call('identify', {
			token: this.token,
			email: this.value('email'),
			phone: digits(this.value('phone')),
			document: digits(this.value('document'))
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

	Checkout.prototype.acceptToken = function (payload) {
		var card = pickCard(payload);

		if (!card.token && !card.temporaryToken) return;

		this.card = card;
		this.showError('card', '');

		var pay = this.$('[data-ck-pay]');
		if (pay) pay.disabled = false;

		var empty = this.$('[data-ck-card-empty]');
		if (empty) empty.hidden = true;
	};

	Checkout.prototype.mountCard = function () {
		var self = this;
		var mount = this.$('[data-ck-card-mount]');
		var empty = this.$('[data-ck-card-empty]');

		if (!mount || mount.getAttribute('data-ready') === '1') return;

		var url = this.gateway && this.gateway.scriptUrl;

		if (!url) {
			// Sem o componente oficial não existe caminho seguro de cartão
			// nesta página — e construir um formulário próprio postaria PAN
			// para o WordPress. Continua no checkout da academia.
			if (empty) empty.textContent = T.cardMissing || T.generic;
			this.fallbackTo(T.cardMissing);
			return;
		}

		mount.setAttribute('data-ready', '1');

		// Caminho 1: o componente dispara um evento com o token.
		TOKEN_EVENTS.forEach(function (name) {
			document.addEventListener(name, function (event) {
				self.acceptToken((event && (event.detail || event.data)) || {});
			});
		});

		// Caminho 2: o componente chama uma função global.
		window.contornoCheckoutCardToken = function (payload) {
			self.acceptToken(payload || {});
		};

		var script = document.createElement('script');
		script.src = url;
		script.async = true;
		script.onerror = function () {
			if (empty) empty.textContent = T.cardMissing || T.generic;
			self.fallbackTo(T.cardMissing);
		};
		script.onload = function () {
			var element = document.createElement('evo-cartao');

			// Só dado público do gateway vai para o componente: o servidor já
			// filtrou gatewayData por lista fechada antes de mandar para cá.
			Object.keys(self.gateway.data || {}).forEach(function (key) {
				element.setAttribute(key.replace(/[A-Z]/g, function (c) {
					return '-' + c.toLowerCase();
				}), String(self.gateway.data[key]));
			});

			mount.appendChild(element);
			if (empty) empty.textContent = '';
		};

		document.head.appendChild(script);
	};

	/* ---------------- pagamento ---------------- */

	Checkout.prototype.pay = function () {
		var self = this;
		var button = this.$('[data-ck-pay]');
		var label = this.$('[data-ck-pay-label]');
		var spinner = this.$('[data-ck-pay-spinner]');

		// Duplo clique não passa daqui; o servidor tem a própria trava.
		if (this.busy) return;

		if (!this.card) {
			this.showError('card', T.cardMissing || T.generic);
			return;
		}

		if (this.withAddress && !this.report('address', this.validateAddress())) {
			this.go('address');
			return;
		}

		this.busy = true;
		if (button) button.disabled = true;
		if (spinner) spinner.hidden = false;
		if (label && T.processing) label.textContent = T.processing;
		this.say(T.dontClose || '', 'info');

		var person = {
			firstName: this.value('firstName'),
			lastName: this.value('lastName'),
			email: this.value('email'),
			phone: digits(this.value('phone')),
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
			if (label) label.textContent = 'Finalizar matrícula';

			// Preço mudou: o resumo é repintado e a pessoa precisa confirmar de
			// novo. Não reenviamos sozinhos com o valor novo.
			if (data.error === 'preco_mudou') {
				if (data.summary) self.paint(data.summary);
				self.say(data.message, 'warning');
				self.go('plan');
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

			// Recusa comum de cartão: a pessoa corrige e tenta de novo.
			if (button) button.disabled = false;
			self.say(data.message || T.generic, 'error');
			self.showError('card', '');
			self.card = null;
			if (button) button.disabled = true;
		});
	};

	/* ---------------- ligação ---------------- */

	Checkout.prototype.bind = function () {
		var self = this;

		this.$$('[data-contorno-mask]').forEach(function (input) {
			input.addEventListener('input', function () {
				var kind = input.getAttribute('data-contorno-mask');
				var atEnd = input.selectionStart === input.value.length;

				if (kind === 'phone') input.value = maskPhone(input.value);
				else if (kind === 'cpf') input.value = maskCpf(input.value);
				else if (kind === 'cep') input.value = maskCep(input.value);

				if (atEnd) input.setSelectionRange(input.value.length, input.value.length);
			});
		});

		this.$$('[data-ck-next]').forEach(function (button) {
			button.addEventListener('click', function () {
				var from = button.getAttribute('data-ck-next');

				if (from === 'plan') {
					self.go('data');
					return;
				}

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
	};

	function init() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-contorno-checkout]'), function (root) {
			if (root.dataset.ckReady === '1') return;
			root.dataset.ckReady = '1';

			var checkout = new Checkout(root);
			checkout.bind();
			checkout.go('plan');
			checkout.start();
		});
	}

	if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}
})();
