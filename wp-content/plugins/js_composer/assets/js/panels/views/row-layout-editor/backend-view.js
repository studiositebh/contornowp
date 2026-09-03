/**
 * RowLayoutEditorPanelViewBackend extends the row layout editor for backend operations.
 *
 * It customizes layout handling and builder integration for the backend editor.
 */

( function ( $ ) {
	'use strict';
	if ( _.isUndefined( window.vc ) ) {
		window.vc = {};
	}

	var events = {
		'click [data-vc-ui-element="button-save"]': 'save',
		'click [data-vc-ui-element="button-close"]': 'hide',
		'touchstart [data-vc-ui-element="button-close"]': 'hide',
		'click [data-vc-ui-element="button-minimize"]': 'toggleOpacity',
		'click [data-vc-ui-element="button-layout"]': 'setLayout',
		'click [data-vc-ui-element="button-update-layout"]': 'updateFromInput'
	};

	vc.RowLayoutEditorPanelViewBackend = vc.RowLayoutEditorPanelView.extend({
		builder: function () {
			if ( !this.builder ) {
				this.builder = vc.storage;
			}
			return this.builder;
		},
		isBuildComplete: function () {
			return true;
		},
		setLayout: function ( e ) {
			if ( e && e.preventDefault ) {
				e.preventDefault();
			}
			var $control = $( e.currentTarget ),
				layout = $control.attr( 'data-cells' ),
				columns = this.model.view.convertRowColumns( layout );
			this.$input.val( columns.join( ' + ' ) );
		}
	});

	vc.RowLayoutUIPanelBackendEditor = vc.RowLayoutEditorPanelViewBackend
		.vcExtendUI( vc.HelperPanelViewHeaderFooter )
		.vcExtendUI( vc.HelperPanelViewDraggable )
		.extend({
			panelName: 'rowLayouts',
			events: events
		});

})( window.jQuery );
