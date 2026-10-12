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
t('bandeiras e traducoes da EVO repassadas ao componente', /element\.bandeiras = flags;/.test(src) && /element\.traducoes = texts;/.test(src));

console.log('\n== Disparo da tokenizacao (guia oficial: o componente nao tem botao)');

const payFn = grab(/Checkout\.prototype\.pay = function \(\) \{[\s\S]*?\n\t\};/, 'pay()');
const acceptFn = grab(/Checkout\.prototype\.acceptToken = function \(payload\) \{[\s\S]*?\n\t\};/, 'acceptToken()');
const submitFn = grab(/Checkout\.prototype\.submit = function \(\) \{[\s\S]*?\n\t\};/, 'submit()');
const mountFn = grab(/Checkout\.prototype\.mountCard = function \(\) \{[\s\S]*?\n\t\};/, 'mountCard()');

t('pay() dispara CustomEvent "salvar" no componente', /this\.cardElement\.dispatchEvent\(new CustomEvent\('salvar'\)\)/.test(payFn));
t('pay() descarta o token anterior (token novo a cada tentativa)', /this\.card = null;/.test(payFn));
t('pay() NAO chama a venda direto: espera o token', !/this\.call\('pay'/.test(payFn));
t('pay() tem tempo limite para o token', /setTimeout\(/.test(payFn) && /tokenFailed/.test(payFn));
t('acceptToken() ignora token que nao foi pedido', /if \(!this\.awaitingToken\) return;/.test(acceptFn));
t('acceptToken() segue para a venda com o token', /this\.submit\(\);/.test(acceptFn));
t('a venda (call pay) so sai de submit()', /this\.call\('pay'/.test(submitFn) && (src.match(/this\.call\('pay'/g) || []).length === 1);
t('erro do componente libera o botao (tokenFailed)', /EVENT_ERROR, function \(event\) \{[\s\S]*?self\.tokenFailed\(/.test(mountFn));
t('botao "Finalizar" habilita quando o componente monta', /self\.cardElement = element;[\s\S]*?pay\.disabled = false;/.test(mountFn));

console.log('\n== Fluxo com lead na 1a etapa (12/10/2026)');

const ctorFn = grab(/function Checkout\(root\) \{[\s\S]*?\n\t\}/, 'Checkout()');
const submitDataFn = grab(/Checkout\.prototype\.submitData = function \(button\) \{[\s\S]*?\n\t\};/, 'submitData()');
const validateDataFn = grab(/Checkout\.prototype\.validateData = function \(\) \{[\s\S]*?\n\t\};/, 'validateData()');

t('1a etapa e "data" (sem etapa de confirmar plano)', /this\.order = \['data'\]/.test(ctorFn) && !/'plan'\]/.test(ctorFn));
t('init abre em "data"', /checkout\.go\('data'\)/.test(src) && !/go\('plan'\)/.test(src));
t('lead vai para a rota "lead" com nome completo, WhatsApp, e-mail e consentimento',
	/this\.call\('lead'/.test(submitDataFn) && /fullName:/.test(submitDataFn) && /phone:/.test(submitDataFn) && /email:/.test(submitDataFn) && /consent: true/.test(submitDataFn));
t('1a etapa NAO pede CPF', !/document/.test(validateDataFn) && !/document/.test(submitDataFn));
t('1a etapa exige nome + sobrenome e consentimento', /errors\.fullName/.test(validateDataFn) && /errors\.consent/.test(validateDataFn));
t('pagamento valida CPF e termos antes de gerar token', /this\.report\('payment', this\.validatePayment\(\)\)/.test(payFn));
t('pagamento nao reenvia nome/e-mail/telefone (ja estao no lead)', !/firstName|lastName|email:|phone:/.test(submitFn));
t('rota antiga "identify" nao e mais usada pelo front', !/this\.call\('identify'/.test(src));

console.log('\n== WhatsApp: normalizacao + mascara (codigo real do arquivo)');

const phoneCtx = {};
vm.runInNewContext([
	grab(/function digits\(value\) \{[\s\S]*?\n\t\}/, 'digits()'),
	grab(/function normalizePhone\(value\) \{[\s\S]*?\n\t\}/, 'normalizePhone()'),
	grab(/function validPhone\(value\) \{[\s\S]*?\n\t\}/, 'validPhone()'),
	grab(/function maskPhone\(value\) \{[\s\S]*?\n\t\}/, 'maskPhone()'),
	grab(/function syncPhone\(field, preserveCursor\) \{[\s\S]*?\n\t\}/, 'syncPhone()'),
	'this.maskPhone = maskPhone; this.validPhone = validPhone; this.normalizePhone = normalizePhone; this.syncPhone = syncPhone;'
].join('\n'), phoneCtx);

const phoneCases = [
	['+55 31 97524-8061', '(31) 97524-8061'],
	['5531975248061', '(31) 97524-8061'],
	['31975248061', '(31) 97524-8061'],
	['(55) 99999-1234', '(55) 99999-1234'],
	['3133334444', '(31) 3333-4444'],
	['+55 (31) 3333-4444', '(31) 3333-4444']
];
phoneCases.forEach(([input, expected]) => {
	const got = phoneCtx.maskPhone(input);
	t('"' + input + '" -> "' + expected + '"', got === expected, got);
});
t('mascara e idempotente (reaplicar nao muda)', phoneCtx.maskPhone('(31) 97524-8061') === '(31) 97524-8061');
t('digitacao parcial: "3197" -> "(31) 97"', phoneCtx.maskPhone('3197') === '(31) 97');
t('mais de 13 digitos NAO e cortado em silencio (fica visivel e invalido)', phoneCtx.maskPhone('55319752480619') === '55319752480619' && !phoneCtx.validPhone('55319752480619'));
t('validPhone: celular, fixo e DDD 55 validos', phoneCtx.validPhone('(31) 97524-8061') && phoneCtx.validPhone('(31) 3333-4444') && phoneCtx.validPhone('(55) 99999-1234'));
t('validPhone: DDD com zero / celular sem 9 / curto invalidos', !phoneCtx.validPhone('(01) 97524-8061') && !phoneCtx.validPhone('(31) 87524-8061') && !phoneCtx.validPhone('(31) 9752'));

// Autofill: o Chrome escreve o valor no campo de uma vez (sem digitar).
const field = { value: '+55 31 97524-8061', selectionStart: 17, setSelectionRange() {} };
phoneCtx.syncPhone(field, false);
t('autofill "+55 31 97524-8061" no campo -> "(31) 97524-8061"', field.value === '(31) 97524-8061', field.value);
t('envio usa o numero nacional normalizado', phoneCtx.normalizePhone(field.value) === '31975248061');

const bindFn = grab(/Checkout\.prototype\.bind = function \(\) \{[\s\S]*?\n\t\};/, 'bind()');
t('WhatsApp sincroniza em input, change, blur e focus (autofill)', ['input', 'change', 'blur', 'focus'].every((ev) => new RegExp("addEventListener\\('" + ev + "', function \\(\\) \\{[^}]*syncPhone|addEventListener\\('" + ev + "', function \\(\\) \\{\\s*setTimeout\\(function \\(\\) \\{ syncPhone").test(bindFn)));
t('submissao revalida o campo antes de validar', /syncPhone\(this\.root\.querySelector\('\[name="phone"\]'\), false\);/.test(validateDataFn));
t('lead envia normalizePhone (DDI nunca duplicado)', /phone: normalizePhone\(this\.value\('phone'\)\)/.test(submitDataFn));

const tpl = fs.readFileSync(path.join(__dirname, '..', 'wp-content', 'plugins', 'contorno-core', 'includes', 'shortcodes', 'enrollment-native.php'), 'utf8');
const phoneField = (tpl.match(/'name' => 'phone'[^\n]*/) || [''])[0];
t('campo: type tel, autocomplete tel-national, inputmode numeric', /'type' => 'tel'/.test(phoneField) && /'autocomplete' => 'tel-national'/.test(phoneField) && /'inputmode' => 'numeric'/.test(phoneField));
t('campo: prefixo +55 visual (fora do input)', /'prefix' => '\+55'/.test(phoneField) && /contorno-input__prefix" aria-hidden="true"/.test(tpl));
t('campo: maxlength nao trunca autofill com DDI (>= 19)', parseInt((phoneField.match(/'maxlength' => '(\d+)'/) || [0, 0])[1], 10) >= 19);

console.log('\n== Etapa de pagamento: CPF, ordem, aceite (12/10/2026)');

const cpfCtx = {};
vm.runInNewContext([
	grab(/function digits\(value\) \{[\s\S]*?\n\t\}/, 'digits()'),
	grab(/function maskCpf\(value\) \{[\s\S]*?\n\t\}/, 'maskCpf()'),
	grab(/function validCpf\(value\) \{[\s\S]*?\n\t\}/, 'validCpf()'),
	'this.validCpf = validCpf; this.maskCpf = maskCpf;'
].join('\n'), cpfCtx);
t('CPF valido (529.982.247-25) aceito', cpfCtx.validCpf('529.982.247-25') && cpfCtx.validCpf('52998224725'));
t('CPF com digito verificador errado recusado', !cpfCtx.validCpf('529.982.247-24'));
t('CPF incompleto recusado', !cpfCtx.validCpf('529.982.247'));
t('CPF repetido (111.111.111-11, 000...) recusado', !cpfCtx.validCpf('111.111.111-11') && !cpfCtx.validCpf('00000000000'));
t('mascara 000.000.000-00', cpfCtx.maskCpf('52998224725') === '529.982.247-25' && cpfCtx.maskCpf('529982247259999') === '529.982.247-25');

const panel = (tpl.match(/data-ck-panel="payment"[\s\S]*?<\/section>/) || [''])[0];
const at = (needle) => panel.indexOf(needle);
const order = ["'name'      => 'document'", "'name' => 'birthday'", 'data-ck-card-mount', 'contorno-ck__secure"', 'contorno-ck__terms--accept', 'data-ck-pay '];
t('ordem: CPF -> nascimento -> cartao -> seguranca -> aceite -> botoes', order.every((n, i) => at(n) > -1 && (i === 0 || at(order[i - 1]) < at(n))), order.map(at).join(' < '));
t('aceite: texto exato', /esc_html__\( 'Li e concordo com os %1\$s e a %2\$s'/.test(panel) && /'termos'/.test(panel) && /'política de privacidade'/.test(panel));
t('aceite: links /termos-de-uso/ e /politica-de-privacidade/ em nova aba seguros',
	/home_url\( '\/termos-de-uso\/' \)/.test(panel) && /home_url\( '\/politica-de-privacidade\/' \)/.test(panel) && (panel.match(/target="_blank" rel="noopener noreferrer"/g) || []).length === 2);
t('aceite: obrigatorio e inicialmente desmarcado', /name="acceptedTerms" value="1" required \/>/.test(panel) && !/name="acceptedTerms"[^>]*checked/.test(panel));
t('aceite separado do aviso de seguranca e do consentimento da etapa 1', at('contorno-ck__terms--accept') > at('contorno-ck__secure"') && !/name="consent"/.test(panel));
t('sem aceite: botao com aria-disabled e motivo explicado', /setAttribute\('aria-disabled', terms\.checked \? 'false' : 'true'\)/.test(src) && /errors\.acceptedTerms = T\.terms/.test(src));
t('CPF: erro discreto ao sair do campo', /cpf\.addEventListener\('blur'/.test(src));
t('rolagem entre etapas desconta o header fixo', /data-contorno-header/.test(src) && /- covered - 16/.test(src));
t('cartao: componente sem estilo proprio recebe o visual do site (CSS escopado)', /\.contorno-ck__card evo-cartao input,/.test(fs.readFileSync(path.join(__dirname, '..', 'wp-content', 'plugins', 'contorno-core', 'assets', 'css', 'components.css'), 'utf8')));

console.log('\n== Resultado: ' + ok + ' OK, ' + fail + ' falhas');
process.exit(fail > 0 ? 1 : 0);
