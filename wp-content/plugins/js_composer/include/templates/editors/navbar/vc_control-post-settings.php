<?php
/**
 * Post settings control template for the navbar
 *
 * @package WPBakery Page Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}
?>
<li class="vc_pull-right vc_hide-mobile vc_hide-desktop-more">
	<a id="vc_post-settings-button" href="javascript:;" class="vc_icon-btn vc_post-settings" title="<?php echo esc_attr( wpb_get_title_with_shortcut( 'Page settings' ) ); ?>">
		<div class="vc_post-settings-icon">
			<i class="vc-composer-icon vc-c-icon-cog"></i>
		</div>
		<p class="vc_hide-desktop"><?php echo esc_html__( 'Settings', 'js_composer' ); ?></p>
	</a>
</li>
