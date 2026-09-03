<?php
/**
 * Override PHP functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

if ( ! function_exists( 'lcfirst' ) ) :
	/**
	 * Backward compatibility for mb_strtolower function.
	 * If php version < 5.3 this function is required.
	 *
	 * @param string $str
	 *
	 * @return mixed
	 * @since 4.3, fix #1093
	 */
	function lcfirst( $str ) {
		$str[0] = function_exists( 'mb_strtolower' ) ? mb_strtolower( $str[0] ) : strtolower( $str[0] );

		return $str;
	}
endif;
