/**
 * vc.ExtendTemplates object, provides functionality for managing
 * element templates in the editor. It includes methods for saving, loading, and
 * displaying templates, as well as handling UI interactions such as dialogs for template management.
 * The file integrates AJAX calls to interact with the backend for template-related operations.
 *
 * vc.ExtendTemplates object is initialized in the window.vc.EditElementPanelView
 */

( function ( $ ) {
	'use strict';

	window.vc.ExtendTemplates = {
		fetchSaveTemplateDialogAjaxData: function () {
			return {
				action: 'vc_action_render_settings_templates_prompt',
				vc_inline: true,
				_vcnonce: window.vcAdminNonce
			};
		},
		fetchSaveTemplateDialog: function ( callback ) {
			var $tab = this.$el.find( '.vc_ui-panel-content-container' );

			if ( $tab.find( '.vc_ui-prompt-templates' ).length ) {
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
				data: this.fetchSaveTemplateDialogAjaxData()
			}).done( function ( response ) {
				if ( response.success ) {
					$tab.prepend( response.html );

					if ( 'undefined' !== typeof ( callback ) ) {
						callback( true );
					}
				}
			}).always( this.resetAjax );

			return this.ajax;
		},
		showSaveTemplateDialog: function () {
			var _this = this;

			this.fetchSaveTemplateDialog( function ( created ) {
				var $tab = _this.$el.find( '.vc_ui-panel-content-container' ),
					$prompt = $tab.find( '.vc_ui-prompt-templates' ),
					$title = $prompt.find( '.textfield' );
				$tab.find( '.vc_ui-prompt.vc_visible' ).removeClass( 'vc_visible' );

				$prompt.addClass( 'vc_visible' );
				$title.trigger( 'focus' );
				$tab.addClass( 'vc_ui-content-hidden' );

				if ( !created ) {
					return;
				}
				var delay = 0;
				var $btn = $prompt.find( '#vc_ui-save-templates-btn' );

				$prompt.on( 'submit', function () {
					var title = $title.val(),
						// TODO: check if $button is used
						// eslint-disable-next-line no-unused-vars
						$button = _this.$el.find( _this.settingsButtonSelector );

					if ( !title.length ) {
						return false;
					}

					var data = {
						action: vc.templates_panel_view.save_template_action,
						template: vc.shortcodes.singleStringify( _this.model.get( 'id' ), 'template' ),
						template_name: title,
						vc_inline: true,
						_vcnonce: window.vcAdminNonce
					};

					vc.templates_panel_view.reloadTemplateList( data, function () {
						$title.val( '' );
						_this.setCustomButtonMessage( $btn, undefined, undefined, true );

						delay = _.delay( function () {
							$prompt.removeClass( 'vc_visible' );
							$tab.removeClass( 'vc_ui-content-hidden' );
						}, 5000 );
					}, function () {
						_this.setCustomButtonMessage( $btn, window.i18nLocale.ui_danger, 'danger' );
					});

					return false;
				});

				$prompt.on( 'click', '.vc_ui-prompt-close', function () {
					_this.checkAjax();
					$prompt.removeClass( 'vc_visible' );
					$tab.removeClass( 'vc_ui-content-hidden' );
					_this.clearCustomButtonMessage.call( this, $btn );
					if ( delay ) {
						window.clearTimeout( delay );
						delay = 0;
					}
					return false;
				});
			});
		}
	};
})( window.jQuery );
