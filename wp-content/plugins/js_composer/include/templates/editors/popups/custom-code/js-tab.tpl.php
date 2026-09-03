<?php
/**
 * Custom JavaScript tab template.
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
			<?php esc_html_e( 'Custom JavaScript in <head>', 'js_composer' ); ?>
		</div>
		<?php
		vc_include_template(
			$page_settings_data['js_head_info_template'],
			[ 'description' => $page_settings_data['js_head_info_description'] ]
		);
		?>
	</div>
	<div class="edit_form_line">
		<div class="vc_ui-settings-text-wrapper">
			<p class="wpb-code-editor-tag">&lt;script&gt;</p>
			<?php
			if ( vc_modules_manager()->is_module_on( 'vc-ai' ) ) {
				wpb_add_ai_icon_to_code_field( 'custom_js', 'wpb_js_header_editor' );
			}
			?>
		</div>
		<pre id="wpb_js_header_editor" class="wpb_content_element custom_code" data-code-type="javascript" data-ace-location="page-js"><?php echo esc_textarea( $page_settings_data['custom_js_header'] ); ?></pre>
		<p class="wpb-code-editor-tag">&lt;/script&gt;</p>
	</div>
</div>
<div class="vc_col-sm-12 vc_column">
	<div class="wpb_settings-title">
		<div class="wpb_element_label">
			<?php esc_html_e( 'Custom JavaScript before </body>', 'js_composer' ); ?>
		</div>
		<?php
		vc_include_template(
			$page_settings_data['js_body_info_template'],
			[ 'description' => $page_settings_data['js_body_info_description'] ]
		);
		?>
	</div>
	<div class="edit_form_line">
		<div class="vc_ui-settings-text-wrapper">
			<p class="wpb-code-editor-tag">&lt;script&gt;</p>
			<?php
			if ( vc_modules_manager()->is_module_on( 'vc-ai' ) ) {
				wpb_add_ai_icon_to_code_field( 'custom_js', 'wpb_js_footer_editor' );
			}
			?>
		</div>
		<pre id="wpb_js_footer_editor" class="wpb_content_element custom_code" data-code-type="javascript" data-ace-location="page-js"><?php echo esc_textarea( $page_settings_data['custom_js_footer'] ); ?></pre>
		<p class="wpb-code-editor-tag">&lt;/script&gt;</p>
	</div>
</div>

