<?php
/**
 * Autoload lib for default template for post type manager.
 *
 * @note we require our autoload files everytime and everywhere after plugin load.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

if ( ! function_exists( 'vc_set_default_content_for_post_type_wpb_vc_js_status_filter' ) ) {
	/**
	 * Return true value for filter 'wpb_vc_js_status_filter'.
	 * It allows to start backend editor on load.
	 *
	 * @return string
	 * @since 4.12
	 */
	function vc_set_default_content_for_post_type_wpb_vc_js_status_filter() {
		return 'true';
	}
}

if ( ! function_exists( 'vc_set_default_content_for_post_type_back_editor' ) ) {
	/**
	 * Set default content by post type in backend editor.
	 *
	 * Data for post type templates stored in settings.
	 *
	 * @param string|null $post_content
	 * @param WP_Post $post
	 * @return string|null
	 * @throws Exception
	 * @since 8.5
	 */
	function vc_set_default_content_for_post_type_back_editor( $post_content, $post ) {
		if ( ! empty( $post_content ) || ! vc_backend_editor()->isValidPostType( $post->post_type ) ) {
			return $post_content;
		}
		require_once vc_path_dir( 'SETTINGS_DIR', 'class-vc-setting-post-type-default-template-field.php' );

		$template_settings = new Vc_Setting_Post_Type_Default_Template_Field( 'general', 'default_template_post_type' );
		$new_post_content = $template_settings->getTemplateByPostType( $post->post_type );
		if ( null !== $new_post_content ) {
			add_filter( 'wpb_vc_js_status_filter', 'vc_set_default_content_for_post_type_wpb_vc_js_status_filter' );

			return $new_post_content;
		}

		return $post_content;
	}
}

if ( ! function_exists( 'vc_set_default_content_for_post_type_front_editor' ) ) {
	/**
	 * Set default content by post type in front editor.
	 *
	 * Data for post type templates stored in settings.
	 *
	 * @param array $post_data
	 * @param WP_Post|null $post
	 * @return array
	 * @since 8.5
	 */
	function vc_set_default_content_for_post_type_front_editor( $post_data, $post ) {
		if ( empty( $post ) || ! empty( $post->post_content ) ) {
			return $post_data;
		}
		require_once vc_path_dir( 'SETTINGS_DIR', 'class-vc-setting-post-type-default-template-field.php' );

		$template_settings = new Vc_Setting_Post_Type_Default_Template_Field( 'general', 'default_template_post_type' );
		$new_post_content = $template_settings->getTemplateByPostType( $post->post_type );
		if ( null !== $new_post_content ) {
			$post_data['post_content'] = $new_post_content;
		}

		return $post_data;
	}
}



if ( ! function_exists( 'vc_is_default_content_for_post_type' ) ) {
	/**
	 * Check if default content for post type is set.
	 *
	 * @since 8.2
	 * @param string $post_type
	 * @return bool
	 */
	function vc_is_default_content_for_post_type( $post_type ) {
		require_once vc_path_dir( 'SETTINGS_DIR', 'class-vc-setting-post-type-default-template-field.php' );

		$template_settings = new Vc_Setting_Post_Type_Default_Template_Field( 'general', 'default_template_post_type' );
		$option_key = $template_settings->getFieldKey();
		$default_content_post_types = get_option( $option_key, [] );

		if ( isset( $default_content_post_types[ $post_type ] ) ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'vc_add_backend_editor_param_to_button_link' ) ) {
	/**
	 * Check if default content set and if yes
	 * add backend editor param to 'Add New Post' button links.
	 *
	 * @since 8.2
	 * @param string $url
	 * @param string $path
	 * @return string
	 */
	function vc_add_backend_editor_param_to_button_link( $url, $path ) {
		$is_new_post_path = strpos( $path, 'post-new.php' );

		if ( false === $is_new_post_path ) {
			return $url;
		}

		$post_type = preg_match( '/\bpost_type=([^&]+)/', $url, $matches ) ? $matches[1] : 'post';

		if ( vc_is_default_content_for_post_type( $post_type ) ) {
			$url = add_query_arg( 'wpb-backend-editor', '', $url );
		}

		return $url;
	}
}

if ( ! function_exists( 'vc_redirect_new_post_page_to_back_editor' ) ) {
	/**
	 * Process our redirect to backend editor user role functionality.
	 *
	 * @since 8.4
	 */
	function vc_redirect_new_post_page_to_back_editor() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( isset( $_GET['wpb-backend-editor'] ) ) {
			return;
		}

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		global $pagenow;
		if ( 'post-new.php' !== $pagenow ) {
			return;
		}

		$is_backend_editor_default = vc_user_access()->part( 'backend_editor' )->checkState( 'default' )->get();
		if ( ! $is_backend_editor_default ) {
			return;
		}

		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post';
		if ( ! vc_check_post_type( $post_type ) ) {
			return;
		}

		$request_uri = sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$url = add_query_arg( 'wpb-backend-editor', '', $request_uri );
		wp_safe_redirect( $url );
		exit;
	}
}


if ( ! function_exists( 'vc_add_backend_editor_param_add_post_menu_links' ) ) {
	/**
	 * Check if default content set and if yes
	 * add backend editor param to 'Add New Post' menu links.
	 *
	 * @since 8.2
	 */
	function vc_add_backend_editor_param_add_post_menu_links() {
		global $submenu;

		// Loop through the $menu array to find and modify links.
		foreach ( $submenu as $key => $menu_item ) {
			if ( ! isset( $menu_item[10][2] ) ) {
				continue;
			}

			$is_new_post_path = strpos( $menu_item[10][2], 'post-new.php' );
			if ( false === $is_new_post_path ) {
				continue;
			}

			$post_type = preg_match( '/\bpost_type=([^&]+)/', $menu_item[10][2], $matches ) ? $matches[1] : 'post';

			if ( vc_is_default_content_for_post_type( $post_type ) ) {
				$submenu[ $key ][10][2] = add_query_arg( 'wpb-backend-editor', '', $menu_item[10][2] );
			}
		}
	}
}

if ( ! function_exists( 'vc_settings_post_type_default_template_field_init' ) ) {
	/**
	 * Initialize Vc_Setting_Post_Type_Default_Template_Field
	 * Called by admin_init hook
	 */
	function vc_settings_post_type_default_template_field_init() {
		require_once vc_path_dir( 'SETTINGS_DIR', 'class-vc-setting-post-type-default-template-field.php' );

		new Vc_Setting_Post_Type_Default_Template_Field( 'general', 'default_template_post_type' );
	}
}

if ( ! function_exists( 'vc_set_post_custom_layout_front_editor' ) ) {
	/**
	 * We need to disable custom layout screen if we have predefined layout in front editor.
	 *
	 * @param string $layout_name
	 * @return string
	 * @since 8.5
	 */
	function vc_set_post_custom_layout_front_editor( $layout_name ) {
		if ( '' !== $layout_name ) {
			return $layout_name;
		}

		if ( ! vc_is_frontend_editor() ) {
			return $layout_name;
		}

		global $post;
		require_once vc_path_dir( 'SETTINGS_DIR', 'class-vc-setting-post-type-default-template-field.php' );
		$template_settings = new Vc_Setting_Post_Type_Default_Template_Field( 'general', 'default_template_post_type' );
		$new_post_content = $template_settings->getTemplateByPostType( $post->post_type );
		if ( null !== $new_post_content ) {
			return 'default';
		}

		return $layout_name;
	}
}

/**
 * Start only for admin part with hooks
 */
if ( is_admin() ) {
	add_filter( 'default_content', 'vc_set_default_content_for_post_type_back_editor', 100, 2 );
	add_filter( 'vc_frontend_editor_new_post_data', 'vc_set_default_content_for_post_type_front_editor', 100, 2 );
	add_filter( 'vc_post_custom_layout_name', 'vc_set_post_custom_layout_front_editor', 10, 1 );
	add_action( 'admin_init', 'vc_settings_post_type_default_template_field_init', 8 );
	add_filter( 'admin_url', 'vc_add_backend_editor_param_to_button_link', 10, 2 );
	add_action( 'admin_init', 'vc_redirect_new_post_page_to_back_editor' );
	add_action( 'admin_menu', 'vc_add_backend_editor_param_add_post_menu_links', 10, 2 );
}
