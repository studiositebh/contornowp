/**
 * CustomCodeViewBackendEditor extends the base CustomCodePanelView for the backend editor.
 *
 * It extends the save method to mark storage as changed and sets alert if custom code data differs from saved data.
 */

( function () {
	'use strict';
	if ( _.isUndefined( window.vc ) ) {
		window.vc = {};
	}

	vc.CustomCodeViewBackendEditor = vc.CustomCodePanelView.extend({
		render: function () {
			this.trigger( 'render' );
			this.trigger( 'afterRender' );
			this.setEditor();
			return this;
		},
		/**
         * Set alert if custom code data differs from saved data.
         *
         * @deprecated
         */
		setAlertOnDataChange: function () {
			if ( this.editor_css && vc.saved_custom_css !== this.editor_css.getValue() && window.tinymce ) {
				window.switchEditors.go( 'content', 'tmce' );
				window.setTimeout( function () {
					window.tinymce.get( 'content' ).isNotDirty = false;
				}, 1000 );
			}
		},
		save: function () {
			vc.CustomCodeViewBackendEditor.__super__.save.call( this );
			vc.storage.isChanged = true;
		}
	});

	vc.CustomCodeUIPanelBackendEditor = vc.CustomCodeViewBackendEditor
		.vcExtendUI( vc.HelperPanelViewHeaderFooter )
		.vcExtendUI( vc.HelperPanelViewResizable )
		.vcExtendUI( vc.HelperPanelViewDraggable )
		.vcExtendUI({
			uiEvents: {
				'setSize': 'setEditorSize',
				'show': 'setEditorSize'
			},
			events: function () {
				return _.extend({
					'click [data-vc-ui-element="panel-tab-control"]': 'changeTab'
				}, window.vc.CustomCodeUIPanelBackendEditor.__super__.events );
			},
			setSize: function () {
				this.trigger( 'setSize' );
			},
			setEditorSize: function () {
				if ( window.Vc_postSettingsEditor ) {
					this.editor_css.setSizeResizable();
					this.editor_js_header.setSizeResizable();
					this.editor_js_footer.setSizeResizable();
				}
			},
			setDefaultHeightSettings: function () {
				this.$el.css( 'height', '75vh' );
			}
		});

})( window.jQuery );
