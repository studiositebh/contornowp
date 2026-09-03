/**
 * TemplatesEditorPanelViewBackendEditor extends the templates editor for backend operations.
 *
 * It customizes template loading and content handling for the backend editor.
 * @deprecated 4.7
 */

( function ( $ ) {
	'use strict';
	if ( _.isUndefined( window.vc ) ) {
		window.vc = {};
	}

	vc.TemplatesEditorPanelViewBackendEditor = vc.TemplatesEditorPanelView.extend({
		ajaxData: function ( $button ) {
			return {
				action: 'vc_backend_template',
				template_id: $button.attr( 'data-template_id' ),
				vc_inline: true,
				_vcnonce: window.vcAdminNonce
			};
		},
		/**
         * Load saved template from server.
         *
         * @param e - Event object
         */
		loadTemplate: function ( e ) {
			if ( e && e.preventDefault ) {
				e.preventDefault();
			}
			var $button = $( e.currentTarget );
			$.ajax({
				type: 'POST',
				url: window.ajaxurl,
				data: this.ajaxData( $button ),
				context: this
			}).done( function ( shortcodes ) {
				_.each( vc.filters.templates, function ( callback ) {
					shortcodes = callback( shortcodes );
				});
				vc.storage.append( shortcodes );
				vc.shortcodes.fetch({ reset: true });
				// this.showMessage( window.i18nLocale.template_added, 'success' );
				vc.closeActivePanel();
			});
		},
		/**
         * Load default template from server.
         *
         * @param e - Event object
         */
		loadDefaultTemplate: function ( e ) {
			if ( e && e.preventDefault ) {
				e.preventDefault();
			}
			var $button = $( e.currentTarget );
			$.ajax({
				type: 'POST',
				url: window.ajaxurl,
				data: {
					action: 'vc_backend_default_template',
					template_name: $button.attr( 'data-template_name' ),
					vc_inline: true,
					_vcnonce: window.vcAdminNonce
				},
				context: this
			}).done( function ( shortcodes ) {
				_.each( vc.filters.templates, function ( callback ) {
					shortcodes = callback( shortcodes );
				});
				vc.storage.append( shortcodes );
				vc.shortcodes.fetch({ reset: true });
				// this.showMessage( window.i18nLocale.template_added, 'success' );
			});
		},
		getPostContent: function () {
			return vc.storage.getContent();
		}
	});

})( window.jQuery );
