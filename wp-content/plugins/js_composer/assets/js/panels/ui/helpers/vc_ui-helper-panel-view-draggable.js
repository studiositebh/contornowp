( function () {
	'use strict';

	window.vc.HelperPanelViewDraggable = {
		draggable: true,
		draggableOptions: {
			iframeFix: true,
			handle: '[data-vc-ui-element="panel-heading"]'
		},
		uiEvents: {
			'show': 'initDraggable'
		},
		initDraggable: function () {
			// Disable draggable for mobile devices.
			// It triggers an overlay div over the iframe, don't need draggable on mobile anyway.
			if ( window.matchMedia( '(max-width: 767px )' ).matches ) {
				return;
			}

			this.$el.draggable( _.extend({}, this.draggableOptions, {
				start: this.fixElContainment,
				stop: this.fixElContainment
			}) );
		}
	};
})();
