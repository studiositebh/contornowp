/* =========================================================
 * Copyright 2023 Wpbakery
 *
 * WPBakery Page Builder SEO panel in the navbar
 *
 * ========================================================= */
/* global vc, i18nLocale, Backbone */
if ( !window.vc ) {
	window.vc = {};
}

( function ( $ ) {
	'use strict';

	var storagePrefix = 'formData';
	var previousStatePrefix = 'formDataPrevious';

	window.vc.PostSettingsSeoUIPanel = vc.PostSettingsSeoUIPanelView
		.vcExtendUI( vc.HelperPanelViewHeaderFooter )
		.vcExtendUI( vc.HelperPanelViewResizable )
		.vcExtendUI( vc.HelperPanelViewDraggable )
		.extend({
			el: '#vc_ui-panel-post-seo',
			panelName: 'post_seo',
			events: {
				'click [data-vc-ui-element="button-close"]': 'hide',
				'touchstart [data-vc-ui-element="button-close"]': 'hide',
				'click [data-vc-ui-element="panel-tab-control"]': 'changeTab',
				'click [data-vc-ui-element="button-save"]': 'save',
				'click [data-vc-ui-element="button-minimize"]': 'toggleOpacity',
				'change #vc_ui-seo-social .gallery_widget_attached_images_ids': 'updateImagePreview',
				'input #social-title-x, #social-title-facebook': 'updateTitlePreview',
				'input #social-description-x, #social-description-facebook': 'updateDescriptionPreview',
				'click #preview-dots, #vc_seo-title, #vc_description-container': 'focusTarget',
				'change .vc-preview-radio input[type="radio"]': 'changePreviewMode',
				'input #vc_seo-title-field, #vc_seo-description-field, #vc_seo-slug-field': 'updateGeneralPreviewText',
				'blur #vc_seo-title-field, #vc_seo-description-field': 'fillSocialInputs',
				'change #vc_focus-keyphrase-field, #vc_seo-title-field, #vc_seo-description-field, #vc_seo-slug-field, #social-title-facebook, #social-description-facebook, #social-title-x, #social-description-x': 'handleInputChange'
			},
			initialize: function () {
				_.bindAll( this,
					'fixElContainment',
					'setSize' );
				this.on( 'setSize', this.setResize, this );
				this.setFormDataState();
			},
			render: function () {
				if ( this.$el.is( ':hidden' ) ) {
					vc.closeActivePanel();
				}
				vc.active_panel = this;
				this.show();
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
				var $tabs = this.$el.find( '.vc_panel-tab' );
				if ( $tabs.length ) {
					this.$tabs = $tabs;
				}
			},
			hide: function ( e ) {
				if ( this.isSeoSettingsChanged() ) {
					var isModalConfirmed = confirm( window.i18nLocale.page_settings_confirm );
					if ( isModalConfirmed ) {
						vc.PanelView.prototype.hide.call( this, e );
						this.rollBackChanges();
					} else {
						return;
					}
				} else {
					vc.PanelView.prototype.hide.call( this, e );
				}

				vc.PanelView.prototype.hide.call( this, e );
				this.trigger( 'hide' );
			},
			isSeoSettingsChanged: function () {
				var formData = vc.seo_storage.get( storagePrefix );
				var formDataPrevious = vc.seo_storage.get( previousStatePrefix );

				return !_.isEqual( formData, formDataPrevious );
			},
			rollBackChanges: function () {
				var formDataPrevious = vc.seo_storage.get( previousStatePrefix );
				var $form = $( '#vc_setting-seo-form' );

				for ( var key in formDataPrevious ) {
					if ( formDataPrevious.hasOwnProperty( key ) ) {
						// Reset the current formData to the previous state
						vc.seo_storage.setResults( formDataPrevious[key], key, storagePrefix );
						var fieldName = key;
						if ( key === 'keyphrase' ) {
							fieldName = 'focus-keyphrase';
						}
						$form.find( '[name="' + fieldName + '"]' ).val( formDataPrevious[key]);
					}
				}
			},
			changeTab: function ( e ) {
				e.preventDefault();
				var $control = $( e.currentTarget );
				var $parent = $control.parent();

				$parent.parent().find( '[data-vc-ui-element="panel-add-element-tab"].vc_active' ).removeClass( 'vc_active' );
				$parent.addClass( 'vc_active' );

				this.$tabs.filter( '.vc_active' ).removeClass( 'vc_active' );
				var activeIndex = $parent.data( 'tabIndex' );
				this.$tabs.filter( '[data-tab-index="' + activeIndex + '"]' ).addClass( 'vc_active' );
			},
			updateImagePreview: function ( e ) {
				var $control = $( e.currentTarget );
				var wrapper = $control.closest( '.edit_form_line' );
				var src = wrapper.find( '.inner img' ).attr( 'src' );
				var socialNetSlug = wrapper.attr( 'data-social-net-preview-slug' );

				if ( socialNetSlug && src ) {
					src = src.replace( '-150x150', '' );
					var preview = $( '#' + socialNetSlug );
					var image = preview.find( 'img' );
					image.attr( 'src', src );
					image.show();
					preview.find( '.wpb-social-placeholder-image' ).hide();
				}
			},
			updateTitlePreview: function ( e ) {
				var $control = $( e.currentTarget );
				var wrapper = $control.closest( '.vc_seo-social-block' );
				wrapper.find( '.wpb-social-net-preview .vc_social-title' ).text( $control.val() );
			},
			updateDescriptionPreview: function ( e ) {
				var $control = $( e.currentTarget );
				var wrapper = $control.closest( '.vc_seo-social-block' );
				var value = $control.val();
				wrapper.find( '.wpb-social-net-preview .vc_social-description' ).text( value );
				wrapper.find( '.vc_social-description-counter' ).text( value.length );
			},
			focusTarget: function ( e ) {
				var target = $( e.currentTarget ).data( 'focus' );
				$( '#' + target ).focus();
			},
			changePreviewMode: function ( e ) {
				var pagePreview = this.$el.find( '.page-preview' );
				if ( 'mobile' === $( e.currentTarget ).val() ) {
					pagePreview.removeClass( 'desktop-view' );
				} else {
					pagePreview.addClass( 'desktop-view' );
				}
			},
			updateGeneralPreviewText: function ( e ) {
				var value = $( e.currentTarget ).val();
				if ( 'vc_seo-slug-field' === $( e.currentTarget ).attr( 'id' ) ) {
					value = window.vc.utils.slugify( value );
					this.updatePostSettingsSlug( value );
				}
				var previewElementId = $( e.currentTarget ).data( 'preview' );
				var previewElement = this.$el.find( '#' + previewElementId );
				if ( previewElement ) {
					previewElement.text( value );
				}
			},
			updatePostSettingsSlug: function ( slug ) {
				$( '#vc_post_name' ).val( slug );
				var $slugLink = $( '.wpb-post-url--slug' );

				if ( $slugLink ) {
					$slugLink.text( slug );
				}
			},
			handleInputChange: function ( e ) {
				var trimmedValue = e.target.value.trim();
				vc.seo_storage.setResults( trimmedValue, e.target.name, storagePrefix );
			},
			setFormDataState: function () {
				this.$el.find( '#vc_ui-seo-general' ).find( 'input[type="text"], textarea' ).each( function ( index, input ) {
					var inputName = $( input ).attr( 'name' );
					var inputValue = $( input ).val();
					vc.seo_storage.setResults( inputValue, inputName, storagePrefix );
					vc.seo_storage.setResults( inputValue, inputName, previousStatePrefix );
				});
				this.$el.find( '#vc_ui-seo-social' ).find( 'input[type="text"], textarea' ).each( function ( index, input ) {
					var inputName = $( input ).attr( 'name' );
					var inputValue = $( input ).val();
					vc.seo_storage.setResults( inputValue, inputName, storagePrefix );
					vc.seo_storage.setResults( inputValue, inputName, previousStatePrefix );
				});
			},
			// If empty fills social inputs with general tab data
			fillSocialInputs: function ( e ) {
				var value = $( e.currentTarget ).val();
				var name = $( e.currentTarget ).attr( 'name' );
				var formData = vc.seo_storage.get( storagePrefix );
				var fieldMap = {
					'title': [ 'social-title-x', 'social-title-facebook' ],
					'description': [ 'social-description-x', 'social-description-facebook' ]
				};

				if ( fieldMap[name]) {
					fieldMap[name].forEach( function ( field ) {
						if ( !formData[field]) {
							$( '#' + field ).val( value ).trigger( 'input' );
							vc.seo_storage.setResults( value, field, storagePrefix );
						}
					});
				}
			}
		});
})( window.jQuery );
