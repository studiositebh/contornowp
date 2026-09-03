<?php
/**
 * Default template for post types settings manager.
 */

/**
 * Class Vc_Setting_Post_Type_Default_Template_Field
 *
 * @since 4.12
 */
class Vc_Setting_Post_Type_Default_Template_Field {
	/**
	 * Tab name
	 *
	 * @var string
	 */
	protected $tab;

	/**
	 * Field key
	 *
	 * @var string
	 */
	protected $key;

	/**
	 * Post types
	 *
	 * @var bool|array
	 */
	protected $post_types = false;

	/**
	 * Vc_Setting_Post_Type_Default_Template_Field constructor.
	 *
	 * @param string $tab
	 * @param string $key
	 */
	public function __construct( $tab, $key ) {
		$this->tab = $tab;
		$this->key = $key;
		add_action( 'vc_settings_tab-general', [
			$this,
			'addField',
		] );
	}

	/**
	 * Get field name
	 *
	 * @return string
	 */
	protected function getFieldName() {
		return esc_html__( 'Default template for post types', 'js_composer' );
	}

	/**
	 * Get field key
	 *
	 * @return string
	 */
	public function getFieldKey() {
		require_once vc_path_dir( 'SETTINGS_DIR', 'class-vc-settings.php' );

		return Vc_Settings::getFieldPrefix() . $this->key;
	}

	/**
	 * Check if post type is valid
	 *
	 * @param string $type
	 * @return bool
	 */
	protected function isValidPostType( $type ) {
		return post_type_exists( $type );
	}

	/**
	 * Get post types.
	 *
	 * @return array|bool
	 */
	protected function getPostTypes() {
		if ( false === $this->post_types ) {
			require_once vc_path_dir( 'SETTINGS_DIR', 'class-vc-roles.php' );
			$vc_roles = new Vc_Roles();
			$this->post_types = $vc_roles->getPostTypes();
		}

		return $this->post_types;
	}

	/**
	 * Get templates.
	 *
	 * @return array
	 */
	protected function getTemplates() {
		return $this->getTemplatesEditor()->getAllTemplates();
	}

	/**
	 * Get templates editor.
	 *
	 * @return bool|Vc_Templates_Panel_Editor
	 */
	protected function getTemplatesEditor() {
		return wpbakery()->templatesPanelEditor();
	}

	/**
	 * Get settings data for default templates
	 *
	 * @return array|mixed
	 */
	protected function get() {
		require_once vc_path_dir( 'SETTINGS_DIR', 'class-vc-settings.php' );

		$value = Vc_Settings::get( $this->key );

		return $value ?: [];
	}

	/**
	 * Get template's shortcodes string
	 *
	 * @param string $template_data
	 * @return string|null
	 */
	public function getTemplate( $template_data ) {
		$template_settings = preg_split( '/\:\:/', $template_data );
		$template_type = $template_settings[0];
		$template_id = $this->get_template_id( $template_settings );

		if ( ! isset( $template_id, $template_type ) || '' === $template_id || '' === $template_type ) {
			return null;
		}
		WPBMap::addAllMappedShortcodes();

		$template = null;
		switch ( $template_type ) {
			case 'my_templates':
				$saved_templates = get_option( $this->getTemplatesEditor()->getOptionName() );
				if ( ! isset( $saved_templates[ $template_id ] ) ) {
					return null;
				}
				$content = trim( $saved_templates[ $template_id ]['template'] );
				$content = str_replace( '\"', '"', $content );
				$pattern = get_shortcode_regex();
				$template = preg_replace_callback( "/{$pattern}/s", 'vc_convert_shortcode', $content );
				break;

			case 'default_templates':
				$template_data = $this->getTemplatesEditor()->getDefaultTemplate( (int) $template_id );
				if ( isset( $template_data['content'] ) ) {
					$template = $template_data['content'];
				}
				break;

			default:
				$template_preview = apply_filters( 'vc_templates_render_backend_template_preview', $template_id, $template_type );
				if ( (string) $template_preview !== $template_id ) {
					$template = $template_preview;
				}
				break;
		}

		return $template;
	}

	/**
	 * Get template id.
	 *
	 * @param false|string[] $template_settings
	 * @return string
	 */
	public function get_template_id( $template_settings ) {
		$template_type = $template_settings[0];
		if ( 'shared_templates' === $template_type ) {
			$posts = get_posts([
				'post_type'   => 'vc4_templates',
				'meta_key'    => '_vc4_templates-id',
				'meta_value'  => $template_settings[1],
				'fields'      => 'ids',
				'numberposts' => 1,
			]);

			$template_id = $posts ? $posts[0] : '';
		} else {
			$template_id = $template_settings[1];
		}

		return $template_id;
	}

	/**
	 * Get template by post type.
	 *
	 * @param string $type
	 * @return string|null
	 */
	public function getTemplateByPostType( $type ) {
		$value = $this->get();

		return isset( $value[ $type ] ) ? $this->getTemplate( $value[ $type ] ) : null;
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $settings
	 * @return array
	 */
	public function sanitize( $settings ) {
		foreach ( $settings as $type => $template ) {
			if ( empty( $template ) ) {
				unset( $settings[ $type ] );
			} elseif ( ! $this->isValidPostType( $type ) || ! $this->getTemplate( $template ) ) {
				add_settings_error( $this->getFieldKey(), 1, esc_html__( 'Invalid template or post type.', 'js_composer' ) );

				return $settings;
			}
		}

		return $settings;
	}

	/**
	 * Include template for default post type.
	 */
	public function render() {
		vc_include_template( 'pages/vc-settings/default-template-post-type.tpl.php', [
			'post_types' => $this->getPostTypes(),
			'templates' => $this->getTemplates(),
			'title' => $this->getFieldName(),
			'value' => $this->get(),
			'field_key' => $this->getFieldKey(),
		] );
	}

	/**
	 * Add field settings page
	 *
	 * Method called by vc hook vc_settings_tab-general.
	 */
	public function addField() {
		vc_settings()->addField( $this->tab, $this->getFieldName(), $this->key, [
			$this,
			'sanitize',
		], [
			$this,
			'render',
		] );
	}
}
