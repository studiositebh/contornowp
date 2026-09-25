#!/usr/bin/env bash
# Checkout nativo da EVO: seguranca, idempotencia e resolucao server-side.
#
# Roda o codigo REAL do contorno-evo-sync com stubs do WordPress e um cliente
# HTTP falso. Nao toca rede, banco nem staging — as respostas da EVO sao
# fixtures. Pode rodar em qualquer maquina com PHP 8.1.
#
# Alem dos testes de comportamento (em test-evo-native-checkout.php), este
# script faz as verificacoes ESTATICAS que so fazem sentido no repositorio:
# o fluxo antigo continua de pe e nada de credencial vive no frontend.
#
#   scripts/test-evo-native-checkout.sh
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$SCRIPT_DIR/.."
EVO="$ROOT/wp-content/plugins/contorno-evo-sync"
CORE="$ROOT/wp-content/plugins/contorno-core"
PHP_BIN="${CONTORNO_PHP_BIN:-php}"

[[ -d "$EVO" ]]  || { echo "[FALHOU] contorno-evo-sync nao encontrado" >&2; exit 1; }
[[ -d "$CORE" ]] || { echo "[FALHOU] contorno-core nao encontrado" >&2; exit 1; }

static_ok=0
static_fail=0

ok()   { printf '  [OK]       %s\n' "$*"; static_ok=$((static_ok + 1)); }
bad()  { printf '  [FALHOU]   %s\n' "$*" >&2; static_fail=$((static_fail + 1)); }

check_absent() {
	local label="$1"; shift
	local pattern="$1"; shift
	if grep -rniE "$pattern" "$@" >/dev/null 2>&1; then
		bad "$label"
		grep -rniE "$pattern" "$@" | head -3 | sed 's/^/             ! /'
	else
		ok "$label"
	fi
}

check_present() {
	local label="$1"; shift
	local pattern="$1"; shift
	if grep -rniE "$pattern" "$@" >/dev/null 2>&1; then
		ok "$label"
	else
		bad "$label"
	fi
}

echo
echo "== Sintaxe"
syntax_fail=0
while IFS= read -r file; do
	"$PHP_BIN" -l "$file" >/dev/null 2>&1 || { bad "erro de sintaxe: ${file#$ROOT/}"; syntax_fail=1; }
done < <(find "$EVO" "$CORE" -name '*.php' -type f)
[[ "$syntax_fail" == "0" ]] && ok "todos os arquivos PHP dos dois plugins compilam"

if command -v node >/dev/null 2>&1; then
	node --check "$CORE/assets/js/enrollment-native.js" >/dev/null 2>&1 \
		&& ok "enrollment-native.js compila" \
		|| bad "enrollment-native.js tem erro de sintaxe"
else
	printf '  [PENDENTE] node ausente: nao foi possivel validar o JS\n'
fi

echo
echo "== O fluxo atual continua de pe (FASE 29)"

check_present 'contorno_plan_checkout_url ainda existe' 'function contorno_plan_checkout_url' "$CORE/includes/data/units.php"
check_present 'campo checkout_url ainda no registro de planos' "checkout_url" "$CORE/includes/meta/registry.php"
check_present 'urlSale continua sendo lido do EVO' "urlSale" "$EVO/includes/class-sync.php"
check_present 'handler POST de /matricula/ sem JS preservado' 'CONTORNO_ENROLL_ACTION' "$CORE/includes/forms.php"
check_present 'nonce preservado no formulario de matricula' 'wp_nonce_field' "$CORE/includes/shortcodes/enrollment.php"
check_present 'honeypot preservado no formulario de matricula' 'contorno-honeypot' "$CORE/includes/shortcodes/enrollment.php"
check_present 'rate limit do fluxo antigo preservado' 'contorno_rate_limit_hit' "$CORE/includes/forms.php"

echo
echo "== Matricula 100% interna (rodada 'sem redirect externo')"

check_absent 'nenhum CTA publico usa checkout_url como destino (card/hero da unidade)' "enrollment_url *= *'' *!== *contorno_field_text\\( *'checkout_url'|enroll_url *= *'' *!== *contorno_field_text\\( *'checkout_url'" "$CORE/includes/shortcodes/units.php"
check_absent 'forms.php nao redireciona mais pra checkout_url/urlSale (sem JS)' 'wp_redirect\(' "$CORE/includes/forms.php"
check_absent 'JS nativo nao navega mais pro externo em fallbackTo' 'window\.location\.assign\(url\)|window\.location\.assign\(CFG\.fallback\)' "$CORE/assets/js/enrollment-native.js"
check_absent 'JS nativo nao recebe mais URL de fallback externo do PHP' "'fallback' *=> *esc_url_raw" "$CORE/includes/shortcodes/enrollment-native.php"
check_present 'JS nativo usa contato da unidade (WhatsApp), nunca URL externa' 'CFG.contactUrl' "$CORE/assets/js/enrollment-native.js"
check_present 'checkout_url continua gravado (compatibilidade), so nao e mais destino publico' 'function contorno_plan_checkout_url' "$CORE/includes/data/units.php"
check_present 'plano sem selecao mostra etapa de escolha, nunca decide sozinho' 'function contorno_enrollment_plan_picker_markup' "$CORE/includes/shortcodes/enrollment.php"
check_present 'unidade sem checkout nativo ligado mostra aviso interno, nunca redirect' 'function contorno_enrollment_unavailable_markup' "$CORE/includes/shortcodes/enrollment.php"
check_absent 'mensagem de "checkout desligado" nao anuncia mais redirect pra EVO' "Vamos continuar sua matr.cula no ambiente seguro da academia" "$EVO/includes/class-checkout.php"

echo
echo "== Credenciais e cartao nunca chegam ao frontend"

check_absent 'nenhuma credencial EVO no JS do site' 'CONTORNO_EVO_TOKEN|evo_token|Authorization|Basic [A-Za-z0-9+/=]{8}' "$CORE/assets/js"
check_absent 'nenhum campo de PAN/CVV no HTML do checkout' 'name="(cardNumber|cvv|cvc|securityCode|numeroCartao)"' "$CORE/includes/shortcodes"
check_absent 'o JS do checkout nao le numero de cartao nem CVV' '\b(cvv|cvc|securityCode)\b *[:=]' "$CORE/assets/js/enrollment-native.js"
# O core TEM chamadas HTTP legitimas (ViaCEP e Google Geocoding, em
# includes/data/geo.php), entao proibir wp_remote_* seria proibir o que ja
# funciona. O invariante real e outro: o core nao fala com a EVO.
check_absent 'a apresentacao nao chama endpoint da EVO' '/api/v[0-9]+/(sales|prospects|membership|members|configuration|activities)' "$CORE/includes" "$CORE/assets/js"
check_absent 'a apresentacao nao conhece o host da API da EVO' 'evo-integracao-api' "$CORE/includes" "$CORE/assets/js"
check_absent 'a apresentacao nao le credencial da EVO' 'Contorno_Evo_Settings::(token|dns)|CONTORNO_EVO_TOKEN' "$CORE/includes" "$CORE/assets/js"
check_present 'a apresentacao fala com a EVO so pela API interna' 'contorno_evo_checkout_boot_data' "$CORE/includes/shortcodes/enrollment-native.php"
check_present 'toda chamada EVO usa wp_safe_remote_request' 'wp_safe_remote_request' "$EVO/includes/class-client.php"
check_present 'header culture: pt-BR na venda' "'culture'" "$EVO/includes/class-client.php"

echo
echo "== Nada de codigo de pagamento nem gateway fixos no codigo"

check_absent "payment nao e fixado no codigo" "'payment' *=> *[0-9]" "$EVO/includes/class-checkout.php"
check_present 'payment vem da configuracao conferida' 'payment_code_card' "$EVO/includes/class-checkout.php"
check_absent 'gatewayType nao e interpretado por numero fixo' '=== *[0-9]+ *\$?\(?gateway_?type|gateway_type *(===|==) *[0-9]' "$EVO/includes"

echo
echo "== Memberships corrigidos e piloto Lourdes (dataset)"

"$PHP_BIN" -r '
$d = json_decode( file_get_contents( $argv[1] ), true );
$pat = "#/contornodocorpo/(\d+)/site/landing-page/checkout/(\d+)/#i";
$want = [
	"lourdes" => [ "branch" => 21, "plans" => [ "black" => 5809, "premium" => 486, "fit" => 5697 ] ],
];
$fail = 0;
foreach ( $want as $slug => $spec ) {
	foreach ( $d["units"] as $u ) {
		if ( $u["slug"] !== $slug ) { continue; }
		$branch = (int) ( $u["fields"]["evo_branch_id"] ?? 0 );
		printf( "  %s  %-44s -> %s\n", $branch === $spec["branch"] ? "[OK]      " : "[FALHOU]  ", "$slug idBranch = {$spec["branch"]}", $branch );
		$branch === $spec["branch"] || $fail++;
		foreach ( $spec["plans"] as $id => $mid ) {
			$found = 0;
			foreach ( (array) ( $u["fields"]["plans"] ?? [] ) as $p ) {
				if ( (string) ( $p["id"] ?? "" ) !== (string) $id ) { continue; }
				if ( "" !== (string) ( $p["evo_membership_id"] ?? "" ) ) { $found = (int) $p["evo_membership_id"]; }
				elseif ( preg_match( $pat, (string) ( $p["checkout_url"] ?? "" ), $m ) ) { $found = (int) $m[2]; }
			}
			printf( "  %s  %-44s -> %s\n", $found === $mid ? "[OK]      " : "[FALHOU]  ", "$slug/$id idMembership = $mid", $found );
			$found === $mid || $fail++;
		}
	}
}
exit( $fail > 0 ? 1 : 0 );
' "$CORE/data/dataset.json" || static_fail=$((static_fail + 1))

echo
echo "== Comportamento (fixtures, sem rede)"

"$PHP_BIN" "$SCRIPT_DIR/test-evo-native-checkout.php" "$EVO" "$CORE"
behaviour=$?

echo
printf '== Estatico: %d OK, %d falhas\n' "$static_ok" "$static_fail"

if [[ "$static_fail" -gt 0 || "$behaviour" -ne 0 ]]; then
	exit 1
fi

exit 0
