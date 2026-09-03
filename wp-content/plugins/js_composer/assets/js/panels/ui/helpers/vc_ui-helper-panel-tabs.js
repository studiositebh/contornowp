( function () {
	'use strict';

	window.vc.HelperPanelTabs = {
		switchActiveTab: function ( el, tab ) {
			el.find(
				'[data-vc-ui-element="panel-tabs-controls"] .vc_active:not([data-vc-ui-element="panel-tabs-line-dropdown"])' ).removeClass(
				'vc_active' );
			tab.parent().addClass( 'vc_active' );
			el.find( '[data-vc-ui-element="panel-edit-element-tab"].vc_active' ).removeClass( 'vc_active' );
		}
	};
})();
