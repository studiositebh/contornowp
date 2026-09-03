/**
 * Add Element panel view for the frontend editor.
 * Extends the backend editors Add Element panel view.
 */

/* global vc, i18nLocale */
( function ( $ ) {
	'use strict';

	window.vc.AddElementUIPanelFrontendEditor = vc.AddElementUIPanelBackendEditor
		.vcExtendUI( vc.HelperPanelViewHeaderFooter )
		.extend({
			events: {
				'click [data-vc-ui-element="button-close"]': 'hide',
				'touchstart [data-vc-ui-element="button-close"]': 'hide',
				'keyup #vc_elements_name_filter': 'handleFiltering',
				'search #vc_elements_name_filter': 'handleFiltering',
				'cut #vc_elements_name_filter': 'handleFiltering',
				'paste #vc_elements_name_filter': 'handleFiltering',
				'click .vc_shortcode-link': 'createElement',
				'click [data-vc-ui-element="panel-tab-control"]': 'changeTab'
			},
			createElement: function ( e ) {
				var options;
				if ( e && e.preventDefault ) {
					e.preventDefault();
				}

				var showSettings,
					newData,
					i,
					shortcode,
					columnParams,
					rowParams,
					innerRowParams,
					innerColumnParams,
					$control,
					tag,
					preset,
					presetType,
					closestPreset;

				$control = $( e.currentTarget );
				tag = $control.data( 'tag' );

				rowParams = {};

				columnParams = { width: '1/1' };

				closestPreset = $control.closest( '[data-preset]' );
				if ( closestPreset ) {
					preset = closestPreset.data( 'preset' );
					presetType = closestPreset.data( 'element' );
				}

				if ( this.prepend ) {
					window.vc.activity = 'prepend';
				}
				if ( false == this.model ) {
					if ( 'vc_section' === tag ) {
						var modelOptions = {
							shortcode: tag
						};
						if ( preset && 'vc_section' === presetType ) {
							modelOptions.preset = preset;
						}
						this.builder.create( modelOptions );
						this.model = this.builder.last();
					} else {
						var rowOptions = {
							shortcode: 'vc_row',
							params: rowParams
						};
						if ( preset && 'vc_row' === presetType ) {
							rowOptions.preset = preset;
						}
						this.builder.create( rowOptions );

						var columnOptions = {
							shortcode: 'vc_column',
							parent_id: this.builder.lastID(),
							params: columnParams
						};
						if ( preset && 'vc_column' === presetType ) {
							columnOptions.preset = preset;
						}
						this.builder.create( columnOptions );

						if ( 'vc_row' !== tag ) {
							options = {
								shortcode: tag,
								parent_id: this.builder.lastID()
							};

							if ( preset && presetType === tag ) {
								options.preset = preset;
							}

							this.builder.create( options );
						}
						this.model = this.builder.last();
					}
				} else {
					if ( 'vc_row' === tag ) {
						if ( 'vc_section' === this.model.get( 'shortcode' ) ) {
							// we can add real row!
							this.builder.create({
								shortcode: 'vc_row',
								params: rowParams,
								parent_id: this.model.id,
								order: ( this.prepend ? this.getFirstPositionIndex() : vc.shortcodes.nextOrder() )
							}).create({
								shortcode: 'vc_column',
								params: columnParams,
								parent_id: this.builder.lastID()
							});

							this.model = this.builder.last();
						} else {
							// we can add only row_inner!
							innerRowParams = {};

							innerColumnParams = { width: '1/1' };

							this.builder.create({
								shortcode: 'vc_row_inner',
								params: innerRowParams,
								parent_id: this.model.id,
								order: ( this.prepend ? this.getFirstPositionIndex() : vc.shortcodes.nextOrder() )
							}).create({
								shortcode: 'vc_column_inner',
								params: innerColumnParams,
								parent_id: this.builder.lastID()
							});

							this.model = this.builder.last();
						}
					} else {
						options = {
							shortcode: tag,
							parent_id: this.model.id,
							order: ( this.prepend ? this.getFirstPositionIndex() : vc.shortcodes.nextOrder() )
						};

						if ( preset && presetType === tag ) {
							options.preset = preset;
						}
						this.builder.create( options );
						this.model = this.builder.last();
					}
				}

				// extend default params with settings presets if there are any
				for ( i = this.builder.models.length - 1; i >= 0; i -- ) {
					// TODO: check if shortcode is used
					// eslint-disable-next-line no-unused-vars
					shortcode = this.builder.models[ i ].get( 'shortcode' );
				}

				if ( _.isString( vc.getMapped( tag ).default_content ) && vc.getMapped( tag ).default_content.length ) {
					newData = this.builder.parse({},
						window.vc.getMapped( tag ).default_content,
						this.builder.last().toJSON() );
					_.each( newData, function ( object ) {
						object.default_content = true;
						this.builder.create( object );
					}, this );
				}

				if ( ! this.model ) {
					this.model = this.builder.last();
				}
				window.vc.latestAddedElement = this.model;

				showSettings = !( _.isBoolean( vc.getMapped( tag ).show_settings_on_create ) && false === vc.getMapped(
					tag ).show_settings_on_create );

				this.hide();

				if ( showSettings ) {
					// showEditForm call window.vc.edit_element_block_view.render( this.model );
					// window.vc.edit_element_block_view is set equal to EditElementUIPanel() in editors/frontend_editor/build.js
					// EditElementUIPanel is located in editors/ui/edit-element/vc_ui-panel-edit-element.js
					// EditElementUIPanel is set equal to EditElementPanelView which is equal to PanelView
					// this.model should be available in the render method of EditElementPanelView
					this.showEditForm();
				}

				// this.builder is equal to vc.ShortcodesBuilder constructor from frontend_editor/shortcodes_builder.js
				this.builder.render( null, this.model );
			}
		});
})( window.jQuery );

