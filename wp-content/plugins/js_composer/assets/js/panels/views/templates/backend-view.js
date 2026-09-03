/**
 * TemplatesPanelViewBackend provides the core template management interface for the backend editor.
 *
 * It handles template saving, loading, previewing, and deletion with AJAX operations.
 *
 * @since 4.4
 */

( function ( $ ) {
	'use strict';
	if ( _.isUndefined( window.vc ) ) {
		window.vc = {};
	}

	vc.TemplatesPanelViewBackend = vc.PanelView.extend({
		// new feature -> elements filtering
		$name: false,
		$list: false,
		template_load_action: 'vc_backend_load_template',
		templateLoadPreviewAction: 'vc_load_template_preview',
		save_template_action: 'vc_save_template',
		delete_template_action: 'vc_delete_template',
		appendedTemplateType: 'my_templates',
		appendedTemplateCategory: 'my_templates',
		appendedCategory: 'my_templates',
		appendedClass: 'my_templates',
		loadUrl: window.ajaxurl,
		events: $.extend( vc.PanelView.prototype.events, {
			'click .vc_template-save-btn': 'saveTemplate',
			'click [data-template_id] [data-template-handler]': 'loadTemplate',
			'click .vc_template-delete-icon': 'removeTemplate'
		}),
		initialize: function () {
			_.bindAll( this, 'checkInput', 'saveTemplate' );
			vc.TemplatesPanelViewBackend.__super__.initialize.call( this );
		},
		render: function () {
			this.$el.css( 'left', ( $( window ).width() - this.$el.width() ) / 2 );
			this.$name = this.$el.find( '[data-js-element="vc-templates-input"]' );
			this.$name.off( 'keypress' ).on( 'keypress', this.checkInput );
			this.$list = this.$el.find( '.vc_templates-list-my_templates' );
			return vc.TemplatesPanelViewBackend.__super__.render.call( this );
		},
		/**
		 * Save My Template
		 *
		 * @param e
		 * @return {boolean}
		 */
		saveTemplate: function ( e ) {
			if ( e && e.preventDefault ) {
				e.preventDefault();
			}
			var name, data, shortcodes, _this;
			name = this.$name.val();
			_this = this;
			if ( _.isString( name ) && name.length ) {
				shortcodes = this.getPostContent();
				if ( !shortcodes.trim().length ) {
					this.showMessage( window.i18nLocale.template_is_empty, 'error' );
					return false;
				}
				data = {
					action: this.save_template_action,
					template: shortcodes,
					template_name: name,
					vc_inline: true,
					_vcnonce: window.vcAdminNonce
				};
				this
					.setButtonMessage( undefined, undefined )
					.reloadTemplateList( data, function () {
						// success
						_this.$name.val( '' ).trigger( 'change' );
					}, function () {
						// error
						_this.showMessage( window.i18nLocale.template_save_error, 'error' );
						_this.clearButtonMessage();
					});
			} else {
				this.showMessage( window.i18nLocale.please_enter_templates_name, 'error' );
				return false;
			}
		},
		checkInput: function ( e ) {
			if ( 13 === e.which ) {
				this.saveTemplate();
				return false;
			}
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
			if ( e && e.stopPropagation ) {
				e.stopPropagation();
			}
			var $button = $( e.target );
			var $template = $button.closest( '[data-template_id]' );
			var templateName = $template.find( '[data-vc-ui-element="template-title"]' ).text();
			var answer = confirm( window.i18nLocale.confirm_deleting_template.replace( '{template_name}',
				templateName ) );
			if ( answer ) {
				var templateId = $template.data( 'template_id' );
				var templateType = $template.data( 'template_type' );
				var templateAction = $template.data( 'template_action' );
				$template.remove();
				$.ajax({
					type: 'POST',
					url: window.ajaxurl,
					data: {
						action: templateAction ? templateAction : this.delete_template_action,
						template_id: templateId,
						template_type: templateType,
						vc_inline: true,
						_vcnonce: window.vcAdminNonce
					},
					context: this
				}).done( function () {
					this.showMessage( window.i18nLocale.template_removed, 'success' );
					vc.events.trigger( 'templates:delete', {
						id: templateId,
						type: templateType
					});
				});
			}
		},
		reloadTemplateList: function ( data, successCallback, errorCallback ) {
			var _this = this;
			$.ajax({
				type: 'POST',
				url: window.ajaxurl,
				data: data,
				context: this
			}).done( function ( html ) {
				_this.filter = false; // reset current filter
				if ( !_this.$list ) {
					_this.$list = _this.$el.find( '.vc_templates-list-my_templates' );
				}
				_this.$list.prepend( $( html ) );
				if ( 'function' === typeof successCallback ) {
					successCallback( html );
				}
			}).fail( 'function' === typeof errorCallback ? errorCallback : function () {
			});
		},
		getPostContent: function () {
			return vc.shortcodes.stringify( 'template' );
		},
		loadTemplate: function ( e ) {
			if ( e && e.preventDefault ) {
				e.preventDefault();
			}
			if ( e && e.stopPropagation ) {
				e.stopPropagation();
			}
			var $template = $( e.target ).closest( '[data-template_id][data-template_type]' );
			$.ajax({
				type: 'POST',
				url: this.loadUrl,
				data: {
					action: this.template_load_action,
					template_unique_id: $template.data( 'template_id' ),
					template_type: $template.data( 'template_type' ),
					vc_inline: true,
					_vcnonce: window.vcAdminNonce
				},
				context: this
			}).done( this.renderTemplate );
		},
		renderTemplate: function ( html ) {
			var models;

			_.each( vc.filters.templates, function ( callback ) {
				html = callback( html );
			});
			models = vc.storage.parseContent({}, html );
			_.each( models, function ( model ) {
				vc.shortcodes.create( model );
				vc.latestAddedElement = vc.shortcodes.get( model.id );
			});
			vc.events.trigger( 'templateAdd' );
			vc.closeActivePanel();
		},
		buildTemplatePreview: function ( e ) {
			if ( e && e.preventDefault ) {
				e.preventDefault();
			}
			try {
				var url, $el = $( e.currentTarget );
				var $wrapper = $el.closest( '[data-template_id]' );
				if ( !$wrapper.hasClass( 'vc_active' ) && !$wrapper.hasClass( 'vc_loading' ) ) {
					var $localContent = $wrapper.find( '[data-js-content]' );
					var localContentChilds = $localContent.children().length > 0;
					this.$content = $localContent;
					if ( this.$content.find( 'iframe' ).length ) {
						$el.vcAccordion( 'collapseTemplate' );
						return true;
					}
					var _this = this;
					$el.vcAccordion( 'collapseTemplate', function () {
						var templateId = $wrapper.data( 'template_id' );
						var templateType = $wrapper.data( 'template_type' );
						if ( templateId && !localContentChilds ) {
							var question = '?';
							if ( window.ajaxurl.indexOf( '?' ) > - 1 ) {
								question = '&';
							}
							url = window.ajaxurl + question + $.param({
								action: _this.templateLoadPreviewAction,
								template_unique_id: templateId,
								template_type: templateType,
								vc_inline: true,
								post_id: window.vc_post_id, // set in the backend_editor.tpl.php
								_vcnonce: window.vcAdminNonce
							});
							$el.find( 'i' ).addClass( 'vc_ui-wp-spinner' );

							_this.$content.html( '<iframe style="width: 100%;" data-vc-template-preview-frame="' + templateId + '"></iframe>' );
							var $frame = _this.$content.find( '[data-vc-template-preview-frame]' );
							$frame.attr( 'src', url );
							$wrapper.addClass( 'vc_loading' );
							$frame.on( 'load', function () {
								$wrapper.removeClass( 'vc_loading' );
								$el.find( 'i' ).removeClass( 'vc_ui-wp-spinner' );
							});
						}
					});
				} else {
					$el.vcAccordion( 'collapseTemplate' );
				}
			} catch ( err ) {
				if ( window.console && window.console.warn ) {
					window.console.warn( 'buildTemplatePreview error', err );
				}
				this.showMessage( 'Failed to build preview', 'error' );
			}
		},
		/**
         * Set template iframe height
         * @param height (int) optional
         */
		setTemplatePreviewSize: function ( height ) {
			var iframe = this.$content.find( 'iframe' );
			if ( iframe.length > 0 ) {
				iframe = iframe[ 0 ];
				if ( undefined === height ) {
					iframe.height = iframe.contentWindow.document.body.offsetHeight;
					height = iframe.contentWindow.document.body.scrollHeight;
				}
				iframe.height = height + 'px';
			}
		}
	});

	window.vc.TemplateWindowUIPanelBackendEditor = vc.TemplatesPanelViewBackend
		.vcExtendUI( vc.HelperPanelViewHeaderFooter )
		.vcExtendUI( vc.HelperTemplatesPanelViewSearch )
		.extend({
			panelName: 'template_window',
			showMessageDisabled: false,
			initialize: function () {
				window.vc.TemplateWindowUIPanelBackendEditor.__super__.initialize.call( this );
				this.trigger( 'show', this.initTemplatesTabs, this );
			},
			show: function () {
				this.clearSearch();
				window.vc.TemplateWindowUIPanelBackendEditor.__super__.show.call( this );
			},
			initTemplatesTabs: function () {
				this.$el.find( '[data-vc-ui-element="panel-tabs-controls"]' ).vcTabsLine( 'moveTabs' );
			},
			showMessage: function ( text, type ) {
				var wrapperCssClasses;
				if ( this.showMessageDisabled ) {
					return false;
				}
				wrapperCssClasses = 'vc_col-xs-12 wpb_element_wrapper';
				if ( this.message_box_timeout ) {
					this.$el.find( '[data-vc-panel-message]' ).remove();
					window.clearTimeout( this.message_box_timeout );
				}
				this.message_box_timeout = false;
				var messageBoxTemplate = vc.template( '<div class="vc_message_box vc_message_box-standard vc_message_box-rounded vc_color-<%- color %>">' + '<div class="vc_message_box-icon"><i class="fa fa fa-<%- icon %>"></i></div><p><%- text %></p></div>' );
				var $messageBox;
				switch ( type ) {
					case 'error': {
						$messageBox = $( '<div class="' + wrapperCssClasses + '" data-vc-panel-message>' ).html( messageBoxTemplate({
							color: 'danger',
							icon: 'times',
							text: text
						}) );
						break;
					}
					case 'warning': {
						$messageBox = $( '<div class="' + wrapperCssClasses + '" data-vc-panel-message>' ).html( messageBoxTemplate({
							color: 'warning',
							icon: 'exclamation-triangle',
							text: text
						}) );
						break;
					}
					case 'success': {
						$messageBox = $( '<div class="' + wrapperCssClasses + '" data-vc-panel-message>' ).html( messageBoxTemplate({
							color: 'success',
							icon: 'check',
							text: text
						}) );
						break;
					}
				}
				$messageBox.prependTo( this.$el.find( '[data-vc-ui-element="panel-edit-element-tab"].vc_row.vc_active' ) );
				$messageBox.fadeIn();
				this.message_box_timeout = window.setTimeout( function () {
					$messageBox.remove();
				}, 6000 );
			},
			changeTab: function ( e ) {
				if ( e && e.preventDefault ) {
					e.preventDefault();
				}
				if ( e && !e.isClearSearch ) {
					this.clearSearch();
				}
				var $tab = $( e.currentTarget );
				if ( !$tab.parent().hasClass( 'vc_active' ) ) {
					this.$el.find( '[data-vc-ui-element="panel-tabs-controls"] .vc_active:not([data-vc-ui-element="panel-tabs-line-dropdown"])' ).removeClass( 'vc_active' );
					$tab.parent().addClass( 'vc_active' );
					this.$el.find( '[data-vc-ui-element="panel-edit-element-tab"].vc_active' ).removeClass( 'vc_active' );
					this.$el.find( $tab.data( 'vcUiElementTarget' ) ).addClass( 'vc_active' );
					if ( this.$tabsMenu ) {
						this.$tabsMenu.vcTabsLine( 'checkDropdownContainerActive' );
					}
				}
			},
			setPreviewFrameHeight: function ( templateID, height ) {
				if ( parseInt( height, 10 ) < 100 ) {
					height = 100;
				}
				$( 'data-vc-template-preview-frame="' + templateID + '"' ).height( height );
			}
		});

	window.vc.TemplateWindowUIPanelBackendEditor.prototype.events = $.extend( true,
		window.vc.TemplateWindowUIPanelBackendEditor.prototype.events,
		{
			// header footer
			'click [data-vc-ui-element="button-save"]': 'save', // need to save, hide into this code.
			'click [data-vc-ui-element="button-close"]': 'hide',
			'touchstart [data-vc-ui-element="button-close"]': 'hide',
			'click [data-vc-ui-element="button-minimize"]': 'toggleOpacity',
			// search
			'keyup [data-vc-templates-name-filter]': 'searchTemplate',
			'search [data-vc-templates-name-filter]': 'searchTemplate',
			// templates
			'click .vc_template-save-btn': 'saveTemplate',
			'click [data-template_id] [data-template-handler]': 'loadTemplate',
			'click [data-vc-container=".vc_ui-list-bar"][data-vc-preview-handler]': 'buildTemplatePreview',
			'click [data-vc-ui-delete="template-title"]': 'removeTemplate',
			'click [data-vc-ui-element="panel-tab-control"]': 'changeTab'
		});

})( window.jQuery );
