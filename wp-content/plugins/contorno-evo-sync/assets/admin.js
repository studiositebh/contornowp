/* Contorno EVO Sync — admin. Nunca toca no valor do token além de enviá-lo no POST autenticado. */
(function () {
	'use strict';

	var cfg = window.ContornoEvo || {};

	function post(action, data) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', cfg.nonce || '');
		Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
		return fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body }).then(function (r) { return r.json(); });
	}

	function q(sel, root) { return (root || document).querySelector(sel); }

	// Alterar token: revela o campo password vazio.
	var change = q('[data-evo-change-token]');
	if (change) {
		change.addEventListener('click', function () {
			var field = q('[data-evo-token-field]');
			if (field) { field.hidden = false; var input = field.querySelector('input'); if (input) input.focus(); }
			change.hidden = true;
		});
	}

	// Testar conexão com o que está no formulário (DNS / token novo / base).
	var test = q('[data-evo-test]');
	if (test) {
		test.addEventListener('click', function () {
			var out = q('[data-evo-test-result]');
			var form = q('#contorno-evo-settings');
			out.textContent = (cfg.i18n && cfg.i18n.testing) || '…';
			out.className = 'contorno-evo__test-result';
			test.disabled = true;
			post('contorno_evo_test', {
				dns: form.dns && !form.dns.disabled ? form.dns.value : '',
				token: form.token ? form.token.value : '',
				base_url: form.base_url && !form.base_url.disabled ? form.base_url.value : ''
			}).then(function (res) {
				var d = (res && res.data) || {};
				out.textContent = d.message || ((cfg.i18n && cfg.i18n.failed) || 'Falha');
				out.className = 'contorno-evo__test-result ' + (res.success && !d.failed ? 'is-ok' : 'is-error');
			}).catch(function () {
				out.textContent = (cfg.i18n && cfg.i18n.failed) || 'Falha';
				out.className = 'contorno-evo__test-result is-error';
			}).finally(function () { test.disabled = false; });
		});
	}

	// Buscar filiais no EVO e sugerir por nome (o vínculo é sempre o ID digitado).
	var branches = q('[data-evo-branches]');
	if (branches) {
		branches.addEventListener('click', function () {
			var out = q('[data-evo-branches-result]');
			var list = q('[data-evo-branches-list]');
			out.textContent = (cfg.i18n && cfg.i18n.loading) || '…';
			branches.disabled = true;
			post('contorno_evo_branches', {}).then(function (res) {
				if (!res.success) { out.textContent = (res.data && res.data.message) || cfg.i18n.failed; return; }
				var items = (res.data && res.data.branches) || [];
				out.textContent = items.length ? items.length + ' filiais' : cfg.i18n.noResult;
				if (!items.length) return;
				var norm = function (s) { return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim(); };
				var html = '<table class="widefat striped" style="max-width:720px;margin:8px 0 16px"><thead><tr><th>idBranch</th><th>Filial EVO</th><th>Grupo</th><th>Sugestão (por nome, só para conferir)</th></tr></thead><tbody>';
				var inputs = Array.prototype.slice.call(document.querySelectorAll('[data-evo-branch-input]'));
				items.forEach(function (b) {
					var bn = norm(b.name);
					var match = inputs.filter(function (i) { var t = norm(i.dataset.title); return t && (bn.indexOf(t) !== -1 || t.indexOf(bn) !== -1); });
					var sug = match.map(function (i) { return '<button type="button" class="button-link" data-evo-apply="' + i.name + '" data-evo-id="' + b.id + '">' + i.dataset.title + ' → usar ' + b.id + '</button>'; }).join('<br>');
					html += '<tr><td><code>' + b.id + '</code></td><td>' + (b.name || '') + '</td><td>' + (b.group || '') + '</td><td>' + (sug || '<span class="description">—</span>') + '</td></tr>';
				});
				list.innerHTML = html + '</tbody></table>';
				list.hidden = false;
				list.querySelectorAll('[data-evo-apply]').forEach(function (btn) {
					btn.addEventListener('click', function () {
						var input = document.querySelector('[name="' + btn.dataset.evoApply + '"]');
						if (input) { input.value = btn.dataset.evoId; input.focus(); }
					});
				});
			}).catch(function () { out.textContent = cfg.i18n.failed; }).finally(function () { branches.disabled = false; });
		});
	}

	// Ações de linha: sincronizar uma unidade / restaurar snapshot.
	document.querySelectorAll('[data-evo-sync-one]').forEach(function (btn) {
		btn.addEventListener('click', function (ev) {
			ev.preventDefault();
			var f = q('#contorno-evo-sync-one');
			if (!f) return;
			f.post_id.value = btn.dataset.evoSyncOne;
			f.submit();
		});
	});
	document.querySelectorAll('[data-evo-rollback]').forEach(function (btn) {
		btn.addEventListener('click', function (ev) {
			ev.preventDefault();
			if (!window.confirm('Restaurar a versão anterior dos planos EVO desta unidade?')) return;
			var f = q('#contorno-evo-rollback');
			if (!f) return;
			f.post_id.value = btn.dataset.evoRollback;
			f.submit();
		});
	});
})();
