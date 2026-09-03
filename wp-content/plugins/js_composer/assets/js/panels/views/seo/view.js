/**
 * PostSettingsSeoUIPanelView handles SEO settings for pages and posts in the editor.
 *
 * It manages SEO-related form data and saves it as JSON in a hidden input field.
 */

( function ( $ ) {
	'use strict';
	if ( _.isUndefined( window.vc ) ) {
		window.vc = {};
	}

	vc.PostSettingsSeoUIPanelView = vc.PanelView.extend({
		save: function () {
			var $form = $( '#vc_setting-seo-form' );
			var seoData = $form.serializeArray();
			var customFormat = {};
			var seoStorageData = vc.seo_storage.get( 'formDataPrevious' ) || {};

			for ( var i = 0; i < seoData.length; i++ ) {
				var name = seoData[i].name;
				var value = seoData[i].value;
				try {
					// Validate JSON format by attempting to stringify the value
					JSON.stringify({ [name]: value });
					customFormat[name] = value;

					if ( seoStorageData && seoStorageData.hasOwnProperty( name ) || name === 'focus-keyphrase' ) {
						vc.seo_storage.setResults( value, name, 'formDataPrevious' );
					}
				} catch ( error ) {
					console.warn( `Invalid JSON format for field: ${name}`, error );
					continue; // Skip invalid fields
				}
			}

			var $seoSettingsHiddenInput = $( '#vc_post-custom-seo-settings' );
			$seoSettingsHiddenInput.val( JSON.stringify( customFormat ) );

			this.trigger( 'save' );
			window.vc.showMessage( window.i18nLocale.seo_settings_updated, 'success' );
		}
	});

})( window.jQuery );
