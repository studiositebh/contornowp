/**
 * AddElementBlockViewBackendEditor extends the base AddElementBlockView to handle element creation in the backend editor.
 *
 * It overrides the element creation logic to work with the backend shortcode model and maintains proper parent-child relationships
 * for nested elements (rows, columns, inner elements). This view is initialized when adding elements in the backend editor interface.
 *
 * @deprecated 4.7
 *
 */

( function ( $ ) {
	'use strict';
	if ( _.isUndefined( window.vc ) ) {
		window.vc = {};
	}

	vc.AddElementBlockViewBackendEditor = vc.AddElementBlockView.extend({
		render: function ( model, prepend ) {
			this.prepend = _.isBoolean( prepend ) ? prepend : false;
			this.place_after_id = _.isString( prepend ) ? prepend : false;
			this.model = _.isObject( model ) ? model : false;
			this.$content = this.$el.find( '[data-vc-ui-element="panel-add-element-list"]' );
			this.$buttons = $( '[data-vc-ui-element="add-element-button"]', this.$content );
			return vc.AddElementBlockView.__super__.render.call( this );
		},
		createElement: function ( e ) {
			var that, shortcode, rowParams, columnParams, rowInnerParams, columnInnerParams;
			if ( this.preventDoubleExecution ) {
				return;
			}
			this.preventDoubleExecution = true;
			var model, column, row;
			if ( e && e.preventDefault ) {
				e.preventDefault();
			}
			this.do_render = true;
			var tag = $( e.currentTarget ).data( 'tag' );

			rowParams = {};

			columnParams = { width: '1/1' };

			if ( false === this.model ) {
				row = vc.shortcodes.create({
					shortcode: 'vc_row',
					params: rowParams
				});

				column = vc.shortcodes.create({
					shortcode: 'vc_column',
					params: columnParams,
					parent_id: row.id,
					root_id: row.id
				});
				if ( 'vc_row' !== tag ) {
					model = vc.shortcodes.create({
						shortcode: tag,
						parent_id: column.id,
						root_id: row.id
					});
				} else {
					model = row;
				}
			} else {
				if ( 'vc_row' === tag ) {
					rowInnerParams = {};

					columnInnerParams = { width: '1/1' };

					row = vc.shortcodes.create({
						shortcode: 'vc_row_inner',
						params: rowInnerParams,
						parent_id: this.model.id,
						order: ( this.prepend ? this.getFirstPositionIndex() : vc.shortcodes.getNextOrder() )
					});
					model = vc.shortcodes.create({
						shortcode: 'vc_column_inner',
						params: columnInnerParams,
						parent_id: row.id,
						root_id: row.id
					});
				} else {
					model = vc.shortcodes.create({
						shortcode: tag,
						parent_id: this.model.id,
						order: ( this.prepend ? this.getFirstPositionIndex() : vc.shortcodes.getNextOrder() ),
						root_id: this.model.get( 'root_id' )
					});
				}
			}
			this.show_settings = !( _.isBoolean( vc.getMapped( tag ).show_settings_on_create ) && false === vc.getMapped(
				tag ).show_settings_on_create );
			this.model = model;

			// extend default params with settings presets if there are any
			// TODO: check if shortcode is used
			// eslint-disable-next-line no-unused-vars
			shortcode = this.model.get( 'shortcode' );

			that = this;
			this.$el.one( 'hidden.bs.modal', function () {
				that.preventDoubleExecution = false;
			}).modal( 'hide' );
		},
		showEditForm: function () {
			vc.edit_element_block_view.render( this.model );
		},
		exit: function () {
		},
		getFirstPositionIndex: function () {
			vc.element_start_index -= 1;
			return vc.element_start_index;
		}
	});
})( window.jQuery );
