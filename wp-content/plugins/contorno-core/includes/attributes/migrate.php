<?php
/**
 * Migracao dos atributos: rotulos soltos -> chaves do catalogo.
 *
 * Regras, nesta ordem:
 *  1. NADA e gravado sem que todos os valores daquela unidade tenham sido
 *     mapeados. Um valor desconhecido interrompe SO aquela unidade, que e
 *     reportada com o valor exato — nunca se perde informacao em silencio.
 *  2. A ordem dentro da unidade e preservada tal como esta.
 *  3. E idempotente: rodar de novo sobre chaves ja migradas nao muda nada,
 *     porque a chave resolve para ela mesma.
 *
 * @package ContornoCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executa (ou simula) a migracao.
 *
 * @return array{
 *   units:int, changed:int, unchanged:int, blocked:int, dry_run:bool,
 *   lines:string[], unmapped:array<int,array{unit:string,field:string,value:string}>
 * }
 */
function contorno_attributes_migrate( bool $dry_run = true ): array {
	$report = array(
		'units'     => 0,
		'changed'   => 0,
		'unchanged' => 0,
		'blocked'   => 0,
		'dry_run'   => $dry_run,
		'lines'     => array(),
		'unmapped'  => array(),
	);

	// Semeia o catalogo na primeira execucao, para que exista o que mapear.
	if ( ! is_array( get_option( CONTORNO_ATTRIBUTES_OPTION, null ) ) && ! $dry_run ) {
		contorno_attribute_save_catalog( contorno_attribute_seed() );
		$report['lines'][] = sprintf( 'Catálogo semeado com %d atributos.', count( contorno_attribute_seed() ) );
	}

	$units = get_posts(
		array(
			'post_type'        => CONTORNO_CPT_UNIT,
			'post_status'      => 'any',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		)
	);

	foreach ( $units as $post_id ) {
		$post_id = (int) $post_id;
		++$report['units'];
		$slug = (string) get_post_field( 'post_name', $post_id );

		foreach ( contorno_attribute_types() as $type => $definition ) {
			$field   = (string) $definition['field'];
			$current = array();

			foreach ( contorno_field_list( $field, $post_id ) as $value ) {
				if ( is_string( $value ) && '' !== trim( $value ) ) {
					$current[] = trim( $value );
				}
			}

			if ( array() === $current ) {
				continue;
			}

			$keys    = array();
			$blocked = false;

			foreach ( $current as $value ) {
				$attribute = contorno_attribute_resolve( $type, $value );

				if ( null === $attribute ) {
					$blocked              = true;
					$report['unmapped'][] = array( 'unit' => $slug, 'field' => $field, 'value' => $value );
					continue;
				}

				$keys[] = (string) $attribute['key'];
			}

			if ( $blocked ) {
				++$report['blocked'];
				$report['lines'][] = sprintf( '%s / %s: NÃO migrado — valor fora do catálogo.', $slug, $field );
				continue;
			}

			// Remove repeticao mantendo a primeira ocorrencia (a ordem da unidade).
			$keys = array_values( array_unique( $keys ) );

			if ( $keys === $current ) {
				++$report['unchanged'];
				continue;
			}

			++$report['changed'];
			$report['lines'][] = sprintf(
				'%s %s / %s: %s -> %s',
				$dry_run ? 'Migraria' : 'Migrado',
				$slug,
				$field,
				implode( ' | ', $current ),
				implode( ' | ', $keys )
			);

			if ( ! $dry_run ) {
				contorno_update_field( $post_id, $field, $keys );
			}
		}
	}

	return $report;
}
