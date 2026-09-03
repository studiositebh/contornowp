<?php
/**
 * Param type 'checkbox'
 * Used to create a checkbox field
 *
 * @see https://kb.wpbakery.com/docs/inner-api/vc_map/#vc_map()-ParametersofparamsArray
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

add_filter( 'vc_map_get_param_defaults', 'vc_checkbox_param_defaults', 10, 2 );
if ( ! function_exists( 'vc_checkbox_param_defaults' ) ) :
	/**
	 * Get default value for checkbox.
	 *
	 * @param string $value
	 * @param array $param
	 * @return mixed|string
	 */
	function vc_checkbox_param_defaults( $value, $param ) {
		if ( 'checkbox' === $param['type'] ) {
			$value = '';
			if ( isset( $param['std'] ) ) {
				$value = $param['std'];
			}
		}

		return $value;
	}
endif;
