<?php
/**
 * Backend editor part template.
 *
 * @var string $part
 * @var string $role
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

$options = [
	[
		true,
		esc_html__( 'Enabled', 'js_composer' ),
	],
	[
		'default',
		esc_html__( 'Enabled and default', 'js_composer' ),
	],
];

if ( 'administrator' !== $role ) {
	$options[] = [
		false,
		esc_html__( 'Disabled', 'js_composer' ),
	];
	$capabilities = [
		[
			'disabled_ce_editor',
			esc_html__( 'Disable Classic editor', 'js_composer' ),
		],
	];
}

vc_include_template( 'pages/partials/vc-roles-parts/_part.tpl.php', [
	'part' => $part,
	'role' => $role,
	'params_prefix' => 'vc_roles[' . $role . '][' . $part . ']',
	'controller' => vc_role_access()->who( $role )->part( $part ),
	'capabilities' => $capabilities ?? [],
	'options' => $options,
	'main_label' => esc_html__( 'Backend editor', 'js_composer' ),
	'custom_label' => esc_html__( 'Backend editor', 'js_composer' ),
	'custom_value' => true,
] );
