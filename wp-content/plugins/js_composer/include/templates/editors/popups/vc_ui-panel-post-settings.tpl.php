<?php
/**
 * UI Panel Post Settings template.
 *
 * @var array $page_settings_data
 * @var Vc_Post_Settings $box
 * @var array $header_tabs_template_variables
 * @var array $controls
 * @var array $permalink
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}
?>
<div class="vc_ui-font-open-sans vc_ui-panel-window vc_media-xs vc_ui-panel" data-vc-panel=".vc_ui-panel-header-header" data-vc-ui-element="panel-post-settings" id="vc_ui-panel-post-settings">
	<div class="vc_ui-panel-window-inner">
		<?php
		vc_include_template(
			'editors/popups/vc_ui-header.tpl.php',
			[
				'title' => esc_html__( 'Page Settings', 'js_composer' ),
				'controls' => [ 'minimize', 'close' ],
				'header_css_class' => 'vc_ui-post-settings-header-container',
				'box' => $box,
			]
		);
		?>
		<div class="vc_ui-panel-content-container">
			<div class="vc_ui-panel-content vc_properties-list vc_edit_form_elements" data-vc-ui-element="panel-content">
				<form id="vc_settings-post-settings-form" action method="post">
					<?php
					vc_include_template(
						'editors/popups/page-settings/page-settings-tab.tpl.php',
						[
							'page_settings_data' => $page_settings_data,
							'permalink' => $permalink,
						]
					);
					?>
				</form>
			</div>
		</div>
		<?php
		// Include the template with the dynamic controls array.
		vc_include_template(
			'editors/popups/vc_ui-footer.tpl.php',
			[
				'controls' => $controls,
			]
		);
		?>
	</div>
</div>
