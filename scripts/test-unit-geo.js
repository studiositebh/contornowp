/**
 * Busca por GPS ("Usar minha localizacao") — contrato do unit-search.js.
 *
 * Carrega o JS REAL (sem navegador) e prova:
 *   - Haversine com fixture conhecida e igual ao contorno_distance_km() do PHP;
 *   - formato da distancia (650 m / 1,8 km / 48 km);
 *   - ordenacao: distancia crescente, empate pela ordem atual, sem coordenada
 *     ao final, 1 unidade, coordenadas invalidas ignoradas;
 *   - privacidade: nenhum storage, log, analytics, beacon ou Google no arquivo;
 *     a unica requisicao e o GET da listagem, sem posicao;
 *   - o cabecalho Permissions-Policy libera geolocation so para a propria origem.
 *
 *   node scripts/test-unit-geo.js
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { execFileSync } = require('child_process');

const plugin = path.join(__dirname, '..', 'wp-content', 'plugins', 'contorno-core');
const jsFile = path.join(plugin, 'assets', 'js', 'unit-search.js');
const src = fs.readFileSync(jsFile, 'utf8');

let ok = 0;
let fail = 0;
function t(name, cond, extra) {
	console.log((cond ? '  [OK]       ' : '  [FALHOU]   ') + name + (extra ? '  -> ' + extra : ''));
	cond ? ok++ : fail++;
}

// Ambiente minimo: o script so registra init() e expoe window.ContornoUnitGeo.
const sandbox = {
	document: {
		readyState: 'complete',
		querySelectorAll: () => [],
		addEventListener: () => {}
	},
	window: {},
	navigator: {},
	URL,
	CustomEvent: function () {}
};
sandbox.window = sandbox;
vm.createContext(sandbox);
vm.runInContext(src, sandbox);

const geo = sandbox.ContornoUnitGeo;
if (!geo) {
	console.error('  [FALHOU]   window.ContornoUnitGeo nao foi exposto');
	process.exit(1);
}

console.log('Haversine');
// Fixtures: Sao Paulo (Se) -> Rio de Janeiro (Centro) ~ 361 km; BH Lourdes -> Savassi < 1 km.
const sp = [-23.5505, -46.6333];
const rio = [-22.9068, -43.1729];
const lourdes = [-19.9319, -43.9386];
const savassi = [-19.9387, -43.9372];

const spRio = geo.haversineKm(sp[0], sp[1], rio[0], rio[1]);
t('Sao Paulo -> Rio ~ 361 km (+-0,5%)', Math.abs(spRio - 360.7) / 360.7 < 0.005, spRio.toFixed(2) + ' km');
t('distancia de um ponto a ele mesmo = 0', geo.haversineKm(sp[0], sp[1], sp[0], sp[1]) === 0);
t('simetrica', Math.abs(geo.haversineKm(sp[0], sp[1], rio[0], rio[1]) - geo.haversineKm(rio[0], rio[1], sp[0], sp[1])) < 1e-9);

// Mesma formula do PHP (contorno_distance_km): conferido com o php real, se houver.
try {
	const php = process.env.CONTORNO_PHP_BIN || 'php';
	const code =
		'$e=6371.0;$f=function($a,$b,$c,$d)use($e){$x=deg2rad($c-$a);$y=deg2rad($d-$b);' +
		'$h=sin($x/2)**2+cos(deg2rad($a))*cos(deg2rad($c))*sin($y/2)**2;return $e*2*atan2(sqrt($h),sqrt(1-$h));};' +
		'echo $f(' + lourdes.join(',') + ',' + savassi.join(',') + ');';
	const phpKm = parseFloat(execFileSync(php, ['-r', code], { encoding: 'utf8' }));
	const jsKm = geo.haversineKm(lourdes[0], lourdes[1], savassi[0], savassi[1]);
	t('JS == PHP (Lourdes -> Savassi)', Math.abs(phpKm - jsKm) < 1e-9, jsKm.toFixed(4) + ' km');
} catch (e) {
	console.log('  [PULADO]   php indisponivel para comparar a formula');
}

console.log('Formato da distancia');
t('0,65 km -> 650 m', geo.formatDistance(0.65) === '650 m', geo.formatDistance(0.65));
t('0,6549 km -> 650 m (10 m)', geo.formatDistance(0.6549) === '650 m');
t('1,8 km -> 1,8 km', geo.formatDistance(1.8) === '1,8 km', geo.formatDistance(1.8));
t('0,9996 km nunca vira "1000 m"', geo.formatDistance(0.9996) === '1,0 km', geo.formatDistance(0.9996));
t('48,2 km -> 48 km', geo.formatDistance(48.2) === '48 km');
t('1234,5 km -> 1.235 km', geo.formatDistance(1234.5) === '1.235 km', geo.formatDistance(1234.5));
t('valor invalido -> vazio', geo.formatDistance(NaN) === '' && geo.formatDistance(-1) === '');

console.log('Coordenadas');
t('aceita lat/lng validos', geo.parseCoord('-19.93', 90) === -19.93);
t('rejeita vazio/null/texto', geo.parseCoord('', 90) === null && geo.parseCoord(null, 90) === null && geo.parseCoord('abc', 90) === null);
t('rejeita fora do intervalo', geo.parseCoord('91', 90) === null && geo.parseCoord('-181', 180) === null);
t('rejeita Infinity', geo.parseCoord('Infinity', 90) === null);

console.log('Ordenacao');
const user = savassi; // visitante na Savassi
const entries = [
	{ id: 'longe', coords: { lat: rio[0], lng: rio[1] }, index: 0 },
	{ id: 'sem', coords: null, index: 1 },
	{ id: 'lourdes', coords: { lat: lourdes[0], lng: lourdes[1] }, index: 2 },
	{ id: 'savassi', coords: { lat: savassi[0], lng: savassi[1] }, index: 3 },
	{ id: 'savassi-b', coords: { lat: savassi[0], lng: savassi[1] }, index: 4 }
];
const ranked = geo.sortByDistance(entries, user[0], user[1]);
const order = ranked.map((r) => r.entry.id).join(',');
t('mais proxima primeiro, sem coordenada por ultimo', order === 'savassi,savassi-b,lourdes,longe,sem', order);
t('empate de distancia respeita a ordem atual', ranked[0].entry.id === 'savassi' && ranked[1].entry.id === 'savassi-b');
t('unidade sem coordenada nao recebe distancia', ranked[ranked.length - 1].distance === null);
t('distancia da Savassi para ela mesma = 0', ranked[0].distance === 0);
const one = geo.sortByDistance([{ id: 'u', coords: { lat: lourdes[0], lng: lourdes[1] }, index: 0 }], user[0], user[1]);
t('1 unidade', one.length === 1 && one[0].distance > 0 && one[0].distance < 1.5);
t('todas sem coordenada mantem a ordem', geo.sortByDistance([{ coords: null, index: 1, id: 'b' }, { coords: null, index: 0, id: 'a' }], 0, 0).map((r) => r.entry.id).join('') === 'ab');

console.log('Privacidade e dependencias');
const code = src.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
t('sem localStorage/sessionStorage/indexedDB/cookie', !/localStorage|sessionStorage|indexedDB|document\.cookie/.test(code));
t('sem console.* (coordenadas nao vao para log)', !/console\./.test(code));
t('sem analytics (dataLayer/gtag/fbq/ga/_paq)', !/dataLayer|gtag\(|fbq\(|\bga\(|_paq|sendBeacon/.test(code));
t('sem XMLHttpRequest e um unico fetch', !/XMLHttpRequest/.test(code) && (code.match(/fetch\(/g) || []).length === 1);
t('sem Google Maps / Places / Distance Matrix / geocoder', !/google|maps\.|places|distancematrix|geocod/i.test(code));
t('fetch so leva a URL da listagem (sem lat/lng)', /fetch\(listUrl\(page\)/.test(code));
t('listUrl nao recebe posicao', /function listUrl\(page\)/.test(code));
t('usa navigator.geolocation.getCurrentPosition', /navigator\.geolocation\.getCurrentPosition/.test(code));
t('nao usa watchPosition (sem rastreio continuo)', !/watchPosition/.test(code));
t('mensagens de erro nao expõem codigos tecnicos', !/PERMISSION_DENIED|POSITION_UNAVAILABLE|TIMEOUT/.test(code));

const security = fs.readFileSync(path.join(plugin, 'includes', 'security.php'), 'utf8');
t('Permissions-Policy: geolocation=(self)', /'Permissions-Policy'\s*=>\s*'geolocation=\(self\),/.test(security));

const units = fs.readFileSync(path.join(plugin, 'includes', 'shortcodes', 'units.php'), 'utf8');
t('botao e <button type="button"> com aria-label e status aria-live', /<button type="button" class="contorno-unit-geo__btn"[^>]*aria-label=/.test(units) && /role="status" aria-live="polite"/.test(units));
t('GPS desligado por padrao e ausente no hero', /'gps'\s*=>\s*'no'/.test(units) && /'hero' !== \$a\['target'\]/.test(units));
t('o servidor nao le coordenadas do visitante', !/\$_(GET|POST|REQUEST)\[\s*'(lat|lng|latitude|longitude)'/.test(units));

console.log('\n' + ok + ' ok, ' + fail + ' falhas');
process.exit(fail ? 1 : 0);
