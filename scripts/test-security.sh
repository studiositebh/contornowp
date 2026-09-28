#!/usr/bin/env bash
# Testes de regressao das correcoes de seguranca.
#
# Cada bloco corresponde a um achado da auditoria: antes da correcao o teste
# falha, depois passa. Rode contra o staging (padrao) ou contra producao:
#
#   scripts/test-security.sh
#   CONTORNO_TEST_BASE_URL=https://contornodocorpo.com.br scripts/test-security.sh
#
# Somente leitura: nenhuma requisicao aqui altera estado.
set -uo pipefail

BASE_URL="${CONTORNO_TEST_BASE_URL:-https://contornowp.voceconecta.com.br}"
BASE_URL="${BASE_URL%/}"
CURL=(curl --silent --show-error --max-time 30)

pass_count=0
fail_count=0
skip_count=0

pass() { printf '  [OK]        %s\n' "$*"; pass_count=$((pass_count + 1)); }
fail() { printf '  [FALHOU]    %s\n' "$*" >&2; fail_count=$((fail_count + 1)); }
pend() { printf '  [PENDENTE]  %s\n' "$*"; skip_count=$((skip_count + 1)); }

section() { printf '\n== %s\n' "$*"; }

status_of() { "${CURL[@]}" -o /dev/null -w '%{http_code}' "$1"; }

# ---------------------------------------------------------------------------
section "FIX-02  Cabecalhos de seguranca na resposta publica"

headers="$("${CURL[@]}" -D - -o /dev/null "$BASE_URL/")"

check_header() {
	local name="$1"
	local expected="$2"

	if grep -iq "^$name:.*$expected" <<<"$headers"; then
		pass "$name contem '$expected'"
	else
		fail "$name ausente ou sem '$expected'"
	fi
}

check_header 'X-Content-Type-Options' 'nosniff'
check_header 'Referrer-Policy'        'strict-origin-when-cross-origin'
check_header 'X-Frame-Options'        'SAMEORIGIN'
check_header 'Content-Security-Policy' "frame-ancestors 'self'"
check_header 'Permissions-Policy'     'geolocation=()'
check_header 'Strict-Transport-Security' 'max-age='

# ---------------------------------------------------------------------------
section "FIX-03  XML-RPC desligado (multicall / pingback)"

xmlrpc="$("${CURL[@]}" -X POST \
	-d '<methodCall><methodName>system.listMethods</methodName><params></params></methodCall>' \
	"$BASE_URL/xmlrpc.php")"

xmlrpc_status="$(status_of "$BASE_URL/xmlrpc.php")"
if [[ "$xmlrpc_status" == "403" || "$xmlrpc_status" == "404" ]]; then
	pass "/xmlrpc.php recusa a requisicao ($xmlrpc_status)"
else
	fail "/xmlrpc.php responde $xmlrpc_status; esperado 403"
fi

# O que realmente importa: nenhum metodo do WordPress continua chamavel.
# system.multicall so amplifica um ataque de senha se houver um metodo de
# autenticacao (wp.getUsersBlogs) para envolver.
if grep -qE 'wp\.getUsersBlogs|metaWeblog\.|blogger\.' <<<"$xmlrpc"; then
	fail "metodos autenticados do WordPress ainda expostos em /xmlrpc.php"
else
	pass "nenhum metodo autenticado do WordPress exposto (sem amplificacao de senha)"
fi

if grep -qi 'pingback.ping' <<<"$xmlrpc"; then
	fail "pingback.ping ainda exposto (SSRF / amplificacao)"
else
	pass "pingback.ping nao exposto"
fi

if grep -iq '^X-Pingback:' <<<"$headers"; then
	fail "cabecalho X-Pingback ainda anunciado"
else
	pass "cabecalho X-Pingback removido"
fi

# ---------------------------------------------------------------------------
section "FIX-04  Enumeracao de usuarios fechada"

users_body="$("${CURL[@]}" "$BASE_URL/wp-json/wp/v2/users")"

if grep -q '"slug"' <<<"$users_body"; then
	fail "/wp-json/wp/v2/users ainda devolve a lista de usuarios"
else
	pass "/wp-json/wp/v2/users nao devolve usuarios sem login"
fi

author_status="$(status_of "$BASE_URL/?author=1")"
if [[ "$author_status" == "404" ]]; then
	pass "/?author=1 responde 404 (era 301 para /author/<login>/)"
else
	fail "/?author=1 responde $author_status; esperado 404"
fi

sitemap_status="$(status_of "$BASE_URL/wp-sitemap-users-1.xml")"
if [[ "$sitemap_status" == "404" ]]; then
	pass "sitemap de autores removido"
else
	fail "wp-sitemap-users-1.xml responde $sitemap_status; esperado 404"
fi

# ---------------------------------------------------------------------------
section "FIX-05  Versao do WordPress nao anunciada"

home="$("${CURL[@]}" "$BASE_URL/")"

if grep -qiE '<meta name="generator" content="WordPress' <<<"$home"; then
	fail "meta generator ainda expoe a versao do WordPress"
else
	pass "meta generator do WordPress removido"
fi

# ---------------------------------------------------------------------------
section "FIX-01  Busca por CEP continua funcionando (sem regressao)"

cep_page="$("${CURL[@]}" "$BASE_URL/unidades/?q=30140000")"

if grep -qE 'unit-card|contorno-unit' <<<"$cep_page"; then
	pass "/unidades/?q=30140000 ainda lista unidades"
else
	fail "busca por CEP deixou de listar unidades — regressao do orcamento de geocodificacao"
fi

if grep -qE 'raio|Raio' <<<"$cep_page"; then
	pass "seletor de raio presente"
else
	pend "seletor de raio nao encontrado no HTML (pode ser montado pelo JS)"
fi

# ---------------------------------------------------------------------------
section "FIX-08  /matricula/ nao envia dados pessoais na query string"

# A tag <form> ocupa varias linhas no HTML; achata antes de casar.
matricula="$("${CURL[@]}" "$BASE_URL/matricula/")"
matricula_flat="$(tr '\n' ' ' <<<"$matricula")"

if grep -qE '<form[^>]*contorno-enroll__form[^>]*method="post"' <<<"$matricula_flat"; then
	pass 'formulario de matricula usa method="post"'
else
	fail 'formulario de matricula sem method="post" — nome/e-mail/telefone iriam para a URL'
fi

if grep -q 'contorno_nonce' <<<"$matricula"; then
	pass "nonce presente no formulario de matricula"
else
	fail "nonce ausente no formulario de matricula"
fi

if grep -qE 'name="checkout"|[?&]checkout=' <<<"$matricula"; then
	fail "existe parametro 'checkout' na pagina — destino nao deve vir da requisicao"
else
	pass "nenhum parametro de destino na requisicao (checkout resolvido no servidor)"
fi

# ---------------------------------------------------------------------------
section "FIX-09  Cabecalhos tambem no login e no painel"

for alvo in "/wp-login.php" "/wp-admin/"; do
	h="$("${CURL[@]}" -D - -o /dev/null -L "$BASE_URL$alvo")"

	for par in "X-Frame-Options:SAMEORIGIN" "Content-Security-Policy:frame-ancestors" \
	           "X-Content-Type-Options:nosniff" "Referrer-Policy:strict-origin" \
	           "Permissions-Policy:geolocation=()" "Strict-Transport-Security:max-age="; do
		nome="${par%%:*}"
		esperado="${par#*:}"
		if grep -iq "^$nome:.*$esperado" <<<"$h"; then
			pass "$alvo  $nome"
		else
			fail "$alvo  $nome ausente ou sem '$esperado'"
		fi
	done

	# "Nao duplicar headers" e requisito: cada um tem de aparecer UMA vez.
	dups="$(grep -ioE '^(content-security-policy|x-frame-options|x-content-type-options|referrer-policy|permissions-policy|strict-transport-security)' <<<"$h" | sort | uniq -d | paste -sd' ')"
	if [[ -z "$dups" ]]; then
		pass "$alvo  nenhum cabecalho duplicado"
	else
		fail "$alvo  cabecalho duplicado: $dups"
	fi
done

# ---------------------------------------------------------------------------
section "FIX-10  REST: CORS reflete Origin, mas sem leitura autenticada"

# O WordPress reflete qualquer Origin com Allow-Credentials: true. Isso so
# seria exploravel se um pedido com cookie e SEM nonce contasse como
# autenticado. rest_cookie_check_errors() faz wp_set_current_user(0) quando
# nao ha nonce, entao nao conta. Estes testes travam esse comportamento: se
# um plugin desligar o portao do nonce, eles quebram.
ORIGEM_EXTERNA="https://evil.example"

cors_status() { "${CURL[@]}" -o /dev/null -w '%{http_code}' -H "Origin: $ORIGEM_EXTERNA" "$@"; }

s="$(cors_status "$BASE_URL/wp-json/wp/v2/settings")"
if [[ "$s" == "401" || "$s" == "403" ]]; then
	pass "endpoint que exige manage_options nega origem externa sem nonce ($s)"
else
	fail "/wp-json/wp/v2/settings devolveu $s para origem externa sem nonce; esperado 401/403"
fi

corpo_nonce="$("${CURL[@]}" -H "Origin: $ORIGEM_EXTERNA" -H "X-WP-Nonce: nonce-invalido-de-teste" "$BASE_URL/wp-json/wp/v2/posts?per_page=1")"
if grep -q 'rest_cookie_invalid_nonce' <<<"$corpo_nonce"; then
	pass "nonce invalido e recusado (portao do nonce ativo neste servidor)"
else
	fail "nonce invalido NAO foi recusado — rest_cookie_check_errors pode ter sido desligado por um plugin"
fi

s="$(cors_status -X POST -H 'Content-Type: application/json' -d '{"title":"teste"}' "$BASE_URL/wp-json/wp/v2/posts")"
if [[ "$s" == "401" || "$s" == "403" ]]; then
	pass "escrita cross-origin sem nonce negada ($s)"
else
	fail "POST cross-origin sem nonce devolveu $s; esperado 401/403"
fi

# ---------------------------------------------------------------------------
section "PENDENCIAS MANUAIS (precisam de acesso ao servidor / GitHub)"

deploy_status="$(status_of "$BASE_URL/deploy.php")"
if [[ "$deploy_status" == "404" || "$deploy_status" == "403" ]]; then
	pass "/deploy.php removido do webroot"
else
	pend "/deploy.php ainda responde $deploy_status — remover o arquivo e o webhook antigo do GitHub"
fi

# scripts/.htaccess (versionado) bloqueia o diretorio inteiro. Nao e mais
# pendencia manual: se voltar a responder 200, e regressao.
for script_path in scripts/cpanel-deploy.sh scripts/cpanel-auto-update.sh scripts/test-security.sh scripts/test-dataset-evo.sh; do
	s="$(status_of "$BASE_URL/$script_path")"
	if [[ "$s" == "403" || "$s" == "404" ]]; then
		pass "/$script_path nao e baixavel"
	else
		fail "/$script_path responde $s — scripts/.htaccess nao esta valendo"
	fi
done

readme_status="$(status_of "$BASE_URL/readme.html")"
if [[ "$readme_status" == "403" || "$readme_status" == "404" ]]; then
	pass "/readme.html nao e baixavel"
else
	pend "/readme.html responde $readme_status — expoe a versao do WordPress"
fi

wp_version="$("${CURL[@]}" "$BASE_URL/feed/" | grep -oE 'wordpress.org/\?v=[0-9.]+' | head -1 | grep -oE '[0-9.]+$')"
if [[ -n "$wp_version" ]]; then
	pend "versao do WordPress ainda visivel no feed: $wp_version"
else
	pass "versao do WordPress nao exposta no feed"
fi

# ---------------------------------------------------------------------------
printf '\n== Resultado: %d OK, %d falhas, %d pendencias manuais\n' "$pass_count" "$fail_count" "$skip_count"

[[ "$fail_count" -eq 0 ]]
