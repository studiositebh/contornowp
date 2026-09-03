<?php
/**
 * Helper functions shorthands to get main plugin components.
 *
 * @package WPBakeryPageBuilder
 * @since   4.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

if ( ! function_exists( 'vc_manager' ) ) :
	/**
	 * WPBakery Page Builder manager.
	 *
	 * @return Vc_Manager
	 * @since 4.2
	 */
	function vc_manager() {
		return Vc_Manager::getInstance();
	}
endif;
if ( ! function_exists( 'wpbakery' ) ) :
	/**
	 * WPBakery Page Builder instance.
	 *
	 * @return Vc_Base
	 * @since 6.8
	 */
	function wpbakery() {
		return vc_manager()->vc();
	}
endif;
if ( ! function_exists( 'vc_mapper' ) ) :
	/**
	 * Shorthand for Vc Mapper.
	 *
	 * @return Vc_Mapper
	 * @since 4.2
	 */
	function vc_mapper() {
		return vc_manager()->mapper();
	}
endif;
if ( ! function_exists( 'vc_settings' ) ) :
	/**
	 * Shorthand for WPBakery settings.
	 *
	 * @return Vc_Settings
	 * @since 4.2
	 */
	function vc_settings() {
		return vc_manager()->settings();
	}
endif;
if ( ! function_exists( 'vc_license' ) ) :
	/**
	 * Shorthand for WPBakery license manager.
	 *
	 * @return Vc_License
	 * @since 4.2
	 */
	function vc_license() {
		return vc_manager()->license();
	}
endif;
if ( ! function_exists( 'vc_automapper' ) ) :
	/**
	 * Shorthand for WPBakery automapper.
	 *
	 * @return Vc_Automapper
	 * @since 4.2
	 */
	function vc_automapper() {
		return vc_manager()->automapper();
	}
endif;
if ( ! function_exists( 'vc_autoload_manager' ) ) :
	/**
	 * Shorthand for WPBakery autoload manager.
	 *
	 * @return Vc_Autoload_Manager
	 * @since 7.7
	 */
	function vc_autoload_manager() {
		return vc_manager()->autoload();
	}
endif;
if ( ! function_exists( 'vc_modules_manager' ) ) :
	/**
	 * Shorthand for WPBakery module manager.
	 *
	 * @return Vc_Modules_Manager
	 * @since 7.7
	 */
	function vc_modules_manager() {
		return vc_manager()->modules();
	}
endif;
if ( ! function_exists( 'vc_frontend_editor' ) ) :
	/**
	 * Shorthand for WPBakery frontend editor.
	 *
	 * @return Vc_Frontend_Editor
	 * @since 4.2
	 */
	function vc_frontend_editor() {
		return vc_manager()->frontendEditor();
	}
endif;
if ( ! function_exists( 'vc_backend_editor' ) ) :
	/**
	 * Shorthand for WPBakery backend editor.
	 *
	 * @return Vc_Backend_Editor
	 * @since 4.2
	 */
	function vc_backend_editor() {
		return vc_manager()->backendEditor();
	}
endif;
if ( ! function_exists( 'vc_updater' ) ) :
	/**
	 * Shorthand for WPBakery updater.
	 *
	 * @return Vc_Updater
	 * @since 4.2
	 */
	function vc_updater() {
		return vc_manager()->updater();
	}
endif;
if ( ! function_exists( 'vc_is_network_plugin' ) ) :
	/**
	 * Check if is network plugin or not.
	 *
	 * @return bool
	 * @since 4.2
	 */
	function vc_is_network_plugin() {
		return vc_manager()->isNetworkPlugin();
	}
endif;
if ( ! function_exists( 'vc_path_dir' ) ) :
	/**
	 * Get file/directory path in Vc.
	 *
	 * @param string $name - path name.
	 * @param string $file
	 *
	 * @return string
	 * @since 4.2
	 */
	function vc_path_dir( $name, $file = '' ) {
		return vc_manager()->path( $name, $file );
	}
endif;
if ( ! function_exists( 'vc_asset_url' ) ) :
	/**
	 * Get full url for assets.
	 *
	 * @param string $file
	 *
	 * @return string
	 * @since 4.2
	 */
	function vc_asset_url( $file ) {
		return vc_manager()->assetUrl( $file );
	}
endif;
if ( ! function_exists( 'vc_upload_dir' ) ) :
	/**
	 * Temporary files upload dir.
	 *
	 * @return string
	 * @since 4.2
	 */
	function vc_upload_dir() {
		return vc_manager()->uploadDir();
	}
endif;
if ( ! function_exists( 'vc_template' ) ) :
	/**
	 * Shorthand for getting to the plugin templates.
	 *
	 * @param string $file
	 *
	 * @return string
	 * @since 4.2
	 */
	function vc_template( $file ) {
		return vc_path_dir( 'TEMPLATES_DIR', $file );
	}
endif;
if ( ! function_exists( 'vc_mode' ) ) :
	/**
	 * Return current VC mode.
	 *
	 * @see Vc_Manager::setMode
	 * @since 4.2
	 * @return string
	 */
	function vc_mode() {
		return vc_manager()->mode();
	}
endif;
if ( ! function_exists( 'vc_is_frontend_editor' ) ) :
	/**
	 * Check if the current plugin mode is frontend editor mode.
	 *
	 * @return bool
	 * @since 4.2
	 */
	function vc_is_frontend_editor() {
		return 'admin_frontend_editor' === vc_mode();
	}
endif;
if ( ! function_exists( 'vc_is_page_editable' ) ) :
	/**
	 * Check if the current plugin mode is page_editable mode.
	 *
	 * @return bool
	 * @since 4.2
	 */
	function vc_is_page_editable() {
		return 'page_editable' === vc_mode();
	}
endif;
if ( ! function_exists( 'vc_is_gutenberg_editor' ) ) :
	/**
	 * Check if current screen is Gutenberg editor screen.
	 *
	 * @return bool
	 * @since 7.0
	 */
	function vc_is_gutenberg_editor() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$current_screen = get_current_screen();
		if ( ! method_exists( $current_screen, 'is_block_editor' ) ) {
			return false;
		}

		return get_current_screen()->is_block_editor();
	}
endif;
if ( ! function_exists( 'vc_is_inline' ) ) :
	/**
	 * Get is inline or not.
	 *
	 * @return bool
	 * @since 4.2
	 */
	function vc_is_inline() {
		global $vc_is_inline;
		if ( is_null( $vc_is_inline ) ) {
			$vc_is_inline = ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) ) && 'vc_inline' === vc_action() || ! is_null( vc_request_param( 'vc_inline' ) ) || 'true' === vc_request_param( 'vc_editable' );
		}

		return $vc_is_inline;
	}
endif;
if ( ! function_exists( 'vc_is_frontend_ajax' ) ) :
	/**
	 * Check if current request is frontend ajax request.
	 *
	 * @return bool
	 * @since 4.2
	 */
	function vc_is_frontend_ajax() {
		return 'true' === vc_post_param( 'vc_inline' ) || vc_get_param( 'vc_inline' );
	}
endif;
if ( ! function_exists( 'vc_action' ) ) :
	/**
	 * Get VC special action param.
	 *
	 * @return string|null
	 * @since 4.2
	 */
	function vc_action() {
		return wp_strip_all_tags( vc_request_param( 'vc_action' ) );
	}
endif;
if ( ! function_exists( 'vc_plugin_name' ) ) :
	/**
	 * Plugin name for WPBakery.
	 *
	 * @return string
	 * @since 4.2
	 */
	function vc_plugin_name() {
		return vc_manager()->pluginName();
	}
endif;
if ( ! function_exists( 'vc_role_access' ) ) :
	/**
	 * Shorthand for WPBakery role access manager.
	 *
	 * HowTo: vc_role_access()->who('administrator')->with('editor')->can('frontend_editor');
	 *
	 * @return Vc_Role_Access;
	 * @since 4.8
	 */
	function vc_role_access() {
		return vc_manager()->getRoleAccess();
	}
endif;
if ( ! function_exists( 'vc_user_access' ) ) :
	/**
	 * Shorthand for current user access.
	 *
	 * HowTo: vc_user_access()->->with('editor')->can('frontend_editor');
	 *
	 * @return Vc_Current_User_Access;
	 * @since 4.8
	 */
	function vc_user_access() {
		return vc_manager()->getCurrentUserAccess();
	}
endif;
