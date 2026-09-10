/**
 * Fale Conosco — porte de ContactPageForm.tsx.
 *
 * Valida no cliente com as mesmas mensagens do React, respeita o rate limit
 * de 60s e abre o `mailto:` com a mensagem pronta — o comportamento aprovado.
 *
 * Se o JavaScript falhar, o formulário faz POST normal e o servidor cuida de
 * nonce, sanitização, validação e rate limit (includes/forms.php).
 */
(function () {
	'use strict';

	var RATE_KEY = 'contorno_contact_last_submit';
	var RATE_MS = 60000;

	var MESSAGES = {
		name: 'Informe seu nome.',
		email: 'Informe um e-mail válido.',
		message: 'A mensagem deve ter pelo menos 10 caracteres.',
		limit: 'Aguarde um momento antes de enviar novamente.'
	};

	function canSubmit() {
		try {
			var last = sessionStorage.getItem(RATE_KEY);
			if (!last) return true;
			return Date.now() - Number(last) > RATE_MS;
		} catch (e) {
			return true;
		}
	}

	function markSubmitted() {
		try {
			sessionStorage.setItem(RATE_KEY, String(Date.now()));
		} catch (e) {
			/* sessionStorage indisponível — não bloqueia o envio */
		}
	}

	function maskPhone(value) {
		var d = String(value || '').replace(/\D/g, '').slice(0, 11);
		if (!d) return '';
		if (d.length <= 2) return '(' + d;
		if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
		if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
		return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
	}

	function showError(form, field, message) {
		var box = form.querySelector('[data-error-for="' + field + '"]');
		if (!box) return;
		box.textContent = message;
		box.hidden = !message;
		var input = form.querySelector('[name="' + field + '"]');
		if (input) {
			if (message) {
				input.setAttribute('aria-invalid', 'true');
			} else {
				input.removeAttribute('aria-invalid');
			}
		}
	}

	function clearErrors(form) {
		Array.prototype.forEach.call(form.querySelectorAll('[data-error-for]'), function (b) {
			b.textContent = '';
			b.hidden = true;
		});
		Array.prototype.forEach.call(form.querySelectorAll('[aria-invalid]'), function (i) {
			i.removeAttribute('aria-invalid');
		});
	}

	function setup(form) {
		if (form.dataset.contactReady === '1') return;
		form.dataset.contactReady = '1';

		Array.prototype.forEach.call(form.querySelectorAll('[data-contorno-phone-mask]'), function (input) {
			input.addEventListener('input', function () {
				input.value = maskPhone(input.value);
			});
		});

		form.addEventListener('submit', function (event) {
			clearErrors(form);

			var name = (form.elements.name.value || '').trim();
			var email = (form.elements.email.value || '').trim();
			var message = (form.elements.message.value || '').trim();

			var errors = {};
			if (name.length < 2) errors.name = MESSAGES.name;
			if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) errors.email = MESSAGES.email;
			if (message.length < 10) errors.message = MESSAGES.message;

			var keys = Object.keys(errors);
			if (keys.length) {
				event.preventDefault();
				keys.forEach(function (k) {
					showError(form, k, errors[k]);
				});
				var first = form.querySelector('[name="' + keys[0] + '"]');
				if (first && typeof first.focus === 'function') first.focus();
				return;
			}

			if (!canSubmit()) {
				event.preventDefault();
				showError(form, 'form', MESSAGES.limit);
				return;
			}

			event.preventDefault();
			markSubmitted();

			/*
			 * A mensagem é montada AQUI, no navegador, e entregue pelo
			 * `mailto:` — igual ao React. O texto nunca passa pelo servidor
			 * nem pela URL de nenhuma requisição.
			 */
			var to = form.dataset.mailto || '';
			if (to) {
				var phone = (form.elements.phone && form.elements.phone.value || '').trim();
				var unit = form.elements.unidade && form.elements.unidade.value;

				var lines = [
					'Nome: ' + name,
					'E-mail: ' + email,
					'Telefone: ' + (phone || 'Não informado')
				];
				if (unit) lines.push('Unidade: ' + unit);
				lines.push('', 'Mensagem:', message);

				window.location.href =
					'mailto:' + to +
					'?subject=' + encodeURIComponent('Contato pelo site — ' + name) +
					'&body=' + encodeURIComponent(lines.join('\n'));
			}

			renderSuccess(form);
		});
	}

	function renderSuccess(form) {
		var wrap = form.closest('.contorno-contact') || form.parentNode;
		var existing = wrap.querySelector('.contorno-contact__notice');
		if (existing) existing.remove();

		var box = document.createElement('div');
		box.className = 'contorno-contact__notice is-success';
		box.setAttribute('role', 'status');

		var to = form.dataset.mailto || '';
		box.innerHTML =
			'<div><p><strong>Mensagem pronta para envio.</strong></p>' +
			'<p>Abrimos seu aplicativo de e-mail com a mensagem preenchida. ' +
			'Se ele não abrir, escreva para o endereço abaixo.</p>' +
			(to ? '<p><a href="mailto:' + to + '">' + to + '</a></p>' : '') +
			'</div>';

		wrap.insertBefore(box, form);
		form.reset();
		box.scrollIntoView({ block: 'nearest' });
	}

	function init() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-contorno-contact]'), setup);
	}

	if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}

	document.addEventListener('contorno:refresh', init);
})();
