/**
 * TemplatesEditorPanelView manages template operations in the editor interface.
 *
 * It handles saving, loading, and removing templates with AJAX operations and template preview functionality.
 *
 * @deprecated 4.4 use vc.TemplatesModalViewBackend/Frontend
 */

( function ( $ ) {
	'use strict';
	if ( _.isUndefined( window.vc ) ) {
		window.vc = {};
	}

	vc.TemplatesEditorPanelView = vc.PanelView.extend({
		events: {
			'click [data-dismiss=panel]': 'hide',
			'click [data-transparent=panel]': 'toggleOpacity',
			'mouseover [data-transparent=panel]': 'addOpacity',
			'mouseout [data-transparent=panel]': 'removeOpacity',
			'click .wpb_remove_template': 'removeTemplate',
			'click [data-template_id]': 'loadTemplate',
			'click [data-template_name]': 'loadDefaultTemplate',
			'click #vc_template-save': 'saveTemplate'
		},
		render: function () {
			this.trigger( 'render' );
			this.$name = $( '#vc_template-name' );
			this.$list = $( '#vc_template-list' );
			var $tabs = $( '#vc_tabs-templates' );
			$tabs.find( '.vc_edit-form-tab-control' ).removeClass( 'vc_active' ).eq( 0 ).addClass( 'vc_active' );
			$tabs.find( '[data-vc-ui-element="panel-edit-element-tab"]' ).removeClass( 'vc_active' ).eq( 0 ).addClass(
				'vc_active' );
			$tabs.find( '.vc_edit-form-link' ).on( 'click', function ( e ) {
				e.preventDefault();
				var $this = $( this );
				$tabs.find( '.vc_active' ).removeClass( 'vc_active' );
				$this.parent().addClass( 'vc_active' );
				$( $this.attr( 'href' ) ).addClass( 'vc_active' );
			});
			this.trigger( 'afterRender' );
			return this;
		},
		/**
		 * Remove template from server database.
		 *
		 * @param e - Event object
		 */
		removeTemplate: function ( e ) {
			if ( e && e.preventDefault ) {
				e.preventDefault();
			}
			var $button = $( e.currentTarget );
			var templateName = $button.closest( '[data-vc-ui-element="template-title"]' ).text();
			var answer = confirm( window.i18nLocale.confirm_deleting_template.replace( '{template_name}',
				templateName ) );
			if ( answer ) {
				$button.closest( '[data-vc-ui-element="template"]' ).remove();
				this.$list.html( window.i18nLocale.loading );
				$.ajax({
					type: 'POST',
					url: window.ajaxurl,
					data: {
						action: 'wpb_delete_template',
						template_id: $button.attr( 'rel' ),
						vc_inline: true,
						_vcnonce: window.vcAdminNonce
					},
					context: this
				}).done( function ( html ) {
					this.$list.html( html );
				});
			}
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
				url: vc.frame_window.location.href,
				data: {
					action: 'vc_frontend_template',
					template_id: $button.data( 'template_id' ),
					vc_inline: true,
					_vcnonce: window.vcAdminNonce
				},
				context: this
			}).done( function ( html ) {
				var template, data;
				_.each( $( html ), function ( element ) {
					if ( 'vc_template-data' === element.id ) {
						try {
							data = JSON.parse( element.innerHTML );
						} catch ( err ) {
							if ( window.console && window.console.warn ) {
								window.console.warn( 'loadTemplate json error', err );
							}
						}
					}
					if ( 'vc_template-html' === element.id ) {
						template = element.innerHTML;
					}
				});
				if ( template && data ) {
					vc.builder.buildFromTemplate( template, data );
				}
				this.showMessage( window.i18nLocale.template_added, 'success' );
				vc.closeActivePanel();
			});
		},
		ajaxData: function ( $button ) {
			return {
				action: 'vc_frontend_default_template',
				template_name: $button.data( 'template_name' ),
				vc_inline: true,
				_vcnonce: window.vcAdminNonce
			};
		},
		/**
         * Load saved template from server.
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
				url: vc.frame_window.location.href,
				data: this.ajaxData( $button ),
				context: this
			}).done( function ( html ) {
				var template, data;
				_.each( $( html ), function ( element ) {
					if ( 'vc_template-data' === element.id ) {
						try {
							data = JSON.parse( element.innerHTML );
						} catch ( err ) {
							if ( window.console && window.console.warn ) {
								window.console.warn( 'loadDefaultTemplate json error', err );
							}
						}
					}
					if ( 'vc_template-html' === element.id ) {
						template = element.innerHTML;
					}
				});
				if ( template && data ) {
					vc.builder.buildFromTemplate( template, data );
				}
				this.showMessage( window.i18nLocale.template_added, 'success' );
			});
		},
		/**
         * Save current shortcode design as template with title.
         *
         * @param e - Event object
         */
		saveTemplate: function ( e ) {
			if ( e && e.preventDefault ) {
				e.preventDefault();
			}
			var name = this.$name.val(),
				data, shortcodes;
			if ( _.isString( name ) && name.length ) {
				shortcodes = this.getPostContent();
				if ( !shortcodes.trim().length ) {
					this.showMessage( window.i18nLocale.template_is_empty, 'error' );
					return false;
				}
				data = {
					action: 'wpb_save_template',
					template: shortcodes,
					template_name: name,
					frontend: true,
					vc_inline: true,
					_vcnonce: window.vcAdminNonce
				};
				this.$name.val( '' );
				this.showMessage( window.i18nLocale.template_save, 'success' );
				this.reloadTemplateList( data );
			} else {
				this.showMessage( window.i18nLocale.please_enter_templates_name, 'error' );
			}
		},
		reloadTemplateList: function ( data ) {
			this.$list.html( window.i18nLocale.loading ).load( window.ajaxurl, data );
		},
		getPostContent: function () {
			return vc.builder.getContent();
		}
	});

})( window.jQuery );
