<?php
/**
 * Regras de pre-venda — porte de src/lib/contorno/preSale.ts.
 *
 * Contrato: quando `status` volta para `open`, TODO elemento de pre-venda
 * desaparece automaticamente. Nenhum template deve checar campos de pre-venda
 * sem passar por contorno_is_pre_sale().
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CONTORNO_PRE_SALE_DEFAULT_LABEL = 'NOVA UNIDADE';
const CONTORNO_PRE_SALE_STATUS_LABEL  = 'PRÉ-VENDA';

function contorno_unit_status( ?int $post_id = null ): string {
	$status = contorno_field_text( 'status', $post_id, 'open' );

	return in_array( $status, array( 'open', 'pre_sale', 'closed' ), true ) ? $status : 'open';
}

function contorno_is_pre_sale( ?int $post_id = null ): bool {
	return 'pre_sale' === contorno_unit_status( $post_id );
}

/**
 * Label da pill sobre a foto. Cai no texto aprovado quando nao houver custom.
 */
function contorno_pre_sale_label( ?int $post_id = null ): string {
	if ( ! contorno_is_pre_sale( $post_id ) ) {
		return '';
	}

	$custom = trim( contorno_field_text( 'presale_label', $post_id ) );

	return '' !== $custom ? $custom : CONTORNO_PRE_SALE_DEFAULT_LABEL;
}

/**
 * Data de abertura formatada por extenso ("15 de outubro de 2026") quando
 * o campo esta em AAAA-MM-DD (input type="date", desde esta rodada). Um
 * valor legado em texto livre (unidade cadastrada antes) passa direto —
 * nunca reescreve o que o cliente ja digitou como texto de verdade.
 */
function contorno_pre_sale_opening_date_label( ?int $post_id = null ): string {
	$raw = trim( contorno_field_text( 'presale_opening_date', $post_id ) );

	if ( '' === $raw ) {
		return '';
	}

	$date = DateTime::createFromFormat( 'Y-m-d', $raw );

	if ( ! ( $date instanceof DateTime ) || $date->format( 'Y-m-d' ) !== $raw ) {
		return $raw;
	}

	return date_i18n( 'j \d\e F \d\e Y', $date->getTimestamp() );
}

/**
 * Linha informativa opcional (pre-inauguracao / data / texto promocional).
 *
 * So retorna conteudo a partir de dados cadastrados — nao inventa inauguracao.
 */
function contorno_pre_sale_info_line( ?int $post_id = null ): string {
	if ( ! contorno_is_pre_sale( $post_id ) ) {
		return '';
	}

	$parts = array_values(
		array_filter(
			array(
				trim( contorno_field_text( 'presale_opening_label', $post_id ) ),
				contorno_pre_sale_opening_date_label( $post_id ),
			),
			static fn ( string $part ): bool => '' !== $part
		)
	);

	if ( array() !== $parts ) {
		return implode( ' • ', $parts );
	}

	return trim( contorno_field_text( 'presale_promo_text', $post_id ) );
}
