/**
 * TemplateLibraryView is a view for managing the template library under the Templates panel.
 */

( function ( $ ) {
	'use strict';

	window.vc.TemplateLibraryView = vc.PanelView
		.vcExtendUI( vc.HelperAjax )
		.extend({
			myTemplates: [],
			$mainPopup: false,
			$loadingPage: false,
			$gridContainer: false,
			$errorMessageContainer: false,
			$myTemplateContainer: false,
			$popupItems: false,
			$previewImage: false,
			$previewTitle: false,
			$previewUpdate: false,
			$previewDownload: false,
			$previewUpdateBtn: false,
			$previewDownloadBtn: false,
			$templatePreview: false,
			$templatePage: false,
			$downloadPage: false,
			$updatePage: false,
			$content: false,
			$filter: false,
			compiledGridTemplate: false,
			compiledTemplate: false,
			loaded: false,
			data: false,
			events: {
				'click [data-dismiss=panel]': 'hide',
				'click .vc_ui-panel-close-button': 'closePopupButton',
				'click .vc_ui-access-library-btn': 'accessLibrary',
				'click #vc_template-library-template-grid .vc_ui-panel-template-preview-button': 'previewButton',
				'click .vc_ui-panel-back-button': 'backToTemplates',
				'click .vc_ui-panel-template-download-button, #vc_template-library-download-btn': 'downloadButton',
				'click .vc_ui-panel-template-update-button, #vc_template-library-update-btn': 'updateButton',
				'keyup #vc_template_lib_name_filter': 'filterTemplates',
				'search #vc_template_lib_name_filter': 'filterTemplates'
			},

			initialize: function () {
				_.bindAll( this, 'loadLibrary', 'addTemplateStatus', 'loadMyTemplates', 'deleteTemplate' );
				this.$mainPopup = this.$el.find( '.vc_ui-panel-popup' );
				this.$loadingPage = this.$el.find( '.vc_ui-panel-loading' );
				this.$gridContainer = this.$el.find( '#vc_template-library-template-grid' );
				this.$errorMessageContainer = this.$el.find( '#vc_template-library-panel-error-message' );
				this.$myTemplateContainer = this.$el.find( '#vc_template-library-shared_templates' );
				this.$popupItems = this.$el.find( '.vc_ui-panel-popup-item' );
				this.$previewImage = this.$el.find( '.vc_ui-panel-preview-image' );
				this.$previewTitle = this.$el.find( '.vc_ui-panel-template-preview .vc_ui-panel-title' );
				this.$previewUpdate = this.$el.find( '#vc_template-library-update' );
				this.$previewDownload = this.$el.find( '#vc_template-library-download' );
				this.$previewUpdateBtn = this.$previewUpdate.find( '#vc_template-library-update-btn' );
				this.$previewDownloadBtn = this.$previewUpdate.find( '#vc_template-library-download-btn' );
				this.$templatePreview = this.$el.find( '.vc_ui-panel-template-preview' );
				this.$templatePage = this.$el.find( '.vc_ui-panel-template-content' );
				this.$downloadPage = this.$el.find( '.vc_ui-panel-download' );
				this.$updatePage = this.$el.find( '.vc_ui-panel-update' );
				this.$filter = this.$el.find( '#vc_template_lib_name_filter' );
				this.$content = this.$el.find( '.vc_ui-templates-content' );
				var gridTemplateHtml = $( '#vc_template-grid-item' ).html();
				this.compiledGridTemplate = vc.template( gridTemplateHtml );
				var myTemplateHtml = $( '#vc_template-item' ).html();
				this.compiledTemplate = vc.template( myTemplateHtml );

				window.vc.events.on( 'templates:delete', this.deleteTemplate );
			},
			getLibrary: function () {
				if ( this.loaded ) {
					this.showLibrary();
					return;
				}
				this.checkAjax();
				var data = this.getStorage( 'templates' );
				var _this = this;
				if ( data && 'object' === typeof data && !_.isEmpty( data ) ) {
					this.loaded = true;
					this.loadLibrary( data );
					this.showLibrary();
				} else {
					this.ajax = $.getJSON( 'https://vc-cc-templates.wpbakery.com/templates.json' ).done( function ( data ) {
						_this.setStorage( 'templates', data );
						_this.loaded = true;
						_this.loadLibrary( data );
						_this.showLibrary();
					}).always( this.resetAjax );
				}
			},
			/**
			 * removeStorage: removes a key from localStorage and its sibling expiry key
			 * params: key <string>     : localStorage key to remove
			 * returns: <boolean> : telling if operation succeeded
			 */
			removeStorage: function ( name ) {
				try {
					localStorage.removeItem( 'vc4-' + name );
					localStorage.removeItem( 'vc4-' + name + '_expiresIn' );
				} catch ( e ) {
					console.error( 'removeStorage: Error removing key [' + name + '] from localStorage: ' + JSON.stringify( e ) );
					return false;
				}
				return true;
			},
			/**
			 *  getStorage: retrieves a key from localStorage previously set with setStorage().
			 *  params: key <string> : localStorage key
			 *  returns: <string> : value of localStorage key
			 *               null : in case of expired key or failure
			 */
			getStorage: function ( key ) {

				var now = Date.now(); // epoch time, lets deal only with integer
				// set expiration for storage
				var expiresIn = localStorage.getItem( 'vc4-' + key + '_expiresIn' );
				if ( undefined === expiresIn || null === expiresIn ) {
					expiresIn = 0;
				}

				if ( expiresIn < now ) {// Expired
					this.removeStorage( key );
					return null;
				} else {
					try {
						return JSON.parse( localStorage.getItem( 'vc4-' + key ) );
					} catch ( e ) {
						console.error( 'getStorage: Error reading key [' + key + '] from localStorage: ' + JSON.stringify( e ) );
						return null;
					}
				}
			},
			/**
			 * setStorage: writes a key into localStorage setting a expire time
			 * params: key <string>       : localStorage key
			 *         value <string>     : localStorage value
			 *         expires <number>   : number of seconds from now to expire the key
			 * returns: <boolean> : telling if operation succeeded
			 */
			setStorage: function ( key, value, expires ) {

				if ( undefined === expires || null === expires ) {
					expires = ( 24 * 60 * 60 ); // seconds for 1 day
				} else {
					expires = Math.abs( expires ); // make sure it's positive
				}

				var now = Date.now();
				var schedule = now + expires * 1000;
				try {
					localStorage.setItem( 'vc4-' + key, JSON.stringify( value ) );
					localStorage.setItem( 'vc4-' + key + '_expiresIn', schedule );
				} catch ( err ) {
					if ( window.console && window.console.warn ) {
						window.console.warn( 'template setStorage error', err );
					}
					return false;
				}
				return true;
			},
			loadLibrary: function ( data ) {
				if ( !data ) {
					return;
				}
				var renderedOutput = '';
				var _this = this;
				this.loaded = true;
				this.data = data;
				this.$filter.val( '' );
				data.forEach( function ( item ) {

					item = _this.addTemplateStatus( item );

					renderedOutput += _this.compiledGridTemplate({
						id: item.id,
						title: item.title,
						thumbnailUrl: item.thumbnailUrl,
						previewUrl: item.previewUrl,
						status: item.status,
						downloaded: _.find( _this.myTemplates, { id: item.id }),
						version: item.version
					});
				});

				this.$gridContainer.html( renderedOutput );
			},
			showLibrary: function () {
				this.$loadingPage.addClass( 'vc_ui-hidden' );
				this.$mainPopup.removeClass( 'vc_ui-hidden' );
				this.$templatePage.removeClass( 'vc_ui-hidden' );
			},
			addTemplateStatus: function ( template ) {
				var statusHtml = '';

				var myTemplate = _.find( this.myTemplates, { id: template.id });
				if ( myTemplate ) {
					var status = window.i18nLocale.ui_template_downloaded;
					if ( template.version > myTemplate.version ) {
						status = window.i18nLocale.ui_template_fupdate;
					}
					statusHtml = '<span class="vc_ui-panel-template-item-info"><span>' + status + '</span></span>';
				}
				template.status = statusHtml;
				return template;
			},
			loadMyTemplates: function () {
				var renderedOutput = '';
				var _this = this;
				this.myTemplates.forEach( function ( item ) {
					renderedOutput += _this.compiledTemplate({
						post_id: item.post_id,
						title: item.title
					});
				});

				this.$myTemplateContainer.html( renderedOutput );
			},
			closePopupButton: function ( e ) {
				if ( e && e.preventDefault ) {
					e.preventDefault();
				}
				this.$mainPopup.toggleClass( 'vc_ui-hidden' );
				this.$popupItems.addClass( 'vc_ui-hidden' );
				this.$content.removeClass( 'vc_ui-hidden' );
			},
			accessLibrary: function () {
				this.$loadingPage.removeClass( 'vc_ui-hidden' );
				this.$content.addClass( 'vc_ui-hidden' );
				this.getLibrary();
			},
			previewButton: function ( e ) {
				var $template = $( e.currentTarget );
				var imgUrl = $template.data( 'preview-url' );
				var title = $template.data( 'title' );
				var templateId = $template.data( 'template-id' );
				var templateVersion = $template.data( 'template-version' );
				this.$previewImage.attr( 'src', imgUrl );
				this.$previewTitle.text( title );
				var myTemplate = _.find( this.myTemplates, { id: templateId });
				this.$previewUpdate.toggleClass( 'vc_ui-hidden', !( myTemplate && myTemplate.version < templateVersion ) );
				this.$previewDownload.toggleClass( 'vc_ui-hidden', !!myTemplate );
				this.$previewUpdateBtn.data( 'template-id', templateId );
				this.$previewDownloadBtn.data( 'template-id', templateId );
				this.$popupItems.addClass( 'vc_ui-hidden' );
				this.$templatePreview.removeClass( 'vc_ui-hidden' );
				this.$templatePreview.attr( 'data-template-id', templateId );
			},
			backToTemplates: function () {
				this.$popupItems.addClass( 'vc_ui-hidden' );
				this.$templatePage.removeClass( 'vc_ui-hidden' );
			},
			deleteTemplate: function ( data ) {
				if ( 'shared_templates' === data.type ) {
					var index = _.findIndex( this.myTemplates, { post_id: data.id });
					if ( index !== - 1 ) {
						this.myTemplates.splice( index, 1 );
						if ( this.loaded ) {
							this.loadLibrary( this.data );
						}
					}
				}
			},
			downloadButton: function ( e ) {
				if ( e && e.preventDefault ) {
					e.preventDefault();
				}
				var id = jQuery( e.currentTarget ).closest( '[data-template-id]' ).data( 'templateId' );
				if ( id ) {
					this.showDownloadOverlay();
					this.downloadTemplate( id );
				}
			},
			updateButton: function ( e ) {
				if ( e && e.preventDefault ) {
					e.preventDefault();
				}
				var id = jQuery( e.currentTarget ).closest( '[data-template-id]' ).data( 'templateId' );
				if ( id ) {
					this.showUpdateOverlay();
					// this.updateTemplate( id );
				}
			},
			showDownloadOverlay: function () {
				this.$popupItems.addClass( 'vc_ui-hidden' );
				this.$downloadPage.removeClass( 'vc_ui-hidden' );
			},
			hideDownloadOverlay: function ( message ) {
				if ( message ) {
					this.$errorMessageContainer.html( message );
					this.$errorMessageContainer.removeClass( 'vc_ui-hidden' );
				} else {
					this.$errorMessageContainer.addClass( 'vc_ui-hidden' );
				}

				this.$downloadPage.addClass( 'vc_ui-hidden' );
				this.$templatePage.removeClass( 'vc_ui-hidden' );
			},
			showUpdateOverlay: function () {
				this.$popupItems.addClass( 'vc_ui-hidden' );
				this.$updatePage.removeClass( 'vc_ui-hidden' );
			},
			hideUpdateOverlay: function () {
				this.$updatePage.addClass( 'vc_ui-hidden' );
				this.$templatePage.removeClass( 'vc_ui-hidden' );
			},
			downloadTemplate: function ( id ) {
				this.checkAjax();
				var fail = true;
				this.ajax = $.ajax({
					type: 'POST',
					url: window.ajaxurl,
					data: {
						action: 'vc_shared_templates_download',
						id: id,
						_vcnonce: window.vcAdminNonce
					},
					dataType: 'json',
					context: this
				}).done( function ( response ) {
					if ( response && response.success ) {
						var template = _.find( this.data, { id: id });
						if ( template ) {
							fail = false;
							template.post_id = response.data.post_id;
							this.myTemplates.unshift( template );
							this.loadMyTemplates();
							this.loadLibrary( this.data );
							this.showLibrary();
						}
					}
				}).always( function ( response, status ) {
					var message = '';
					if ( 'success' !== status || fail ) {
						message = response && response.data && response.data.message ? response.data.message : window.i18nLocale.ui_templates_failed_to_download;
					}
					this.hideDownloadOverlay( message );
					this.resetAjax();
				});

			},
			filterTemplates: function () {
				var inputValue = this.$filter.val();
				var filter = '.vc_ui-panel-template-item .vc_ui-panel-template-item-name:containsi("' + inputValue + '")';

				$( '.vc_ui-panel-template-item.vc_ui-visible', this.$gridContainer ).removeClass( 'vc_ui-visible' );
				$( filter, this.$gridContainer ).closest( '.vc_ui-panel-template-item' ).addClass( 'vc_ui-visible' );
			}
		});

	$( function () {
		if ( !window.vcTemplatesLibraryData ) {
			return;
		}
		window.vc.templatesLibrary = new vc.TemplateLibraryView({ el: '[data-vc-ui-element="panel-edit-element-tab"][data-tab="shared_templates"]' });
		window.vc.templatesLibrary.myTemplates = window.vcTemplatesLibraryData.templates || [];
		window.vc.templatesLibrary.loadMyTemplates();
	});
})( window.jQuery );
