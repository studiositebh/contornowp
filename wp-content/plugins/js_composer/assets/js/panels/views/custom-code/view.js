/**
 * CustomCodePanelView provides the core interface for managing custom code in the editor.
 *
 * It handles custom CSS, JavaScript with real-time preview, providing a unified interface
 * for managing code customizations.
 */

( function ( $ ) {
	'use strict';
	if ( _.isUndefined( window.vc ) ) {
		window.vc = {};
	}

	vc.CustomCodePanelView = vc.PanelView.extend({
		events: {
			'click [data-save=true]': 'save',
			'click [data-dismiss=panel]': 'hide',
			'click [data-transparent=panel]': 'toggleOpacity',
			'mouseover [data-transparent=panel]': 'addOpacity',
			'mouseout [data-transparent=panel]': 'removeOpacity',
			'click [data-vc-ui-element="panel-tab-control"]': 'changeTab'
		},
		saved_css_data: '',
		saved_js_header_data: '',
		saved_js_footer_data: '',
		editor_css: false,
		editor_js_header: false,
		editor_js_footer: false,
		is_frontend_editor: false,
		initialize: function () {
			this.is_frontend_editor = $( '#vc_inline-frame' ).length > 0;

			vc.$custom_css = $( '#vc_post-custom-css' );
			vc.$custom_js_header = $( '#vc_post-custom-js-header' );
			vc.$custom_js_footer = $( '#vc_post-custom-js-footer' );
			this.saved_css_data = vc.$custom_css.val();
			this.saved_js_header_data = vc.$custom_js_header.val();
			this.saved_js_footer_data = vc.$custom_js_footer.val();

			if ( window.Vc_postSettingsEditor ) {
				this.initEditor();
			}
			_.bindAll( this, 'setSize', 'fixElContainment', 'changeTab' );
			this.on( 'show', this.setSize, this );
			this.on( 'setSize', this.setResize, this );
			this.on( 'render', this.resetMinimize, this );
			this.on( 'afterRender', function () {
				$( '.edit-form-info' ).initializeTooltips( '.vc_column' );
			}, this );
		},
		initEditor: function () {
			this.editor_css = new Vc_postSettingsEditor();
			this.editor_css.sel = 'wpb_css_editor';
			this.is_css = $( '#' + this.editor_css.sel ).length;
			this.editor_css.mode = 'css';
			this.editor_js_header = new Vc_postSettingsEditor();
			this.editor_js_header.sel = 'wpb_js_header_editor';
			this.is_js_header = $( '#' + this.editor_js_header.sel ).length;
			this.editor_js_header.mode = 'javascript';
			this.editor_js_footer = new Vc_postSettingsEditor();
			this.editor_js_footer.sel = 'wpb_js_footer_editor';
			this.is_js_footer = $( '#' + this.editor_js_footer.sel ).length;
			this.editor_js_footer.mode = 'javascript';
		},
		render: function () {
			this.trigger( 'render' );
			this.trigger( 'afterRender' );
			this.setEditor();
			return this;
		},
		setEditor: function () {
			if ( this.is_css ) {
				this.editor_css.setEditor( vc.$custom_css.val() );
			}
			if ( this.is_js_header ) {
				this.editor_js_header.setEditor( vc.$custom_js_header.val() );
			}
			if ( this.is_js_footer ) {
				this.editor_js_footer.setEditor( vc.$custom_js_footer.val() );
			}
		},
		setSize: function () {
			if ( window.Vc_postSettingsEditor ) {
				if ( this.is_css ) {
					this.editor_css.setSize();
				}
				if ( this.is_js_header ) {
					this.editor_js_header.setSize();
				}
				if ( this.is_js_footer ) {
					this.editor_js_footer.setSize();
				}
				this.trigger( 'setSize' );
			}
		},
		changeTab: function ( e ) {
			if ( e && e.preventDefault ) {
				e.preventDefault();
			}
			var $tabControl = $( e.currentTarget );
			var $tabListItem = $tabControl.parent( 'li' );

			if ( $tabListItem.hasClass( 'vc_active' ) ) {
				return;
			}

			$tabControl.closest( '[data-vc-ui-element="panel-tabs-controls"]' ).find( '.vc_active' ).removeClass( 'vc_active' );
			this.$el.find( '.vc_panel-tab.vc_active' ).removeClass( 'vc_active' );

			$tabListItem.addClass( 'vc_active' );
			var filter = $tabControl.data( 'filter' );
			this.$el.find( '.vc_panel-tab[data-filter="' + filter + '"]' ).addClass( 'vc_active' );

			this.setSize();

			this.trigger( 'tabChange', this );
		},
		save: function () {
			this.setAlertOnDataChange();

			if ( this.is_css ) {
				var cssVal = this.editor_css.getValue();
				vc.$custom_css.val( cssVal );
				this.saved_css_data = cssVal;
			}
			if ( this.is_js_header ) {
				var jsHeaderVal = this.editor_js_header.getValue();
				vc.$custom_js_header.val( jsHeaderVal );
				this.saved_js_header_data = jsHeaderVal;
			}
			if ( this.is_js_footer ) {
				var jsFooterVal = this.editor_js_footer.getValue();
				vc.$custom_js_footer.val( jsFooterVal );
				this.saved_js_footer_data = jsFooterVal;
			}

			if ( vc.frame_window ) {
				if ( this.is_css ) {
					vc.frame_window.vc_iframe.loadCustomCss( this.saved_css_data );
				}
				if ( this.is_js_header ) {
					vc.frame_window.vc_iframe.loadCustomJsHeader(
						this.saved_js_header_data
					);
				}
				if ( this.is_js_footer ) {
					vc.frame_window.vc_iframe.loadCustomJsFooter(
						this.saved_js_footer_data
					);
				}
			}
			vc.updateSettingsBadge();
			window.vc.showMessage( window.i18nLocale.custom_code_updated, 'success' );

			vc.storage = vc.storage || {};
			vc.storage.isChanged = true;

			this.trigger( 'save' );
		},
		show: function () {
			if ( this.$el.hasClass( 'vc_active' ) ) {
				return;
			}

			vc.closeActivePanel();
			vc.active_panel = this;

			this.$el.addClass( 'vc_active' );
			if ( !this.draggable ) {
				this.initDraggable();
			}
			this.fixElContainment();
			this.trigger( 'show' );
		},
		hide: function ( e ) {
			if ( this.isCodeChanged() ) {
				if ( !confirm( window.i18nLocale.page_settings_confirm ) ) {
					return;
				}
				this.rollBackChanges();
			}
			vc.PanelView.prototype.hide.call( this, e );

			vc.updateSettingsBadge();
			this.trigger( 'hide' );
		},
		isCodeChanged: function () {
			return (
				( this.is_css && this.saved_css_data !== this.editor_css.getValue() ) ||
				( this.is_js_header && this.saved_js_header_data !== this.editor_js_header.getValue() ) ||
				( this.is_js_footer && this.saved_js_footer_data !== this.editor_js_footer.getValue() )
			);
		},
		rollBackChanges: function () {
			if ( this.is_css ) {
				this.editor_css.setEditor( this.saved_css_data );
			}
			if ( this.is_js_header ) {
				this.editor_js_header.setEditor( this.saved_js_header_data );
			}
			if ( this.is_js_footer ) {
				this.editor_js_footer.setEditor( this.saved_js_footer_data );
			}
		},
		setAlertOnDataChange: function () {
			if ( this.isCodeChanged() ) {
				vc.setDataChanged();
			}
		}
	});

	vc.CustomCodeUIPanelFrontendEditor = vc.CustomCodePanelView
		.vcExtendUI( vc.HelperPanelViewHeaderFooter )
		.vcExtendUI( vc.HelperPanelViewResizable )
		.vcExtendUI( vc.HelperPanelViewDraggable )
		.vcExtendUI({
			panelName: 'custom_code',
			uiEvents: {
				'setSize': 'setEditorSize',
				'show': 'setEditorSize'
			},
			previewTab: null,
			events: function () {
				return _.extend({
					'click [data-vc-ui-element="panel-tab-control"]': 'changeTab'
				}, window.vc.CustomCodeUIPanelFrontendEditor.__super__.events );
			},
			// Fix old editor call for resize.
			setSize: function () {
				this.trigger( 'setSize' );
			},
			setDefaultHeightSettings: function () {
				this.$el.css( 'height', '75vh' );
			},
			setEditorSize: function () {
				if ( window.Vc_postSettingsEditor ) {
					this.editor_css.setSizeResizable();
					this.editor_js_header.setSizeResizable();
					this.editor_js_footer.setSizeResizable();
				}
			}
		});

})( window.jQuery );
