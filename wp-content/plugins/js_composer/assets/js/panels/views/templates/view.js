/**
 * @since 4.4
 * TemplatesPanelViewFrontend extends the backend templates view for frontend editor.
 *
 * It customizes template loading and rendering for the frontend editor interface.
 */

( function ( $ ) {
	'use strict';
	if ( _.isUndefined( window.vc ) ) {
		window.vc = {};
	}

	vc.TemplatesPanelViewFrontend = vc.TemplatesPanelViewBackend.extend({
		template_load_action: 'vc_frontend_load_template',
		loadUrl: false,
		initialize: function () {
			this.loadUrl = vc.$frame.attr( 'src' );
			vc.TemplatesPanelViewFrontend.__super__.initialize.call( this );
		},
		render: function () {
			return vc.TemplatesPanelViewFrontend.__super__.render.call( this );
		},
		renderTemplate: function ( html ) {
			// Render template for frontend
			var template, data;
			_.each( $( html ), function ( element ) {
				if ( 'vc_template-data' === element.id ) {
					try {
						data = JSON.parse( element.innerHTML );
					} catch ( err ) {
						if ( window.console && window.console.warn ) {
							window.console.warn( 'renderTemplate error', err );
						}
					}
				}
				if ( 'vc_template-html' === element.id ) {
					template = element.innerHTML;
				}
			});
			// todo check this message appearing: #48591595835639
			if ( template && data && vc.builder.buildFromTemplate( template, data ) ) {
				this.showMessage( window.i18nLocale.template_added_with_id, 'error' );
			} else {
				this.showMessage( window.i18nLocale.template_added, 'success' );
			}
			vc.closeActivePanel();
		}
	});

	window.vc.TemplateWindowUIPanelFrontendEditor = vc.TemplatesPanelViewFrontend
		.vcExtendUI( vc.HelperPanelViewHeaderFooter )
		.vcExtendUI( vc.HelperTemplatesPanelViewSearch )
		.extend({
			panelName: 'template_window',
			showMessageDisabled: false,
			show: function () {
				this.clearSearch();
				window.vc.TemplateWindowUIPanelFrontendEditor.__super__.show.call( this );
			},
			showMessage: function ( text, type ) {
				if ( this.showMessageDisabled ) {
					return false;
				}
				if ( this.message_box_timeout ) {
					this.$el.find( '[data-vc-panel-message]' ).remove();
					window.clearTimeout( this.message_box_timeout );
				}
				this.message_box_timeout = false;
				var messageBoxTemplate = vc.template( '<div class="vc_message_box vc_message_box-standard vc_message_box-rounded vc_color-<%- color %>">' + '<div class="vc_message_box-icon"><i class="fa fa fa-<%- icon %>"></i></div><p><%- text %></p></div>' );
				var $messageBox;
				var wrapperCssClasses;
				wrapperCssClasses = 'vc_col-xs-12 wpb_element_wrapper';
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
			}
		});

})( window.jQuery );
