/**
 * Panel management utilities for editor.
 * Handles active panel state and window events.
 */

( function ( $ ) {
	'use strict';
	if ( _.isUndefined( window.vc ) ) {
		window.vc = {};
	}

	vc.active_panel = false;
	vc.element_start_index = 0;

	vc.closeActivePanel = function ( model ) {
		if ( !this.active_panel ) {
			return false;
		}
		if ( model && vc.active_panel.model && vc.active_panel.model.get( 'id' ) === model.get( 'id' ) ) {
			vc.active_panel.model = null;
			this.active_panel.hide();
		} else if ( !model ) {
			vc.active_panel.model = null;
			this.active_panel.hide();
		}
	};

	vc.activePanelName = function () {
		return this.active_panel && this.active_panel.panelName ? this.active_panel.panelName : null;
	};

	// Panel resize handling
	$( window ).on( 'orientationchange', function () {
		if ( vc.active_panel ) {
			vc.active_panel.$el.css({
				top: '',
				left: 'auto',
				height: 'auto',
				width: 'auto'
			});
		}
	});

	$( window ).on( 'resize.fixElContainment', function () {
		if ( vc.active_panel && vc.active_panel.fixElContainment ) {
			vc.active_panel.fixElContainment();
		}
	});
})( window.jQuery );
