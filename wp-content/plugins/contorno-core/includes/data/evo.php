<?php
/**
 * Agenda de aulas coletivas — EVO Totem (W12).
 *
 * Porte de src/lib/contorno/evoAgenda.ts.
 *
 * IMPORTANTE: a grade semanal (horarios nas linhas, dias nas colunas,
 * navegacao de semana, filtros Todos/Manha/Tarde/Noite, botao Filtrar, drawer
 * lateral, vaga disponivel, aula experimental, professor, local) e renderizada
 * pelo proprio widget EVO dentro do iframe. Nao existe dataset de horarios no
 * projeto React — e integracao, nao conteudo. Nunca inventar horarios aqui.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CONTORNO_EVO_TOTEM_BASE = 'https://evo-totem.w12app.com.br/contornodocorpo';

/**
 * Largura minima do viewport do iframe. Abaixo disso o widget EVO troca a grade
 * semanal pela lista vertical; mantemos a grade e rolamos so o container.
 */
const CONTORNO_EVO_GRID_MIN_WIDTH = 1120;
const CONTORNO_EVO_GRID_HEIGHT    = 980;

function contorno_evo_agenda_url( string $branch_id ): string {
	$branch_id = trim( $branch_id );

	if ( '' === $branch_id ) {
		return '';
	}

	return CONTORNO_EVO_TOTEM_BASE . '/' . rawurlencode( $branch_id ) . '/page/landing-page/agenda';
}

/**
 * IDs de filial EVO por slug de unidade.
 *
 * Confirmados no site legado (iframe .../page/landing-page/agenda) ou via
 * branchId extraido de checkout EVO na mesma pagina.
 *
 * Serve como fallback do importador: o valor definitivo fica no campo
 * `evo_branch_id` da unidade, editavel pelo cliente.
 *
 * @return array<string,string>
 */
function contorno_evo_branch_map(): array {
	return array(
		'alfenas'                => '38',
		'centro'                 => '20',
		'planalto'               => '3',
		'planalto-2'             => '44',
		'barreiro'               => '5',
		'betim'                  => '8',
		'sete-lagoas'            => '29',
		'uba'                    => '32',
		'lourdes'                => '21',
		'funcionarios'           => '2',
		'buritis'                => '7',
		'castelo'                => '4',
		'cidade-nova'            => '19',
		'nova-suica'             => '10',
		'bernardo-vasconcelos'   => '60',
		'jacui'                  => '42',
		'floramar'               => '39',
		'parque-real-my-mall'    => '55',
		'betania'                => '33',
		'palmeiras'              => '28',
		'prado'                  => '27',
		'cabral'                 => '53',
		'xangri-la'              => '56',
		'industrial'             => '45',
		'jardim-riacho'          => '15',
		'veneza'                 => '35',
		'lagoa-santa'            => '13',
		'lagoa-santa-2'          => '48',
		'itauna'                 => '17',
		'nova-serrana'           => '14',
		'congonhas'              => '16',
		'ouro-preto'             => '30',
		'bom-despacho'           => '49',
		'patos-de-minas'         => '40',
		'santa-luzia'            => '23',
		'para-de-minas'          => '22',
		'barbacena'              => '24',
		'fleming'                => '46',
		'av-fleming'             => '46',
		'sao-lucas'              => '1',
		'lavras'                 => '52',
		'santo-agostinho-prime'  => '26',
		'raja-gabaglia'          => '62',
		'lafaiete'               => '25',
		'ponte-nova'             => '47',
		'ctn-buritis-prime'      => '50',
		'ctn-castelo'            => '57',
	);
}

function contorno_evo_branch_for_slug( string $slug ): string {
	$map = contorno_evo_branch_map();

	return $map[ $slug ] ?? '';
}

/**
 * URL final da agenda de um post (unidade ou CTN).
 *
 * Precedencia: URL explicita > ID de filial no campo > mapa por slug.
 * Devolve string vazia quando a secao nao deve aparecer.
 */
function contorno_schedule_url( ?int $post_id = null ): string {
	$post_id = $post_id ?: get_the_ID();

	if ( ! $post_id ) {
		return '';
	}

	if ( ! contorno_field( 'classes_enabled', $post_id ) ) {
		return '';
	}

	$explicit = contorno_field_text( 'classes_url', $post_id );
	if ( '' !== $explicit ) {
		return $explicit;
	}

	$branch = contorno_field_text( 'evo_branch_id', $post_id );
	if ( '' === $branch ) {
		$branch = contorno_evo_branch_for_slug( (string) get_post_field( 'post_name', $post_id ) );
	}

	return contorno_evo_agenda_url( $branch );
}
