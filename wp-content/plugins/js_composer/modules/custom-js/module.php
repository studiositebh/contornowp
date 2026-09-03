<?php
/**
 * Module Name: Custom JS
 *
 * Description: Allow implement custom JS code to the whole site and individual pages.
 *
 * @since 7.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

require_once vc_manager()->path( 'MODULES_DIR', 'custom-js/class-vc-custom-js-module-settings.php' );

/**
 * Module entry point.
 *
 * @since 7.7
 */
class Vc_Custom_Js_Module {

	/**
	 * Settings object.
	 *
	 * @var Vc_Custom_Js_Module_Settings
	 */
	public $settings;

	/**
	 * Vc_Custom_Js_Module constructor.
	 *
	 * @since 8.0
	 */
	public function __construct() {
		$this->settings = new Vc_Custom_Js_Module_Settings();
		$this->settings->init();
	}

	/**
	 * Init module implementation.
	 *
	 * @since 7.7
	 */
	public function init() {
		add_action( 'vc_build_page', [ $this, 'output_custom_js_to_page' ] );

		add_filter( 'vc_post_meta_list', [ $this, 'add_custom_meta_to_update' ] );

		add_filter( 'wpb_set_post_custom_meta', [ $this, 'set_post_custom_meta' ], 10, 2 );

		add_filter( 'wpb_enqueue_backend_editor_js', [
			$this,
			'enqueue_editor_js',
		]);

		add_filter( 'vc_enqueue_frontend_editor_js', [
			$this,
			'enqueue_editor_js',
		]);

		// Add custom code filters.
		add_filter( 'vc_template_custom_code', [ $this, 'add_template_to_custom_code' ] );
		add_filter( 'vc_custom_code_categories', [ $this, 'add_custom_code_categories' ], 20 );
		add_filter( 'vc_custom_code_templates', [ $this, 'add_custom_code_templates' ], 20 );
	}

	/**
	 * Add custom js to page.
	 *
	 * @since 7.7
	 */
	public function output_custom_js_to_page() {
		add_filter( 'print_head_scripts', [
			$this,
			'output_post_header_custom_js',
		], 90, 1 );
		add_action( 'wp_print_footer_scripts', [
			$this,
			'output_post_footer_custom_js',
		], 90 );
		add_filter( 'print_head_scripts', [
			$this,
			'output_global_header_custom_html',
		], 100, 1 );
		add_action( 'wp_print_footer_scripts', [
			$this,
			'output_global_footer_custom_html',
		], 100 );
	}

	/**
	 * Add post custom html to the header tag of the page.
	 *
	 * @param bool $is_print
	 *
	 * @since 7.0
	 */
	public function output_post_header_custom_js( $is_print ) {
		$id = wpb_get_post_id_for_custom_output();

		if ( ! $id ) {
			return $is_print;
		}

		$id = wpb_update_id_with_preview_id( $id );

		$post_header_html = get_post_meta( $id, '_wpb_post_custom_js_header', true );

		if ( empty( $post_header_html ) ) {
			return $is_print;
		}

		$this->output_custom_js( $post_header_html, 'header' );
		return $is_print;
	}

	/**
	 * Add post custom html to the footer tag of the page.
	 *
	 * @since 7.0
	 */
	public function output_post_footer_custom_js() {
		$id = wpb_get_post_id_for_custom_output();

		if ( ! $id ) {
			return;
		}

		$id = wpb_update_id_with_preview_id( $id );

		$post_footer_html = get_post_meta( $id, '_wpb_post_custom_js_footer', true );

		if ( empty( $post_footer_html ) ) {
			return;
		}

		$this->output_custom_js( $post_footer_html, 'footer' );
	}

	/**
	 * Output custom js on a page.
	 *
	 * @since 7.0
	 * @param string $js
	 * @param string $area
	 */
	public function output_custom_js( $js, $area ) {
		echo '<script data-type="vc_custom-js-' . esc_attr( $area ) . '">';
		if ( vc_is_page_editable() ) {
			// we need to wait for iframe load on frontend editor side.
			$js = "setTimeout(() => {\r\n" . wp_unslash( $js ) . "\r\n}, 2000);";
		}
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_unslash( $js );
		echo '</script>';
	}

	/**
	 * Add custom html to the header tag of the page.
	 *
	 * @param bool $is_print
	 *
	 * @since 7.7
	 */
	public function output_global_header_custom_html( $is_print ) {
		$global_header_html = get_option( Vc_Settings::$field_prefix . 'custom_js_header' );

		echo '<script>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_unslash( $global_header_html );
		echo '</script>';

		return $is_print;
	}

	/**
	 * Add custom html to the footer tag of the page.
	 *
	 * @since 7.7
	 */
	public function output_global_footer_custom_html() {
		$global_footer_html = get_option( Vc_Settings::$field_prefix . 'custom_js_footer' );

		echo '<script>';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_unslash( $global_footer_html );
		echo '</script>';
	}

	/**
	 * Add custom js to the plugin post custom meta list.
	 *
	 * @param array $meta_list
	 * @return array
	 */
	public function add_custom_meta_to_update( $meta_list ) {
		$meta_list[] = 'custom_js_header';
		$meta_list[] = 'custom_js_footer';

		return $meta_list;
	}

	/**
	 * Set post custom meta.
	 *
	 * @param array $post_custom_meta
	 * @param WP_Post $post
	 * @return array
	 */
	public function set_post_custom_meta( $post_custom_meta, $post ) {
		$post_custom_meta['post_custom_js_header'] = get_post_meta( $post->ID, '_wpb_post_custom_js_header', true );
		$post_custom_meta['post_custom_js_footer'] = get_post_meta( $post->ID, '_wpb_post_custom_js_footer', true );

		return $post_custom_meta;
	}

	/**
	 * Load module JS in frontend and backend editor.
	 *
	 * @since 7.8
	 * @param array $dependencies
	 * @return array
	 */
	public function enqueue_editor_js( $dependencies ) {
		$dependencies[] = 'ace-editor';
		$dependencies[] = 'wpb-code-editor';

		return $dependencies;
	}

	/**
	 * Add template info to custom code.
	 *
	 * @since 8.5
	 * @param array $custom_code_info
	 * @return array
	 */
	public function add_template_to_custom_code( $custom_code_info ) {
		global $post;
		$custom_code_info['custom_js_header'] = get_post_meta( $post->ID, '_wpb_post_custom_js_header', true );
		$custom_code_info['custom_js_footer'] = get_post_meta( $post->ID, '_wpb_post_custom_js_footer', true );
		$custom_code_info['js_head_info_template'] = 'editors/partials/param-info.tpl.php';
		$custom_code_info['js_head_info_description'] = esc_html__( 'Enter custom JS (Note: it will be outputted only on this particular page inside <head> tag).', 'js_composer' );
		$custom_code_info['js_body_info_template'] = 'editors/partials/param-info.tpl.php';
		$custom_code_info['js_body_info_description'] = esc_html__( 'Enter custom JS (Note: it will be outputted only on this particular page before closing).', 'js_composer' );
		return $custom_code_info;
	}

	/**
	 * Add categories to custom code.
	 *
	 * @since 8.5
	 * @param array $categories
	 * @return array
	 */
	public function add_custom_code_categories( $categories ) {
		if ( vc_modules_manager()->is_module_on( 'vc-custom-js' ) ) {
			$categories[] = esc_html__( 'JS', 'js_composer' );
		}
		return $categories;
	}

	/**
	 * Add templates to custom code.
	 *
	 * @since 8.5
	 * @param array $templates
	 * @return array
	 */
	public function add_custom_code_templates( $templates ) {
		if ( vc_modules_manager()->is_module_on( 'vc-custom-js' ) ) {
			$templates[] = 'editors/popups/custom-code/js-tab.tpl.php';
		}
		return $templates;
	}
}
