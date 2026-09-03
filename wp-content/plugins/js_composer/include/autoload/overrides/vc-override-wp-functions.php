<?php
/**
 * Override WP core functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

if ( ! function_exists( 'shortcode_exists' ) ) :
	/**
	 * Check if a shortcode is registered in WordPress.
	 *
	 * Examples: shortcode_exists( 'caption' ) - will return true.
	 * shortcode_exists( 'blah' ) - will return false.
	 *
	 * @param bool $shortcode
	 *
	 * @return bool
	 * @since 4.2
	 */
	function shortcode_exists( $shortcode = false ) {
		global $shortcode_tags;

		if ( ! $shortcode ) {
			return false;
		}

		if ( array_key_exists( $shortcode, $shortcode_tags ) ) {
			return true;
		}

		return false;
	}
endif;
