/**
 * PostSettingsPanelViewBackendEditor extends the base PostSettingsPanelView for the backend editor.
 *
 * It extends the save method to mark storage as changed.
 */

( function () {
	'use strict';
	if ( _.isUndefined( window.vc ) ) {
		window.vc = {};
	}

	vc.PostSettingsPanelViewBackendEditor = vc.PostSettingsPanelView.extend({
		render: function () {
			this.trigger( 'render' );
			this.trigger( 'afterRender' );
			return this;
		},
		save: function () {
			vc.PostSettingsPanelViewBackendEditor.__super__.save.call( this );
			vc.storage.isChanged = true;
		}
	});

	/**
	 * PostSettingsUIPanelBackendEditor extends the PostSettingsPanelViewBackendEditor
	 *
	 * This view is used in the backend editor to manage post settings.
	 */
	vc.PostSettingsUIPanelBackendEditor = vc.PostSettingsPanelViewBackendEditor
		.vcExtendUI( vc.HelperPanelViewHeaderFooter )
		.vcExtendUI( vc.HelperPanelViewResizable )
		.vcExtendUI( vc.HelperPanelViewDraggable )
		.vcExtendUI({
			events: function () {
				return _.extend({
					'click [data-vc-ui-element="button-update"], [data-vc-ui-element="button-publish"]': 'updatePost',
					'click [data-vc-ui-element="button-save-draft"]': 'saveDraft',
					'click #wpb-settings-preview': 'preview'
				}, window.vc.PostSettingsUIPanelBackendEditor.__super__.events );
			},
			setSize: function () {
				this.trigger( 'setSize' );
			},
			setDefaultHeightSettings: function () {
				this.$el.css( 'height', '75vh' );
			},
			preview: function () {
				this.save();
				// we wait until storage is saved.
				var checkInterval = setInterval( function () {
					if ( vc.storage.isChanged ) {
						clearInterval( checkInterval );
						document.getElementById( 'post-preview' ).click();

						var isPostPublished = jQuery( '#vc_ui-panel-post-settings [data-change-status="publish"]' ).length === 0;
						if ( ! isPostPublished ) {
							// wp save everything if post is not published and user click preview
							window.vc.pagesettingseditor = {};
						}
					}
				}, 50 );
			}
		});

})( window.jQuery );
