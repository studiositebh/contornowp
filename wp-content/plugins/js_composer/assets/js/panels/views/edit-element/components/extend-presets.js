/**
 * vc.ExtendPresets object, provides functionality for managing
 * element settings presets. It includes methods for saving, loading, applying, and
 * restoring presets, as well as handling UI interactions such as dropdown menus and dialogs
 * for preset management. The file also integrates AJAX calls to interact with the backend
 * for preset-related operations.
 *
 * vc.ExtendPresets object is initialized in the window.vc.EditElementPanelView
 */

( function ( $ ) {
	'use strict';

	window.vc.ExtendPresets = {
		settingsMenuSelector: '[data-vc-ui-element="settings-dropdown-list"]',
		settingsButtonSelector: '[data-vc-ui-element="settings-dropdown-button"]',
		settingsDropdownSelector: '[data-vc-ui-element="settings-dropdown"]',
		settingsPresetId: null,
		uiEvents: {
			'init': 'addEvents',
			'render': 'hideDropdown',
			'afterRender': 'afterRenderActions'
		},
		afterRenderActions: function () {
			this.untaintSettingsPresetData();
			this.showDropdown();
		},
		hideDropdown: function () {
			this.$el.find( '[data-vc-ui-element="settings-dropdown"]' ).hide();
		},
		showDropdown: function () {
			// must be called when content added to DOM
			var tag = this.model.get( 'shortcode' );
			if ( window.vc_settings_show && 'vc_column' !== tag ) {
				this.$el.find( '[data-vc-ui-element="settings-dropdown"]' ).show();
			}
		},
		showDropdownMenu: function () {
			var tag = this.model.get( 'shortcode' );

			var $this = $( this );

			if ( $this.data( 'vcSettingsMenuLoaded' ) && tag === $this.data( 'vcShortcodeName' ) ) {
				return;
			}

			this.reloadSettingsMenuContent();
		},
		addEvents: function () {
			var $tab = this.$el.find( '.vc_edit-form-tab.vc_active' );
			var tag = this.model.get( 'shortcode' );
			var _this = this;
			$( document ).off( 'beforeMinimize.vc.paramWindow',
				this.minimizeButtonSelector ).on( 'beforeMinimize.vc.paramWindow', this.minimizeButtonSelector,
				function () {
					$tab.find( '.vc_ui-prompt-presets .vc_ui-prompt-close' ).trigger( 'click' );
				}
			);

			$( document ).off( 'close.vc.paramWindow',
				this.closeButtonSelector ).on( 'beforeClose.vc.paramWindow', this.closeButtonSelector,
				function () {
					$tab.find( '.vc_ui-prompt-presets .vc_ui-prompt-close' ).trigger( 'click' );
				}
			);

			$( document ).off( 'show.vc.accordion', this.settingsButtonSelector ).on( 'show.vc.accordion',
				this.settingsButtonSelector,
				function () {
					var $this = $( this );

					if ( $this.data( 'vcSettingsMenuLoaded' ) && tag === $this.data( 'vcShortcodeName' ) ) {
						return;
					}

					_this.reloadSettingsMenuContent();
				}
			);
		},
		saveSettingsAjaxData: function ( shortcodeName, title, isDefault, data ) {
			return {
				action: 'vc_action_save_settings_preset',
				shortcode_name: shortcodeName,
				is_default: isDefault ? 1 : 0,
				vc_inline: true,
				title: title,
				data: data,
				_vcnonce: window.vcAdminNonce
			};
		},
		saveSettings: function ( title, isDefault ) {
			var shortcodeName = this.model.get( 'shortcode' ),
				data = JSON.stringify( this.getParamsForSettingsPreset() );

			if ( 'undefined' === typeof ( title ) || !title.length ) {
				return;
			}

			if ( 'undefined' === typeof ( isDefault ) ) {
				isDefault = false;
			}

			this.checkAjax();
			this.ajax = $.ajax({
				type: 'POST',
				dataType: 'json',
				url: window.ajaxurl,
				data: this.saveSettingsAjaxData( shortcodeName, title, isDefault, data ),
				context: this
			}).done( function ( response ) {
				if ( response.success ) {
					this.setSettingsMenuContent( response.html );
					this.settingsPresetId = response.id;
					this.untaintSettingsPresetData();
				}
			}).always( this.resetAjax );

			return this.ajax;
		},
		fetchSaveSettingsDialogAjaxData: function () {
			return {
				action: 'vc_action_render_settings_preset_title_prompt',
				vc_inline: true,
				_vcnonce: window.vcAdminNonce
			};
		},
		/**
		 * Fetch save settings dialog and insert it into DOM
		 *
		 * First param of callback function will be passed bool value whether dialog was created (true) or already existed in DOM (false)
		 *
		 * @param {function} callback function to execute after element has been added to DOM
		 */
		fetchSaveSettingsDialog: function ( callback ) {
			var $contentContainer = this.$el.find( '.vc_ui-panel-content-container' );

			if ( $contentContainer.find( '.vc_ui-prompt-presets' ).length ) {
				if ( 'undefined' !== typeof ( callback ) ) {
					callback( false );
				}
				return;
			}

			this.checkAjax();
			this.ajax = $.ajax({
				type: 'POST',
				dataType: 'json',
				url: window.ajaxurl,
				data: this.fetchSaveSettingsDialogAjaxData()
			}).done( function ( response ) {
				if ( response.success ) {

					$contentContainer.prepend( response.html );

					if ( 'undefined' !== typeof ( callback ) ) {
						callback( true );
					}
				}
			}).fail( function () {
				if ( 'undefined' !== typeof ( callback ) ) {
					callback( false );
				}
			}).always( this.resetAjax );
		},
		showSaveSettingsDialog: function ( isDefault ) {
			var _this = this;

			this.isSettingsPresetDefault = !!isDefault;

			this.fetchSaveSettingsDialog( function ( created ) {
				var $contentContainer = _this.$el.find( '.vc_ui-panel-content-container' ),
					$prompt = $contentContainer.find( '.vc_ui-prompt-presets' ),
					$title = $prompt.find( '.textfield' );
				$contentContainer.find( '.vc_ui-prompt.vc_visible' ).removeClass( 'vc_visible' );

				var $viewPresetsButton = $prompt.find( '[data-vc-view-settings-preset]' );
				if ( 'undefined' !== window.vc_vendor_settings_presets[ _this.model.get( 'shortcode' ) ]) {
					$viewPresetsButton.removeAttr( 'disabled' );
				} else {
					$viewPresetsButton.attr( 'disabled', 'disabled' );
				}
				$prompt.addClass( 'vc_visible' );
				$title.trigger( 'focus' );
				$contentContainer.addClass( 'vc_ui-content-hidden' );

				if ( !created ) {
					return;
				}
				var $btn = $prompt.find( '#vc_ui-save-preset-btn' );
				var delay = 0;
				$prompt.on( 'submit', function () {
					var title = $title.val();

					if ( !title.length ) {
						return false;
					}

					_this.saveSettings( title, _this.isSettingsPresetDefault ).done( function ( e ) {
						var data = this.getParamsForSettingsPreset();
						$title.val( '' );
						_this.setCustomButtonMessage( $btn, undefined, undefined, true );
						vc.events.trigger( 'vc:savePreset', e.id, _this.model.get( 'shortcode' ), title, data );
						delay = _.delay( function () {
							$prompt.removeClass( 'vc_visible' );
							$contentContainer.removeClass( 'vc_ui-content-hidden' );
						}, 5000 );
					}).fail( function () {
						_this.setCustomButtonMessage( $btn, window.i18nLocale.ui_danger, 'danger', true );
					});

					return false;
				});

				$prompt.on( 'click', '.vc_ui-prompt-close', function () {
					_this.checkAjax();
					$prompt.removeClass( 'vc_visible' );
					$contentContainer.removeClass( 'vc_ui-content-hidden' );
					_this.clearCustomButtonMessage.call( this, $btn );
					if ( delay ) {
						window.clearTimeout( delay );
						delay = 0;
					}
					return false;
				});
				$( '.edit-form-info' ).initializeTooltips();
			});
		},
		loadSettingsAjaxData: function ( id ) {
			return {
				action: 'vc_action_get_settings_preset',
				vc_inline: true,
				id: id,
				_vcnonce: window.vcAdminNonce
			};
		},
		/**
		 * Load and render specific preset
		 *
		 * @param {number} id
		 */
		loadSettings: function ( id ) {
			this.panelInit = false;

			this.checkAjax();
			this.ajax = $.ajax({
				type: 'POST',
				dataType: 'json',
				url: window.ajaxurl,
				data: this.loadSettingsAjaxData( id ),
				context: this
			}).done( function ( response ) {
				if ( response.success ) {
					this.settingsPresetId = id;
					this.applySettingsPreset( response.data );
				}
			}).always( this.resetAjax );

			return this.ajax;
		},

		saveAsDefaultSettingsAjaxData: function ( shortcodeName, id ) {
			return {
				action: 'vc_action_set_as_default_settings_preset',
				shortcode_name: shortcodeName,
				id: id,
				vc_inline: true,
				_vcnonce: window.vcAdminNonce
			};
		},
		/**
		 * Save currently loaded preset as default
		 *
		 * If no preset has been loaded or loaded preset has been changed (tainted),
		 * show "save as" dialog. Otherwise save w/o any prompt.
		 */
		saveAsDefaultSettings: function ( id, doneCallback ) {
			var shortcodeName = this.model.get( 'shortcode' );
			var presetId = id ? id : this.settingsPresetId;
			// if user has not loaded preset or made any changes...
			if ( !presetId ) {
				this.showSaveSettingsDialog( true );
			} else {
				this.checkAjax();
				this.ajax = $.ajax({
					type: 'POST',
					dataType: 'json',
					url: window.ajaxurl,
					data: this.saveAsDefaultSettingsAjaxData( shortcodeName, presetId ),
					context: this
				}).done( function ( response ) {
					if ( response.success ) {
						this.setSettingsMenuContent( response.html );
						this.untaintSettingsPresetData();
						if ( doneCallback ) {
							doneCallback();
						}
					}
				}).always( this.resetAjax );
			}
		},
		restoreDefaultSettingsAjaxData: function ( shortcodeName ) {
			return {
				action: 'vc_action_restore_default_settings_preset',
				shortcode_name: shortcodeName,
				vc_inline: true,
				_vcnonce: window.vcAdminNonce
			};
		},
		/**
		 * Remove "default" flag from currently default preset
		 */
		restoreDefaultSettings: function () {
			var shortcodeName = this.model.get( 'shortcode' );

			this.checkAjax();
			this.ajax = $.ajax({
				type: 'POST',
				dataType: 'json',
				url: window.ajaxurl,
				data: this.restoreDefaultSettingsAjaxData( shortcodeName ),
				context: this
			}).done( function ( response ) {
				if ( response.success ) {
					this.setSettingsMenuContent( response.html );
				}
			}).always( this.resetAjax );

		},
		/**
		 * Update settings menu (popup) content with specified html
		 *
		 * @param {string} html
		 */
		setSettingsMenuContent: function ( html ) {
			var $button = this.$el.find( this.settingsButtonSelector ),
				$menu = this.$el.find( this.settingsMenuSelector ),
				shortcodeName = this.model.get( 'shortcode' ),
				_this = this;

			$button.data( 'vcShortcodeName', shortcodeName );
			$menu.html( html );

			if ( window.vc_presets_data && window.vc_presets_data.presetsCount > 0 ) {
				$menu.find( '[data-vc-view-settings-preset]' ).removeAttr( 'disabled' );
			} else {
				$menu.find( '[data-vc-view-settings-preset]' ).attr( 'disabled', 'disabled' );
			}

			$menu.find( '[data-vc-view-settings-preset]' ).on( 'click', function () {
				_this.showViewSettingsList();
				_this.closeSettings();
			});

			$menu.find( '[data-vc-save-settings-preset]' ).on( 'click', function () {
				_this.showSaveSettingsDialog();
				_this.closeSettings();
			});

			$menu.find( '[data-vc-save-template]' ).on( 'click', function () {
				_this.showSaveTemplateDialog();
				_this.closeSettings();
			});

			$menu.find( '[data-vc-save-default-settings-preset]' ).on( 'click', function () {
				_this.saveAsDefaultSettings();
				_this.closeSettings();
			});

			$menu.find( '[data-vc-restore-default-settings-preset]' ).on( 'click', function () {
				_this.restoreDefaultSettings();
				_this.closeSettings();
			});

		},
		reloadSettingsMenuContentAjaxData: function ( shortcodeName ) {
			return {
				action: 'vc_action_render_settings_preset_popup',
				shortcode_name: shortcodeName,
				vc_inline: true,
				_vcnonce: window.vcAdminNonce
			};
		},
		showViewSettingsList: function () {
			var $contentContainer = this.$el.find( '.vc_ui-panel-content-container' );
			$contentContainer.find( '.vc_ui-prompt-view-presets:not(.vc_visible)' ).remove();
			if ( $contentContainer.find( '.vc_ui-prompt-view-presets' ).length ) {
				return;
			}
			$contentContainer.find( '.vc_ui-prompt.vc_visible' ).removeClass( 'vc_visible' );

			var _this = this;
			var $prompt = jQuery(
				'<form class="vc_ui-prompt vc_ui-prompt-view-presets"><div class="vc_ui-prompt-controls"><button type="button" class="vc_general vc_ui-control-button vc_ui-prompt-close"><i class="vc-composer-icon vc-c-icon-close"></i></button></div><div class="vc_ui-prompt-title"><label for="prompt_title" class="wpb_element_label">Elements</label></div><div class="vc_ui-prompt-content"><div class="vc_ui-prompt-column"><div class="vc_ui-template-list vc_ui-list-bar" data-vc-action="collapseAll" style="margin-top: 20px;" data-vc-presets-list-content></div></div></div>' );
			this.buildsettingsListContent( $prompt );
			$prompt.appendTo( $contentContainer );
			$prompt.addClass( 'vc_visible' );

			$contentContainer.addClass( 'vc_ui-content-hidden' );

			var closePrompt = function () {
				$prompt.remove();
				$contentContainer.removeClass( 'vc_ui-content-hidden' );
				return false;
			};

			$prompt.off( 'click.vc1' ).on( 'click.vc1', '[data-vc-load-settings-preset]', function ( e ) {
				_this.loadSettings( $( e.currentTarget ).data( 'vcLoadSettingsPreset' ) );
				closePrompt();
			});

			$prompt.off( 'click.vc4' ).on( 'click.vc4', '[data-vc-set-default-settings-preset]', function () {
				_this.saveAsDefaultSettings( $( this ).data( 'vcSetDefaultSettingsPreset' ), function () {
					_this.buildsettingsListContent( $prompt );
				});
			});

			$prompt.off( 'click.vc3' ).on( 'click.vc3', '.vc_ui-prompt-close', function () {
				closePrompt();
				_this.checkAjax();
			});
		},
		buildsettingsListContent: function ( $prompt ) {
			var itemsTemplate = vc.template(
				'<div class="vc_ui-template"><div class="vc_ui-list-bar-item"><button class="vc_ui-list-bar-item-trigger" title="Apply Element" type="button" data-vc-load-settings-preset="<%- id %>"><%- title %></button><div class="vc_ui-list-bar-item-actions"><button class="vc_general vc_ui-control-button" title="Apply Element" type="button" data-vc-load-settings-preset="<%- id %>"><i class="vc-composer-icon vc-c-icon-add"></i></button><button class="vc_general vc_ui-control-button" title="Delete Element" type="button" data-vc-delete-settings-preset="<%- id %>"><i class="vc-composer-icon vc-c-icon-delete_empty"></i></button></div></div></div>' );
			var $content = $prompt.find( '[data-vc-presets-list-content]' );
			$content.empty();
			_.each( window.vc_presets_data.presets[ 0 ], function ( item, id ) {
				var title = item;
				if ( window.vc_presets_data.defaultId > 0 && parseInt( id, 10 ) === window.vc_presets_data.defaultId ) {
					title = item + ' (default)';
				}
				$content.append( itemsTemplate({ title: title, id: id }) );
			});
			_.each( window.vc_presets_data.presets[ 1 ], function ( item, id ) {
				var title = item;
				if ( window.vc_presets_data.defaultId > 0 && parseInt( id, 10 ) === window.vc_presets_data.defaultId ) {
					title = item + ' (default)';
				}
				$content.append( itemsTemplate({ title: title, id: id }) );
			});
		},
		/**
		 * Reload settings menu (popup) content
		 *
		 * This is envoked for the first time menu is opened and every time preset is
		 * saved or deleted
		 */
		reloadSettingsMenuContent: function () {
			var shortcodeName = this.model.get( 'shortcode' ),
				$button = this.$el.find( this.settingsButtonSelector ),
				success = false;

			this.setSettingsMenuContent( '' );

			this.checkAjax();
			this.ajax = $.ajax({
				type: 'POST',
				dataType: 'json',
				url: window.ajaxurl,
				data: this.reloadSettingsMenuContentAjaxData( shortcodeName ),
				context: this
			}).done( function ( response ) {
				if ( response.success ) {
					success = true;
					this.setSettingsMenuContent( response.html );
					$button
						.data( 'vcSettingsMenuLoaded', true );
				}
			}).always( function () {
				if ( !success ) {
					this.closeSettings();
				}
				this.resetAjax();
			});

			return this.ajax;
		},
		/**
		 * Close settings menu
		 *
		 * @param {boolean} [destroy=false] If true, mark menu as 'not loaded', so next time user opens it, it will be fetched again
		 */
		closeSettings: function ( destroy ) {
			if ( 'undefined' === typeof ( destroy ) ) {
				destroy = false;
			}

			var $menu = this.$el.find( this.settingsMenuSelector ),
				$button = this.$el.find( this.settingsButtonSelector );

			if ( destroy ) {
				$button.data( 'vcSettingsMenuLoaded', false );
				$menu.html( '' );
			}

			$button.vcAccordion( 'hide' );
		},
		/**
		 * Check if setting preset data is tainted in current window
		 *
		 * Every time this.getParamsForSettingsPreset() is accessed and design options are used, new random
		 * classname (vc_custom_RANDOM-DIGITS) is created which would generate different
		 * hash every time, so we delete this random part.
		 *
		 * @return {boolean}
		 */
		isSettingsPresetDataTainted: function () {
			var params = JSON.stringify( this.getParamsForSettingsPreset() );
			params = params.replace( /vc_custom_\d+/, '' );

			return this.$el.data( 'vcSettingsPresetHash' ) !== vc_globalHashCode( params );
		},
		/**
		 * Untaint settings preset data in current window
		 *
		 * @see isSettingsPresetDataTainted for reason why vc_custom_* is removed before hashing
		 */
		untaintSettingsPresetData: function () {
			var params = JSON.stringify( this.getParamsForSettingsPreset() );
			params = params.replace( /vc_custom_\d+/, '' );

			this.$el.data( 'vcSettingsPresetHash', vc_globalHashCode( params ) );
		},
		applySettingsPresetAjaxData: function ( params ) {
			var parentId = this.model.get( 'parent_id' );

			return {
				action: 'vc_edit_form',
				tag: this.model.get( 'shortcode' ),
				parent_tag: parentId ? vc.shortcodes.get( parentId ).get( 'shortcode' ) : null,
				post_id: window.vc_post_id,
				params: params,
				_vcnonce: window.vcAdminNonce
			};
		},
		/**
		 * Render preset
		 *
		 * @see render
		 *
		 * @param {object} params
		 * @return {vc.EditElementPanelView}
		 */
		applySettingsPreset: function ( params ) {
			this.currentModelParams = params;
			vc.events.trigger( 'presets:apply', this.model, params );

			this._killEditor();
			this.trigger( 'render' );
			this.show();

			this.checkAjax();
			this.ajax = $.ajax({
				type: 'POST',
				url: window.ajaxurl,
				data: this.applySettingsPresetAjaxData( params ),
				context: this
			}).done( this.buildParamsContent ).always( this.resetAjax );

			return this;
		},
		/**
		 * Same as getParams, but exclude some attributes
		 */
		getParamsForSettingsPreset: function () {
			var shortcode = this.model.get( 'shortcode' ),
				params = this.getParams();

			if ( 'vc_column' === shortcode || 'vc_column_inner' === shortcode ) {
				delete params.width;
				delete params.offset;
			}

			return params;
		}
	};

	vc.events.on( 'presets.apply', function ( model, params ) {
		if ( 'vc_tta_section' === model.get( 'shortcode' ) && 'undefined' !== typeof ( params.tab_id ) ) {
			params.tab_id = vc_guid() + '-cl';
		}

		return params;
	});
})( window.jQuery );
