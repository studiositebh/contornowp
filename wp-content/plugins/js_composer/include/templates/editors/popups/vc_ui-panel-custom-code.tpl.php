<?php
/**
 * UI Panel Custom CSS & JS template.
 *
 * @var array $page_settings_data
 * @var Vc_Custom_Code $box
 * @var array $controls
 * @var array $header_tabs_template_variables
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}
?>
<div class="vc_ui-font-open-sans vc_ui-panel-window vc_media-xs vc_ui-panel" data-vc-panel=".vc_ui-panel-header-header" data-vc-ui-element="panel-custom-code" id="vc_ui-panel-custom-code">
	<div class="vc_ui-panel-window-inner">
		<?php
		vc_include_template('editors/popups/vc_ui-header.tpl.php', [
			'title' => esc_html__( 'Custom CSS & JS', 'js_composer' ),
			'controls' => [ 'minimize', 'close' ],
			'header_css_class' => 'vc_ui-custom-code-header-container',
			'header_tabs_template' => 'editors/partials/add_element_tabs.tpl.php',
			'header_tabs_template_variables' => $header_tabs_template_variables,
			'box' => $box,
		]);
		?>
		<div class="vc_ui-panel-content-container">
			<div class="vc_ui-panel-content vc_properties-list vc_edit_form_elements" data-vc-ui-element="panel-content">
				<form id="vc_setting-custom-code-form" action method="post">
					<div class="vc_panel-tabs">
						<?php
						$custom_code_tab_templates = $box->get_tabs_templates();
						$custom_code_tab_categories = $box->get_categories();

						foreach ( $custom_code_tab_templates as $key => $template_name ) {
							$active_class = 0 === $key ? ' vc_active' : '';
							$tab_id = 'vc_custom-code-tab-' . sanitize_key( $custom_code_tab_categories[ $key ] );
							echo '<div id="' . esc_attr( $tab_id ) . '" class="vc_panel-tab vc_row' . esc_attr( $active_class ) . '" data-tab-index="' . esc_attr( $key ) . '" data-filter=".' . esc_attr( $tab_id ) . '">';
							vc_include_template(
								$template_name,
								[
									'page_settings_data' => $page_settings_data,
								]
							);
							echo '</div>';
						}
						?>
					</div>
				</form>
			</div>
		</div>
		<?php
		vc_include_template(
			'editors/popups/vc_ui-footer.tpl.php',
			[
				'controls' => $controls,
			]
		);
		?>
	</div>
</div>
