<?php
/**
 * WPBakery Page Builder Content elements refresh.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

/**
 * Class VcSharedLibrary
 *
 * Here we will store plugin wise (shared) settings. Colors, Locations, Sizes, etc.
 */
class VcSharedLibrary {
	/**
	 * Available color options.
	 *
	 * @var array
	 */
	private static $colors = [
		'Blue' => 'blue',
		'Turquoise' => 'turquoise',
		'Pink' => 'pink',
		'Violet' => 'violet',
		'Peacoc' => 'peacoc',
		'Chino' => 'chino',
		'Mulled Wine' => 'mulled_wine',
		'Vista Blue' => 'vista_blue',
		'Black' => 'black',
		'Grey' => 'grey',
		'Orange' => 'orange',
		'Sky' => 'sky',
		'Green' => 'green',
		'Juicy pink' => 'juicy_pink',
		'Sandy brown' => 'sandy_brown',
		'Purple' => 'purple',
		'White' => 'white',
	];

	/**
	 * Available icon options.
	 *
	 * @var array
	 */
	public static $icons = [
		'Glass' => 'glass',
		'Music' => 'music',
		'Search' => 'search',
	];

	/**
	 * Available size options.
	 *
	 * @var array
	 */
	public static $sizes = [
		'Mini' => 'xs',
		'Small' => 'sm',
		'Normal' => 'md',
		'Large' => 'lg',
	];

	/**
	 * Available button styles.
	 *
	 * @var array
	 */
	public static $button_styles = [
		'Rounded' => 'rounded',
		'Square' => 'square',
		'Round' => 'round',
		'Outlined' => 'outlined',
		'3D' => '3d',
		'Square Outlined' => 'square_outlined',
	];

	/**
	 * Available message box styles.
	 *
	 * @var array
	 */
	public static $message_box_styles = [
		'Standard' => 'standard',
		'Solid' => 'solid',
		'Solid icon' => 'solid-icon',
		'Outline' => 'outline',
		'3D' => '3d',
	];

	/**
	 * Available toggle styles.
	 *
	 * @var array
	 */
	public static $toggle_styles = [
		'Default' => 'default',
		'Simple' => 'simple',
		'Round' => 'round',
		'Round Outline' => 'round_outline',
		'Rounded' => 'rounded',
		'Rounded Outline' => 'rounded_outline',
		'Square' => 'square',
		'Square Outline' => 'square_outline',
		'Arrow' => 'arrow',
		'Text Only' => 'text_only',
	];

	/**
	 * Available animation styles.
	 *
	 * @var array
	 */
	public static $animation_styles = [
		'Bounce' => 'easeOutBounce',
		'Elastic' => 'easeOutElastic',
		'Back' => 'easeOutBack',
		'Cubic' => 'easeInOutCubic',
		'Quint' => 'easeInOutQuint',
		'Quart' => 'easeOutQuart',
		'Quad' => 'easeInQuad',
		'Sine' => 'easeOutSine',
	];

	/**
	 * Available call to action styles.
	 *
	 * @var array
	 */
	public static $cta_styles = [
		'Rounded' => 'rounded',
		'Square' => 'square',
		'Round' => 'round',
		'Outlined' => 'outlined',
		'Square Outlined' => 'square_outlined',
	];

	/**
	 * Available text align options.
	 *
	 * @var array
	 */
	public static $txt_align = [
		'Left' => 'left',
		'Right' => 'right',
		'Center' => 'center',
		'Justify' => 'justify',
	];

	/**
	 * Available element widths.
	 *
	 * @var array
	 */
	public static $el_widths = [
		'100%' => '',
		'90%' => '90',
		'80%' => '80',
		'70%' => '70',
		'60%' => '60',
		'50%' => '50',
		'40%' => '40',
		'30%' => '30',
		'20%' => '20',
		'10%' => '10',
	];

	/**
	 * Available separator widths.
	 *
	 * @var array
	 */
	public static $sep_widths = [
		'1px' => '',
		'2px' => '2',
		'3px' => '3',
		'4px' => '4',
		'5px' => '5',
		'6px' => '6',
		'7px' => '7',
		'8px' => '8',
		'9px' => '9',
		'10px' => '10',
	];

	/**
	 * Available separator styles.
	 *
	 * @var array
	 */
	public static $sep_styles = [
		'Border' => '',
		'Dashed' => 'dashed',
		'Dotted' => 'dotted',
		'Double' => 'double',
		'Shadow' => 'shadow',
	];

	/**
	 * Available box styles.
	 *
	 * @var array
	 */
	public static $box_styles = [
		'Default' => '',
		'Rounded' => 'vc_box_rounded',
		'Border' => 'vc_box_border',
		'Outline' => 'vc_box_outline',
		'Shadow' => 'vc_box_shadow',
		'Bordered shadow' => 'vc_box_shadow_border',
		'3D Shadow' => 'vc_box_shadow_3d',
	];

	/**
	 * Available round box styles.
	 *
	 * @var array
	 */
	public static $round_box_styles = [
		'Round' => 'vc_box_circle',
		'Round Border' => 'vc_box_border_circle',
		'Round Outline' => 'vc_box_outline_circle',
		'Round Shadow' => 'vc_box_shadow_circle',
		'Round Border Shadow' => 'vc_box_shadow_border_circle',
	];

	/**
	 * Available circle box styles.
	 *
	 * @var array
	 */
	public static $circle_box_styles = [
		'Circle' => 'vc_box_circle_2',
		'Circle Border' => 'vc_box_border_circle_2',
		'Circle Outline' => 'vc_box_outline_circle_2',
		'Circle Shadow' => 'vc_box_shadow_circle_2',
		'Circle Border Shadow' => 'vc_box_shadow_border_circle_2',
	];

	/**
	 * Get available colors.
	 *
	 * @return array
	 */
	public static function getColors() {
		return self::$colors;
	}

	/**
	 * Get available icons.
	 *
	 * @return array
	 */
	public static function getIcons() {
		return self::$icons;
	}

	/**
	 * Get available sizes.
	 *
	 * @return array
	 */
	public static function getSizes() {
		return self::$sizes;
	}

	/**
	 * Get available button styles.
	 *
	 * @return array
	 */
	public static function getButtonStyles() {
		return self::$button_styles;
	}

	/**
	 * Get available message box styles.
	 *
	 * @return array
	 */
	public static function getMessageBoxStyles() {
		return self::$message_box_styles;
	}

	/**
	 * Get available toggle styles.
	 *
	 * @return array
	 */
	public static function getToggleStyles() {
		return self::$toggle_styles;
	}

	/**
	 * Get available animation styles.
	 *
	 * @return array
	 */
	public static function getAnimationStyles() {
		return self::$animation_styles;
	}

	/**
	 * Get available call to action styles.
	 *
	 * @return array
	 */
	public static function getCtaStyles() {
		return self::$cta_styles;
	}

	/**
	 * Get available text align options.
	 *
	 * @return array
	 */
	public static function getTextAlign() {
		return self::$txt_align;
	}

	/**
	 * Get available element widths.
	 *
	 * @return array
	 */
	public static function getBorderWidths() {
		return self::$sep_widths;
	}

	/**
	 * Get available element widths.
	 *
	 * @return array
	 */
	public static function getElementWidths() {
		return self::$el_widths;
	}

	/**
	 * Get available separator styles.
	 *
	 * @return array
	 */
	public static function getSeparatorStyles() {
		return self::$sep_styles;
	}

	/**
	 * Get list of box styles
	 *
	 * Possible $groups values:
	 * - default
	 * - round
	 * - circle
	 *
	 * @param array $groups Array of groups to include. If not specified, return all.
	 *
	 * @return array
	 */
	public static function getBoxStyles( $groups = [] ) {
		$list = [];
		$groups = (array) $groups;

		if ( ! $groups || in_array( 'default', $groups, true ) ) {
			$list += self::$box_styles;
		}

		if ( ! $groups || in_array( 'round', $groups, true ) ) {
			$list += self::$round_box_styles;
		}

		if ( ! $groups || in_array( 'cirlce', $groups, true ) ) {
			$list += self::$circle_box_styles;
		}

		return $list;
	}

	/**
	 * Get available colors.
	 *
	 * @return array
	 */
	public static function getColorsDashed() {
		$colors = [
			esc_html__( 'Blue', 'js_composer' ) => 'blue',
			esc_html__( 'Turquoise', 'js_composer' ) => 'turquoise',
			esc_html__( 'Pink', 'js_composer' ) => 'pink',
			esc_html__( 'Violet', 'js_composer' ) => 'violet',
			esc_html__( 'Peacoc', 'js_composer' ) => 'peacoc',
			esc_html__( 'Chino', 'js_composer' ) => 'chino',
			esc_html__( 'Mulled Wine', 'js_composer' ) => 'mulled-wine',
			esc_html__( 'Vista Blue', 'js_composer' ) => 'vista-blue',
			esc_html__( 'Black', 'js_composer' ) => 'black',
			esc_html__( 'Grey', 'js_composer' ) => 'grey',
			esc_html__( 'Orange', 'js_composer' ) => 'orange',
			esc_html__( 'Sky', 'js_composer' ) => 'sky',
			esc_html__( 'Green', 'js_composer' ) => 'green',
			esc_html__( 'Juicy pink', 'js_composer' ) => 'juicy-pink',
			esc_html__( 'Sandy brown', 'js_composer' ) => 'sandy-brown',
			esc_html__( 'Purple', 'js_composer' ) => 'purple',
			esc_html__( 'White', 'js_composer' ) => 'white',
		];

		return $colors;
	}

	/**
	 * Get available icon libraries.
	 *
	 * @return array
	 */
	public static function getIconLibraries() {
		$icon_libraries = [
			esc_html__( 'Font Awesome', 'js_composer' ) => 'fontawesome',
			esc_html__( 'Open Iconic', 'js_composer' ) => 'openiconic',
			esc_html__( 'Typicons', 'js_composer' ) => 'typicons',
			esc_html__( 'Entypo', 'js_composer' ) => 'entypo',
			esc_html__( 'Linecons', 'js_composer' ) => 'linecons',
			esc_html__( 'Mono Social', 'js_composer' ) => 'monosocial',
			esc_html__( 'Material', 'js_composer' ) => 'material',
			esc_html__( 'Pixel', 'js_composer' ) => 'pixelicons',
		];

		return $icon_libraries;
	}

	/**
	 * Get configuration for pixel_icons shortcode.
	 *
	 * @since 8.6
	 * @return array
	 */
	public static function get_pixel_icons() {
		return [
			[ 'vc_pixel_icon vc_pixel_icon-alert' => esc_html__( 'Alert', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-info' => esc_html__( 'Info', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-tick' => esc_html__( 'Tick', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-explanation' => esc_html__( 'Explanation', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-address_book' => esc_html__( 'Address book', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-alarm_clock' => esc_html__( 'Alarm clock', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-anchor' => esc_html__( 'Anchor', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-application_image' => esc_html__( 'Application Image', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-arrow' => esc_html__( 'Arrow', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-asterisk' => esc_html__( 'Asterisk', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-hammer' => esc_html__( 'Hammer', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-balloon' => esc_html__( 'Balloon', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-balloon_buzz' => esc_html__( 'Balloon Buzz', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-balloon_facebook' => esc_html__( 'Balloon Facebook', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-balloon_twitter' => esc_html__( 'Balloon Twitter', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-battery' => esc_html__( 'Battery', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-binocular' => esc_html__( 'Binocular', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-document_excel' => esc_html__( 'Document Excel', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-document_image' => esc_html__( 'Document Image', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-document_music' => esc_html__( 'Document Music', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-document_office' => esc_html__( 'Document Office', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-document_pdf' => esc_html__( 'Document PDF', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-document_powerpoint' => esc_html__( 'Document Powerpoint', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-document_word' => esc_html__( 'Document Word', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-bookmark' => esc_html__( 'Bookmark', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-camcorder' => esc_html__( 'Camcorder', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-camera' => esc_html__( 'Camera', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-chart' => esc_html__( 'Chart', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-chart_pie' => esc_html__( 'Chart pie', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-clock' => esc_html__( 'Clock', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-fire' => esc_html__( 'Fire', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-heart' => esc_html__( 'Heart', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-mail' => esc_html__( 'Mail', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-play' => esc_html__( 'Play', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-shield' => esc_html__( 'Shield', 'js_composer' ) ],
			[ 'vc_pixel_icon vc_pixel_icon-video' => esc_html__( 'Video', 'js_composer' ) ],
		];
	}
	/**
	 * Get configuration for icons attribute.
	 *
	 * @since 8.6
	 * @return array
	 */
	public static function get_icons_arr() {
		return [
			esc_html__( 'None', 'js_composer' ) => 'none',
			esc_html__( 'Address book icon', 'js_composer' ) => 'wpb_address_book',
			esc_html__( 'Alarm clock icon', 'js_composer' ) => 'wpb_alarm_clock',
			esc_html__( 'Anchor icon', 'js_composer' ) => 'wpb_anchor',
			esc_html__( 'Application Image icon', 'js_composer' ) => 'wpb_application_image',
			esc_html__( 'Arrow icon', 'js_composer' ) => 'wpb_arrow',
			esc_html__( 'Asterisk icon', 'js_composer' ) => 'wpb_asterisk',
			esc_html__( 'Hammer icon', 'js_composer' ) => 'wpb_hammer',
			esc_html__( 'Balloon icon', 'js_composer' ) => 'wpb_balloon',
			esc_html__( 'Balloon Buzz icon', 'js_composer' ) => 'wpb_balloon_buzz',
			esc_html__( 'Balloon Facebook icon', 'js_composer' ) => 'wpb_balloon_facebook',
			esc_html__( 'Balloon Twitter icon', 'js_composer' ) => 'wpb_balloon_twitter',
			esc_html__( 'Battery icon', 'js_composer' ) => 'wpb_battery',
			esc_html__( 'Binocular icon', 'js_composer' ) => 'wpb_binocular',
			esc_html__( 'Document Excel icon', 'js_composer' ) => 'wpb_document_excel',
			esc_html__( 'Document Image icon', 'js_composer' ) => 'wpb_document_image',
			esc_html__( 'Document Music icon', 'js_composer' ) => 'wpb_document_music',
			esc_html__( 'Document Office icon', 'js_composer' ) => 'wpb_document_office',
			esc_html__( 'Document PDF icon', 'js_composer' ) => 'wpb_document_pdf',
			esc_html__( 'Document Powerpoint icon', 'js_composer' ) => 'wpb_document_powerpoint',
			esc_html__( 'Document Word icon', 'js_composer' ) => 'wpb_document_word',
			esc_html__( 'Bookmark icon', 'js_composer' ) => 'wpb_bookmark',
			esc_html__( 'Camcorder icon', 'js_composer' ) => 'wpb_camcorder',
			esc_html__( 'Camera icon', 'js_composer' ) => 'wpb_camera',
			esc_html__( 'Chart icon', 'js_composer' ) => 'wpb_chart',
			esc_html__( 'Chart pie icon', 'js_composer' ) => 'wpb_chart_pie',
			esc_html__( 'Clock icon', 'js_composer' ) => 'wpb_clock',
			esc_html__( 'Fire icon', 'js_composer' ) => 'wpb_fire',
			esc_html__( 'Heart icon', 'js_composer' ) => 'wpb_heart',
			esc_html__( 'Mail icon', 'js_composer' ) => 'wpb_mail',
			esc_html__( 'Play icon', 'js_composer' ) => 'wpb_play',
			esc_html__( 'Shield icon', 'js_composer' ) => 'wpb_shield',
			esc_html__( 'Video icon', 'js_composer' ) => 'wpb_video',
		];
	}

	/**
	 * Get configuration for sizes attribute.
	 *
	 * @since 8.6
	 * @return array
	 */
	public static function get_sizes_arr() {
		return [
			esc_html__( 'Regular', 'js_composer' ) => 'wpb_regularsize',
			esc_html__( 'Large', 'js_composer' ) => 'btn-large',
			esc_html__( 'Small', 'js_composer' ) => 'btn-small',
			esc_html__( 'Mini', 'js_composer' ) => 'btn-mini',
		];
	}

	/**
	 * Get configuration for colors attribute.
	 *
	 * @since 8.6
	 * @return array
	 */
	public static function get_color_arr() {
		return [
			esc_html__( 'Grey', 'js_composer' ) => 'wpb_button',
			esc_html__( 'Blue', 'js_composer' ) => 'btn-primary',
			esc_html__( 'Turquoise', 'js_composer' ) => 'btn-info',
			esc_html__( 'Green', 'js_composer' ) => 'btn-success',
			esc_html__( 'Orange', 'js_composer' ) => 'btn-warning',
			esc_html__( 'Red', 'js_composer' ) => 'btn-danger',
			esc_html__( 'Black', 'js_composer' ) => 'btn-inverse',
		];
	}

	/**
	 * Get configuration for target controls.
	 *
	 * @since 8.6
	 * @return array
	 */
	public static function get_target_param_list() {
		return [
			esc_html__( 'Same window', 'js_composer' ) => '_self',
			esc_html__( 'New window', 'js_composer' ) => '_blank',
		];
	}

	/**
	 * Get configuration for hotkey controls.
	 *
	 * @since 8.6
	 * @return array
	 */
	public static function get_shortcut_list() {
		return [
			__( 'Undo', 'js_composer' ) => '%s+Z',
			__( 'Redo', 'js_composer' ) => '%s+Shift+Z',
			__( 'Templates', 'js_composer' ) => 'Shift+T',
			__( 'Add new element', 'js_composer' ) => 'Shift+A',
			__( 'Close', 'js_composer' ) => 'ESC',
			__( 'Preview', 'js_composer' ) => '%s+Shift+P',
			__( 'Save Draft', 'js_composer' ) => '%s+Shift+S',
			__( 'Save changes', 'js_composer' ) => '%s+Shift+S',
			__( 'Save as Pending', 'js_composer' ) => '%s+Shift+S',
			__( 'Submit for Review', 'js_composer' ) => '%s+Shift+S',
			__( 'Update', 'js_composer' ) => '%s+Shift+S',
			__( 'Publish', 'js_composer' ) => '%s+Shift+S',
			__( 'WPBakery SEO', 'js_composer' ) => 'Shift+I',
			__( 'Exit WPBakery Page Builder edit mode', 'js_composer' ) => '%s+Shift+V',
			__( 'Custom CSS/JS', 'js_composer' ) => 'Shift+C',
			__( 'Page settings', 'js_composer' ) => 'Shift+S',
		];
	}
}
