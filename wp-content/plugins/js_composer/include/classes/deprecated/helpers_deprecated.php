<?php
/**
 * WPBakery Page Builder deprecated helpers functions.
 *
 * Functions of here are deprecated and will be removed in future releases.
 *
 * @deprecated
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

if ( ! function_exists( 'add_shortcode_param' ) ) :
	/**
	 * Helper function to register new shortcode attribute hook.
	 *
	 * @param string $name - attribute name.
	 * @param callable $form_field_callback - hook, will be called when settings form is shown and attribute added to shortcode param list.
	 * @param string $script_url - javascript file url which will be attached at the end of settings form.
	 *
	 * @return bool
	 * @deprecated due to without prefix name 4.4
	 * @since 4.2
	 */
	function add_shortcode_param( $name, $form_field_callback, $script_url = null ) {
		_deprecated_function( 'add_shortcode_param', '4.4 (will be removed in 6.0)', 'vc_add_shortcode_param' );

		return vc_add_shortcode_param( $name, $form_field_callback, $script_url );
	}
endif;
if ( ! function_exists( 'get_row_css_class' ) ) :
	/**
	 * Get row css class.
	 *
	 * @return mixed|string
	 * @since 4.2
	 * @deprecated 4.2
	 */
	function get_row_css_class() {
		_deprecated_function( 'get_row_css_class', '4.2 (will be removed in 6.0)' );
		$custom = vc_settings()->get( 'row_css_class' );

		return ! empty( $custom ) ? $custom : 'vc_row-fluid';
	}
endif;
if ( ! function_exists( 'vc_generate_dependencies_attributes' ) ) :
	/**
	 * Generate dependencies attributes for shortcode.
	 *
	 * @return string
	 * @deprecated 5.2
	 */
	function vc_generate_dependencies_attributes() {
		_deprecated_function( 'vc_generate_dependencies_attributes', '5.1', '' );

		return '';
	}
endif;
if ( ! function_exists( 'vcExtractDimensions' ) ) :
	/**
	 * Extract width/height from string
	 *
	 * @param string $dimensions WxH.
	 * @return mixed array(width, height) or false.
	 * @since 4.7
	 *
	 * @deprecated since 5.8
	 */
    function vcExtractDimensions( $dimensions ) { // phpcs:ignore
		_deprecated_function( 'vcExtractDimensions', '5.8', 'vc_extract_dimensions' );

		return vc_extract_dimensions( $dimensions );
	}
endif;
if ( ! function_exists( 'fieldAttachedImages' ) ) :
	/**
	 * Get image by attachment id.
	 *
	 * @param array $images IDs or srcs of images.
	 * @return string
	 * @since 4.2
	 * @deprecated since 2019, 5.8
	 */
    function fieldAttachedImages( $images = array() ) { // phpcs:ignore
		_deprecated_function( 'fieldAttachedImages', '5.8', 'vc_field_attached_images' );

		return vc_field_attached_images( $images );
	}
endif;
if ( ! function_exists( 'getVcShared' ) ) :
	/**
	 * Get shared asset.
	 *
	 * @param string $asset
	 *
	 * @return array|string
	 * @deprecated
	 */
    function getVcShared( $asset = '' ) { // phpcs:ignore
		_deprecated_function( 'getVcShared', '5.8', 'vc_get_shared' );

		return vc_get_shared( $asset );
	}
endif;
if ( ! function_exists( 'vc_wp_action' ) ) :
	/**
	 * Return a action param for ajax
	 *
	 * @return bool
	 * @since 4.8
	 * @deprecated 6.1
	 */
	function vc_wp_action() {
		_deprecated_function( 'vc_wp_action', '6.1', 'vc_request_param' );

		return vc_request_param( 'action' );
	}
endif;
if ( ! function_exists( 'set_vc_is_inline' ) ) :
	/**
	 * Set inline mode.
	 *
	 * @param bool $value
	 *
	 * @depreacted 5.2
	 * @since 4.3
	 */
	function set_vc_is_inline( $value = true ) {
		_deprecated_function( 'set_vc_is_inline', '5.2' );
		global $vc_is_inline;
		$vc_is_inline = $value;
	}
endif;
if ( ! function_exists( 'vc_is_editor' ) ) :
	/**
	 * Check is plugin editor;
	 *
	 * @depreacted since 4.8 ( use vc_is_frontend_editor ).
	 * @return bool
	 * @since 4.2
	 */
	function vc_is_editor() {
		_deprecated_function( 'vc_is_editor', '4.8', 'vc_is_frontend_editor' );
		return vc_is_frontend_editor();
	}
endif;
if ( ! function_exists( 'vc_disable_automapper' ) ) :
	/**
	 * Disable automapper.
	 *
	 * @depreacted 7.7 ( use modules settings )
	 * @param bool $disable
	 * @since 4.2
	 */
	function vc_disable_automapper( $disable = true ) {
		_deprecated_function( __FUNCTION__, '7.7', 'Use plugin settings module tab to disable automapper' );
		vc_automapper()->setDisabled( $disable );
	}
endif;
if ( ! function_exists( 'vc_automapper_is_disabled' ) ) :
	/**
	 * Check is automapper disabled.
	 *
	 * @depreacted 7.7 ( use modules settings )
	 * @return bool
	 * @since 4.2
	 */
	function vc_automapper_is_disabled() {
		_deprecated_function( __FUNCTION__, '7.7', 'Use plugin settings module tab to disable automapper' );
		return vc_automapper()->disabled();
	}
endif;
if ( ! function_exists( 'visual_composer' ) ) :
	/**
	 * Alias for wpbakery.
	 *
	 * @return Vc_Base
	 * @since 4.2
	 * @depreacted 5.8, use wpbakery() instead
	 */
	function visual_composer() {
		_deprecated_function( __FUNCTION__, '5.8', 'wpbakery' );
		return wpbakery();
	}
endif;
if ( ! function_exists( 'js_composer_body_class' ) ) :
	/**
	 * Method adds css class to body tag.
	 *
	 * Hooked class method by body_class WP filter. Method adds custom css class to body tag of the page to help
	 * identify and build design specially for VC shortcodes.
	 * Used in wp-content/plugins/js_composer/include/classes/core/class-vc-base.php\Vc_Base\bodyClass.
	 *
	 * @param array $classes
	 *
	 * @return array
	 * @since 4.2
	 * @deprecated 8.5
	 */
	function js_composer_body_class( $classes ) {
		_deprecated_function( __FUNCTION__, '8.5', 'wpb_body_class' );
		return wpb_body_class( $classes );
	}
endif;
if ( ! function_exists( 'vc_set_default_content_for_post_type' ) ) :
	/**
	 * Set default content by post type in editor.
	 *
	 * Data for post type templates stored in settings.
	 *
	 * @param string|null $post_content
	 * @param WP_Post $post
	 * @return string|null
	 * @throws Exception
	 * @deprecated 8.5
	 * @since 4.12
	 */
	function vc_set_default_content_for_post_type( $post_content, $post ) {
		_deprecated_function( __FUNCTION__, '8.5', 'vc_set_default_content_for_post_type_back_editor' );
		return vc_set_default_content_for_post_type_back_editor( $post_content, $post );
	}
endif;
if ( ! function_exists( 'vc_add_css_animation' ) ) :
	/**
	 * Add CSS Animation to element.
	 *
	 * @return mixed|void
	 * @deprecated 4.12
	 */
	function vc_add_css_animation() {
			_deprecated_function( __FUNCTION__, '4.12', 'vc_map_add_css_animation' );
			return vc_map_add_css_animation();
	}
endif;
if ( ! function_exists( 'vc_add_css_animation' ) ) :
	/**
	 * Get configuration for post link controls.
	 *
	 * @return array
	 * @deprecated 8.6
	 */
	function vc_layout_sub_controls() {
		_deprecated_function( __FUNCTION__, '8.6' );
		return [
			[
				'link_post',
				esc_html__( 'Link to post', 'js_composer' ),
			],
			[
				'no_link',
				esc_html__( 'No link', 'js_composer' ),
			],
			[
				'link_image',
				esc_html__( 'Link to bigger image', 'js_composer' ),
			],
		];
	}
endif;
if ( ! function_exists( 'vc_pixel_icons' ) ) :
	/**
	 * Get configuration for pixel_icons shortcode.
	 *
	 * @return array
	 * @deprecated 8.6
	 */
	function vc_pixel_icons() {
		_deprecated_function( __FUNCTION__, '8.6', "vc_get_shared( 'pixel icons' )" );
		return vc_get_shared( 'pixel icons' );
	}
endif;
if ( ! function_exists( 'vc_icons_arr' ) ) :
	/**
	 * Get configuration for icons attribute.
	 *
	 * @return array
	 * @deprecated 8.6
	 */
	function vc_icons_arr() {
		_deprecated_function( __FUNCTION__, '8.6', "vc_get_shared( 'icons arr' )" );
		return vc_get_shared( 'icons arr' );
	}
endif;
if ( ! function_exists( 'vc_size_arr' ) ) :
	/**
	 * Get configuration for sizes attribute.
	 *
	 * @return array
	 * @deprecated 8.6
	 */
	function vc_size_arr() {
		_deprecated_function( __FUNCTION__, '8.6', "vc_get_shared( 'sizes arr' )" );
		return vc_get_shared( 'sizes arr' );
	}
endif;
if ( ! function_exists( 'vc_colors_arr' ) ) :
	/**
	 * Get configuration for colors attribute.
	 *
	 * @return array
	 * @deprecated 8.6
	 */
	function vc_colors_arr() {
		_deprecated_function( __FUNCTION__, '8.6', "vc_get_shared( 'colors arr' )" );
		return vc_get_shared( 'colors arr' );
	}
endif;
if ( ! function_exists( 'vc_target_param_list' ) ) :
	/**
	 * Get configuration for target controls.
	 *
	 * @return array
	 * @deprecated 8.6
	 */
	function vc_target_param_list() {
		_deprecated_function( __FUNCTION__, '8.6', "vc_get_shared( 'target param list' )" );
		return vc_get_shared( 'target param list' );
	}
endif;
