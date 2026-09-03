<?php
/**
 * WPBakery Inner Helper API.
 *
 * Helper functions that can be used by 3 party developers to simplify integration with WPBakery.
 *
 * @see https://kb.wpbakery.com/docs/inner-api
 *
 * @package WPBakeryPageBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

if ( ! function_exists( 'vc_map' ) ) :
	/**
	 * Add your shortcode to the WPBakery elements list.
	 *
	 * @param array $attributes
	 *
	 * @return bool
	 * @throws Exception
	 * @since 4.2
	 */
	function vc_map( $attributes ) {
		if ( ! isset( $attributes['base'] ) ) {
			throw new Exception( esc_html__( 'Wrong vc_map object. Base attribute is required', 'js_composer' ) );
		}

		return WPBMap::map( $attributes['base'], $attributes );
	}
endif;
if ( ! function_exists( 'wpb_map' ) ) :
	/**
	 * This function is alias for vc_map.
	 *
	 * @param array $attributes
	 * @return bool
	 * @throws Exception
	 */
	function wpb_map( $attributes ) {
		return vc_map( $attributes );
	}
endif;
if ( ! function_exists( 'vc_lean_map' ) ) :
	/**
	 * Add your shortcode to the WPBakery elements list with "lazy" method.
	 * It means that attributes for shortcode will be built only when a system uses any data from mapped shortcode.
	 *
	 * @param string $tag
	 * @param string|null $settings_function
	 * @param string|null $settings_file
	 * @since 4.9
	 */
	function vc_lean_map( $tag, $settings_function = null, $settings_file = null ) {
		WPBMap::leanMap( $tag, $settings_function, $settings_file );
	}
endif;
if ( ! function_exists( 'vc_remove_element' ) ) :
	/**
	 * Remove editor element, dropping shortcode of it.
	 *
	 * @param string $shortcode
	 *
	 * @since 4.2
	 */
	function vc_remove_element( $shortcode ) {
		WPBMap::dropShortcode( $shortcode );
	}
endif;
if ( ! function_exists( 'vc_add_param' ) ) :
	/**
	 * Add new shortcode param to existing element.
	 *
	 * @param string $shortcode - tag for shortcode.
	 * @param array $attributes - attribute settings.
	 * @throws Exception
	 * @since 4.2
	 */
	function vc_add_param( $shortcode, $attributes ) {
		WPBMap::addParam( $shortcode, $attributes );
	}
endif;
if ( ! function_exists( 'vc_add_params' ) ) :
	/**
	 * Add multiple params to existing element.
	 *
	 * @param string $shortcode - tag for shortcode.
	 * @param array $attributes - list of attributes arrays.
	 * @throws Exception
	 * @since 4.3
	 */
	function vc_add_params( $shortcode, $attributes ) {
		if ( is_array( $attributes ) ) {
			foreach ( $attributes as $attr ) {
				vc_add_param( $shortcode, $attr );
			}
		}
	}
endif;
if ( ! function_exists( 'vc_get_shortcode' ) ) :
	/**
	 * Get settings of the mapped shortcode.
	 *
	 * @param string $tag
	 *
	 * @return array|null - settings or null if shortcode not mapped.
	 * @throws Exception
	 * @since 4.4.3
	 */
	function vc_get_shortcode( $tag ) {
		return WPBMap::getShortCode( $tag );
	}
endif;
if ( ! function_exists( 'vc_map_update' ) ) :
	/**
	 * Modify shortcode's mapped settings.
	 *
	 * @see WPBMap::modify
	 *
	 * @param string $name
	 * @param string $setting
	 * @param string $value
	 *
	 * @return array|bool
	 * @throws Exception
	 * @since 4.2
	 */
	function vc_map_update( $name = '', $setting = '', $value = '' ) {
		return WPBMap::modify( $name, $setting, $value );
	}
endif;
if ( ! function_exists( 'vc_update_shortcode_param' ) ) :
	/**
	 * Change param attributes of mapped shortcode.
	 *
	 * @see WPBMap::mutateParam.
	 *
	 * @param string $name
	 * @param array $attribute
	 *
	 * @return bool
	 * @throws Exception
	 * @since 4.2
	 */
	function vc_update_shortcode_param( $name, $attribute = [] ) {
		return WPBMap::mutateParam( $name, $attribute );
	}
endif;
if ( ! function_exists( 'vc_map_get_attributes' ) ) :
	/**
	 * Get attributes for shortcode.
	 *
	 * @param string $tag - shortcode tag.
	 * @param array $atts - shortcode attributes.
	 *
	 * @return array - return merged values with provided attributes (
	 *     'a'=>1,'b'=>2 + 'b'=>3,'c'=>4 --> 'a'=>1,'b'=>3 )
	 *
	 * @since 4.6
	 * @throws Exception
	 * @see vc_shortcode_attribute_parse - return union of provided attributes (
	 *     'a'=>1,'b'=>2 + 'b'=>3,'c'=>4 --> 'a'=>1,
	 *     'b'=>3, 'c'=>4 )
	 */
	function vc_map_get_attributes( $tag, $atts = [] ) {
		$atts = shortcode_atts( vc_map_get_defaults( $tag ), $atts, $tag );

		return apply_filters( 'vc_map_get_attributes', $atts, $tag );
	}
endif;
if ( ! function_exists( 'vc_map_get_defaults' ) ) :
	/**
	 * Function to get defaults values for shortcode.
	 *
	 * @param string $tag - shortcode tag.
	 * @return array - list of param=>default_value.
	 * @throws Exception
	 * @since 4.6
	 */
	function vc_map_get_defaults( $tag ) {
		$shortcode = vc_get_shortcode( $tag );
		$params = [];
		if ( is_array( $shortcode ) && ! empty( $shortcode['params'] ) ) {
			$params = vc_map_get_params_defaults( $shortcode['params'] );
		}

		return $params;
	}
endif;
if ( ! function_exists( 'vc_remove_param' ) ) :
	/**
	 * Drop shortcode param.
	 *
	 * @see WPBMap::dropParam
	 *
	 * @param string $name
	 * @param string $attribute_name
	 *
	 * @return bool
	 * @since 4.2
	 */
	function vc_remove_param( $name = '', $attribute_name = '' ) {
		return WPBMap::dropParam( $name, $attribute_name );
	}
endif;
if ( ! function_exists( 'vc_set_as_theme' ) ) :
	/**
	 * Sets plugin as theme plugin.
	 *
	 * @internal param bool $disable_updater - If value is true disables auto updater options.
	 *
	 * @since 4.2
	 */
	function vc_set_as_theme() {
		vc_manager()->setIsAsTheme();
	}
endif;
if ( ! function_exists( 'vc_is_as_theme' ) ) :
	/**
	 * Is VC as-theme-plugin.
	 *
	 * @return bool
	 * @since 4.2
	 */
	function vc_is_as_theme() {
		return vc_manager()->isAsTheme();
	}
endif;
if ( ! function_exists( 'vc_is_updater_disabled' ) ) :
	/**
	 * Check if the plugin updater is disabled.
	 *
	 * @return bool
	 * @since 4.2
	 */
	function vc_is_updater_disabled() {
		return vc_manager()->isUpdaterDisabled();
	}
endif;
if ( ! function_exists( 'vc_remove_all_elements' ) ) :
	/**
	 * Remove all mapped shortcodes at the moment when function is called.
	 *
	 * @since 4.5
	 */
	function vc_remove_all_elements() {
		WPBMap::dropAllShortcodes();
	}
endif;
if ( ! function_exists( 'vc_editor_post_types' ) ) :
	/**
	 * Returns list of post-types where WPBakery Page Builder editor is enabled.
	 *
	 * @return array
	 * @since 4.2
	 */
	function vc_editor_post_types() {
		return vc_manager()->editorPostTypes();
	}
endif;
if ( ! function_exists( 'vc_default_editor_post_types' ) ) :
	/**
	 * Returns list of default post-types where user can use WPBakery Page Builder editors.
	 * Right now by default, it is only for 'page' post-type enabled.
	 *
	 * @return array
	 * @since 4.2
	 */
	function vc_default_editor_post_types() {
		return vc_manager()->editorDefaultPostTypes();
	}
endif;
if ( ! function_exists( 'vc_set_default_editor_post_types' ) ) :
	/**
	 * Set default post-types for WPBakery editor.
	 *
	 * @param array $type_list - list of valid post-types to set.
	 * @since 4.2
	 */
	function vc_set_default_editor_post_types( array $type_list ) {
		vc_manager()->setEditorDefaultPostTypes( $type_list );
	}
endif;
if ( ! function_exists( 'vc_editor_set_post_types' ) ) :
	/**
	 * Set the list of post-types where WPBakery editor is enabled.
	 *
	 * @param array $post_types
	 * @throws Exception
	 * @since 4.4
	 */
	function vc_editor_set_post_types( array $post_types ) {
		vc_manager()->setEditorPostTypes( $post_types );
	}
endif;
if ( ! function_exists( 'vc_set_shortcodes_templates_dir' ) ) :
	/**
	 * Sets directory where WPBakery Page Builder should look for template files for content elements.
	 *
	 * @param string $dir - full directory path to new template directory with trailing slash.
	 * @since 4.2
	 */
	function vc_set_shortcodes_templates_dir( $dir ) {
		vc_manager()->setCustomUserShortcodesTemplateDir( $dir );
	}
endif;
if ( ! function_exists( 'vc_shortcodes_theme_templates_dir' ) ) :
	/**
	 * Get custom theme template path.
	 *
	 * @param string $template - filename for template.
	 *
	 * @return string
	 * @since 4.2
	 */
	function vc_shortcodes_theme_templates_dir( $template ) {
		return vc_manager()->getShortcodesTemplateDir( $template );
	}
endif;
if ( ! function_exists( 'vc_disable_frontend' ) ) :
	/**
	 * Disable frontend editor for WPBakery Page Builder.
	 *
	 * @param bool $disable
	 * @since 4.3
	 */
	function vc_disable_frontend( $disable = true ) {
		vc_frontend_editor()->disableInline( $disable );
	}
endif;
if ( ! function_exists( 'vc_enabled_frontend' ) ) :
	/**
	 * Check is front end editor enabled.
	 * We check it on a Role Manager level.
	 *
	 * @return bool
	 * @throws Exception
	 * @since 4.3
	 */
	function vc_enabled_frontend() {
		return vc_frontend_editor()->frontendEditorEnabled();
	}
endif;
if ( ! function_exists( 'vc_add_default_templates' ) ) :
	/**
	 * Add templates for default templates list.
	 *
	 * @param array $data | template data (name, content, custom_class, image_path).
	 *
	 * @return bool
	 * @since 4.3
	 */
	function vc_add_default_templates( $data ) {
		return wpbakery()->templatesPanelEditor()->addDefaultTemplates( $data );
	}
endif;
if ( ! function_exists( 'vc_map_integrate_shortcode' ) ) :
	/**
	 * Get element shortcode map with custom parameters.
	 *
	 * @sinse 4.4.2
	 * @param array $shortcode
	 * @param string $field_prefix
	 * @param string $group_prefix
	 * @param null|array $change_fields
	 * @param null|array $dependency
	 * @return array
	 * @throws Exception
	 */
	function vc_map_integrate_shortcode( $shortcode, $field_prefix = '', $group_prefix = '', $change_fields = null, $dependency = null ) { // phpcs:ignore:Generic.Metrics.CyclomaticComplexity.TooHigh
		if ( is_string( $shortcode ) ) {
			$shortcode_data = WPBMap::getShortCode( $shortcode );
		} else {
			$shortcode_data = $shortcode;
		}
		if ( is_array( $shortcode_data ) && ! empty( $shortcode_data ) ) {
			// WPBakeryShortCodeFishBones $shortcode - base shortcode.
			$params = ! empty( $shortcode_data['params'] ) ? $shortcode_data['params'] : false;
			if ( is_array( $params ) && ! empty( $params ) ) {
				$keys = array_keys( $params );
				$count = count( $keys );
				for ( $i = 0; $i < $count; $i++ ) {
					$param = &$params[ $keys[ $i ] ]; // Note! passed by reference to automatically update data.
					if ( isset( $change_fields ) ) {
						$param = vc_map_integrate_include_exclude_fields( $param, $change_fields );
						if ( empty( $param ) ) {
							continue;
						}
					}
					if ( ! empty( $group_prefix ) ) {
						if ( isset( $param['group'] ) ) {
							$param['group'] = $group_prefix . ': ' . $param['group'];
						} else {
							$param['group'] = $group_prefix;
						}
					}
					if ( ! empty( $field_prefix ) && isset( $param['param_name'] ) ) {
						$param['param_name'] = $field_prefix . $param['param_name'];
						if ( isset( $param['dependency']['element'] ) && is_array( $param['dependency'] ) ) {
							$param['dependency']['element'] = $field_prefix . $param['dependency']['element'];
						}
						$param = vc_map_integrate_add_dependency( $param, $dependency );

					} elseif ( ! empty( $dependency ) ) {
						$param = vc_map_integrate_add_dependency( $param, $dependency );
					}
					$param['integrated_shortcode'] = is_array( $shortcode ) ? $shortcode['base'] : $shortcode;
					$param['integrated_shortcode_field'] = $field_prefix;
				}
			}

			return is_array( $params ) ? array_filter( $params ) : [];
		}

		return [];
	}
endif;
if ( ! function_exists( 'vc_map_integrate_parse_atts' ) ) :
	/**
	 * Parses and integrates attributes between two shortcodes.
	 *
	 * This function retrieves parameters for a base shortcode and an integrated shortcode,
	 * then processes the provided attributes (`$atts`) based on these parameters. It maps
	 * the attribute values and returns an associative array of the processed attributes.
	 *
	 * @since 4.4.2
	 * @param string $base_shortcode
	 * @param string $integrated_shortcode
	 * @param array $atts
	 * @param string $field_prefix
	 * @return array
	 * @throws Exception
	 */
	function vc_map_integrate_parse_atts( $base_shortcode, $integrated_shortcode, $atts, $field_prefix = '' ) {
		$params = vc_map_integrate_get_params( $base_shortcode, $integrated_shortcode, $field_prefix );
		$data = [];
		if ( is_array( $params ) && ! empty( $params ) ) {
			foreach ( $params as $param ) {
				$value = '';
				if ( isset( $atts[ $param['param_name'] ] ) ) {
					$value = $atts[ $param['param_name'] ];
				}
				if ( isset( $value ) ) {
					$key = $param['param_name'];
					if ( strlen( $field_prefix ) > 0 ) {
						$key = substr( $key, strlen( $field_prefix ) );
					}
					$data[ $key ] = $value;
				}
			}
		}

		return $data;
	}
endif;
if ( ! function_exists( 'vc_add_shortcode_param' ) ) :
	/**
	 * Helper function to create new element param type.
	 *
	 * @see https://kb.wpbakery.com/docs/developers-how-tos/create-new-param-type
	 *
	 * @param string $name - attribute name.
	 * @param callable $form_field_callback - Callback will be called when settings form is shown and attribute added to shortcode
	 *     param list.
	 * @param string $script_url - javascript file url which will be attached at the end of settings form.
	 *
	 * @return bool
	 * @since 4.4
	 */
	function vc_add_shortcode_param( $name, $form_field_callback, $script_url = null ) {
		return WpbakeryShortcodeParams::addField( $name, $form_field_callback, $script_url );
	}
endif;
