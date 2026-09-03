<?php
/**
 * Custom code settings like custom css and js for page are displayed here.
 *
 * @since 8.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

/**
 * Class Vc_Custom_Code.
 */
class Vc_Custom_Code {
	/**
	 * Editor type.
	 *
	 * @var mixed
	 */
	protected $editor;

	/**
	 * Post.
	 *
	 * @var WP_Post
	 */
	protected $post;

	/**
	 * Vc_Custom_Code constructor.
	 *
	 * @param mixed $editor
	 * @param WP_Post $post
	 */
	public function __construct( $editor, $post ) {
		$this->editor = $editor;
		$this->post = $post;
	}

	/**
	 * Get editor.
	 *
	 * @return mixed
	 */
	public function editor() {
		return $this->editor;
	}


	/**
	 * Render UI template.
	 */
	public function renderUITemplate() { // phpcs:ignore
		$custom_code_data = apply_filters( 'vc_template_custom_code', [] );

		vc_include_template( 'editors/popups/vc_ui-panel-custom-code.tpl.php',
		[
			'controls' => $this->get_controls(),
			'box' => $this,
			'page_settings_data' => array_merge([
				'can_unfiltered_html_cap' =>
					vc_user_access()->part( 'unfiltered_html' )->checkStateAny( true, null )->get(),
			], $custom_code_data),
			'header_tabs_template_variables' => [
				'categories' => $this->get_categories(),
			],
		] );
	}

	/**
	 * Get tab categories.
	 *
	 * @return array
	 */
	public function get_categories() {
		return apply_filters( 'vc_custom_code_categories', [] );
	}

	/**
	 * Get tabs templates (still needed for the main panel content loop).
	 *
	 * @return array
	 */
	public function get_tabs_templates() {
		return apply_filters( 'vc_custom_code_templates', [] );
	}

	/**
	 * Get modal popup template tabs, formatted for add_element_tabs.tpl.php.
	 *
	 * @param array $categories
	 * @return array
	 */
	public function get_tabs( $categories ) {
		$tabs = [];
		foreach ( $categories as $key => $name ) {
			$filter = '.vc_custom-code-tab-' . sanitize_key( $name ); // Match data-filter with tab content ID.
			$tabs[] = [
				'name' => $name,
				'filter' => $filter,
				'active' => 0 === $key, // Set the first tab as active.
			];
		}
		return $tabs;
	}

	/**
	 * Get controls of the custom code panel.
	 *
	 * @return array
	 */
	public function get_controls() {
		return [
			[
				'name'  => 'close',
				'label' => esc_html__( 'Close', 'js_composer' ),
				'title' => esc_html( wpb_get_title_with_shortcut( 'Close' ) ),
				'css_classes' => 'vc_ui-button-dismiss',
				'data_dismiss' => 'panel',
			],
			[
				'name'        => 'save',
				'label'       => esc_html__( 'Save changes', 'js_composer' ),
				'title'       => esc_html( wpb_get_title_with_shortcut( 'Save changes' ) ),
				'css_classes' => 'vc_ui-button-fw',
				'style'       => 'action',
			],
		];
	}
}
