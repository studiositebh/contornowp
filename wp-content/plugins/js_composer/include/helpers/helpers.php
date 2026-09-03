<?php
/**
 * WPBakery Page Builder helpers functions.
 *
 * We use helper functions inside our plugin core for simple duplication actions.
 *
 * @package WPBakeryPageBuilder
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

// Check if this file is loaded in js_composer.
if ( ! defined( 'WPB_VC_VERSION' ) ) {
	die( '-1' );
}

if ( ! function_exists( 'wpb_get_keyboard_modifier_key' ) ) :
	/**
	 * Get the keyboard modifier key based on user's platform.
	 *
	 * @return string 'Cmd' for Mac users, 'Ctrl' for others
	 * @since 8.6
	 */
	function wpb_get_keyboard_modifier_key() {
		$is_mac = ( isset( $_SERVER['HTTP_USER_AGENT'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 'Mac' ) );
		return $is_mac ? 'Cmd' : 'Ctrl';
	}
endif;

if ( ! function_exists( 'wpb_get_title_with_shortcut' ) ) :
	/**
	 * Get title with keyboard shortcut based on settings and platform.
	 *
	 * @param string $title Base title text.
	 * @return string Title with or without shortcut based on settings
	 * @since 8.6
	 */
	function wpb_get_title_with_shortcut( $title ) {
		// Check if shortcuts are enabled.
		if ( ! Vc_Settings::areShortcutsEnabled() ) {
			return $title;
		}

		$hotkey_list = vc_get_shared( 'shortcuts' );
		if ( ! array_key_exists( $title, $hotkey_list ) ) {
			return $title;
		}

		$shortcut = $hotkey_list[ $title ];

		// Replace %s with the modifier key if present.
		if ( strpos( $shortcut, '%s' ) !== false ) {
			$mod_key = wpb_get_keyboard_modifier_key();
			$shortcut = sprintf( $shortcut, $mod_key );
		}

		// Return title with shortcut in parentheses.
		/* translators: %1$s: Button title, %2$s: Keyboard shortcut */
		return sprintf( __( '%1$s (%2$s)', 'js_composer' ), $title, $shortcut );
	}
endif;

if ( ! function_exists( 'vc_include_template' ) ) :
	/**
	 * Include template from templates dir.
	 *
	 * @param string $template
	 * @param array $variables - passed variables to the template.
	 *
	 * @param bool $once
	 *
	 * @return mixed
	 * @since 4.3
	 */
	function vc_include_template( $template, $variables = [], $once = false ) {
		is_array( $variables ) && extract( $variables );
		if ( $once ) {
			return require_once vc_template( $template );
		} else {
			return require vc_template( $template );
		}
	}
endif;
if ( ! function_exists( 'vc_get_template' ) ) :
	/**
	 * Output template from templates dir.
	 *
	 * @param string $template
	 * @param array $variables - passed variables to the template.
	 *
	 * @param bool $once
	 *
	 * @return string
	 * @since 4.4
	 */
	function vc_get_template( $template, $variables = [], $once = false ) {
		ob_start();
		$output = vc_include_template( $template, $variables, $once );

		if ( 1 === $output ) {
			$output = ob_get_contents();
		}

		ob_end_clean();

		return $output;
	}
endif;
if ( ! function_exists( 'vc_post_param' ) ) :
	/**
	 * Get param value from $_POST if exists.
	 *
	 * @param string $param
	 * @param mixed $default_value
	 *
	 * @param bool $check
	 * @return null|string - null for undefined param.
	 * @since 4.2
	 */
	function vc_post_param( $param, $default_value = null, $check = false ) {
		if ( 'admin' === $check ) {
			check_admin_referer();
		} elseif ( 'ajax' === $check ) {
			check_ajax_referer();
		}

        // phpcs:ignore
        return isset( $_POST[ $param ] ) ? $_POST[ $param ] : $default_value;
	}
endif;
if ( ! function_exists( 'vc_get_param' ) ) :
	/**
	 * Get param value from $_GET if exists.
	 *
	 * @param string $param
	 * @param mixed $default_value
	 *
	 * @param bool $check
	 * @return null|string - null for undefined param.
	 * @since 4.2
	 */
	function vc_get_param( $param, $default_value = null, $check = false ) {
		if ( 'admin' === $check ) {
			check_admin_referer();
		} elseif ( 'ajax' === $check ) {
			check_ajax_referer();
		}

        // @codingStandardsIgnoreLine
        return isset( $_GET[ $param ] ) ? $_GET[ $param ] : $default_value;
	}
endif;
if ( ! function_exists( 'vc_request_param' ) ) :
	/**
	 * Get param value from $_REQUEST if exists.
	 *
	 * @param string $param
	 * @param mixed $default_value
	 *
	 * @param bool $check
	 * @return mixed - null for undefined param.
	 * @since 4.4
	 */
	function vc_request_param( $param, $default_value = null, $check = false ) {
		if ( 'admin' === $check ) {
			check_admin_referer();
		} elseif ( 'ajax' === $check ) {
			check_ajax_referer();
		}

        // @codingStandardsIgnoreLine
        return isset( $_REQUEST[ $param ] ) ? $_REQUEST[ $param ] : $default_value;
	}
endif;
if ( ! function_exists( 'vc_file_get_contents' ) ) :
	/**
	 * Get file content.
	 *
	 * @param string $filename
	 *
	 * @return bool|mixed|string
	 * @since 4.4.3 used in vc_base when getting a custom css output.
	 */
	function vc_file_get_contents( $filename ) {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem( false, false, true );
		}
		// WP_Filesystem_Base $wp_filesystem - global variable.
		$output = '';
		if ( is_object( $wp_filesystem ) ) {
			$output = $wp_filesystem->get_contents( $filename );
		}

		if ( ! $output ) {
            // @codingStandardsIgnoreLine
            $output = file_get_contents( $filename );
		}

		return $output;
	}
endif;
if ( ! function_exists( 'vc_user_roles_get_all' ) ) :
	/**
	 * Get all user roles.
	 *
	 * @return array
	 * @throws Exception
	 */
	function vc_user_roles_get_all() {
		require_once vc_path_dir( 'SETTINGS_DIR', 'class-vc-roles.php' );
		$vc_roles = new Vc_Roles();
		$capabilities = [];
		foreach ( $vc_roles->getParts() as $part ) {
			$part_obj = vc_user_access()->part( $part );
			$capabilities[ $part ] = [
				'state' => ( is_multisite() && is_super_admin() ) ? true : $part_obj->getState(),
				'state_key' => $part_obj->getStateKey(),
				'capabilities' => $part_obj->getAllCaps(),
			];
		}

		return $capabilities;
	}
endif;
if ( ! function_exists( 'vc_generate_nonce' ) ) :
	/**
	 * Generate nonce.
	 *
	 * @param string|array $data
	 * @param bool $from_esi
	 *
	 * @return string
	 */
	function vc_generate_nonce( $data, $from_esi = false ) {
		if ( ! $from_esi && ! vc_is_frontend_editor() ) {
			if ( method_exists( 'LiteSpeed_Cache_API', 'esi_enabled' ) && LiteSpeed_Cache_API::esi_enabled() ) {
				if ( method_exists( 'LiteSpeed_Cache_API', 'v' ) && LiteSpeed_Cache_API::v( '1.3' ) ) {
					$params = [ 'data' => $data ];

					return LiteSpeed_Cache_API::esi_url( 'js_composer', 'WPBakery Page Builder', $params, 'default', true );// The last parameter is to remove ESI comment wrapper.
				}
			}
		}

		return wp_create_nonce( is_array( $data ) ? ( 'vc-nonce-' . implode( '|', $data ) ) : ( 'vc-nonce-' . $data ) );
	}
endif;
if ( ! function_exists( 'vc_hook_esi' ) ) :
	/**
	 * Output ESI nonce.
	 *
	 * @param array $params
	 */
	function vc_hook_esi( $params ) {
		$data = $params['data'];
		echo vc_generate_nonce( $data, true ); // phpcs:ignore:WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
endif;
if ( ! function_exists( 'vc_verify_nonce' ) ) :
	/**
	 * Verify nonce.
	 *
	 * @param string $nonce
	 * @param array|string $data
	 *
	 * @return bool
	 */
	function vc_verify_nonce( $nonce, $data ) {
		return (bool) wp_verify_nonce( $nonce, ( is_array( $data ) ? ( 'vc-nonce-' . implode( '|', $data ) ) : ( 'vc-nonce-' . $data ) ) );
	}
endif;
if ( ! function_exists( 'vc_verify_admin_nonce' ) ) :
	/**
	 * Verify admin nonce.
	 *
	 * @param string $nonce
	 *
	 * @return bool
	 */
	function vc_verify_admin_nonce( $nonce = '' ) {
		return (bool) vc_verify_nonce( ! empty( $nonce ) ? $nonce : vc_request_param( '_vcnonce' ), 'vc-admin-nonce' );
	}
endif;
if ( ! function_exists( 'vc_verify_public_nonce' ) ) :
	/**
	 * Verify public nonce.
	 *
	 * @param string $nonce
	 *
	 * @return bool
	 */
	function vc_verify_public_nonce( $nonce = '' ) {
		return (bool) vc_verify_nonce( ( ! empty( $nonce ) ? $nonce : vc_request_param( '_vcnonce' ) ), 'vc-public-nonce' );
	}
endif;
if ( ! function_exists( 'vc_check_post_type' ) ) :
	/**
	 * Check if post type can be editable with WPBakery by current user.
	 *
	 * @param string $type
	 * @return bool|mixed|void
	 * @throws Exception
	 */
	function vc_check_post_type( $type = '' ) {
		if ( empty( $type ) ) {
			$type = get_post_type();
		}
		$valid = apply_filters( 'vc_check_post_type_validation', null, $type );
		if ( is_null( $valid ) ) {
			if ( is_multisite() && is_super_admin() ) {
				return true;
			}
			$current_user = wp_get_current_user();
			$all_caps = $current_user->get_role_caps();
			$cap_key = vc_user_access()->part( 'post_types' )->getStateKey();
			$state = null;
			if ( array_key_exists( $cap_key, $all_caps ) ) {
				$state = $all_caps[ $cap_key ];
			}
			if ( false === $state ) {
				return false;
			}

			if ( null === $state ) {
				return in_array( $type, vc_default_editor_post_types(), true );
			}

			return in_array( $type, vc_editor_post_types(), true );
		}

		return $valid;
	}
endif;
if ( ! function_exists( 'vc_user_access_check_shortcode_edit' ) ) :
	/**
	 * Check if user have edit access level to specific shortcode.
	 *
	 * @throws Exception
	 * @param string $shortcode
	 * @return bool|mixed|void
	 */
	function vc_user_access_check_shortcode_edit( $shortcode ) {
        // phpcs:ignore:WordPress.NamingConventions.ValidHookName.UseUnderscores
		$do_check = apply_filters( 'vc_user_access_check-shortcode_all', null, $shortcode );

		if ( ! is_null( $do_check ) ) {
			return $do_check;
		}

		return vc_get_user_shortcode_access( $shortcode, 'edit' );
	}
endif;
if ( ! function_exists( 'vc_user_access_check_shortcode_all' ) ) :
	/**
	 * Check if user have all access levels to specific shortcode.
	 *
	 * @param string $shortcode
	 * @return bool|mixed|void
	 * @throws Exception
	 */
	function vc_user_access_check_shortcode_all( $shortcode ) {
        // phpcs:ignore:WordPress.NamingConventions.ValidHookName.UseUnderscores
		$do_check = apply_filters( 'vc_user_access_check-shortcode_all', null, $shortcode );

		if ( ! is_null( $do_check ) ) {
			return $do_check;
		}

		return vc_get_user_shortcode_access( $shortcode );
	}
endif;
if ( ! function_exists( 'vc_get_user_shortcode_access' ) ) :
	/**
	 * Get user access to shortcode.
	 *
	 * Note you can set access to specific shortcode in plugin settings 'Role Manager' tab.
	 *
	 * @since 7.9
	 * @param string $shortcode
	 * @param string $access_level right now we have 2 levels: 'all' and 'edit'.
	 * @return bool
	 * @throws Exception
	 */
	function vc_get_user_shortcode_access( $shortcode, $access_level = 'all' ) {
		if ( is_multisite() && is_super_admin() ) {
			return true;
		}

		$shortcodes_part = vc_user_access()->part( 'shortcodes' );
		if ( 'edit' === $access_level ) {
			$state_check = $shortcodes_part->checkStateAny( true, 'edit', null )->get();
			if ( $state_check ) {
				return true;
			} else {
				return $shortcodes_part->canAny( $shortcode . '_all', $shortcode . '_edit' )->get();
			}
		} else {
			return $shortcodes_part->checkStateAny( true, 'custom', null )->can( $shortcode . '_all' )->get();
		}
	}
endif;
if ( ! function_exists( 'vc_htmlspecialchars_decode_deep' ) ) :
	/**
	 * Call the htmlspecialchars_decode to a given multilevel array.
	 *
	 * @param mixed $value The value to be stripped.
	 *
	 * @return mixed Stripped value.
	 * @since 4.8
	 */
	function vc_htmlspecialchars_decode_deep( $value ) {
		if ( is_array( $value ) ) {
			$value = array_map( 'vc_htmlspecialchars_decode_deep', $value );
		} elseif ( is_object( $value ) ) {
			$vars = get_object_vars( $value );
			foreach ( $vars as $key => $data ) {
				$value->{$key} = vc_htmlspecialchars_decode_deep( $data );
			}
		} elseif ( is_string( $value ) ) {
			$value = htmlspecialchars_decode( $value );
		}

		return $value;
	}
endif;
if ( ! function_exists( 'vc_str_remove_protocol' ) ) :
	/**
	 * Remove protocol from string.
	 *
	 * @param string $str
	 * @return mixed
	 */
	function vc_str_remove_protocol( $str ) {
		return str_replace( [
			'https://',
			'http://',
		], '//', $str );
	}
endif;
if ( ! function_exists( 'wpb_get_current_theme_slug' ) ) :
	/**
	 * Get current theme slug (actually the directory name).
	 *
	 * When child theme is in use will return the parent's slug.
	 *
	 * @return string
	 */
	function wpb_get_current_theme_slug() {
		$theme  = wp_get_theme();
		$parent = $theme->parent();
		if ( $parent instanceof WP_Theme ) {
			return $parent->get_stylesheet();
		}

		return $theme->get_stylesheet();
	}
endif;
if ( ! function_exists( 'vc_get_dropdown_option' ) ) :
	/**
	 * Get dropdown option.
	 *
	 * @param array $param
	 * @param array $value
	 *
	 * @return mixed|string
	 * @since 4.2
	 */
	function vc_get_dropdown_option( $param, $value ) {
		if ( '' === $value && is_array( $param['value'] ) ) {
			$value = array_shift( $param['value'] );
		}
		if ( is_array( $value ) ) {
			reset( $value );
			$value = isset( $value['value'] ) ? $value['value'] : current( $value );
		}
		$value = is_string( $value ) ? $value : '';
		$value = preg_replace( '/\s/', '_', $value );

		return ( '' !== $value ? $value : '' );
	}
endif;
if ( ! function_exists( 'vc_get_css_color' ) ) :
	/**
	 * Get css color.
	 *
	 * @param string $prefix
	 * @param string $color
	 *
	 * @return string
	 * @since 4.2
	 */
	function vc_get_css_color( $prefix, $color ) {
		$rgb_color = preg_match( '/rgba/', $color ) ? preg_replace( [
			'/\s+/',
			'/^rgba\((\d+)\,(\d+)\,(\d+)\,([\d\.]+)\)$/',
		], [
			'',
			'rgb($1,$2,$3)',
		], $color ) : $color;
		$string = $prefix . ':' . $rgb_color . ';';
		if ( $rgb_color !== $color ) {
			$string .= $prefix . ':' . $color . ';';
		}

		return $string;
	}
endif;
if ( ! function_exists( 'vc_shortcode_custom_css_class' ) ) :
	/**
	 * Get shortcode custom css class.
	 *
	 * @param string $param_value
	 * @param string $prefix
	 *
	 * @return string
	 * @since 4.2
	 */
	function vc_shortcode_custom_css_class( $param_value, $prefix = '' ) {
		$css_class = preg_match( '/\s*\.([^\{]+)\s*\{\s*([^\}]+)\s*\}\s*/', $param_value ) ? $prefix . preg_replace( '/\s*\.([^\{]+)\s*\{\s*([^\}]+)\s*\}\s*/', '$1', $param_value ) : '';

		return $css_class;
	}
endif;
if ( ! function_exists( 'vc_shortcode_custom_css_has_property' ) ) :
	/**
	 * Checks if certain custom CSS shortcode property exists.
	 *
	 * @param string $subject
	 * @param array|string $property
	 * @param bool|false $strict
	 *
	 * @return bool
	 * @since 4.9
	 */
	function vc_shortcode_custom_css_has_property( $subject, $property, $strict = false ) {
		$styles = [];
		$pattern = '/\{([^\}]*?)\}/i';
		preg_match( $pattern, $subject, $styles );
		if ( array_key_exists( 1, $styles ) ) {
			$styles = explode( ';', $styles[1] );
		}
		$new_styles = [];
		foreach ( $styles as $val ) {
			$val = explode( ':', $val );
			if ( is_array( $property ) ) {
				foreach ( $property as $prop ) {
					$pos = strpos( $val[0], $prop );
					$full = ( $strict ) ? ( 0 === $pos && strlen( $val[0] ) === strlen( $prop ) ) : true;
					if ( false !== $pos && $full ) {
						$new_styles[] = $val;
					}
				}
			} else {
				$pos = strpos( $val[0], $property );
				$full = ( $strict ) ? ( 0 === $pos && strlen( $val[0] ) === strlen( $property ) ) : true;
				if ( false !== $pos && $full ) {
					$new_styles[] = $val;
				}
			}
		}

		return ! empty( $new_styles );
	}
endif;
if ( ! function_exists( 'wpb_getImageBySize' ) ) :
	/**
	 * Get image by size.
	 *
	 * @param array $params
	 *
	 * @return array|bool
	 * @since 4.2
	 * vc_filter: vc_wpb_getimagesize - to override output of this function.
	 */
    function wpb_getImageBySize( $params = array() ) { // phpcs:ignore
		$params = array_merge( [
			'post_id' => null,
			'attach_id' => null,
			'thumb_size' => 'thumbnail',
			'class' => '',
		], $params );

		if ( ! $params['thumb_size'] ) {
			$params['thumb_size'] = 'thumbnail';
		}

		if ( ! $params['attach_id'] && ! $params['post_id'] ) {
			return false;
		}

		$post_id = $params['post_id'];

		$attach_id = $post_id ? get_post_thumbnail_id( $post_id ) : $params['attach_id'];
		$attach_id = apply_filters( 'wpml_object_id', $attach_id, 'attachment', true );
		$thumb_size = $params['thumb_size'];
		$thumb_class = ( isset( $params['class'] ) && '' !== $params['class'] ) ? $params['class'] . ' ' : '';

		global $_wp_additional_image_sizes;
		$thumbnail = '';

		$sizes = [
			'thumbnail',
			'thumb',
			'medium',
			'large',
			'full',
		];
		if ( is_string( $thumb_size ) && ( ( ! empty( $_wp_additional_image_sizes[ $thumb_size ] ) && is_array( $_wp_additional_image_sizes[ $thumb_size ] ) ) || in_array( $thumb_size, $sizes, true ) ) ) {
			$attachment = get_post( $attach_id );
			$title = trim( wp_strip_all_tags( $attachment->post_title ) );
			$attributes = [
				'class' => $thumb_class . 'attachment-' . $thumb_size,
				'title' => $title,
				'alt'   => trim( esc_attr( do_shortcode( get_post_meta( $attach_id, '_wp_attachment_image_alt', true ) ) ) ),
			];

			$thumbnail = wp_get_attachment_image( $attach_id, $thumb_size, false, $attributes );
		} elseif ( $attach_id ) {
			if ( is_string( $thumb_size ) ) {
				preg_match_all( '/\d+/', $thumb_size, $thumb_matches );
				if ( isset( $thumb_matches[0] ) ) {
					$thumb_size = [];
					$count = count( $thumb_matches[0] );
					if ( $count > 1 ) {
						$thumb_size[] = $thumb_matches[0][0]; // width.
						$thumb_size[] = $thumb_matches[0][1]; // height.
					} elseif ( 1 === $count ) {
						$thumb_size[] = $thumb_matches[0][0]; // width.
						$thumb_size[] = $thumb_matches[0][0]; // height.
					} else {
						$thumb_size = false;
					}
				}
			}
			if ( is_array( $thumb_size ) ) {
				// Resize image to custom size.
				$p_img = wpb_resize( $attach_id, null, $thumb_size[0], $thumb_size[1], true );
				$alt = trim( esc_attr( do_shortcode( get_post_meta( $attach_id, '_wp_attachment_image_alt', true ) ) ) );
				$attachment = get_post( $attach_id );
				if ( ! empty( $attachment ) ) {
					$title = trim( wp_strip_all_tags( $attachment->post_title ) );

					if ( empty( $alt ) ) {
						$alt = trim( wp_strip_all_tags( $attachment->post_excerpt ) ); // If not, Use the Caption.
					}
					if ( empty( $alt ) ) {
						$alt = $title;
					}
					if ( $p_img ) {
						$attributes = [
							'class' => $thumb_class,
							'src' => $p_img['url'],
							'width' => $p_img['width'],
							'height' => $p_img['height'],
							'alt' => $alt,
							'title' => $title,
						];

						$attributes = vc_stringify_attributes( vc_add_lazy_loading_attribute( $attributes ) );

						$thumbnail = '<img ' . $attributes . ' />';
					}
				}
			}
		}

		$p_img_large = wp_get_attachment_image_src( $attach_id, 'large' );

		return apply_filters( 'vc_wpb_getimagesize', [
			'thumbnail' => $thumbnail,
			'p_img_large' => $p_img_large,
		], $attach_id, $params );
	}
endif;
if ( ! function_exists( 'wpb_get_image_data_by_source' ) ) :
	/**
	 * Get image data by source where image obtained from.
	 *
	 * @since 7.4
	 * @param string $source
	 * @param int $post_id
	 * @param int $image_id
	 * @param string $img_size
	 * @return array
	 */
	function wpb_get_image_data_by_source( $source, $post_id, $image_id, $img_size ) { // phpcs:ignore:Generic.Metrics.CyclomaticComplexity.TooHigh
		$image_src = '';
		switch ( $source ) {
			case 'media_library':
			case 'featured_image':
				if ( 'featured_image' === $source ) {
					if ( $post_id && has_post_thumbnail( $post_id ) ) {
						$img_id = get_post_thumbnail_id( $post_id );
					} else {
						$img_id = 0;
					}
				} else {
					$img_id = preg_replace( '/[^\d]/', '', $image_id );
				}

				if ( ! $img_size ) {
					$img_size = 'thumbnail';
				}

				if ( $img_id ) {
					$image_src = wp_get_attachment_image_src( $img_id, $img_size );
					if ( $image_src ) {
						$image_src = $image_src[0];
					}
				}
				$alt_text = get_post_meta( $img_id, '_wp_attachment_image_alt', true );

				break;

			case 'external_link':
				if ( ! empty( $params['custom_src'] ) ) {
					$image_src = $params['custom_src'];
				}
				$alt_text = '';
				break;
		}

		return [
			'image_src' => $image_src,
			'image_alt' => $alt_text,
		];
	}
endif;
if ( ! function_exists( 'vc_add_lazy_loading_attribute' ) ) :
	/**
	 * Add `loading` attribute with param lazy to attribute list.
	 *
	 * @param array $attributes
	 * @return array
	 * @since 7.1
	 */
	function vc_add_lazy_loading_attribute( $attributes ) {
		if ( ! is_array( $attributes ) ) {
			$attributes = [];
		}

		if ( function_exists( 'wp_lazy_loading_enabled' ) && wp_lazy_loading_enabled( 'img', 'the_content' ) ) {
			$attributes['loading'] = 'lazy';
		}

		return $attributes;
	}
endif;
if ( ! function_exists( 'vc_get_image_by_size' ) ) :
	/**
	 * Get image by size.
	 *
	 * @param int $id
	 * @param string $size
	 * @return array|false|mixed|string
	 */
	function vc_get_image_by_size( $id, $size ) {
		global $_wp_additional_image_sizes;

		$sizes = [
			'thumbnail',
			'thumb',
			'medium',
			'large',
			'full',
		];
		if ( is_string( $size ) && ( ( ! empty( $_wp_additional_image_sizes[ $size ] ) && is_array( $_wp_additional_image_sizes[ $size ] ) ) || in_array( $size, $sizes, true ) ) ) {
			return wp_get_attachment_image_src( $id, $size );
		} else {
			if ( is_string( $size ) ) {
				preg_match_all( '/\d+/', $size, $thumb_matches );
				if ( isset( $thumb_matches[0] ) ) {
					$size = [];
					$count = count( $thumb_matches[0] );
					if ( $count > 1 ) {
						$size[] = $thumb_matches[0][0]; // width.
						$size[] = $thumb_matches[0][1]; // height.
					} elseif ( 1 === $count ) {
						$size[] = $thumb_matches[0][0]; // width.
						$size[] = $thumb_matches[0][0]; // height.
					} else {
						$size = false;
					}
				}
			}
			if ( is_array( $size ) ) {
				// Resize image to custom size.
				$p_img = wpb_resize( $id, null, $size[0], $size[1], true );

				return $p_img['url'];
			}
		}

		return '';
	}
endif;
if ( ! function_exists( 'wpb_translateColumnWidthToFractional' ) ) :
	/**
	 * Convert vc_col-sm-3 to 1/4
	 *
	 * @param string $width
	 *
	 * @return string
	 * @since 4.2
	 */
    function wpb_translateColumnWidthToFractional( $width ) { // phpcs:ignore
		switch ( $width ) {
			case 'vc_col-sm-2':
				$w = '1/6';
				break;
			case 'vc_col-sm-3':
				$w = '1/4';
				break;
			case 'vc_col-sm-4':
				$w = '1/3';
				break;
			case 'vc_col-sm-6':
				$w = '1/2';
				break;
			case 'vc_col-sm-8':
				$w = '2/3';
				break;
			case 'vc_col-sm-9':
				$w = '3/4';
				break;
			case 'vc_col-sm-12':
				$w = '1/1';
				break;

			default:
				$w = is_string( $width ) ? $width : '1/1';
		}

		return $w;
	}
endif;
if ( ! function_exists( 'wpb_translateColumnWidthToSpan' ) ) :
	/**
	 * Column width to span translate.
	 *
	 * @param string $width
	 *
	 * @return bool|string
	 * @since 4.2
	 */
    function wpb_translateColumnWidthToSpan( $width ) { // phpcs:ignore
		$output = $width;
		preg_match( '/(\d+)\/(\d+)/', $width, $matches );

		if ( ! empty( $matches ) ) {
			$part_x = (int) $matches[1];
			$part_y = (int) $matches[2];
			if ( $part_x > 0 && $part_y > 0 ) {
				$value = ceil( $part_x / $part_y * 12 );
				if ( $value > 0 && $value <= 12 ) {
					$output = 'vc_col-sm-' . $value;
				}
			}
		}
		if ( preg_match( '/\d+\/5$/', $width ) ) {
			$output = 'vc_col-sm-' . $width;
		}

		return apply_filters( 'vc_translate_column_width_class', $output, $width );
	}
endif;
if ( ! function_exists( 'wpb_js_remove_wpautop' ) ) :
	/**
	 * Remove wpautop from content.
	 *
	 * @param string $content
	 * @param bool $autop
	 *
	 * @return string
	 * @since 4.2
	 */
	function wpb_js_remove_wpautop( $content, $autop = false ) {

		if ( $autop ) {
			$content = wpautop( preg_replace( '/<\/?p\>/', "\n", $content ) . "\n" );
		}

		return do_shortcode( shortcode_unautop( $content ) );
	}
endif;
if ( ! function_exists( 'vc_siteAttachedImages' ) ) :
	/**
	 *  Helper function which returns list of site attached images, and if image is attached to the current post it adds class 'added'
	 *
	 * @param array $att_ids
	 *
	 * @return string
	 * @since 4.11
	 */
    function vc_siteAttachedImages( $att_ids = array() ) { // phpcs:ignore
		$output = '';

		$limit = (int) apply_filters( 'vc_site_attached_images_query_limit', - 1 );
		$media_images = get_posts( 'post_type=attachment&orderby=ID&numberposts=' . $limit );
		foreach ( $media_images as $image_post ) {
			$thumb_src = wp_get_attachment_image_src( $image_post->ID );
			$thumb_src = $thumb_src[0];

			$class = ( in_array( $image_post->ID, $att_ids, true ) ) ? ' class="added"' : '';

			$output .= '<li' . $class . '>
						<img rel="' . esc_attr( $image_post->ID ) . '" src="' . esc_url( $thumb_src ) . '" />
						<span class="img-added">' . esc_html__( 'Added', 'js_composer' ) . '</span>
					</li>';
		}

		if ( '' !== $output ) {
			$output = '<ul class="gallery_widget_img_select">' . $output . '</ul>';
		}

		return $output;
	}
endif;
if ( ! function_exists( 'vc_field_attached_images' ) ) :
	/**
	 * Get attached images to the list.
	 *
	 * @param array $images IDs or srcs of images.
	 *
	 * @return string
	 * @since 5.8
	 */
	function vc_field_attached_images( $images = [] ) {
		$output = '';

		foreach ( $images as $image ) {
			if ( is_numeric( $image ) ) {
				$thumb_src = wp_get_attachment_image_src( $image );
				$thumb_src = isset( $thumb_src[0] ) ? $thumb_src[0] : '';
			} else {
				$thumb_src = $image;
			}

			if ( $thumb_src ) {
				$output .= '
                <li class="added">
                    <img rel="' . esc_attr( $image ) . '" src="' . esc_url( $thumb_src ) . '" />
                    <a href="javascript:;" class="vc_icon-remove"><i class="vc-composer-icon vc-c-icon-close"></i></a>
                </li>';
			}
		}

		return $output;
	}
endif;
if ( ! function_exists( 'wpb_removeNotExistingImgIDs' ) ) :
	/**
	 * Remove not existing image IDs.
	 *
	 * @param null|string $param_value
	 *
	 * @return array
	 * @since 4.2
	 */
    function wpb_removeNotExistingImgIDs( $param_value ) { // phpcs:ignore
		$param_value = is_null( $param_value ) ? '' : $param_value;
		$tmp = explode( ',', $param_value );
		$return_ar = [];
		foreach ( $tmp as $id ) {
			if ( wp_get_attachment_image( $id ) ) {
				$return_ar[] = $id;
			}
		}
		$tmp = implode( ',', $return_ar );

		return $tmp;
	}
endif;
if ( ! function_exists( 'wpb_resize' ) ) :
	/**
	 * Resize images dynamically using wp built-in functions.
	 *
	 * @param int $attach_id
	 * @param string $img_url
	 * @param int $width
	 * @param int $height
	 * @param bool $crop
	 *
	 * @return array
	 * @since 4.2
	 */
	function wpb_resize( $attach_id, $img_url, $width, $height, $crop = false ) { // phpcs:ignore:Generic.Metrics.CyclomaticComplexity.TooHigh
		// this is an attachment, so we have the ID.
		$image_src = [];
		if ( $attach_id ) {
			$image_src = wp_get_attachment_image_src( $attach_id, 'full' );
			$actual_file_path = get_attached_file( $attach_id );
			// this is not an attachment, let's use the image url.
		} elseif ( $img_url ) {
			$file_path = wp_parse_url( $img_url );
			$actual_file_path = rtrim( ABSPATH, '/' ) . $file_path['path'];
			$orig_size = getimagesize( $actual_file_path );
			$image_src[0] = $img_url;
			$image_src[1] = $orig_size[0];
			$image_src[2] = $orig_size[1];
		}
		if ( ! empty( $actual_file_path ) ) {
			$file_info = pathinfo( $actual_file_path );
			$extension = '.' . $file_info['extension'];

			// the image path without the extension.
			$no_ext_path = $file_info['dirname'] . '/' . $file_info['filename'];

			$cropped_img_path = $no_ext_path . '-' . $width . 'x' . $height . $extension;

			// checking if the file size is larger than the target size.
			// if it is smaller or the same size, stop right here and return.
			if ( $image_src[1] > $width || $image_src[2] > $height ) {

				// the file is larger, check if the resized version already exists (for $crop = true but will also work for $crop = false if the sizes match).
				if ( file_exists( $cropped_img_path ) ) {
					$cropped_img_url = str_replace( basename( $image_src[0] ), basename( $cropped_img_path ), $image_src[0] );
					$vt_image = [
						'url' => $cropped_img_url,
						'width' => $width,
						'height' => $height,
					];

					return $vt_image;
				}

				if ( ! $crop ) {
					// calculate the size proportionally.
					$proportional_size = wp_constrain_dimensions( $image_src[1], $image_src[2], $width, $height );
					$resized_img_path = $no_ext_path . '-' . $proportional_size[0] . 'x' . $proportional_size[1] . $extension;

					// checking if the file already exists.
					if ( file_exists( $resized_img_path ) ) {
						$resized_img_url = str_replace( basename( $image_src[0] ), basename( $resized_img_path ), $image_src[0] );

						$vt_image = [
							'url' => $resized_img_url,
							'width' => $proportional_size[0],
							'height' => $proportional_size[1],
						];

						return $vt_image;
					}
				}

				// no cache files - let's finally resize it.
				$img_editor = wp_get_image_editor( $actual_file_path );

				if ( is_wp_error( $img_editor ) || is_wp_error( $img_editor->resize( $width, $height, $crop ) ) ) {
					return [
						'url' => '',
						'width' => '',
						'height' => '',
					];
				}

				$new_img_path = $img_editor->generate_filename();

				if ( is_wp_error( $img_editor->save( $new_img_path ) ) ) {
					return [
						'url' => '',
						'width' => '',
						'height' => '',
					];
				}
				if ( ! is_string( $new_img_path ) ) {
					return [
						'url' => '',
						'width' => '',
						'height' => '',
					];
				}

				$new_img_size = getimagesize( $new_img_path );
				$new_img = str_replace( basename( $image_src[0] ), basename( $new_img_path ), $image_src[0] );

				// resized output.
				$vt_image = [
					'url' => $new_img,
					'width' => $new_img_size[0],
					'height' => $new_img_size[1],
				];

				return $vt_image;
			}

			// default output - without resizing.
			$vt_image = [
				'url' => $image_src[0],
				'width' => $image_src[1],
				'height' => $image_src[2],
			];

			return $vt_image;
		}

		return false;
	}
endif;
if ( ! function_exists( 'wpb_body_class' ) ) :
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
	 * @since 8.5
	 */
	function wpb_body_class( $classes ) {
		$classes[] = 'wpb-js-composer js-comp-ver-' . WPB_VC_VERSION;
		$disable_responsive = vc_settings()->get( 'not_responsive_css' );
		if ( '1' !== $disable_responsive ) {
			$classes[] = 'vc_responsive';
		} else {
			$classes[] = 'vc_non_responsive';
		}

		return $classes;
	}
endif;
if ( ! function_exists( 'vc_convert_shortcode' ) ) :
	/**
	 * Shortcode converter.
	 *
	 * @param array $m
	 *
	 * @return string
	 * @since 4.2
	 */
	function vc_convert_shortcode( $m ) {
		list( $output, $m_one, $tag, $attr_string, $m_four, $content ) = $m;
		if ( 'vc_row' === $tag || 'vc_section' === $tag ) {
			return $output;
		}
		$result = '';
		$el_position = '';
		$width = '1/1';
		$shortcode_attr = shortcode_parse_atts( $attr_string );
		extract( shortcode_atts( [
			'width' => '1/1',
			'el_class' => '',
			'el_position' => '',
		], $shortcode_attr ) );
		// Start.
		if ( preg_match( '/first/', $el_position ) || empty( $shortcode_attr['width'] ) || '1/1' === $shortcode_attr['width'] ) {
			$result = '[vc_row]';
		}
		if ( 'vc_column' !== $tag ) {
			$result .= '[vc_column width="' . $width . '"]';
		}

		// Tag.
		$pattern = get_shortcode_regex();
		if ( 'vc_column' === $tag ) {
			$result .= "[{$m_one}{$tag} {$attr_string}]" . preg_replace_callback( "/{$pattern}/s", 'vc_convert_inner_shortcode', $content ) . "[/{$tag}{$m_four}]";
		} elseif ( 'vc_tabs' === $tag || 'vc_accordion' === $tag || 'vc_tour' === $tag ) {
			$result .= "[{$m_one}{$tag} {$attr_string}]" . preg_replace_callback( "/{$pattern}/s", 'vc_convert_tab_inner_shortcode', $content ) . "[/{$tag}{$m_four}]";
		} else {
			$result .= preg_replace( '/(\"\d\/\d\")/', '"1/1"', $output );
		}

		// End.
		if ( 'vc_column' !== $tag ) {
			$result .= '[/vc_column]';
		}
		if ( preg_match( '/last/', $el_position ) || empty( $shortcode_attr['width'] ) || '1/1' === $shortcode_attr['width'] ) {
			$result .= '[/vc_row]' . "\n";
		}

		return trim( $result );
	}
endif;
if ( ! function_exists( 'vc_convert_tab_inner_shortcode' ) ) :
	/**
	 * Processes shortcode, extracting attributes, and recursively converting inner shortcodes.
	 * Then reconstructs the modified shortcode string and returns it.
	 *
	 * @param array $m
	 *
	 * @return string
	 * @since 4.2
	 */
	function vc_convert_tab_inner_shortcode( $m ) {
		list( $m_one, $tag, $attr_string, $m_four, $content ) = $m;
		$result = '';
		extract( shortcode_atts( [
			'width' => '1/1',
			'el_class' => '',
			'el_position' => '',
		], shortcode_parse_atts( $attr_string ) ) );
		$pattern = get_shortcode_regex();
		$result .= "[{$m_one}{$tag} {$attr_string}]" . preg_replace_callback( "/{$pattern}/s", 'vc_convert_inner_shortcode', $content ) . "[/{$tag}{$m_four}]";

		return $result;
	}
endif;
if ( ! function_exists( 'vc_convert_inner_shortcode' ) ) :
	/**
	 * Convert inner shortcode.
	 *
	 * @param array $m
	 *
	 * @return string
	 * @since 4.2
	 */
	function vc_convert_inner_shortcode( $m ) {
		list( $output, $m_one, $tag, $attr_string, $m_four, $content ) = $m;
		$result = '';
		$width = '';
		$el_position = '';
		extract( shortcode_atts( [
			'width' => '1/1',
			'el_class' => '',
			'el_position' => '',
		], shortcode_parse_atts( $attr_string ) ) );
		if ( '1/1' !== $width ) {
			if ( preg_match( '/first/', $el_position ) ) {
				$result .= '[vc_row_inner]';
			}
			$result .= "\n" . '[vc_column_inner width="' . esc_attr( $width ) . '" el_position="' . esc_attr( $el_position ) . '"]';
			$attr = '';
			foreach ( shortcode_parse_atts( $attr_string ) as $key => $value ) {
				if ( 'width' === $key ) {
					$value = '1/1';
				} elseif ( 'el_position' === $key ) {
					$value = 'first last';
				}
				$attr .= ' ' . $key . '="' . $value . '"';
			}
			$result .= "[{$m_one}{$tag} {$attr}]" . $content . "[/{$tag}{$m_four}]";
			$result .= '[/vc_column_inner]';
			if ( preg_match( '/last/', $el_position ) ) {
				$result .= '[/vc_row_inner]' . "\n";
			}
		} else {
			$result = $output;
		}

		return $result;
	}
endif;
if ( ! function_exists( 'wpb_vc_get_column_width_indent' ) ) :
	/**
	 * Get indent for column width.
	 *
	 * @param string $width
	 *
	 * @return string
	 * @since 4.2
	 */
	function wpb_vc_get_column_width_indent( $width ) {
		$identy = '11';
		if ( 'vc_col-sm-6' === $width ) {
			$identy = '12';
		} elseif ( 'vc_col-sm-3' === $width ) {
			$identy = '14';
		} elseif ( 'vc_col-sm-4' === $width ) {
			$identy = '13';
		} elseif ( 'vc_col-sm-8' === $width ) {
			$identy = '23';
		} elseif ( 'vc_col-sm-9' === $width ) {
			$identy = '34';
		} elseif ( 'vc_col-sm-2' === $width ) {
			$identy = '16'; // TODO: check why there is no "vc_col-sm-1, -5, -6, -7, -11, -12.
		} elseif ( 'vc_col-sm-10' === $width ) {
			$identy = '56';
		}

		return $identy;
	}
endif;
if ( ! function_exists( 'vc_colorCreator' ) ) :
	/**
	 * Make any HEX color lighter or darker.
	 *
	 * @param string $colour
	 * @param int $per
	 *
	 * @return string
	 * @since 4.2
	 */
    function vc_colorCreator( $colour, $per = 10 ) { // phpcs:ignore
		require_once 'class-vc-color-helper.php';
		$color = $colour;
		if ( stripos( $colour, 'rgba(' ) !== false ) {
			$rgb = str_replace( [
				'rgba',
				'rgb',
				'(',
				')',
			], '', $colour );
			$rgb = explode( ',', $rgb );
			$rgb_array = [
				'R' => $rgb[0],
				'G' => $rgb[1],
				'B' => $rgb[2],
			];
			$alpha = $rgb[3];
			try {
				$color = Vc_Color_Helper::rgbToHex( $rgb_array );
				$color_obj = new Vc_Color_Helper( $color );
				if ( $per >= 0 ) {
					$color = $color_obj->lighten( $per );
				} else {
					$color = $color_obj->darken( abs( $per ) );
				}
				$rgba = $color_obj->hexToRgb( $color );
				$rgba[] = $alpha;
				$css_rgba_color = 'rgba(' . implode( ', ', $rgba ) . ')';

				return $css_rgba_color;
			} catch ( Exception $e ) {
				// In case of error return same as given.
				return $colour;
			}
		} elseif ( stripos( $colour, 'rgb(' ) !== false ) {
			$rgb = str_replace( [
				'rgba',
				'rgb',
				'(',
				')',
			], '', $colour );
			$rgb = explode( ',', $rgb );
			$rgb_array = [
				'R' => $rgb[0],
				'G' => $rgb[1],
				'B' => $rgb[2],
			];
			try {
				$color = Vc_Color_Helper::rgbToHex( $rgb_array );
			} catch ( Exception $e ) {
				// In case of error return same as given.
				return $colour;
			}
		}

		try {
			$color_obj = new Vc_Color_Helper( $color );
			if ( $per >= 0 ) {
				$color = $color_obj->lighten( $per );
			} else {
				$color = $color_obj->darken( abs( $per ) );
			}

			return '#' . $color;
		} catch ( Exception $e ) {
			return $colour;
		}
	}
endif;
if ( ! function_exists( 'vc_hex2rgb' ) ) :
	/**
	 * HEX to RGB converter
	 *
	 * @param string $color
	 *
	 * @return array|bool
	 * @since 4.2
	 */
	function vc_hex2rgb( $color ) {
		$color = str_replace( '#', '', $color );

		if ( strlen( $color ) === 6 ) {
			list( $r, $g, $b ) = [
				$color[0] . $color[1],
				$color[2] . $color[3],
				$color[4] . $color[5],
			];
		} elseif ( strlen( $color ) === 3 ) {
			list( $r, $g, $b ) = [
				$color[0] . $color[0],
				$color[1] . $color[1],
				$color[2] . $color[2],
			];
		} else {
			return false;
		}

		$r = hexdec( $r );
		$g = hexdec( $g );
		$b = hexdec( $b );

		return [
			$r,
			$g,
			$b,
		];
	}
endif;
if ( ! function_exists( 'vc_parse_multi_attribute' ) ) :
	/**
	 * Parse string like "title:Hello world|weekday:Monday" to array('title' => 'Hello World', 'weekday' => 'Monday')
	 *
	 * @param mixed $value
	 * @param array $defaults
	 *
	 * @return array
	 * @since 4.2
	 */
	function vc_parse_multi_attribute( $value, $defaults = [] ) {
		$result = $defaults;
		$params_pairs = is_string( $value ) ? explode( '|', $value ) : [];
		if ( ! empty( $params_pairs ) ) {
			foreach ( $params_pairs as $pair ) {
				$param = preg_split( '/\:/', $pair );
				if ( ! empty( $param[0] ) && isset( $param[1] ) ) {
					$result[ $param[0] ] = trim( rawurldecode( $param[1] ) );
				}
			}
		}

		return $result;
	}
endif;
if ( ! function_exists( 'vc_param_options_parse_values' ) ) :
	/**
	 * Decode params options.
	 *
	 * @param string $v
	 *
	 * @return string
	 * @since 4.2
	 */
	function vc_param_options_parse_values( $v ) {
		return rawurldecode( $v );
	}
endif;
if ( ! function_exists( 'vc_param_options_get_settings' ) ) :
	/**
	 * Get specific param from the settings list.
	 *
	 * @param string $name
	 * @param string $settings
	 *
	 * @return bool
	 * @since 4.2
	 */
	function vc_param_options_get_settings( $name, $settings ) {
		if ( is_array( $settings ) ) {
			foreach ( $settings as $params ) {
				if ( isset( $params['name'] ) && $params['name'] === $name && isset( $params['type'] ) ) {
					return $params;
				}
			}
		}

		return false;
	}
endif;
if ( ! function_exists( 'vc_convert_atts_to_string' ) ) :
	/**
	 * Convert array to string.
	 *
	 * @param array $atts
	 *
	 * @return string
	 * @since 4.2
	 */
	function vc_convert_atts_to_string( $atts ) {
		$output = '';
		foreach ( $atts as $key => $value ) {
			$output .= ' ' . $key . '="' . $value . '"';
		}

		return $output;
	}
endif;
if ( ! function_exists( 'vc_parse_options_string' ) ) :
	/**
	 * String parser for options.
	 *
	 * @param string $initial_string
	 * @param string $tag
	 * @param string $param
	 *
	 * @return array
	 * @throws \Exception
	 * @since 4.2
	 */
	function vc_parse_options_string( $initial_string, $tag, $param ) { // phpcs:ignore:Generic.Metrics.CyclomaticComplexity.TooHigh
		$options = [];
		$option_settings_list = [];
		$settings = WPBMap::getParam( $tag, $param );

		foreach ( preg_split( '/\|/', $initial_string ) as $value ) {
			if ( preg_match( '/\:/', $value ) ) {
				$split = preg_split( '/\:/', $value );
				$option_name = $split[0];
				$option_settings = vc_param_options_get_settings( $option_name, $settings['options'] );
				$option_settings_list[ $option_name ] = $option_settings;
				if ( isset( $option_settings['type'] ) && 'checkbox' === $option_settings['type'] ) {
					$option_value = array_map( 'vc_param_options_parse_values', preg_split( '/\,/', $split[1] ) );
				} else {
					$option_value = rawurldecode( $split[1] );
				}
				$options[ $option_name ] = $option_value;
			}
		}
		if ( isset( $settings['options'] ) ) {
			foreach ( $settings['options'] as $setting_option ) {
				if ( 'separator' !== $setting_option['type'] && isset( $setting_option['value'] ) && empty( $options[ $setting_option['name'] ] ) ) {
					$options[ $setting_option['name'] ] = 'checkbox' === $setting_option['type'] ? preg_split( '/\,/', $setting_option['value'] ) : $setting_option['value'];
				}
				if ( isset( $setting_option['name'] ) && isset( $options[ $setting_option['name'] ] ) && isset( $setting_option['value_type'] ) ) {
					if ( 'integer' === $setting_option['value_type'] ) {
						$options[ $setting_option['name'] ] = (int) $options[ $setting_option['name'] ];
					} elseif ( 'float' === $setting_option['value_type'] ) {
						$options[ $setting_option['name'] ] = (float) $options[ $setting_option['name'] ];
					} elseif ( 'boolean' === $setting_option['value_type'] ) {
						$options[ $setting_option['name'] ] = (bool) $options[ $setting_option['name'] ];
					}
				}
			}
		}

		return $options;
	}
endif;
if ( ! function_exists( 'vc_build_safe_css_class' ) ) :
	/**
	 * Convert string to a valid css class name.
	 *
	 * @param string $class_name
	 *
	 * @return string
	 * @since 4.3
	 */
	function vc_build_safe_css_class( $class_name ) {
		return preg_replace( '/\W+/', '', strtolower( str_replace( ' ', '_', wp_strip_all_tags( $class_name ) ) ) );
	}
endif;
if ( ! function_exists( 'vc_studly' ) ) :
	/**
	 * VC Convert a value to studly caps case.
	 *
	 * @param string $value
	 *
	 * @return string
	 * @since 4.3
	 */
	function vc_studly( $value ) {
		$value = ucwords( str_replace( [
			'-',
			'_',
		], ' ', $value ) );

		return str_replace( ' ', '', $value );
	}
endif;
if ( ! function_exists( 'vc_camel_case' ) ) :
	/**
	 * VC Convert a value to camel case.
	 *
	 * @param string $value
	 *
	 * @return string
	 * @since 4.3
	 */
	function vc_camel_case( $value ) {
		return lcfirst( vc_studly( $value ) );
	}
endif;
if ( ! function_exists( 'vc_icon_element_fonts_enqueue' ) ) :
	/**
	 * Enqueue icon element font
	 *
	 * @param string $font
	 * @since 4.4
	 *
	 * @todo move to separate folder
	 */
	function vc_icon_element_fonts_enqueue( $font ) {
		$font_styles = [
			'fontawesome' => 'vc_font_awesome_6',
			'openiconic' => 'vc_openiconic',
			'typicons' => 'vc_typicons',
			'entypo' => 'vc_entypo',
			'linecons' => 'vc_linecons',
			'monosocial' => 'vc_monosocialiconsfont',
			'material' => 'vc_material',
		];

		if ( 'pixelicons' === $font ) {
			// Pixel icons are embedded in the main CSS files, no separate enqueue needed.
			return;
		}

		if ( isset( $font_styles[ $font ] ) ) {
			wp_enqueue_style( $font_styles[ $font ] );
		} else {
			do_action( 'vc_enqueue_font_icon_element', $font ); // hook to custom do enqueue style.
		}
	}
endif;
if ( ! function_exists( 'vc_shortcode_attribute_parse' ) ) :
	/**
	 * Function merges defaults attributes in attributes by keeping it values
	 *
	 * Example
	 *      array defaults     |   array attributes     |    result array
	 *      'color'=>'black',         -                   'color'=>'black',
	 *      'target'=>'_self',      'target'=>'_blank',   'target'=>'_blank',
	 *             -                'link'=>'google.com'  'link'=>'google.com'
	 *
	 * @param array $defaults
	 * @param array $attributes
	 *
	 * @return array - merged attributes.
	 *
	 * @since 4.4
	 *
	 * @see vc_map_get_attributes
	 */
	function vc_shortcode_attribute_parse( $defaults = [], $attributes = [] ) {
		$atts = $attributes + shortcode_atts( $defaults, $attributes );

		return $atts;
	}
endif;
if ( ! function_exists( 'vc_get_shortcode_regex' ) ) :
	/**
	 * Get shortcode regex.
	 *
	 * @param string $tagregexp
	 * @return string
	 */
	function vc_get_shortcode_regex( $tagregexp = '' ) {
		if ( 0 === strlen( $tagregexp ) ) {
			return get_shortcode_regex();
		}

        //phpcs:disable:Generic.Strings.UnnecessaryStringConcat.Found
		return '\\['                              // Opening bracket.
			. '(\\[?)'                           // 1: Optional second opening bracket for escaping shortcodes: [[tag]].
			. "($tagregexp)"                     // 2: Shortcode name.
			. '(?![\\w\-])'                       // Not followed by word character or hyphen.
			. '('                                // 3: Unroll the loop: Inside the opening shortcode tag.
			. '[^\\]\\/]*'                   // Not a closing bracket or forward slash.
			. '(?:' . '\\/(?!\\])'               // A forward slash not followed by a closing bracket.
			. '[^\\]\\/]*'               // Not a closing bracket or forward slash.
			. ')*?' . ')' . '(?:' . '(\\/)'                        // 4: Self closing tag .
			. '\\]'                          // ... and closing bracket.
			. '|' . '\\]'                          // Closing bracket.
			. '(?:' . '('                        // 5: Unroll the loop: Optionally, anything between the opening and closing shortcode tags.
			. '[^\\[]*+'             // Not an opening bracket.
			. '(?:' . '\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag.
			. '[^\\[]*+'         // Not an opening bracket.
			. ')*+' . ')' . '\\[\\/\\2\\]'             // Closing shortcode tag.
			. ')?' . ')' . '(\\]?)';
        //phpcs:enable:Generic.Strings.UnnecessaryStringConcat.Found
	}
endif;
if ( ! function_exists( 'vc_message_warning' ) ) :
	/**
	 * Used to send warning message
	 *
	 * @param string $message
	 *
	 * @return string
	 * @since 4.5
	 */
	function vc_message_warning( $message ) {
		return '<div class="wpb_element_wrapper"><div class="vc_message_box vc_message_box-standard vc_message_box-rounded vc_color-warning">
        <div class="vc_message_box-icon"><i class="fa fa-exclamation-triangle"></i>
        </div><p class="messagebox_text">' . $message . '</p>
    </div></div>';
	}
endif;
if ( ! function_exists( 'vc_extract_youtube_id' ) ) :
	/**
	 * Extract video ID from youtube url
	 *
	 * @param string $url Youtube url.
	 *
	 * @return string
	 */
	function vc_extract_youtube_id( $url ) {
		$url = wp_parse_url( $url, PHP_URL_QUERY );

		if ( ! is_string( $url ) ) {
			return '';
		}

		parse_str( $url, $vars );

		if ( ! isset( $vars['v'] ) ) {
			return '';
		}

		return $vars['v'];
	}
endif;
if ( ! function_exists( 'vc_taxonomies_types' ) ) :
	/**
	 * Get taxonomies for specific post type.
	 *
	 * @param string|null $post_type
	 *
	 * @return string[]|\WP_Taxonomy[]
	 */
	function vc_taxonomies_types( $post_type = null ) {
		global $vc_taxonomies_types;
		if ( is_null( $vc_taxonomies_types ) || $post_type ) {
			$query = [ 'public' => true ];
			$vc_taxonomies_types = get_taxonomies( $query, 'objects' );
			if ( ! empty( $post_type ) && is_array( $vc_taxonomies_types ) ) {
				foreach ( $vc_taxonomies_types as $key => $taxonomy ) {
					$arr = (array) $taxonomy;
					if ( isset( $arr['object_type'] ) && ! in_array( $post_type, $arr['object_type'] ) ) {
						unset( $vc_taxonomies_types[ $key ] );
					}
				}
			}
		}

		return $vc_taxonomies_types;
	}
endif;
if ( ! function_exists( 'vc_get_term_object' ) ) :
	/**
	 * Get term object.
	 *
	 * @param WP_Term $term
	 *
	 * @return array
	 * @since 4.5.3
	 */
	function vc_get_term_object( $term ) {
		$vc_taxonomies_types = vc_taxonomies_types();

		return [
			'label' => $term->name,
			'value' => $term->term_id,
			'group_id' => $term->taxonomy,
			'group' => isset( $vc_taxonomies_types[ $term->taxonomy ], $vc_taxonomies_types[ $term->taxonomy ]->labels, $vc_taxonomies_types[ $term->taxonomy ]->labels->name ) ? $vc_taxonomies_types[ $term->taxonomy ]->labels->name : esc_html__( 'Taxonomies', 'js_composer' ),
		];
	}
endif;
if ( ! function_exists( 'vc_has_class' ) ) :
	/**
	 * Check if element has a specific class.
	 *
	 * E.g. f('foo', 'foo bar baz') -> true.
	 *
	 * @param string $class_name Class to check for.
	 * @param string $classes Classes separated by space(s).
	 *
	 * @return bool
	 */
	function vc_has_class( $class_name, $classes ) {
		return in_array( $class_name, explode( ' ', strtolower( $classes ), true ), true );
	}
endif;
if ( ! function_exists( 'vc_remove_class' ) ) :
	/**
	 * Remove specific class from classes string.
	 *
	 * E.g. f('foo', 'foo bar baz') -> 'bar baz'.
	 *
	 * @param string $class_name Class to remove.
	 * @param string $classes Classes separated by space(s).
	 *
	 * @return string
	 */
	function vc_remove_class( $class_name, $classes ) {
		$list_classes = explode( ' ', strtolower( $classes ) );

		$key = array_search( $class_name, $list_classes, true );

		if ( false === $key ) {
			return $classes;
		}

		unset( $list_classes[ $key ] );

		return implode( ' ', $list_classes );
	}
endif;
if ( ! function_exists( 'vc_stringify_attributes' ) ) :
	/**
	 * Convert array of named params to string version.
	 * All values will be escaped.
	 *
	 * E.g. f(array('name' => 'foo', 'id' => 'bar')) -> 'name="foo" id="bar"'.
	 *
	 * @param array $attributes
	 *
	 * @return string
	 */
	function vc_stringify_attributes( $attributes ) {
		$atts = [];
		foreach ( $attributes as $name => $value ) {
			$atts[] = $name . '="' . esc_attr( $value ) . '"';
		}

		return implode( ' ', $atts );
	}
endif;
if ( ! function_exists( 'vc_is_responsive_disabled' ) ) :
	/**
	 * Check if plugin no_resonsive_css settings is disabled.
	 *
	 * @return bool
	 */
	function vc_is_responsive_disabled() {
		$disable_responsive = vc_settings()->get( 'not_responsive_css' );

		return '1' === $disable_responsive;
	}
endif;
if ( ! function_exists( 'vc_do_shortcode' ) ) :
	/**
	 * Do shortcode single render point.
	 *
	 * @param array $atts
	 * @param null $content
	 * @param null $tag
	 *
	 * @return string
	 * @throws \Exception
	 */
	function vc_do_shortcode( $atts, $content = null, $tag = null ) {
		ob_start();
        // @codingStandardsIgnoreStart
        echo Vc_Shortcodes_Manager::getInstance()->getElementClass( $tag )->output( $atts, $content );
        $content = ob_get_clean();
        global $wp_embed;
        if ( is_object( $wp_embed ) ) {
            $content = $wp_embed->run_shortcode( $content );
            $content = $wp_embed->autoembed( $content );
        }
        // @codingStandardsIgnoreEnd

		return $content;
	}
endif;
if ( ! function_exists( 'vc_random_string' ) ) :
	/**
	 * Return random string
	 *
	 * @param int $length
	 *
	 * @return string
	 */
	function vc_random_string( $length = 10 ) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$len = strlen( $characters );
		$str = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$str .= $characters[ wp_rand( 0, $len - 1 ) ];
		}

		return $str;
	}
endif;
if ( ! function_exists( 'vc_slugify' ) ) :
	/**
	 * Slugify string, remove all unwanted characters.
	 *
	 * @param string $init_str
	 * @return string|string[]|null
	 */
	function vc_slugify( $init_str ) {
		$init_str = strtolower( $init_str );
		$init_str = html_entity_decode( $init_str );
		$init_str = preg_replace( '/[^\w ]+/', '', $init_str );
		$init_str = preg_replace( '/ +/', '-', $init_str );

		return $init_str;
	}
endif;
if ( ! function_exists( 'vc_extract_dimensions' ) ) :
	/**
	 * Extract width/height from string
	 *
	 * @param string $dimensions WxH.
	 *
	 * @return mixed array(width, height) or false
	 * @since 4.7
	 */
	function vc_extract_dimensions( $dimensions ) {
		$dimensions = str_replace( ' ', '', $dimensions );
		$matches = null;

		if ( preg_match( '/(\d+)x(\d+)/', $dimensions, $matches ) ) {
			return [
				$matches[1],
				$matches[2],
			];
		}

		return false;
	}
endif;
if ( ! function_exists( 'wpb_widget_title' ) ) :
	/**
	 * This filter should be applied to all content elements titles.
	 *
	 * $params['extraclass'] Extra class name will be added.
	 *
	 *
	 * To override content element title default html markup, paste this code in your theme's functions.php file
	 * vc_filter: wpb_widget_title
	 * add_filter('wpb_widget_title', 'override_widget_title', 10, 2);
	 * function override_widget_title($output = '', $params = array('')) {
	 *    $extraclass = (isset($params['extraclass'])) ? " ".$params['extraclass'] : "";
	 *    return '<h1 class="entry-title'.$extraclass.'">'.$params['title'].'</h1>';
	 * }
	 *
	 * @param array $params
	 *
	 * @return mixed|string
	 */
	function wpb_widget_title( $params = [ 'title' => '' ] ) {
		if ( '' === $params['title'] ) {
			return '';
		}

		$extraclass = ( isset( $params['extraclass'] ) ) ? ' ' . $params['extraclass'] : '';
		$output = '<h2 class="wpb_heading' . esc_attr( $extraclass ) . '">' . esc_html( $params['title'] ) . '</h2>';

		return apply_filters( 'wpb_widget_title', $output, $params );
	}
endif;
if ( ! function_exists( 'wpb_remove_custom_html' ) ) :
	/**
	 * Used to remove raw_html/raw_js elements from content.
	 *
	 * @param string $content
	 * @return string|string[]|null
	 * @since 6.3.0
	 */
	function wpb_remove_custom_html( $content ) {
		$is_rest_request = ( defined( 'REST_REQUEST' ) && REST_REQUEST );
		if ( ! empty( $content ) && ! $is_rest_request && ! vc_user_access()->part( 'unfiltered_html' )->checkStateAny( true, null )->get() ) {
			// html encoded shortcodes.
			$regex = vc_get_shortcode_regex( implode( '|', wpb_get_elements_with_custom_html() ) );

			// custom on click.
			$button_regex = vc_get_shortcode_regex( 'vc_btn' );
			$content = preg_replace_callback( '/' . $button_regex . '/', 'wpb_remove_custom_onclick', $content );

			$content = preg_replace( '/' . $regex . '/', '', $content );
		}

		return $content;
	}
endif;
if ( ! function_exists( 'wpb_remove_custom_onclick' ) ) :
	/**
	 * Remove custom onclick.
	 *
	 * @param array $match_list
	 * @return string
	 */
	function wpb_remove_custom_onclick( $match_list ) {
		if ( strpos( $match_list[3], 'custom_onclick' ) !== false ) {
			return '';
		}

		return $match_list[0];
	}
endif;
if ( ! function_exists( 'wpb_check_wordpress_com_env' ) ) :
	/**
	 * We use it only to check the current environment is wordpress.com.
	 *
	 * @since 6.2
	 *
	 * @return bool
	 */
	function wpb_check_wordpress_com_env() {
		return defined( 'IS_ATOMIC' ) &&
			IS_ATOMIC &&
			defined( 'ATOMIC_CLIENT_ID' ) &&
			'2' === ATOMIC_CLIENT_ID;
	}
endif;
if ( ! function_exists( 'wpb_get_post_id_for_custom_output' ) ) :
	/**
	 * Get current post id for plugin custom output like css and js.
	 *
	 * @since  7.7
	 * @return false|int
	 */
	function wpb_get_post_id_for_custom_output() {
		$id = false;
		if ( is_front_page() || is_home() ) {
			$id = get_queried_object_id();
		} elseif ( is_singular() ) {
			$id = get_the_ID();
		}

		return $id;
	}
endif;
if ( ! function_exists( 'wpb_get_elements_with_custom_html' ) ) :
	/**
	 * Get elements list that use custom html in our plugin core.
	 *
	 * @note it's elements lists that can edit only users roles that have unfiltered_html capability.
	 * @note admin can set access to unfiltered_html cap in our role manager plugin settings for individual roles.
	 *
	 * @return array
	 * @since 7.8
	 */
	function wpb_get_elements_with_custom_html() {
		return apply_filters('wpb_custom_html_elements', [
			'vc_raw_html',
			'vc_raw_js',
			'vc_gmaps',
		]);
	}
endif;
if ( ! function_exists( 'wpb_is_regex_valid' ) ) :
	/**
	 * Check if regex string is valid.
	 *
	 * @param string $regex
	 * @since  7.8
	 * @return bool
	 */
	function wpb_is_regex_valid( $regex ) {
        // @phpcs:ignore
        return false !== @preg_match( $regex, '' );
	}
endif;
if ( ! function_exists( 'wpb_get_post_editor_status' ) ) :
	/**
	 * Get post plugin editor status for post.
	 *
	 * @param int $post_id
	 *
	 * @since  7.8
	 * @return bool
	 */
	function wpb_get_post_editor_status( $post_id ) {
		if ( ! is_int( $post_id ) ) {
			return false;
		}

		$status = get_post_meta( $post_id, '_wpb_vc_js_status', true );

		if ( 'true' === $status ) {
			return true;
		} else {
			return false;
		}
	}
endif;
if ( ! function_exists( 'wpb_format_with_css_unit' ) ) :
	/**
	 * Formats a CSS value by ensuring it has a valid unit.
	 * If no unit is provided, defaults to 'px'.
	 *
	 * @param string $value The CSS value to format, e.g., '20', '15em'.
	 * @return string The formatted value with a unit, e.g., '20px', '15em'.
	 * @since 7.9
	 */
	function wpb_format_with_css_unit( $value ) {
		$value = preg_replace( '/\s+/', '', $value );
		$pattern = '/^(\d*(?:\.\d+)?)\s*(px|\%|in|cm|mm|em|rem|ex|pt|pc|vw|vh|vmin|vmax)?$/';
		preg_match( $pattern, $value, $matches );
		$numeric_value = isset( $matches[1] ) ? (float) $matches[1] : (float) $value;
		$unit = isset( $matches[2] ) ? $matches[2] : 'px';
		return $numeric_value . $unit;
	}
endif;
if ( ! function_exists( 'wpb_is_hide_title' ) ) :
	/**
	 * Check if we should hide title for post.
	 *
	 * @since 8.2
	 * @param int $post_id
	 * @return bool
	 */
	function wpb_is_hide_title( $post_id ) {
		$vc_settings = get_post_meta( $post_id, '_vc_post_settings', true );

		return ! empty( $vc_settings['is_hide_title'] );
	}
endif;
if ( ! function_exists( 'wpb_is_preview' ) ) :
	/**
	 * Check if current loaded page is preview.
	 *
	 * @since 8.3
	 * @return bool
	 */
	function wpb_is_preview() {
		// there is some cases when we see preview but don't have nonce to verify it.
		$preview = isset( $_GET['preview'] ) ? sanitize_text_field( wp_unslash( $_GET['preview'] ) ) : '';
		$wp_preview = isset( $_GET['wp-preview'] ) ? sanitize_text_field( wp_unslash( $_GET['wp-preview'] ) ) : '';

		return ( 'true' === $preview ) || ( 'dopreview' === $wp_preview );
	}
endif;
if ( ! function_exists( 'wpb_update_id_with_preview_id' ) ) :
	/**
	 * Check if current page is preview and if it is, update post id with preview post id.
	 *
	 * @param int $post_id
	 * @since 8.3
	 * @return int
	 */
	function wpb_update_id_with_preview_id( $post_id ) {
		if ( ! wpb_is_preview() ) {
			return $post_id;
		}

		if ( get_post_status( $post_id ) === 'draft' ) {
			$preview = wp_get_latest_revision_id_and_total_count( $post_id );
			if ( ! is_wp_error( $preview ) && ! empty( $preview['latest_id'] ) ) {
				$post_id = $preview['latest_id'];
			}
		} else {
			$preview = wp_get_post_autosave( $post_id );
			if ( $preview ) {
				$post_id = $preview->ID;
			}
		}

		return $post_id;
	}
endif;
if ( ! function_exists( 'vc_value_from_safe' ) ) :
	/**
	 * Processes a value by decoding, optionally encoding, and replacing special characters.
	 *
	 * @param mixed $value
	 * @param bool $encode
	 *
	 * @return string
	 * @since 4.2
	 */
	function vc_value_from_safe( $value, $encode = false ) {
		$value = is_string( $value ) ? $value : '';
        // @codingStandardsIgnoreLine
        $value = preg_match( '/^#E\-8_/', $value ) ? rawurldecode( base64_decode( preg_replace( '/^#E\-8_/', '', $value ) ) ) : $value;
		if ( $encode ) {
			$value = htmlentities( $value, ENT_COMPAT, 'UTF-8' );
		}

		return str_replace( [ '`{`', '`}`', '``' ], [ '[', ']', '"' ], $value );
	}
endif;
if ( ! function_exists( 'vc_map_get_params_defaults' ) ) :
	/**
	 * Use it when you have modified shortcode params and need to get defaults.
	 *
	 * @param array $params
	 *
	 * @return array
	 * @since 4.12
	 */
	function vc_map_get_params_defaults( $params ) {
		$result_params = [];
		foreach ( $params as $param ) {
			if ( isset( $param['param_name'] ) && 'content' !== $param['param_name'] ) {
				$value = '';
				if ( isset( $param['std'] ) ) {
					$value = $param['std'];
				} elseif ( isset( $param['value'] ) ) {
					if ( is_array( $param['value'] ) ) {
						$value = current( $param['value'] );
						if ( is_array( $value ) ) {
							// in case if two-dimensional array provided (vc_basic_grid).
							$value = current( $value );
						}
						// return first value from array (by default).
					} else {
						$value = $param['value'];
					}
				}
				$result_params[ $param['param_name'] ] = apply_filters( 'vc_map_get_param_defaults', $value, $param );
			}
		}

		return $result_params;
	}
endif;
if ( ! function_exists( 'vc_map_integrate_include_exclude_fields' ) ) :
	/**
	 * Get element shortcode map include/exclude some param fields.
	 *
	 * @param array $param
	 * @param array $change_fields
	 *
	 * @return array|null
	 * @internal
	 */
	function vc_map_integrate_include_exclude_fields( $param, $change_fields ) {
		if ( ! is_array( $change_fields ) || ! isset( $param['param_name'] ) ) {
			return $param;
		}
		$param_name = $param['param_name'];

		if ( isset( $change_fields['exclude'] ) ) {
			$param = in_array( $param_name, $change_fields['exclude'], true ) ? null : $param;
		} elseif ( isset( $change_fields['exclude_regex'] ) ) {
			$param = vc_map_check_param_field_against_regex( $param, $change_fields['exclude_regex'], 'exclude' );
		}

		if ( isset( $change_fields['include_only'] ) ) {
			$param = ! in_array( $param_name, $change_fields['include_only'], true ) ? null : $param;
		} elseif ( isset( $change_fields['include_only_regex'] ) ) {
			$param = vc_map_check_param_field_against_regex( $param, $change_fields['include_only_regex'], 'include' );
		}

		return $param;
	}
endif;
if ( ! function_exists( 'vc_map_check_param_field_against_regex' ) ) :
	/**
	 * Check shortcode param against regex.
	 *
	 * @param array $param
	 * @param string|array $regex_list
	 * @param string $condition
	 *
	 * @since 7.8
	 * @return array
	 */
	function vc_map_check_param_field_against_regex( $param, $regex_list, $condition ) { // phpcs:ignore:Generic.Metrics.CyclomaticComplexity.TooHigh
		$check_against = 'exclude' === $condition ? 1 : 0;

		if ( is_array( $regex_list ) && ! empty( $regex_list ) ) {
			$break_foreach = false;

			foreach ( $regex_list as $regex ) {
				if ( wpb_is_regex_valid( $regex ) ) {
					if ( preg_match( $regex, $param['param_name'] ) === $check_against ) {
						$param = null;
						$break_foreach = true;
					}
				}
				if ( $break_foreach ) {
					break;
				}
			}
			if ( $break_foreach ) {
				return $param; // to prevent group adding to $param.
			}
		} elseif ( is_string( $regex_list ) && strlen( $regex_list ) > 0 ) {
			$regex = $regex_list;
			if ( wpb_is_regex_valid( $regex ) ) {
				if ( preg_match( $regex, $param['param_name'] ) === $check_against ) {
					return null; // to prevent group adding to $param.
				}
			}
		}

		return $param;
	}
endif;
if ( ! function_exists( 'vc_map_integrate_add_dependency' ) ) :
	/**
	 * Adds a dependency to a parameter if it does not already have one.
	 *
	 * @param array $param
	 * @param mixed $dependency
	 *
	 * @return array
	 * @internal used to add dependency to exist param.
	 */
	function vc_map_integrate_add_dependency( $param, $dependency ) {
		// activator must be used for all elements if they do not have 'dependency'.
		if ( ! empty( $dependency ) && empty( $param['dependency'] ) ) {
			if ( is_array( $dependency ) ) {
				$param['dependency'] = $dependency;
			}
		}

		return $param;
	}
endif;
if ( ! function_exists( 'vc_map_integrate_get_params' ) ) :
	/**
	 * Retrieves parameters of a given base shortcode that are associated with a specified integrated shortcode.
	 *
	 * @param string $base_shortcode
	 * @param string $integrated_shortcode
	 * @param string $field_prefix
	 * @return array
	 * @throws Exception
	 */
	function vc_map_integrate_get_params( $base_shortcode, $integrated_shortcode, $field_prefix = '' ) {
		$shortcode_data = WPBMap::getShortCode( $base_shortcode );
		$params = [];
		if ( is_array( $shortcode_data ) && is_array( $shortcode_data['params'] ) && ! empty( $shortcode_data['params'] ) ) {
			foreach ( $shortcode_data['params'] as $param ) {
				if ( is_array( $param ) && isset( $param['integrated_shortcode'] ) && $integrated_shortcode === $param['integrated_shortcode'] ) {
					if ( ! empty( $field_prefix ) ) {
						if ( isset( $param['integrated_shortcode_field'] ) && $field_prefix === $param['integrated_shortcode_field'] ) {
							$params[] = $param;
						}
					} else {
						$params[] = $param;
					}
				}
			}
		}

		return $params;
	}
endif;
if ( ! function_exists( 'vc_map_integrate_get_atts' ) ) :
	/**
	 * Retrieves and processes default attributes for integrated shortcodes.
	 *
	 * This function fetches the parameters for a base shortcode and an integrated shortcode,
	 * then processes these parameters to generate a default set of attributes.
	 * The resulting associative array of attributes is returned.
	 *
	 * @param string $base_shortcode
	 * @param string $integrated_shortcode
	 * @param string $field_prefix
	 * @return array
	 * @throws Exception
	 */
	function vc_map_integrate_get_atts( $base_shortcode, $integrated_shortcode, $field_prefix = '' ) {
		$params = vc_map_integrate_get_params( $base_shortcode, $integrated_shortcode, $field_prefix );
		$atts = [];
		if ( is_array( $params ) && ! empty( $params ) ) {
			foreach ( $params as $param ) {
				$value = '';
				if ( isset( $param['value'] ) ) {
					if ( isset( $param['std'] ) ) {
						$value = $param['std'];
					} elseif ( is_array( $param['value'] ) ) {
						reset( $param['value'] );
						$value = current( $param['value'] );
					} else {
						$value = $param['value'];
					}
				}
				$atts[ $param['param_name'] ] = $value;
			}
		}

		return $atts;
	}
endif;
if ( ! function_exists( 'vc_map_add_css_animation' ) ) :
	/**
	 * Get CSS animation for shortcode params.
	 *
	 * @param bool $label
	 * @return mixed|void
	 */
	function vc_map_add_css_animation( $label = true ) {
		$data = [
			'type' => 'animation_style',
			'heading' => esc_html__( 'CSS Animation', 'js_composer' ),
			'param_name' => 'css_animation',
			'admin_label' => $label,
			'value' => '',
			'settings' => [
				'type' => 'in',
				'custom' => [
					[
						'label' => esc_html__( 'Default', 'js_composer' ),
						'values' => [
							esc_html__( 'Top to bottom', 'js_composer' ) => 'top-to-bottom',
							esc_html__( 'Bottom to top', 'js_composer' ) => 'bottom-to-top',
							esc_html__( 'Left to right', 'js_composer' ) => 'left-to-right',
							esc_html__( 'Right to left', 'js_composer' ) => 'right-to-left',
							esc_html__( 'Appear from center', 'js_composer' ) => 'appear',
						],
					],
				],
			],
			'description' => esc_html__( 'Select type of animation for element to be animated when it "enters" the browsers viewport (Note: works only in modern browsers).', 'js_composer' ),
		];

		return apply_filters( 'vc_map_add_css_animation', $data, $label );
	}
endif;
if ( ! function_exists( 'vc_convert_vc_color' ) ) :
	/**
	 * Convert color name to hex.
	 *
	 * @param string $name
	 * @return mixed|string
	 */
	function vc_convert_vc_color( $name ) {
		$colors = [
			'blue' => '#5472d2',
			'turquoise' => '#00c1cf',
			'pink' => '#fe6c61',
			'violet' => '#8d6dc4',
			'peacoc' => '#4cadc9',
			'chino' => '#cec2ab',
			'mulled-wine' => '#50485b',
			'vista-blue' => '#75d69c',
			'orange' => '#f7be68',
			'sky' => '#5aa1e3',
			'green' => '#6dab3c',
			'juicy-pink' => '#f4524d',
			'sandy-brown' => '#f79468',
			'purple' => '#b97ebb',
			'black' => '#2a2a2a',
			'grey' => '#ebebeb',
			'white' => '#ffffff',
		];
		$name = str_replace( '_', '-', $name );
		if ( isset( $colors[ $name ] ) ) {
			return $colors[ $name ];
		}

		return '';
	}
endif;
if ( ! function_exists( 'vc_get_shared' ) ) :
	/**
	 * Get a shared library for a specific asset.
	 *
	 * @param string $asset
	 *
	 * @return array|string
	 */
	function vc_get_shared( $asset = '' ) { // phpcs:ignore:Generic.Metrics.CyclomaticComplexity.MaxExceeded
		switch ( $asset ) {
			case 'colors':
				$asset = VcSharedLibrary::getColors();
				break;

			case 'colors-dashed':
				$asset = VcSharedLibrary::getColorsDashed();
				break;

			case 'icons':
				$asset = VcSharedLibrary::getIcons();
				break;

			case 'sizes':
				$asset = VcSharedLibrary::getSizes();
				break;

			case 'button styles':
			case 'alert styles':
				$asset = VcSharedLibrary::getButtonStyles();
				break;
			case 'message_box_styles':
				$asset = VcSharedLibrary::getMessageBoxStyles();
				break;
			case 'cta styles':
				$asset = VcSharedLibrary::getCtaStyles();
				break;

			case 'text align':
				$asset = VcSharedLibrary::getTextAlign();
				break;

			case 'cta widths':
			case 'separator widths':
				$asset = VcSharedLibrary::getElementWidths();
				break;

			case 'separator styles':
				$asset = VcSharedLibrary::getSeparatorStyles();
				break;

			case 'separator border widths':
				$asset = VcSharedLibrary::getBorderWidths();
				break;

			case 'single image styles':
				$asset = VcSharedLibrary::getBoxStyles();
				break;

			case 'single image external styles':
				$asset = VcSharedLibrary::getBoxStyles( [
					'default',
					'round',
				] );
				break;

			case 'toggle styles':
				$asset = VcSharedLibrary::getToggleStyles();
				break;

			case 'animation styles':
				$asset = VcSharedLibrary::getAnimationStyles();
				break;

			case 'icon libraries':
				$asset = VcSharedLibrary::getIconLibraries();
				break;
			case 'pixel icons':
				$asset = VcSharedLibrary::get_pixel_icons();
				break;
			case 'icons arr':
				$asset = VcSharedLibrary::get_icons_arr();
				break;
			case 'sizes arr':
				$asset = VcSharedLibrary::get_sizes_arr();
				break;
			case 'colors arr':
				$asset = VcSharedLibrary::get_color_arr();
				break;
			case 'target param list':
				$asset = VcSharedLibrary::get_target_param_list();
				break;
			case 'shortcuts':
				$asset = VcSharedLibrary::get_shortcut_list();
				break;
		}

		return $asset;
	}
endif;
if ( ! function_exists( 'vc_do_shortcode_param_settings_field' ) ) :
	/**
	 * Call hook for attribute.
	 *
	 * @param string $name - attribute name.
	 * @param array $param_settings - attribute settings from shortcode.
	 * @param mixed $param_value - attribute value.
	 * @param string $tag - attribute tag.
	 *
	 * @return mixed|string - returns html which will be render in hook
	 * @since 4.4
	 */
	function vc_do_shortcode_param_settings_field( $name, $param_settings, $param_value, $tag ) {
		return WpbakeryShortcodeParams::renderSettingsField( $name, $param_settings, $param_value, $tag );
	}
endif;
if ( ! function_exists( 'wpb_remove_emoji_assets' ) ) :
	/**
	 * Remove emoji styles and scripts from the head and admin.
	 *
	 * @since 8.6
	 */
	function wpb_remove_emoji_assets() {
		remove_action( 'wp_head', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
	}
endif;
