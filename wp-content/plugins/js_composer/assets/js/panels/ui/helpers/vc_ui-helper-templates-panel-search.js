( function ( $ ) {
	'use strict';

	window.vc.HelperTemplatesPanelViewSearch = {
		searchSelector: '[data-vc-templates-name-filter]',
		noTemplateSelector: '.vc-panel-no-templates-message',
		lastActiveTab: 0,
		events: {
			'keyup [data-vc-templates-name-filter]': 'searchTemplate',
			'search [data-vc-templates-name-filter]': 'searchTemplate'
		},
		uiEvents: {
			'show': 'focusToSearch'
		},
		focusToSearch: function () {
			if ( !vc.is_mobile ) {
				$( this.searchSelector, this.$el ).trigger( 'focus' );
			}
		},
		searchTemplate: function ( e ) {
			var $el = $( e.currentTarget );
			if ( $el.val().length ) {
				if ( this.$el.find( '.vc_panel-tabs-control.vc_active' ).length ) {
					this.lastActiveTab = this.$el.find( '.vc_panel-tabs-control' ).index( this.$el.find( '.vc_panel-tabs-control.vc_active' ) );
				}
				this.searchByName( $el.val() );
			} else {
				this.clearSearch();
			}
		},
		clearSearch: function () {
			this.$el.find( '[data-vc-templates-name-filter]' ).val( '' );
			this.$el.find( '[data-template_name]' ).css( 'display', 'block' );
			this.$el.removeAttr( 'data-vc-template-search' );
			this.$el.find( '.vc-search-result-empty' ).removeClass( 'vc-search-result-empty' );
			var ev = new jQuery.Event( 'click' );
			ev.isClearSearch = true;
			if ( !this.$el.find( '.vc_panel-tabs-control.vc_active' ).length ) {
				var $tabToActivate = this.$el.find( '.vc_panel-tabs-control' ).eq( this.lastActiveTab );
				$tabToActivate.addClass( 'vc_active' );
				$tabToActivate.find( '[data-vc-ui-element="panel-tab-control"]' ).trigger( ev );
			} else {
				this.$el.find( '.vc_panel-tabs-control:first [data-vc-ui-element="panel-tab-control"]' ).trigger( ev );
			}
			$( this.noTemplateSelector ).hide();
		},
		searchByName: function ( name ) {
			var hasAny = false;
			this.$el.find( '.vc_panel-tabs-control.vc_active' ).removeClass( 'vc_active' );
			this.$el.attr( 'data-vc-template-search', 'true' );
			this.$el.find( '[data-template_name]' ).css( 'display', 'none' );
			this.$el.find( '[data-template_name*="' + vc_slugify( name ) + '"]' ).css( 'display', 'block' );
			this.$el.find( '[data-vc-ui-element="panel-edit-element-tab"]' ).each( function () {
				var $el = $( this );
				$el.removeClass( 'vc-search-result-empty' );
				if ( !$el.find( '[data-template_name]:visible' ).length ) {
					$el.addClass( 'vc-search-result-empty' );
				} else {
					hasAny = true;
				}
			});
			if ( !hasAny ) {
				$( this.noTemplateSelector ).show();
			} else {
				$( this.noTemplateSelector ).hide();
			}
		}
	};
})( window.jQuery );
