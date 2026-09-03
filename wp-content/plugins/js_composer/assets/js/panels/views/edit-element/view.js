/**
 * Edit Element Panel View
 * It is used to edit the settings of a shortcode in the editor.
 */

/* global vc, i18nLocale */
( function ( $ ) {
	'use strict';

	/**
	 * Shortcode settings panel
	 *
	 * @type {*}
	 */
	window.vc.EditElementPanelView = vc.PanelView
		.vcExtendUI( vc.HelperAjax )
		.vcExtendUI( vc.ExtendPresets )
		.vcExtendUI( vc.ExtendTemplates )
		.vcExtendUI( vc.HelperPrompts )
		.extend({
			panelName: 'edit_element',
			el: '#vc_properties-panel',
			// there is more than 1 element with vc_properties-list class name, so we need to increase specificity
			contentSelector: '.vc_ui-panel-content.vc_properties-list',
			minimizeButtonSelector: '[data-vc-ui-element="button-minimize"]',
			closeButtonSelector: '[data-vc-ui-element="button-close"]',
			titleSelector: '.vc_panel-title',
			tabsInit: false,
			doCheckTabs: true,
			$tabsMenu: false,
			dependent_elements: {},
			mapped_params: {},
			draggable: false,
			panelInit: false,
			$spinner: false,
			activeTabIndex: 0,
			buttonMessageTimeout: false,
			// @deprecated 4.7
			notRequestTemplate: false,
			requiredParamsInitialized: false,
			currentModelParams: false,
			customButtonMessageTimeout: false,
			events: {
				'click [data-save=true]': 'save',
				'click [data-dismiss=panel]': 'hide',
				'mouseover [data-transparent=panel]': 'addOpacity',
				'click [data-transparent=panel]': 'toggleOpacity',
				'mouseout [data-transparent=panel]': 'removeOpacity'
			},
			formRender: function () {
				var _this = this;
				var typingTimer = null;
				var typingInterval = 500;
				this.$el.find( '.vc_edit-form-tab' ).on( 'input change', function ( e ) {
					clearTimeout( typingTimer );
					// Need to set vc.saveInProcess to true to prevent the console error from the hide method (this.model === null)
					// This happens when the panel is closed and the change event is triggered (if the input had focus) simultaneously.
					vc.saveInProcess = true;
					typingTimer = setTimeout( function () {
						_this.save( e );
					}, typingInterval );
				});
			},
			initialize: function () {
				_.bindAll( this,
					'setSize',
					'setTabsSize',
					'fixElContainment',
					'hookDependent',
					'resetAjax',
					'removeAllPrompts' );
				this.on( 'setSize', this.setResize, this );
				this.on( 'render', this.resetMinimize, this );
				this.on( 'render', this.setTitle, this );
				this.on( 'render', this.prepareContentBlock, this );
				if ( window.vc_auto_save ) {
					this.on( 'afterRender', this.formRender, this );
				}
				this.on( 'afterRender', function () {
					$( '.edit-form-info' ).initializeTooltips();
					this.reInitJsFunctions();
				}, this );
			},
			reInitJsFunctions: function () {
				// Add event listener when shortcode is updated
				// To trigger re-initialize the JS functions for libraries like flexslider, progress bar, accordion, etc.
				// the window.vc.frame_window.vc_js is located in assets/js/js_composer_front.js file
				try {
					if ( window.vc.frame_window && window.vc.frame_window.vc_js ) {
						vc.events.on( 'shortcodeView:updated', window.vc.frame_window.vc_js );
					}
				} catch ( error ) {
					console.error( 'Failed to execute window.vc.frame_window.vc_js function in reInitJsFunctions(): ', error );
				}
			},
			setCustomButtonMessage: function ( $btn, message, type, showInBackend ) {
				var currentTextHtml;
				if ( 'undefined' === typeof $btn ) {
					$btn = this.$el.find( '[data-vc-ui-element="button-save"]' );
				}
				if ( 'undefined' === typeof ( showInBackend ) ) {
					showInBackend = false;
				}

				// binding to context
				this.clearCustomButtonMessage = _.bind( this.clearCustomButtonMessage, this );

				// we can show only if frontend and only if old message cleared (to avoid double execution)
				if ( ( !showInBackend && !vc.frame_window ) || this.customButtonMessageTimeout ) {
					return this;
				}

				if ( 'undefined' === typeof ( message ) ) {
					message = window.i18nLocale.ui_saved;
				}

				if ( 'undefined' === typeof ( type ) ) {
					type = 'success';
				}

				currentTextHtml = $btn.html();

				$btn
					.addClass( 'vc_ui-button-' + type + ' vc_ui-button-undisabled' )
					.removeClass( 'vc_ui-button-action' )
					.data( 'vcCurrentTextHtml', currentTextHtml )
					.data( 'vcCurrentTextType', type )
					.html( message );

				_.delay( this.clearCustomButtonMessage.bind( this, $btn ), 5000 );

				this.customButtonMessageTimeout = true;

				return this;
			},
			clearCustomButtonMessage: function ( $btn ) {
				var type, currentTextHtml;

				if ( this.customButtonMessageTimeout ) {
					window.clearTimeout( this.customButtonMessageTimeout );

					currentTextHtml = $btn.data( 'vcCurrentTextHtml' ) || 'Save';
					type = $btn.data( 'vcCurrentTextType' );

					$btn.html( currentTextHtml )
						.removeClass( 'vc_ui-button-' + type + ' vc_ui-button-undisabled' )
						.addClass( 'vc_ui-button-action' );

					this.customButtonMessageTimeout = false;
				}
			},
			/**
			 *
			 * @param model
			 * @param notRequestTemplate @deprecated 4.7
			 * @param isUsageCount
			 * @returns {vc.EditElementPanelView}
			 */
			render: function ( model, notRequestTemplate, isUsageCount ) {
				var params;
				if ( this.$el.is( ':hidden' ) ) {
					vc.closeActivePanel();
				}
				// @deprecated 4.7
				if ( notRequestTemplate ) {
					this.notRequestTemplate = true;
				}
				this.model = model;
				this.currentModelParams = this.model.get( 'params' );
				vc.active_panel = this;
				this.resetMinimize();
				this.clicked = false;
				this.$el.css( 'height', 'auto' );
				this.$el.css( 'maxHeight', '75vh' );
				params = this.model.setting( 'params' ) || [];
				this.$el.attr( 'data-vc-shortcode', this.model.get( 'shortcode' ) );
				this.tabsInit = false;
				this.panelInit = false;
				this.activeTabIndex = 0;
				this.requiredParamsInitialized = false;
				this.mapped_params = {};
				this.dependent_elements = {};
				_.each( params, function ( param ) {
					this.mapped_params[ param.param_name ] = param;
				}, this );
				this.trigger( 'render' );
				this.show();
				this.checkAjax();

				var modelId = this.model.get( 'id' );
				// we cache panel in rare cases when user click edit element and do not change anything
				if ( this.isEditElementPanelCache( modelId ) ) {
					this.buildParamsContent( window.vc.EditElementPanelCache[modelId]);
				} else {
					this.ajax = $.ajax({
						type: 'POST',
						url: window.ajaxurl,
						data: this.ajaxData( isUsageCount ),
						context: this
					}).done( this.buildParamsContent ).always( this.resetAjax );
				}

				return this;
			},
			prepareContentBlock: function () {
				this.$content = this.notRequestTemplate ? this.$el : this.$el.find( this.contentSelector ).removeClass(
					'vc_with-tabs' );
				this.$content.empty(); // if pressed multiple times
				this.$spinner = $( '<span class="vc_ui-wp-spinner vc_ui-wp-spinner-lg vc_ui-wp-spinner-dark"></span>' );
				this.$content.prepend( this.$spinner );
			},
			buildParamsContent: function ( data ) {
				var $data, $tabs, $panelHeader, $panelTab;
				var modelId = this.model.get( 'id' );
				this.setEditElementPanelCache( modelId, data );

				var externalScripts = this.getExternalScriptsFromDataHtml( data );
				data = this.removeExternalScriptsFromDataHtml( data );

				$data = $( data );
				$tabs = $data.find( '[data-vc-ui-element="panel-tabs-controls"]' );
				$tabs.find( '.vc_edit-form-tab-control:first-child' ).addClass( 'vc_active' );
				$panelHeader = this.$el.find( '[data-vc-ui-element="panel-header-content"]' );
				$panelTab = $data.find( '[data-vc-ui-element="panel-edit-element-tab"]' );
				// Hide panel parts until render is completed
				if ( $panelTab ) {
					$panelTab.addClass( 'visually-hidden' );
				}
				if ( $panelHeader ) {
					$panelHeader.addClass( 'visually-hidden' );
				}

				var $content = this.$content.html ( $data );
				this.loadScriptsSequentially( externalScripts, function () {
					// we use this flag to check if all our scripts related to edit form are loaded.
					// and we ready to show edit element form content.
					$content.append( '<script async>window.setTimeout(function(){window.wpb_edit_form_loaded=true;},100);</script>' );
				});

				// Add spinner again since it is removed with content html
				this.$content.prepend( this.$spinner );
				$tabs.prependTo( $panelHeader );
				var _self = this;
				// render even if something fails after (1000*100ms = 100s)
				var counter = 0;
				var cb = function () {
					if ( !window.wpb_edit_form_loaded && counter < 1000 ) {
						counter++;
						setTimeout( cb, 100 );
						return;
					}
					_self.$content.removeAttr( 'data-vc-param-initialized' );
					_self.activeTabIndex = 0;
					_self.tabsInit = false;
					_self.panelInit = false;
					_self.dependent_elements = {};
					_self.requiredParamsInitialized = false;
					_self.$content.find( '[data-vc-param-initialized]' ).removeAttr( 'data-vc-param-initialized' );
					// Show panel and emove spinner once render is complete
					$panelTab.removeClass( 'visually-hidden' );
					_self.$content.find( '.vc_ui-wp-spinner' ).remove();
					_self.init();
					// In Firefox, scrollTop(0) is buggy, scrolling to non-0 value first fixes it
					_self.$content.parent().scrollTop( 1 ).scrollTop( 0 );
					_self.$content.removeClass( 'vc_properties-list-init' );
					/**
					 * @deprecated 4.7
					 */
					_self.$el.trigger( 'vcPanel.shown' ); // old stuff
					_self.trigger( 'afterRender' );
					vc.events.trigger( 'editElementPanel:ready' );
				};
				window.setTimeout( cb, 10 );
			},
			getExternalScriptsFromDataHtml: function ( data ) {
				var externalScripts = [];
				var regex = this.getExternalScriptRegex();
				var match;

				while ( match = regex.exec( data ) ) {
					externalScripts.push( match[1]);
				}

				return externalScripts;
			},
			removeExternalScriptsFromDataHtml: function ( data ) {
				return data.replace( this.getExternalScriptRegex(), '' );
			},
			getExternalScriptRegex: function () {
				return /<script\s+src="([^"]+)"><\/script>/g;
			},
			loadScriptsSequentially: function ( scripts, callback ) {
				if ( 0 === scripts.length ) {
					callback();
					return;
				}
				var src = scripts.shift();
				var script = document.createElement( 'script' );

				script.src = src;
				script.onload = function () {
					this.loadScriptsSequentially( scripts, callback );
				}.bind( this );
				document.head.appendChild( script );
			},
			setEditElementPanelCache: function ( modelId, data ) {
				if ( !window.vc.EditElementPanelCache ) {
					window.vc.EditElementPanelCache = {};
				}

				window.vc.EditElementPanelCache[modelId] = data;
			},
			removeElementEditElementPanelCache: function ( modelId ) {
				if ( window.vc.EditElementPanelCache && window.vc.EditElementPanelCache[modelId]) {
					delete window.vc.EditElementPanelCache[modelId];
				}
			},
			isEditElementPanelCache: function ( modelId ) {
				return window.vc.EditElementPanelCache && window.vc.EditElementPanelCache[modelId];
			},
			resetMinimize: function () {
				this.$el.removeClass( 'vc_panel-opacity' );
			},
			ajaxData: function ( isUsageCount ) {
				var parentTag, parentId, params, mergedParams;

				parentId = this.model.get( 'parent_id' );
				parentTag = parentId ? this.model.collection.get( parentId ).get( 'shortcode' ) : null;
				params = this.model.get( 'params' );
				mergedParams = _.extend({}, vc.getDefaults( this.model.get( 'shortcode' ) ), params );

				return {
					action: 'vc_edit_form', // OLD version wpb_show_edit_form
					tag: this.model.get( 'shortcode' ),
					parent_tag: parentTag,
					post_id: window.vc_post_id,
					params: mergedParams,
					usage_count: isUsageCount,
					_vcnonce: window.vcAdminNonce
				};
			},
			init: function () {
				vc.EditElementPanelView.__super__.init.call( this );
				var $panelHeader = this.$el.find( '[data-vc-ui-element="panel-header-content"]' );
				$panelHeader.removeClass( 'visually-hidden' );
				this.initParams();
				this.initDependency();
				$( '.wpb_edit_form_elements .textarea_html' ).each( function () {
					window.init_textarea_html( $( this ) );
				});
				this.trigger( 'init' );
				this.panelInit = true;
			},
			initParams: function () {
				var _this = this;
				var $content = this.content().find(
					'#vc_edit-form-tabs [data-vc-ui-element="panel-edit-element-tab"]:eq(' + this.activeTabIndex + ')' );
				if ( !$content.length ) {
					$content = this.content();
				}
				if ( !$content.attr( 'data-vc-param-initialized' ) ) {
					$( '[data-vc-ui-element="panel-shortcode-param"]', $content ).each( function () {
						var $field;
						var param;
						$field = $( this );
						if ( !$field.data( 'vcInitParam' ) ) {
							param = $field.data( 'param_settings' );
							vc.atts.init.call( _this, param, $field );
							$field.data( 'vcInitParam', true );
						}
					});
					$content.attr( 'data-vc-param-initialized', true );
				}
				if ( !this.requiredParamsInitialized && !_.isUndefined( vc.required_params_to_init ) ) {
					$( '[data-vc-ui-element="panel-shortcode-param"]', this.content() ).each( function () {
						var $field;
						var param;
						$field = $( this );
						if ( !$field.data( 'vcInitParam' ) && _.indexOf( vc.required_params_to_init,
							$field.data( 'param_type' ) ) > - 1 ) {
							param = $field.data( 'param_settings' );
							vc.atts.init.call( _this, param, $field );
							$field.data( 'vcInitParam', true );
						}
					});
					this.requiredParamsInitialized = true;
				}
			},
			initDependency: function () {
				// setup dependencies
				var callDependencies = {};
				_.each( this.mapped_params, function ( param ) {
					if ( _.isObject( param ) && _.isObject( param.dependency ) ) {
						var rules = param.dependency;
						if ( _.isString( param.dependency.element ) ) {
							var $masters, $slave;

							$masters = $( '[name=' + param.dependency.element + '].wpb_vc_param_value', this.$content );
							$slave = $( '[name= ' + param.param_name + '].wpb_vc_param_value', this.$content );
							_.each( $masters, function ( master ) {
								var $master, name;
								$master = $( master );
								name = $master.attr( 'name' );
								if ( !_.isArray( this.dependent_elements[ $master.attr( 'name' ) ]) ) {
									this.dependent_elements[ $master.attr( 'name' ) ] = [];
								}
								this.dependent_elements[ $master.attr( 'name' ) ].push( $slave );
								if ( !$master.data( 'dependentSet' ) ) {
									$master.attr( 'data-dependent-set', 'true' );
									$master.off( 'keyup change', this.hookDependent ).on( 'keyup change', this.hookDependent );
								}
								if ( !callDependencies[ name ]) {
									callDependencies[ name ] = $master;
								}
							}, this );
						}
						if ( _.isString( rules.callback ) ) {
							window[ rules.callback ].call( this );
						}
					}
				}, this );
				this.doCheckTabs = false;
				_.each( callDependencies, function ( obj ) {
					this.hookDependent({ currentTarget: obj });
				}, this );
				this.doCheckTabs = true;
				this.checkTabs();
				callDependencies = null;
			},
			hookDependent: function ( e ) {
				var $master, $masterContainer, isEmpty, dependentElements, masterValue, checkTabs;

				$master = $( e.currentTarget );
				$masterContainer = $master.closest( '.vc_column' );
				dependentElements = this.dependent_elements[ $master.attr( 'name' ) ];
				masterValue = $master.is( ':checkbox' ) ? _.map( this.$content.find( '[name=' + $( e.currentTarget ).attr(
					'name' ) + '].wpb_vc_param_value:checked' ),
				function ( element ) {
					return $( element ).val();
				}) : $master.val();
				checkTabs = true && this.doCheckTabs;
				this.doCheckTabs = false;
				isEmpty = $master.is( ':checkbox' ) ? !this.$content.find( '[name=' + $master.attr( 'name' ) + '].wpb_vc_param_value:checked' ).length : !masterValue.length;
				if ( $masterContainer.hasClass( 'vc_dependent-hidden' ) ) {
					_.each( dependentElements, function ( $element ) {
						var event = jQuery.Event( 'change' );
						event.extra_type = 'vcHookDepended';
						$element.closest( '.vc_column' ).addClass( 'vc_dependent-hidden' );
						$element.trigger( event );
					});
				} else {
					_.each( dependentElements, function ( $element ) {
						var paramName = $element.attr( 'name' ),
							rules = _.isObject( this.mapped_params[ paramName ]) && _.isObject( this.mapped_params[ paramName ].dependency ) ? this.mapped_params[ paramName ].dependency : {},
							$paramBlock = $element.closest( '.vc_column' );
						if ( _.isBoolean( rules.not_empty ) && true === rules.not_empty && !isEmpty ) { // Check is not empty show dependent Element.
							$paramBlock.removeClass( 'vc_dependent-hidden' );
						} else if ( _.isBoolean( rules.is_empty ) && true === rules.is_empty && isEmpty ) {
							$paramBlock.removeClass( 'vc_dependent-hidden' );
						} else if ( rules.value && _.intersection( ( _.isArray( rules.value ) ? rules.value : [ rules.value ]),
							( _.isArray( masterValue ) ? masterValue : [ masterValue ]) ).length ) {
							$paramBlock.removeClass( 'vc_dependent-hidden' );
						} else if ( rules.value_not_equal_to && !_.intersection( ( _.isArray( rules.value_not_equal_to ) ? rules.value_not_equal_to : [ rules.value_not_equal_to ]),
							( _.isArray( masterValue ) ? masterValue : [ masterValue ]) ).length ) {
							$paramBlock.removeClass( 'vc_dependent-hidden' );
						} else if ( rules.value_includes && masterValue.includes( rules.value_includes ) ) {
							$paramBlock.removeClass( 'vc_dependent-hidden' );
						} else {
							$paramBlock.addClass( 'vc_dependent-hidden' );
						}
						var event = jQuery.Event( 'change' );
						event.extra_type = 'vcHookDepended';
						$element.trigger( event );
					}, this );
				}
				if ( checkTabs ) {
					this.checkTabs();
					this.doCheckTabs = true;
				}
				return this;
			},
			// Hide tabs if all params inside is vc_dependent-hidden
			checkTabs: function () {
				var that = this;
				if ( false === this.tabsInit ) {
					this.tabsInit = true;
					if ( this.$content.hasClass( 'vc_with-tabs' ) ) {
						this.$tabsMenu = this.$content.find( '.vc_edit-form-tabs-menu' );
					}
				}
				if ( this.$tabsMenu ) {
					this.$content.find( '[data-vc-ui-element="panel-edit-element-tab"]' ).each( function ( index ) {
						var $tabControl = that.$tabsMenu.find( '> [data-tab-index="' + index + '"]' );
						if ( $( this ).find( '[data-vc-ui-element="panel-shortcode-param"]:not(".vc_dependent-hidden")' ).length ) {
							if ( $tabControl.hasClass( 'vc_dependent-hidden' ) ) {
								$tabControl.removeClass( 'vc_dependent-hidden' ).removeClass( 'vc_tab-color-animated' ).addClass(
									'vc_tab-color-animated' );
								window.setTimeout( function () {
									$tabControl.removeClass( 'vc_tab-color-animated' );
								}, 200 );
							}
						} else {
							$tabControl.addClass( 'vc_dependent-hidden' );
						}
					});
					window.setTimeout( this.setTabsSize, 100 );
				}
			},
			/**
			 * new enhancement from #1467
			 * Set tabs positions absolute and height relative to content, to make sure it is stacked to top of panel
			 * @since 4.4
			 */
			setTabsSize: function () {
				this.$tabsMenu.parents( '.vc_with-tabs.vc_panel-body' ).css( 'margin-top', this.$tabsMenu.outerHeight() );
			},
			setActive: function () {
				this.$el.prev().addClass( 'active' );
			},
			window: function () {
				return window;
			},
			getParams: function () {
				var paramsSettings;

				paramsSettings = this.mapped_params;
				this.params = _.extend({}, this.model.get( 'params' ) );
				_.each( paramsSettings, function ( param ) {
					var value;

					value = vc.atts.parseFrame.call( this, param );
					this.params[ param.param_name ] = value;
				}, this );
				_.each( vc.edit_form_callbacks, function ( callback ) {
					callback.call( this );
				}, this );

				return this.params;
			},
			content: function () {
				return this.$content;
			},
			save: function ( e ) {
				if ( !this.model ) {
					vc.saveInProcess = false;
					return;
				}
				var modelId = this.model.get( 'id' );
				this.removeElementEditElementPanelCache( modelId );
				vc.saveInProcess = true;
				var isTextModeAutoSave = e && e.target && e.target.classList.contains( 'textarea_html' );
				var isTextModeButtonSave = function () {
					if ( e && e.target && e.target.closest( '.vc_ui-panel-footer-container' ) ) {
						var panel = e.target.closest( '.vc_ui-panel-window-inner' );
						if ( !panel ) {
							return false;
						}
						var $contentContainer = $( panel ).find( '.vc_ui-panel-content-container' );
						return $contentContainer && $contentContainer.find( '.html-active' ).length > 0;
					}
					return false;
				};
				var isTinyMceTextMode = isTextModeAutoSave || isTextModeButtonSave();

				if ( !this.panelInit || !this.model ) {
					return;
				}
				var $this = this;
				var shortcode = $this.model.get( 'shortcode' );
				var params = $this.getParams();
				var mergedParams = _.extend({}, vc.getDefaults( shortcode ), vc.getMergedParams( shortcode, params ) );
				if ( !_.isUndefined( params.content ) ) {
					if ( isTinyMceTextMode ) {
						try {
							params.content = window.vc.utils.fixUnclosedTags( params.content );
							params.content = window.vc.utils.validateHtml( params.content );
						} catch ( error ) {
							console.error( 'Error processing HTML content: ', error );
						}
					}
					mergedParams.content = params.content;
				}
				$this.model.save({ params: mergedParams });
				$this.showMessage( window.sprintf( window.i18nLocale.inline_element_saved, vc.getMapped( shortcode ).name ), 'success' );
				if ( !window.vc_auto_save && !window.vc.frame_window ) {
					this.hide();
				}
				$this.trigger( 'save' );
				vc.saveInProcess = false;
			},
			show: function () {
				if ( this.$el.hasClass( 'vc_active' ) ) {
					return;
				}
				this.$el.addClass( 'vc_active' );
				if ( !this.draggable ) {
					this.initDraggable();
				}
				this.fixElContainment();
				this.trigger( 'show' );
			},
			hide: function ( e ) {
				if ( e && e.preventDefault ) {
					e.preventDefault();
				}
				if ( vc.saveInProcess ) {
					this.$el.addClass( 'visually-hidden' );
					setTimeout( function () {
						this.handleHide();
					}.bind( this ), 500 );
				} else {
					this.handleHide();
				}
			},
			handleHide: function () {
				this.checkAjax();
				this.ajax = false;
				this.model = null;
				vc.active_panel = false;
				this.currentModelParams = false;
				this._killEditor();
				this.$el.removeClass( 'vc_active visually-hidden' );
				this.$el.find( '.vc_properties-list' ).removeClass( 'vc_with-tabs' ).css( 'margin-top', '' );
				this.$content.empty();
				this.trigger( 'hide' );
			},
			setTitle: function () {
				this.$el.find( this.titleSelector ).html( vc.getMapped( this.model.get( 'shortcode' ) ).name + ' ' + window.i18nLocale.settings );
				return this;
			},
			_killEditor: function () {
				if ( !_.isUndefined( window.tinyMCE ) ) {
					$( 'textarea.textarea_html', this.$el ).each( function () {
						var id = $( this ).attr( 'id' );
						if ( '4' === tinymce.majorVersion ) {
							window.tinyMCE.execCommand( 'mceRemoveEditor', true, id );
						} else {
							window.tinyMCE.execCommand( 'mceRemoveControl', true, id );
						}
					});
				}
				jQuery( 'body' ).off( 'click.wpcolorpicker' );
			}
		});

	window.vc.EditElementUIPanel = vc.EditElementPanelView
		.vcExtendUI( vc.HelperPanelViewHeaderFooter )
		.vcExtendUI( vc.HelperPanelViewResizable )
		.vcExtendUI( vc.HelperPanelViewDraggable )
		.vcExtendUI( vc.HelperPanelTabs )
		.extend({
			el: '#vc_ui-panel-edit-element',
			events: {
				'click [data-vc-ui-element="button-save"]': 'save',
				'click [data-vc-ui-element="button-close"]': 'hide',
				'touchstart [data-vc-ui-element="button-close"]': 'hide',
				'click [data-vc-ui-element="button-minimize"]': 'toggleOpacity',
				'click [data-vc-ui-element="panel-tab-control"]': 'changeTab'
			},
			titleSelector: '[data-vc-ui-element="panel-title"]',
			initialize: function () {
				vc.EditElementUIPanel.__super__.initialize.call( this );

				this.on( 'afterResizeStart', function () {
					this.$el.css( 'maxHeight', 'none' );
				});
			},
			show: function () {
				vc.EditElementUIPanel.__super__.show.call( this );
				$( '[data-vc-ui-element="panel-tabs-controls"]', this.$el ).remove();

				this.$el.css( 'maxHeight', '75vh' );
			},
			tabsMenu: function () {
				if ( false === this.tabsInit ) {
					this.tabsInit = true;
					var $tabsMenu = this.$el.find( '[data-vc-ui-element="panel-tabs-controls"]' );
					if ( $tabsMenu.length ) {
						this.$tabsMenu = $tabsMenu;
					}
				}
				return this.$tabsMenu;
			},
			buildTabs: function () {
				var $tabs = this.content().find( '[data-vc-ui-element="panel-tabs-controls"]' );
				$tabs.prependTo( '[data-vc-ui-element="panel-header-content"]' );
			},
			changeTab: function ( e ) {
				if ( e && e.preventDefault ) {
					e.preventDefault();
				}
				var $tab = $( e.currentTarget );
				if ( $tab.parent().hasClass( 'vc_active' ) ) {
					return;
				}

				this.switchActiveTab( this.$el, $tab );

				this.activeTabIndex = this.$el.find( $tab.data( 'vcUiElementTarget' ) ).addClass( 'vc_active' ).index();
				this.initParams();
				if ( this.$tabsMenu ) {
					this.$tabsMenu.vcTabsLine( 'checkDropdownContainerActive' );
				}
				// In Firefox, scrollTop(0) is buggy, scrolling to non-0 value first fixes it
				this.$content.parent().scrollTop( 1 ).scrollTop( 0 );
				this.trigger( 'tabChange' );
			},
			checkTabs: function () {
				var _this = this;
				if ( false === this.tabsInit ) {
					this.tabsInit = true;
					this.$tabsMenu = this.$el.find( '[data-vc-ui-element="panel-tabs-controls"]' );
				}
				if ( this.tabsMenu() ) {
					this.content().find( '[data-vc-ui-element="panel-edit-element-tab"]' ).each( function ( index ) {
						// find all tabs (even the ones in the dropdown) to hide if all params are hidden.
						var $tabControl = _this.$tabsMenu.find( '[data-tab-index="' + index + '"]' );
						if ( $( this ).find( '[data-vc-ui-element="panel-shortcode-param"]:not(".vc_dependent-hidden")' ).length ) {
							if ( $tabControl.hasClass( 'vc_dependent-hidden' ) ) {
								$tabControl.removeClass( 'vc_dependent-hidden' );
								window.setTimeout( function () {
									$tabControl.removeClass( 'vc_tab-color-animated' );
								}, 200 );
							}
						} else {
							$tabControl.addClass( 'vc_dependent-hidden' );
						}
					});
					this.$tabsMenu.vcTabsLine( 'refresh' );
					this.$tabsMenu.vcTabsLine( 'moveTabs' );
				}
			}
		});
})( window.jQuery );
