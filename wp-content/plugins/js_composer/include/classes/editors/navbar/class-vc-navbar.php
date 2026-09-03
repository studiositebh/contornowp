<?php
/**
 * Renders navigation bar for Editors.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

/**
 * Class Vc_Navbar
 */
class Vc_Navbar {
	/**
	 *  List of controls to be displayed in the navigation bar.
	 *
	 * @var array
	 */
	protected $controls = [
		'add_element',
		'templates',
		'save_backend',
		'preview',
		'frontend',
		'post_settings',
		'custom_code',
		'fullscreen',
		'windowed',
		'more',
	];
	/**
	 * URL for the brand logo.
	 *
	 * @var string
	 */
	protected $brand_url = 'https://wpbakery.com?utm_source=wpb-plugin&utm_medium=backend-editor&utm_campaign=info&utm_content=logo';
	/**
	 * CSS class for the navigation bar.
	 *
	 * @var string
	 */
	protected $css_class = 'vc_navbar';
	/**
	 * Filter name for the controls.
	 *
	 * @var string
	 */
	protected $controls_filter_name = 'vc_nav_controls';
	/**
	 * The current post object.
	 *
	 * @var bool|WP_Post
	 */
	protected $post = false;

	/**
	 * Vc_Navbar constructor.
	 *
	 * @param WP_Post $post
	 */
	public function __construct( WP_Post $post ) {
		$this->post = $post;
	}

	/**
	 * Generate array of controls by iterating property $controls list.
	 * vc_filter: vc_nav_controls - hook to override list of controls
	 *
	 * @return array - list of arrays witch contains key name and html output for button.
	 */
	public function getControls() {
		$control_list = [];
		foreach ( $this->getControlList() as $control ) {
			$method = vc_camel_case( 'get_control_' . $control );
			if ( method_exists( $this, $method ) ) {
				$control_list[] = [
					$control,
					$this->$method(),
				];
			}
		}

		return apply_filters( $this->controls_filter_name, $control_list );
	}

	/**
	 * Get navbar control list.
	 *
	 * @since 7.7
	 * @return array
	 */
	public function getControlList() {
		/**
		 * Filters list of navbar controls.
		 *
		 * @param array $this->controls
		 * @param Vc_Navbar $this
		 *
		 * @since 7.7
		 */
		return apply_filters( 'vc_nav_control_list', $this->controls, $this );
	}

	/**
	 * Get current post.
	 *
	 * @return null|WP_Post
	 */
	public function post() {
		if ( $this->post ) {
			return $this->post;
		} else {
			$this->post = get_post();
		}

		return $this->post;
	}

	/**
	 * Render template.
	 */
	public function render() {
		vc_include_template( 'editors/navbar/navbar.tpl.php', [
			'css_class' => $this->css_class,
			'controls' => $this->getControls(),
			'nav_bar' => $this,
			'post' => $this->post(),
		] );
	}

	/**
	 * Gets the HTML for the WPBakery Page Builder logo.
	 *
	 * @see vc_filter: vc_nav_front_logo - hook to override WPBakery Page Builder logo
	 * @return string
	 */
	public function getLogo() {
		$output = '<a id="vc_logo" class="vc_navbar-brand" title="' . esc_attr__( 'WPBakery Page Builder', 'js_composer' ) . '" href="' . esc_url( $this->brand_url ) . '" target="_blank">' . esc_attr__( 'WPBakery Page Builder', 'js_composer' ) . '</a>';

		return apply_filters( 'vc_nav_front_logo', $output );
	}

	/**
	 * Renders the post settings control if the user has the necessary access.
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getControlPostSettings() {
		$has_post_settings_access = vc_user_access()->part( 'post_settings' )->can()->get();
		if ( ! $has_post_settings_access ) {
			return '';
		}

		$has_layout_module = vc_modules_manager()->is_module_on( [ 'vc-post-custom-layout' ] );

		// Backend editor: only show if layout module is enabled.
		// Frontend editor: show if post settings access OR layout module is enabled.
		$should_show = vc_is_frontend_editor() ? ( $has_post_settings_access || $has_layout_module ) : $has_layout_module;

		if ( ! $should_show ) {
			return '';
		}

		return vc_get_template( 'editors/navbar/vc_control-post-settings.php' );
	}

	/**
	 * Renders the custom CSS/JS control if at least one custom code module is enabled.
	 *
	 * @return string
	 */
	public function getControlCustomCode() {
		// Only show if at least one of the custom code modules is enabled.
		if ( ! vc_modules_manager()->is_module_on( [ 'vc-custom-js', 'vc-custom-css' ] ) ) {
			return '';
		}

		return vc_get_template( 'editors/navbar/vc_control-custom-code.php' );
	}

	/**
	 * Renders the fullscreen control.
	 *
	 * @return string
	 */
	public function getControlFullscreen() {
		return '<li class="vc_pull-right vc_hide-mobile"><a id="vc_fullscreen-button" class="vc_icon-btn vc_fullscreen-button" title="' . esc_attr__( 'Full screen', 'js_composer' ) . '"><i class="vc-composer-icon vc-c-icon-fullscreen"></i></a></li>';
	}

	/**
	 * Renders the windowed control.
	 *
	 * @return string
	 */
	public function getControlWindowed() {
		return '<li class="vc_pull-right"><a id="vc_windowed-button" class="vc_icon-btn vc_windowed-button" title="' . esc_attr__( 'Exit full screen', 'js_composer' ) . '"><i class="vc-composer-icon vc-c-icon-fullscreen_exit"></i></a></li>';
	}

	/**
	 * Renders the add element control if the user has the necessary access.
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getControlAddElement() {
		if ( vc_user_access()->part( 'shortcodes' )->checkStateAny( true, 'custom', null )->get() && vc_user_access_check_shortcode_all( 'vc_row' ) && vc_user_access_check_shortcode_all( 'vc_column' ) ) {
			$title = esc_attr( wpb_get_title_with_shortcut( 'Add new element' ) );
			return '<li class="vc_show-mobile">	<a href="javascript:;" class="vc_icon-btn vc_element-button" data-model-id="vc_element" id="vc_add-new-element" title="' . $title . '">    <i class="vc-composer-icon vc-c-icon-add_element"></i>	</a></li>';
		}

		return '';
	}

	/**
	 *  Renders the templates control if the user has the necessary access.
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getControlTemplates() {
		if ( ! vc_user_access()->part( 'templates' )->can()->get() ) {
			return '';
		}

		return vc_get_template( 'editors/navbar/vc_control-templates-button.php' );
	}

	/**
	 * Renders the frontend control if the frontend editor is enabled.
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getControlFrontend() {
		if ( ! vc_enabled_frontend() ) {
			return '';
		}

		return '<li class="vc_pull-right" style="display: none;"><a href="' . esc_url( vc_frontend_editor()->getInlineUrl() ) . '" class="vc_btn vc_btn-primary vc_btn-sm vc_navbar-btn" id="wpb-edit-inline">' . esc_html__( 'Frontend', 'js_composer' ) . '</a></li>';
	}

	/**
	 * Renders the preview control.
	 *
	 * @return string
	 */
	public function getControlPreview() {
		return '';
	}

	/**
	 * Renders the save backend control with appropriate label based on post status and user capabilities.
	 *
	 * @since 8.0
	 * @param bool $is_mobile since 8.0.
	 * @return string
	 */
	public function getControlSaveBackend( $is_mobile = false ) {
		$post_type = $this->post()->post_type;
		$post_type_object = get_post_type_object( $post_type );
		$can_publish = current_user_can( $post_type_object->cap->publish_posts );

		$post_type_list = [ 'publish', 'future', 'private' ];

		if ( in_array( get_post_status( $this->post() ), $post_type_list ) ) {
			$save_text = esc_html__( 'Update', 'js_composer' );
		} elseif ( $can_publish ) {
			$save_text = esc_html__( 'Publish', 'js_composer' );
		} else {
			$save_text = esc_html__( 'Submit for Review', 'js_composer' );
		}

		if ( $is_mobile ) {
			return vc_get_template(
				'editors/navbar/vc_control-buttons-mobile.tpl.php',
				[
					'save_text' => $save_text,
				]
			);
		} else {
			return vc_get_template(
				'editors/navbar/vc_control-buttons-desktop.tpl.php',
				[
					'save_text' => $save_text,
				]
			);
		}
	}

	/**
	 * Renders the more control.
	 *
	 * @since 8.0
	 * @return string
	 */
	public function getControlMore() {
		return vc_get_template(
			'editors/navbar/vc_control-get-more-buttons.tpl.php',
			[ '_this' => $this ]
		);
	}

	/**
	 * Output get more menu buttons.
	 *
	 * @since 8.0
	 */
	public function outputGetMoreMenuButtons() {
		$control_list = apply_filters( $this->controls_filter_name, [] );
		foreach ( $control_list as $control ) :
            // @codingStandardsIgnoreLine
            print $control[1];
		endforeach;

		$this->outputIndividualControlElements();
	}

	/**
	 * Output individual control elements.
	 *
	 * @since 8.0
	 */
	public function outputIndividualControlElements() {
		echo wp_kses_post( $this->getControlPostSettings() );
		echo wp_kses_post( $this->getControlCustomCode() );
		echo wp_kses_post( $this->getControlSaveBackend( true ) );
	}
}
