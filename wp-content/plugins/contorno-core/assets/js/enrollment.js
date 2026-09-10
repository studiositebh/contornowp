/**
 * Formulário de matrícula — porte de MatriculaForm.tsx.
 *
 * Valida no cliente (mesmas mensagens do React), aplica a máscara de telefone
 * e leva ao destino correto: checkout externo da EVO quando o plano tem URL,
 * ou a página de confirmação quando não tem.
 *
 * A validação server-side existe em paralelo (includes/forms.php); esta camada
 * é conveniência, não a única barreira.
 */
(function () {
	'use strict';

	var MESSAGES = {
		name: 'Informe seu nome completo.',
		email: 'Informe um e-mail válido.',
		phone: 'Informe o telefone com DDD.',
		acceptedTerms: 'É necessário aceitar os termos.'
	};

	function maskPhone(value) {
		var d = String(value || '').replace(/\D/g, '').slice(0, 11);
		if (!d) return '';
		if (d.length <= 2) return '(' + d;
		if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
		if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
		return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
	}

	function bindMask(scope) {
		Array.prototype.forEach.call(
			(scope || document).querySelectorAll('[data-contorno-phone-mask]'),
			function (input) {
				if (input.dataset.maskReady === '1') return;
				input.dataset.maskReady = '1';
				input.addEventListener('input', function () {
					var pos = input.selectionStart === input.value.length;
					input.value = maskPhone(input.value);
					if (pos) input.setSelectionRange(input.value.length, input.value.length);
				});
			}
		);
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

	function validate(form) {
		var errors = {};
		var name = (form.elements.name.value || '').trim();
		var email = (form.elements.email.value || '').trim();
		var phone = (form.elements.phone.value || '').trim();
		var terms = form.elements.acceptedTerms;

		if (name.length < 2) errors.name = MESSAGES.name;
		if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) errors.email = MESSAGES.email;
		if (phone.replace(/\D/g, '').length < 10) errors.phone = MESSAGES.phone;
		if (terms && !terms.checked) errors.acceptedTerms = MESSAGES.acceptedTerms;

		return errors;
	}

	function setup(form) {
		if (form.dataset.enrollReady === '1') return;
		form.dataset.enrollReady = '1';

		bindMask(form);

		var button = form.querySelector('.contorno-enroll__submit');
		var label = form.querySelector('[data-contorno-enroll-label]');

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			clearErrors(form);

			var errors = validate(form);
			var keys = Object.keys(errors);

			if (keys.length) {
				keys.forEach(function (k) {
					showError(form, k, errors[k]);
				});
				var first = form.querySelector('[name="' + keys[0] + '"]');
				if (first && typeof first.focus === 'function') first.focus();
				return;
			}

			if (button) button.disabled = true;
			if (label) label.textContent = 'Redirecionando...';

			// Mesma decisão do React: checkout da EVO quando existe; senão, confirmação.
			var checkout = form.dataset.checkout || '';
			var fallback = form.dataset.fallback || '/matricula/confirmacao/';

			window.location.assign(checkout || fallback);
		});
	}

	function init() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-contorno-enroll]'), setup);
		bindMask(document);
	}

	if (document.readyState !== 'loading') {
		init();
	} else {
		document.addEventListener('DOMContentLoaded', init);
	}

	document.addEventListener('contorno:refresh', init);
})();
