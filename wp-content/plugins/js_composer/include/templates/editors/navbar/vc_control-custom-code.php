<?php
/**
 * Custom code control template for the navbar
 *
 * @package WPBakery Page Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}
?>
<li class="vc_pull-right vc_hide-mobile vc_hide-desktop-more">
	<a id="vc_custom-code-button" class="vc_icon-btn vc_custom-code" title="<?php echo esc_attr( wpb_get_title_with_shortcut( 'Custom CSS/JS' ) ); ?>">
		<div class="vc_custom-code-icon">
			<i class="vc-composer-icon vc-c-icon-code"></i>
			<span id="vc_custom-code-badge" class="vc_badge vc_badge-custom-code" style="display: none;"></span>
		</div>
		<p class="vc_hide-desktop"><?php echo esc_html__( 'Custom Code', 'js_composer' ); ?></p>
	</a>
</li>
