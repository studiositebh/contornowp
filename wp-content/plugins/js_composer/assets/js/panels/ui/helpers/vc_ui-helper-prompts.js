( function () {
	'use strict';

	window.vc.HelperPrompts = {
		uiEvents: {
			'render': 'removeAllPrompts'
		},
		removeAllPrompts: function () {
			this.$el.find( '.vc_ui-panel-content-container' ).removeClass( 'vc_ui-content-hidden' );
			this.$el.find( '.vc_ui-prompt' ).remove();
		}
	};
})();
