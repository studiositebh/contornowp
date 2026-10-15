<?php
/**
 * Diagnostico da busca de cadastro (somente GET, nada e criado/alterado).
 *
 *   php scripts/diagnose-evo-identify.php
 *
 * Pergunta DNS, token, e-mail, WhatsApp e CPF (opcional) e mostra, para cada busca que o
 * checkout faz (members/basic e prospects por e-mail e por telefone), o que
 * a EVO devolveu e se o cadastro devolvido TEM de fato aquele e-mail/
 * telefone. Depois testa GET /api/v2/members/{id} (perfil com CPF) para
 * cada aluno devolvido: HTTP (200/403) e se o CPF do perfil confere.
 * Dados pessoais saem mascarados; token nunca e exibido. A saida tambem e
 * gravada em %TEMP%/evo-diag-identity.txt.
 *
 * Motivo (15/10/2026): o checkout encontrou o mesmo aluno (idMember 325082,
 * filial 59) para pessoas que nao tem cadastro na EVO.
 */

declare( strict_types = 1 );

$base = rtrim( (string) ( getenv( 'EVO_BASE' ) ?: 'https://evo-integracao-api.w12app.com.br' ), '/' );
$ca   = dirname( __DIR__ ) . '/wp-includes/certificates/ca-bundle.crt';

function ask( string $label, bool $secret = false ): string {
	echo $label . ': ';
	if ( 'Windows' === PHP_OS_FAMILY ) {
		$ps = $secret
			? '$s = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))'
			: '[Console]::ReadLine()';
		$v = (string) shell_exec( 'powershell -NoProfile -Command "' . $ps . '"' );
		if ( $secret ) {
			echo "\n";
		}
		return trim( (string) preg_replace( '/\R/', '', $v ) );
	}
	return trim( (string) fgets( STDIN ) );
}

$dns   = (string) ( getenv( 'EVO_DNS' ) ?: ask( 'DNS EVO' ) );
$token = (string) ( getenv( 'EVO_TOKEN' ) ?: ask( 'Token EVO (oculto)', true ) );
$email = strtolower( trim( (string) ( getenv( 'CK_EMAIL' ) ?: ask( 'E-mail usado no teste' ) ) ) );
$phone = preg_replace( '/\D/', '', (string) ( getenv( 'CK_PHONE' ) ?: ask( 'WhatsApp usado no teste (com DDD)' ) ) ) ?? '';
$cpf   = preg_replace( '/\D/', '', (string) ( getenv( 'CK_CPF' ) ?: ask( 'CPF usado no teste (Enter para pular)' ) ) ) ?? '';
ob_start( static function ( string $chunk ): string {
	file_put_contents( sys_get_temp_dir() . '/evo-diag-identity.txt', $chunk, FILE_APPEND );
	return $chunk;
}, 1 );
@unlink( sys_get_temp_dir() . '/evo-diag-identity.txt' );
if ( str_starts_with( $phone, '55' ) && in_array( strlen( $phone ), array( 12, 13 ), true ) ) {
	$phone = substr( $phone, 2 );
}

function mask_email( string $e ): string {
	if ( ! str_contains( $e, '@' ) ) {
		return '***';
	}
	[ $u, $d ] = explode( '@', $e, 2 );
	return substr( $u, 0, 2 ) . '***@' . $d;
}

function mask_phone( string $p ): string {
	$d = preg_replace( '/\D/', '', $p ) ?? '';
	return strlen( $d ) >= 4 ? '(' . substr( $d, 0, 2 ) . ') ****-' . substr( $d, -4 ) : '****';
}

function mask_cpf( string $c ): string {
	$d = preg_replace( '/\D/', '', $c ) ?? '';
	return 11 === strlen( $d ) ? substr( $d, 0, 3 ) . '.***.***-' . substr( $d, -2 ) : ( '' === $d ? '-' : '***' );
}

function get( string $path, array $query ): array {
	global $base, $ca, $dns, $token;
	usleep( 1600000 );
	$ch = curl_init( $base . $path . '?' . http_build_query( $query ) );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_CAINFO         => $ca,
			CURLOPT_HTTPHEADER     => array( 'Accept: application/json', 'culture: pt-BR', 'Authorization: Basic ' . base64_encode( $dns . ':' . $token ) ),
		)
	);
	$body = (string) curl_exec( $ch );
	$http = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	curl_close( $ch );
	$data = json_decode( $body, true );
	return array( $http, is_array( $data ) ? ( array_is_list( $data ) ? $data : ( $data['list'] ?? $data['lista'] ?? array( $data ) ) ) : array() );
}

$same_phone = static fn ( string $a, string $b ): bool => '' !== $a && '' !== $b && ( str_ends_with( $a, $b ) || str_ends_with( $b, $a ) ) && min( strlen( $a ), strlen( $b ) ) >= 8;

echo "\nE-mail buscado: " . mask_email( $email ) . "   WhatsApp buscado: " . mask_phone( $phone ) . "   CPF buscado: " . mask_cpf( $cpf ) . "\n";
$member_ids = array( 325082 => true ); // aluno do incidente, sempre conferido

$searches = array(
	array( '/api/v1/members/basic', array( 'email' => $email ), 'members/basic por e-mail' ),
	array( '/api/v1/members/basic', array( 'phone' => $phone ), 'members/basic por telefone' ),
	array( '/api/v1/prospects', array( 'email' => $email ), 'prospects por e-mail' ),
	array( '/api/v1/prospects', array( 'phone' => $phone ), 'prospects por telefone' ),
);
if ( 11 === strlen( $cpf ) ) {
	$searches[] = array( '/api/v1/members/basic', array( 'document' => $cpf ), 'members/basic por CPF' );
	$searches[] = array( '/api/v1/prospects', array( 'document' => $cpf ), 'prospects por CPF' );
}

foreach ( $searches as [ $path, $filter, $label ] ) {
	[ $http, $rows ] = get( $path, $filter + array( 'take' => 10 ) );
	echo "\n== $label: HTTP $http, " . count( $rows ) . " registro(s)\n";

	foreach ( array_slice( $rows, 0, 10 ) as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$emails = array();
		$phones = array();
		foreach ( (array) ( $row['contacts'] ?? array() ) as $c ) {
			$desc = (string) ( $c['description'] ?? '' );
			if ( str_contains( $desc, '@' ) ) {
				$emails[] = strtolower( trim( $desc ) );
			} elseif ( '' !== preg_replace( '/\D/', '', $desc ) ) {
				$phones[] = preg_replace( '/\D/', '', $desc );
			}
		}
		if ( ! empty( $row['email'] ) ) {
			$emails[] = strtolower( trim( (string) $row['email'] ) );
		}
		if ( ! empty( $row['cellphone'] ) ) {
			$phones[] = preg_replace( '/\D/', '', (string) $row['cellphone'] );
		}

		if ( (int) ( $row['idMember'] ?? 0 ) > 0 ) {
			$member_ids[ (int) $row['idMember'] ] = true;
		}
		$doc_info = isset( $row['document'] ) ? ' | CPF ' . mask_cpf( (string) $row['document'] ) . ( 11 === strlen( $cpf ) ? ( preg_replace( '/\D/', '', (string) $row['document'] ) === $cpf ? ' (CONFERE)' : ' (nao confere)' ) : '' ) : '';
		$email_ok = in_array( $email, $emails, true );
		$phone_ok = (bool) array_filter( $phones, static fn ( $p ) => $same_phone( (string) $p, $phone ) );

		printf(
			"   %s %s, filial %s | e-mails: %s | telefones: %s | CONFERE COM O BUSCADO? e-mail %s, telefone %s%s\n",
			isset( $row['idMember'] ) && (int) $row['idMember'] > 0 && ! isset( $row['idProspect'] ) ? 'aluno idMember' : ( isset( $row['idProspect'] ) ? 'prospect idProspect' : 'id' ),
			(string) ( $row['idProspect'] ?? $row['idMember'] ?? '?' ),
			(string) ( $row['idBranch'] ?? '?' ),
			implode( ', ', array_map( 'mask_email', array_unique( $emails ) ) ) ?: '-',
			implode( ', ', array_map( 'mask_phone', array_unique( $phones ) ) ) ?: '-',
			$email_ok ? 'SIM' : 'nao',
			$phone_ok ? 'SIM' : 'nao',
			$doc_info
		);
	}
}

echo "\nSe aparecer registro com \"CONFERE ... nao\" nos dois, a EVO devolveu cadastro que NAO tem esse e-mail/telefone (filtro ignorado ou aproximado).\n";

echo "
== Perfil GET /api/v2/members/{id} (prova de identidade do checkout)
";
foreach ( array_keys( $member_ids ) as $id ) {
	[ $http, $rows ] = get( '/api/v2/members/' . $id, array() );
	$p = is_array( $rows[0] ?? null ) ? $rows[0] : array();
	if ( 200 !== $http ) {
		echo "   idMember $id: HTTP $http" . ( in_array( $http, array( 401, 403 ), true ) ? ' — SEM PERMISSAO: o checkout bloqueia (identity_unverifiable)' : '' ) . "
";
		continue;
	}
	$doc = preg_replace( '/\D/', '', (string) ( $p['document'] ?? '' ) ) ?? '';
	printf(
		"   idMember %d: HTTP 200, filial %s, campo document %s, CPF %s%s
",
		$id,
		(string) ( $p['idBranch'] ?? '?' ),
		array_key_exists( 'document', $p ) ? 'presente' : 'AUSENTE',
		mask_cpf( $doc ),
		11 === strlen( $cpf ) ? ( $doc === $cpf ? ' — CONFERE com o CPF buscado' : ' — NAO confere com o CPF buscado' ) : ''
	);
}

echo "
Somente GET. Nada foi criado ou alterado.
";
