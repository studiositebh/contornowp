/**
 * A view for the My elements panel.
 * Displays element presets, with ability to add and remove presets.
 */

/* global vc, i18nLocale */
( function ( $ ) {
	'use strict';

	window.vc.PresetSettingsUIPanelFrontendEditor = vc.PanelView
		.vcExtendUI( vc.HelperPanelViewHeaderFooter )
		.vcExtendUI( vc.HelperAjax )
		.vcExtendUI({
			panelName: 'preset_settings',
			showMessageDisabled: false,
			events: {
				'click [data-vc-ui-delete="preset-title"]': 'removePreset',
				'click [data-vc-ui-element="button-close"]': 'hide',
				'touchstart [data-vc-ui-element="button-close"]': 'hide',
				'click [data-vc-ui-element="button-minimize"]': 'toggleOpacity',
				'click [data-vc-ui-add-preset]': 'createPreset'
			},
			initialize: function ( options ) {
				this.frontEnd = options && options.frontEnd;
			},
			createPreset: function ( e ) {
				var options;
				var columnOptions;
				var $control, preset, tag, row, column, model, rowParams, columnParams, rowOptions, showSettings;

				if ( !_.isUndefined( vc.ShortcodesBuilder ) ) {
					this.builder = new vc.ShortcodesBuilder();
				}

				$control = $( e.currentTarget );
				preset = $control.data( 'preset' );
				tag = $control.data( 'tag' );
				rowParams = {};
				columnParams = { width: '1/1' };

				rowOptions = {
					shortcode: 'vc_row',
					params: rowParams
				};

				if ( this.frontEnd ) {
					this.builder.create( rowOptions );
					columnOptions = {
						shortcode: 'vc_column',
						params: columnParams,
						parent_id: this.builder.lastID()
					};

					this.builder.create( columnOptions );
					options = {
						shortcode: tag,
						parent_id: this.builder.lastID()
					};

					if ( preset ) {
						options.preset = preset;
					}

					window.vc.closeActivePanel();
					this.builder.create( options );
					this.model = this.builder.last();
					this.builder.render();
				} else {
					row = vc.shortcodes.create( rowOptions );
					columnOptions = {
						shortcode: 'vc_column',
						params: columnParams,
						parent_id: row.id,
						root_id: row.id
					};

					column = vc.shortcodes.create( columnOptions );
					model = row;

					options = {
						shortcode: tag,
						parent_id: column.id,
						root_id: row.id
					};

					if ( preset ) {
						options.preset = preset;
					}

					model = vc.shortcodes.create( options );

					window.vc.closeActivePanel();
					this.model = model;
				}

				showSettings = !( _.isBoolean( vc.getMapped( tag ).show_settings_on_create ) && false === vc.getMapped( tag ).show_settings_on_create );

				if ( showSettings ) {
					this.showEditForm();
				}

			},
			showEditForm: function () {
				window.vc.edit_element_block_view.render( this.model );
			},
			render: function () {
				this.$el.css( 'left', ( $( window ).width() - this.$el.width() ) / 2 );
				return this;
			},
			removePreset: function ( e ) {
				if ( e && e.preventDefault ) {
					e.preventDefault();
				}
				var closestPreset = jQuery( e.currentTarget ).closest( '[data-vc-ui-delete="preset-title"]' );
				var presetId = closestPreset.data( 'preset' );
				var presetParent = closestPreset.data( 'preset-parent' );

				this.deleteSettings( presetId, presetParent, e );
			},
			/**
			 * Delete specific preset
			 *
			 * @param {number} id
			 * @param {string} shortcodeName
			 */
			deleteSettings: function ( id, shortcodeName ) {
				var _this = this;
				if ( !confirm( window.i18nLocale.delete_preset_confirmation ) ) {
					return false;
				}

				this.checkAjax();
				this.ajax = $.ajax({
					type: 'POST',
					dataType: 'json',
					url: window.ajaxurl,
					data: this.deleteSettingsAjaxData( shortcodeName, id ),
					context: this
				}).done( function ( response ) {
					if ( response && response.success ) {
						this.showMessage( window.i18nLocale.preset_removed, 'success' );
						_this.$el.find( '[data-preset="' + id + '"]' ).closest( '.vc_ui-template' ).remove();
						window.vc.events.trigger( 'vc:deletePreset', id );
					}
				}).always( this.resetAjax );

				return this.ajax;
			},
			deleteSettingsAjaxData: function ( shortcodeName, id ) {
				return {
					action: 'vc_action_delete_settings_preset',
					shortcode_name: shortcodeName,
					vc_inline: true,
					id: id,
					_vcnonce: window.vcAdminNonce
				};
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
				$messageBox.prependTo( this.$el.find( '.vc_properties-list' ) );
				$messageBox.fadeIn();
				this.message_box_timeout = window.setTimeout( function () {
					$messageBox.remove();
				}, 6000 );
			}
		});
})( window.jQuery );
