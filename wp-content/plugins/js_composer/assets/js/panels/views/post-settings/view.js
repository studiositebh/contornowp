/**
 * PostSettingsPanelView provides the core interface for managing page/post settings in the editor.
 *
 * It handles page settings with real-time preview, providing a unified interface
 * for managing page-level customizations.
 */

( function ( $ ) {
	'use strict';
	if ( _.isUndefined( window.vc ) ) {
		window.vc = {};
	}

	vc.PostSettingsPanelView = vc.PanelView.extend({
		events: {
			'click [data-save=true]': 'save',
			'click [data-dismiss=panel]': 'hide',
			'click [data-transparent=panel]': 'toggleOpacity',
			'mouseover [data-transparent=panel]': 'addOpacity',
			'mouseout [data-transparent=panel]': 'removeOpacity'
		},
		post_settings_editor: false,
		is_frontend_editor: false,
		eventsAdded: false,
		initialize: function () {
			this.is_frontend_editor = $( '#vc_inline-frame' ).length > 0;
			this.$settingsFields = this.$el.find( '.vc_ui-panel-content' ).find( 'input, textarea, select' );

			_.bindAll( this, 'setSize', 'fixElContainment', 'initializeCategorySelect2', 'toggleAddCategory', 'addNewCategory' );
			this.on( 'show', this.setSize, this );
			this.on( 'setSize', this.setResize, this );
			this.on( 'render', this.resetMinimize, this );
			this.on( 'afterRender', function () {
				$( '.edit-form-info' ).initializeTooltips( '.vc_column' );
				// Some event handlers not fired, via events property, so we need to bind them manually
				if ( !this.eventsAdded ) {
					this.addEvents();
				}
				this.initializeCategorySelect2();
				this.initializeTagsSelect2();
				this.setFieldValues();
			}, this );
			if ( this.isPageSettingsStatusActive() ) {
				this.render().show();
			}
		},
		render: function () {
			this.trigger( 'render' );
			this.trigger( 'afterRender' );
			return this;
		},
		setSize: function () {
			this.trigger( 'setSize' );
		},
		save: function () {
			// Handle empty categories by ensuring Uncategorized is selected
			if ( this.is_frontend_editor && $( '#vc_post-category' ).length ) {
				var $categorySelect = $( '#vc_post-category' );
				if ( !$categorySelect.val() || !$categorySelect.val().length ) {
					$categorySelect.val( '1' ).trigger( 'change' );
				}
			}

			this.showMessage( window.i18nLocale.page_settings_updated, 'success' );

			vc.storage = vc.storage || {};
			vc.storage.isChanged = true;

			this.trigger( 'save' );
		},
		show: function () {
			if ( this.$el.hasClass( 'vc_active' ) ) {
				return;
			}

			this.activatePageSettingsStatus();

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
			this.deactivatePageSettingsStatus();

			if ( this.isPageSettingsChanged() ) {
				var isModalConfirmed = confirm( window.i18nLocale.page_settings_confirm );
				if ( isModalConfirmed ) {
					vc.PanelView.prototype.hide.call( this, e );
					this.rollBackChanges();
				}
			} else {
				vc.PanelView.prototype.hide.call( this, e );
			}

			this.trigger( 'hide' );
		},
		isPageSettingsChanged: function () {
			if ( ! window.vc.pagesettingseditor ) {
				return false;
			}

			var pageSettings = window.vc.pagesettingseditor;

			for ( var id of Object.keys( pageSettings ) ) {
				if ( !_.isEqual( pageSettings[id].currentValue, pageSettings[id].previousValue ) ) {
					return true;
				}
			}

			return false;
		},
		rollBackChanges: function () {
			var pageSettings; var currentValue; var previousValue; var fieldElement; var customEvent; var id;

			pageSettings = window.vc.pagesettingseditor;
			for ( id of Object.keys( pageSettings ) ) {
				currentValue = pageSettings[id].currentValue;
				previousValue = pageSettings[id].previousValue;
				fieldElement = this.$settingsFields.filter( '#' + id );
				pageSettings[id].currentValue = previousValue;
				fieldElement.val( previousValue );
				if ( id === 'vc_featured_image' ) {
					fieldElement.next( '.gallery_widget_attached_images' ).find( '.vc_icon-remove' ).trigger( 'click' );
				}
				if ( fieldElement.attr( 'type' ) === 'checkbox' && currentValue !== previousValue ) {
					fieldElement.next( 'label' ).trigger( 'click' );
				}
			}
			customEvent = new CustomEvent( 'wpbPageSettingRollBack', {
				detail: pageSettings
			});
			document.dispatchEvent( customEvent );
		},
		isPageSettingsStatusActive: function () {
			var url = new URL( window.location.href );
			var hasUrlParam = url.searchParams.get( this.getPageSettingsSlug() );

			if ( hasUrlParam && $( '#vc_post-settings-button' ).length === 0 ) {
				this.deactivatePageSettingsStatus();
				return false;
			}

			return hasUrlParam;
		},
		getPageSettingsSlug: function () {
			return 'vc_page_settings';
		},
		activatePageSettingsStatus: function () {
			var url = new URL( window.location.href );
			url.searchParams.set( this.getPageSettingsSlug(), true );
			window.history.pushState({}, '', url );
		},
		deactivatePageSettingsStatus: function () {
			var url = new URL( window.location.href );
			url.searchParams.delete( this.getPageSettingsSlug() );
			window.history.pushState({}, '', url );
		},
		updatePost: function ( e ) {
			// save settings, call this.save() method from the parent view vc.PostSettingsPanelView;
			this.save();
			if ( window.vc_mode === 'admin_page' ) {
				// update/publish post by triggering a click on the WP publish button.
				// needed to properly handle the vc.storage.isChanged value.
				$( '#publish' ).trigger( 'click' );
			} else {
				var $postStatusElement = $( '#vc_post_status' );
				var postStatusValue = $( e.currentTarget ).data( 'changeStatus' );
				if ( $postStatusElement.length ) {
					postStatusValue = $( '#vc_post_status' ).val();
				}
				// save post, call window.vc.ShortcodesBuilder.save();
				vc.builder.save( postStatusValue, true );
			}
		},
		saveDraft: function () {
			this.save();
			if ( window.vc_mode === 'admin_page' ) {
				// save draft, trigger click on the WP save draft button.
				$( '#save-post' ).trigger( 'click' );
			} else {
				// first parameret should be empty, to avoid publishing
				vc.builder.save( '', true );
			}
		},
		handlePostNameInput: function ( e ) {
			var $slug = $( '.wpb-post-url--slug' );

			if ( $slug ) {
				$slug.text( $( e.target ).val() );
			}
		},
		handlePostNameBlur: function ( e ) {
			var $target = $( e.target );
			var $slug = $( '.wpb-post-url--slug' );
			var value = $target.val();
			var slugifiedValue = window.vc.utils.slugify( value );

			$target.val( slugifiedValue );
			if ( $slug ) {
				$slug.text( slugifiedValue );
			}
		},
		addEvents: function () {
			if ( this.$el.find( '[name=post_name]' ) ) {
				this.$el.find( '[name=post_name]' ).on( 'input', this.handlePostNameInput.bind( this ) );
				this.$el.find( '[name=post_name]' ).on( 'blur', this.handlePostNameBlur.bind( this ) );
			}
			if ( this.$el.find( '#vc_toggle-add-new-category' ) ) {
				( this.$el.find( '#vc_toggle-add-new-category' ).on( 'click', this.toggleAddCategory.bind( this ) ) );
			}
			if ( this.$el.find( '#vc_add-new-category-btn' ) ) {
				( this.$el.find( '#vc_add-new-category-btn' ).on( 'click', this.addNewCategory.bind( this ) ) );
			}
			if ( this.$settingsFields ) {
				this.$settingsFields.on( 'change', this.handleSettingsChange.bind( this ) );
			}
			this.eventsAdded = true;
		},
		initializeCategorySelect2: function () {
			if ( this.is_frontend_editor ) {
				$( '#vc_post-category' ).select2({
					width: '100%',
					closeOnSelect: false,
					allowHtml: true,
					templateResult: function ( data ) {
						if ( !data.id ) { return data.text; }
						var $result = $( '<span class="wpb_select2-option-checkbox"></span>' );
						var text = data.text.replace( /^\s+/, '' );
						var indentation = data.text.match( /^\s+/ );
						if ( indentation && indentation[ 0 ]) {
							$result.html( indentation[ 0 ].replace( /\s/g, '&nbsp;' ) );
						}
						var $textSpan = $( '<span class="option-text"></span>' ).text( text );
						$result.append( $textSpan );
						return $result;
					},
					templateSelection: function ( data ) {
						return data.text.replace( /^\s+/, '' );
					}
				});
			}
		},
		initializeTagsSelect2: function () {
			if ( this.is_frontend_editor ) {
				$( '#vc_post-tags' ).select2({
					width: '100%',
					tags: true,
					tokenSeparators: [ ',' ],
					minimumInputLength: 1,
					ajax: {
						url: window.ajaxurl,
						type: 'POST',
						data: function ( params ) {
							return {
								action: 'vc_get_tags',
								_vcnonce: window.vcAdminNonce,
								search: params.term // user-typed term as 'search'
							};
						},
						processResults: function ( response ) {
							if ( !response.success ) {
								console.error( 'Invalid response from server: ', response.data.message );
								vc.showMessage( response.data.message, 'error' );
								return { results: [] }; // Return an empty result set
							}
							return {
								results: response.data.map( function ( tag ) {
									return { id: tag.id, text: tag.name };
								})
							};
						}
					}
				});
			}
		},
		toggleAddCategory: function () {
			var container = $( '#vc_add-new-category' );
			container.toggle();
		},
		addNewCategory: function ( e ) {
			e.preventDefault();
			var newCategoryName = $( '#vc_new-category' ).val();
			if ( !newCategoryName ) {
				return;
			}

			var parentCategoryId = $( '#vc_new-category-parent' ).val();
			$.ajax({
				url: window.ajaxurl,
				type: 'POST',
				data: {
					action: 'vc_create_new_category',
					category_name: newCategoryName,
					'vc_new-category-parent': parentCategoryId,
					_vcnonce: window.vcAdminNonce
				},
				success: function ( response ) {
					if ( response.success ) {
						var categoryIdExists = $( '#vc_post-category option[value="' + response.data.id + '"]' ).length > 0;
						if ( !categoryIdExists ) {
							var $newOption = $( '<option></option>' )
								.val( response.data.id )
								.text( response.data.name )
								.prop( 'selected', true );
							$( '#vc_post-category' ).prepend( $newOption );
							$( '#vc_post-category' ).trigger( 'change' );
						}
						$( '#vc_new-category' ).val( '' );
						$( '#vc_new-category-parent' ).val( '' );
					} else {
						console.error( 'Error adding category:', response.data.message );
						vc.showMessage( response.data.message, 'error' );
					}
				}
			});
		},
		handleSettingsChange: function ( e ) {
			var $target = $( e.currentTarget );
			if ( !window.vc.pagesettingseditor ) {
				window.vc.pagesettingseditor = {};
			}
			// custom layout change is handled in post-custom-layout module's file.
			if ( !$target.attr( 'data-post-custom-layout' ) ) {
				var value = $target.val();
				if ( $target.attr( 'type' ) === 'checkbox' ) {
					value = $target.is( ':checked' );
				}
				if ( window.vc.pagesettingseditor[$target.attr( 'id' )]) {
					window.vc.pagesettingseditor[$target.attr( 'id' )] = {
						previousValue: window.vc.pagesettingseditor[$target.attr( 'id' )].previousValue,
						currentValue: value
					};
				}
			}
		},
		setFieldValues: function () {
			if ( !window.vc.pagesettingseditor ) {
				window.vc.pagesettingseditor = {};
			}
			this.$settingsFields.each( function () {
				var $field = $( this );
				var value = $field.val();
				if ( $field.attr( 'type' ) === 'checkbox' ) {
					value = $field.is( ':checked' );
				}
				window.vc.pagesettingseditor[$field.attr( 'id' )] = {
					previousValue: value,
					currentValue: value
				};
			});
		}
	});

	/**
	 * PostSettingsUIPanelFrontendEditor extends the base PostSettingsPanelView for the frontend editor.
	 *
	 * It provides a user interface for managing page/post settings with features like
	 * resizable and draggable panels, real-time preview, and custom event handling.
	 */
	vc.PostSettingsUIPanelFrontendEditor = vc.PostSettingsPanelView
		.vcExtendUI( vc.HelperPanelViewHeaderFooter )
		.vcExtendUI( vc.HelperPanelViewResizable )
		.vcExtendUI( vc.HelperPanelViewDraggable )
		.vcExtendUI({
			panelName: 'post_settings',
			previewTab: null,
			events: function () {
				return _.extend({
					'click [data-vc-ui-element="button-update"], [data-vc-ui-element="button-publish"], [data-vc-ui-element="button-submit-review"]': 'updatePost',
					'click [data-vc-ui-element="button-save-draft"]': 'saveDraft',
					'click #wpb-settings-preview': 'preview'
				}, window.vc.PostSettingsUIPanelFrontendEditor.__super__.events );
			},
			// Fix old editor call for resize.
			setSize: function () {
				this.trigger( 'setSize' );
			},
			setDefaultHeightSettings: function () {
				this.$el.css( 'height', '75vh' );
			},
			preview: function ( e ) {
				this.save();

				var checkInterval = setInterval( function () {
					if ( vc.storage.isChanged ) {
						clearInterval( checkInterval );

						var data = vc.builder.getPostData();

						data.action = 'vc_preview';

						vc.builder.ajax( data )
							.done( function ( response ) {
								response = JSON.parse( response );

								if ( response.success ) {
									var previewButtonLink = e.target.getAttribute( 'data-button-link' );
									if ( previewButtonLink ) {
										if ( this.previewTab && !this.previewTab.closed ) {
											this.previewTab.location.reload();
											this.previewTab.focus();
										} else {
											this.previewTab = window.open( previewButtonLink, '_blank' );
										}
									}
								} else {
									window.vc.showMessage( window.i18nLocale.preview_error + response.data, 'error' );
								}
							}).fail( function () {
								window.vc.showMessage( window.i18nLocale.preview_error, 'error' );
							});
					}
				}, 50 );
			}
		});

})( window.jQuery );
