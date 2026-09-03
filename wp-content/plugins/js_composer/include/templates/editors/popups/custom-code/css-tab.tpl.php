<?php
/**
 * Custom CSS tab template.
 *
 * @var array $page_settings_data
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}
?>
<div class="vc_col-sm-12 vc_column">
	<div class="wpb_settings-title">
		<div class="wpb_element_label">
			<?php esc_html_e( 'Custom CSS settings', 'js_composer' ); ?>
		</div>
		<?php
		vc_include_template(
			$page_settings_data['css_info_template'],
			[ 'description' => $page_settings_data['css_info_description'] ]
		);
		?>
	</div>
	<div class="edit_form_line">
		<div class="vc_ui-settings-text-wrapper">
			<p class="wpb-code-editor-tag">&lt;style&gt;</p>
			<?php
			if ( vc_modules_manager()->is_module_on( 'vc-ai' ) ) {
				wpb_add_ai_icon_to_code_field( 'custom_css', 'wpb_css_editor' );
			}
			?>
		</div>
		<pre id="wpb_css_editor" class="wpb_content_element custom_code" data-code-type="css" data-ace-location="page-css"><?php echo esc_textarea( $page_settings_data['custom_css'] ); ?></pre>
		<p class="wpb-code-editor-tag">&lt;/style&gt;</p>
	</div>
</div>
