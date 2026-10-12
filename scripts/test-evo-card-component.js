/**
 * Contrato do <evo-cartao> no checkout nativo (enrollment-native.js).
 *
 * Usa o codigo REAL: extrai CARD_FIELDS, pickCard() e evoCard() do arquivo e
 * roda com o event.detail no formato que o componente oficial emite
 * (evo-pay.js, 09/10/2026). Tambem confere, no texto do arquivo, a montagem
 * do componente: propriedade `gateway`, eventos no proprio elemento.
 *
 *   node scripts/test-evo-card-component.js
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const file = path.join(__dirname, '..', 'wp-content', 'plugins', 'contorno-core', 'assets', 'js', 'enrollment-native.js');
const src = fs.readFileSync(file, 'utf8');

let ok = 0;
let fail = 0;
function t(name, cond, extra) {
	console.log((cond ? '  [OK]       ' : '  [FALHOU]   ') + name + (extra ? '  -> ' + extra : ''));
	cond ? ok++ : fail++;
}

function grab(re, label) {
	const m = src.match(re);
	if (!m) {
		console.error('  [FALHOU]   nao achei ' + label + ' em enrollment-native.js');
		process.exit(1);
	}
	return m[0];
}

const code = [
	grab(/var CARD_FIELDS = \[[\s\S]*?\];/, 'CARD_FIELDS'),
	grab(/function pickCard\(source\) \{[\s\S]*?\n\t\}/, 'pickCard'),
	grab(/function evoCard\(detail\) \{[\s\S]*?\n\t\}/, 'evoCard'),
	'this.evoCard = evoCard;'
].join('\n');

const ctx = {};
vm.runInNewContext(code, ctx);
const evoCard = ctx.evoCard;

console.log('\n== evoCard(): detail do <evo-cartao> -> cardData');

// Exatamente o que dispararEventoTokenGerado() emite.
const detail = {
	nmIgualCartao: 'MARIA DE SOUZA',
	mesVencimento: '05',
	anoVencimento: '30',
	ultimoDigitosCartao: '****1111',
	bandeira: 'VISA',
	token: 'card_tok_abc123',
	tokenFranquia: 'card_tok_franquia',
	numeroParcelas: 3,
	tipo: 'credito',
	idCartaoCliente: null
};
const card = evoCard(detail);

t('token do adquirente -> token', card.token === 'card_tok_abc123');
t('mesmo token -> temporaryToken (como o checkout da EVO faz)', card.temporaryToken === 'card_tok_abc123');
t('tokenFranquia -> branchToken', card.branchToken === 'card_tok_franquia');
t('ultimoDigitosCartao -> truncatedCardNumber', card.truncatedCardNumber === '****1111');
t('bandeira -> brand', card.brand === 'VISA');
t('nmIgualCartao -> cardHolderName', card.cardHolderName === 'MARIA DE SOUZA');
t('mes "05" -> 5', card.cardExpirationMonth === 5);
t('ano de 2 digitos "30" -> 2030', card.cardExpirationYear === 2030);
t('ano de 4 digitos fica como esta', evoCard(Object.assign({}, detail, { anoVencimento: '2031' })).cardExpirationYear === 2031);
t('so campos de CARD_FIELDS saem (tipo, numeroParcelas, idCartaoCliente ficam)',
	Object.keys(card).every((k) => ['token', 'temporaryToken', 'branchToken', 'truncatedCardNumber', 'brand', 'cardHolderName', 'cardExpirationYear', 'cardExpirationMonth'].includes(k)));

const noFranchise = evoCard(Object.assign({}, detail, { tokenFranquia: null }));
t('sem franquia: branchToken ausente (nao "null")', !('branchToken' in noFranchise));

// Mesmo que uma versao futura do componente mande o numero e o CVV, eles
// nao tem para onde ir: evoCard so le os campos acima.
const dirty = evoCard(Object.assign({}, detail, { numeroCartao: '4111111111111111', codigoSeguranca: '123', cvv: '123' }));
const dirtyJson = JSON.stringify(dirty);
t('numero completo do cartao NAO passa', !dirtyJson.includes('4111111111111111'));
t('CVV NAO passa', !dirtyJson.includes('"123"') && !('codigoSeguranca' in dirty) && !('cvv' in dirty));

t('detail vazio/invalido -> objeto vazio', JSON.stringify(evoCard(null)) === '{}' && JSON.stringify(evoCard('x')) === '{}');
t('CVV-only ({}) nao produz token', !evoCard({}).token);

console.log('\n== Montagem do componente');

t('evento de token = eventoTokenGerado', /EVENT_TOKEN = 'eventoTokenGerado'/.test(src));
t('evento de erro = eventoOcorreuErro', /EVENT_ERROR = 'eventoOcorreuErro'/.test(src));
t('escuta no PROPRIO elemento', /element\.addEventListener\(EVENT_TOKEN/.test(src) && /element\.addEventListener\(EVENT_ERROR/.test(src));
t('nao escuta token no document (nomes chutados removidos)',
	!/document\.addEventListener\((?!'DOMContentLoaded')/.test(src) && !/TOKEN_EVENTS|evo-cartao-token|evoCardToken|tokenGenerated|evo-pay-token/.test(src));
t('nao expoe funcao global para receber cartao', !/window\.contornoCheckoutCardToken/.test(src));
t('gateway entra como PROPRIEDADE (objeto), nao atributo', /element\.gateway = config;/.test(src) && !/element\.setAttribute\(/.test(src));
t('tokenizacao forcada no navegador', /element\.flTokenizaBackend = false;/.test(src));
t('detail do evento passa por evoCard()', /acceptToken\(evoCard\(event && event\.detail\)\)/.test(src));
t('nenhum input de cartao no proprio JS', !/name=["']?(cardNumber|numeroCartao|cvv|codigoSeguranca)/i.test(src));

console.log('\n== Resultado: ' + ok + ' OK, ' + fail + ' falhas');
process.exit(fail > 0 ? 1 : 0);
