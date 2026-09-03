<?php
/**
 * Get navbar template button.
 *
 * @var $_this Vc_Navbar
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

?>

<li>
	<a href="javascript:;" class="vc_icon-btn vc_templates-button"  id="vc_templates-editor-button" title="<?php echo esc_attr( wpb_get_title_with_shortcut( 'Templates' ) ); ?>">
		<i class="vc-composer-icon vc-c-icon-add_template"></i>
	</a>
</li>
