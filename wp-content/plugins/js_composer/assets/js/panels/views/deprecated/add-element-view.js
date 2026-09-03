/**
 * AddElementBlockView provides the modal interface for adding new elements to the editor.
 *
 * It manages element selection, filtering, and creation with proper nesting (e.g. rows, columns).
 * The view extends ModalView and integrates with ShortcodesBuilder to create and manage elements.
 *
 * @deprecated 4.7
 *
 */

( function ( $ ) {
	'use strict';
	if ( _.isUndefined( window.vc ) ) {
		window.vc = {};
	}

	vc.AddElementBlockView = vc.ModalView.extend({
		el: $( '#vc_add-element-dialog' ),
		prepend: false,
		builder: '',
		events: {
			'click .vc_shortcode-link': 'createElement',
			'keyup #vc_elements_name_filter': 'filterElements',
			'hidden.bs.modal': 'hide',
			'show.bs.modal': 'buildFiltering',
			'click .wpb-content-layouts-container [data-filter]': 'filterElements',
			'shown.bs.modal': 'shown'
		},
		buildFiltering: function () {
			this.do_render = false;
			var itemSelector, tag, notIn;

			itemSelector = '[data-vc-ui-element="add-element-button"]';
			tag = this.model ? this.model.get( 'shortcode' ) : 'vc_column';
			notIn = this._getNotIn( tag );
			$( '#vc_elements_name_filter' ).val( '' );
			this.$content.addClass( 'vc_filter-all' );
			this.$content.attr( 'data-vc-ui-filter', '*' );
			// New vision
			var mapped = vc.getMapped( tag );
			var asParent = tag && !_.isUndefined( mapped.as_parent ) ? mapped.as_parent : false;
			if ( _.isObject( asParent ) ) {
				var parentSelector = [];
				if ( _.isString( asParent.only ) ) {
					parentSelector.push( _.reduce( asParent.only.replace( /\s/, '' ).split( ',' ),
						function ( memo, val ) {
							return memo + ( _.isEmpty( memo ) ? '' : ',' ) + '[data-element="' + val.trim() + '"]';
						},
						'' ) );
				}
				if ( _.isString( asParent.except ) ) {
					parentSelector.push( _.reduce( asParent.except.replace( /\s/, '' ).split( ',' ),
						function ( memo, val ) {
							return memo + ':not([data-element="' + val.trim() + '"])';
						},
						'' ) );
				}
				itemSelector += parentSelector.join( ',' );
			} else {
				if ( notIn ) {
					itemSelector = notIn;
				}
			}
			// OLD fashion
			if ( tag && !_.isUndefined( mapped.allowed_container_element ) ) {
				if ( !mapped.allowed_container_element ) {
					itemSelector += ':not([data-is-container=true])';
				} else if ( _.isString( mapped.allowed_container_element ) ) {
					itemSelector += ':not([data-is-container=true]), [data-element=' + mapped.allowed_container_element + ']';
				}
			}
			this.$buttons.removeClass( 'vc_visible' ).addClass( 'vc_inappropriate' );
			$( itemSelector, this.$content ).removeClass( 'vc_inappropriate' ).addClass( 'vc_visible' );
			this.hideEmptyFilters();
		},
		hideEmptyFilters: function () {
			this.$el.find( '.vc_filter-content-elements .active' ).removeClass( 'active' );
			this.$el.find( '.vc_filter-content-elements > :first' ).addClass( 'active' );
			var self = this;
			this.$el.find( '[data-filter]' ).each( function () {
				if ( $( $( this ).data( 'filter' ) + '.vc_visible:not(.vc_inappropriate)', self.$content ).length ) {
					$( this ).parent().show();
				} else {
					$( this ).parent().hide();
				}
			});
		},
		render: function ( model, prepend ) {
			this.builder = new vc.ShortcodesBuilder();
			this.prepend = _.isBoolean( prepend ) ? prepend : false;
			this.place_after_id = _.isString( prepend ) ? prepend : false;
			this.model = _.isObject( model ) ? model : false;
			this.$content = this.$el.find( '[data-vc-ui-element="panel-add-element-list"]' );
			this.$buttons = $( '[data-vc-ui-element="add-element-button"]', this.$content );
			this.preventDoubleExecution = false;
			return vc.AddElementBlockView.__super__.render.call( this );
		},
		hide: function () {
			if ( this.do_render ) {
				if ( this.show_settings ) {
					this.showEditForm();
				}
				this.exit();
			}
		},
		showEditForm: function () {
			vc.edit_element_block_view.render( this.builder.last() );
		},
		exit: function () {
			this.builder.render();
		},
		createElement: function ( e ) {
			var $control, tag, rowParams, columnParams, rowInnerParams;
			var _this, shortcode, i;
			if ( this.preventDoubleExecution ) {
				return;
			}
			this.preventDoubleExecution = true;
			this.do_render = true;
			e.preventDefault();
			$control = $( e.currentTarget );
			tag = $control.data( 'tag' );

			rowParams = {};

			rowInnerParams = {};

			columnParams = { width: '1/1' };

			if ( false === this.model && 'vc_row' !== tag ) {
				this.builder
					.create({
						shortcode: 'vc_row',
						params: rowParams
					})
					.create({
						shortcode: 'vc_column',
						parent_id: this.builder.lastID(),
						params: columnParams
					});
				this.model = this.builder.last();
			} else if ( false !== this.model && 'vc_row' === tag ) {
				tag += '_inner';
			}
			var params = {
				shortcode: tag,
				parent_id: ( this.model ? this.model.get( 'id' ) : false ),
				params: 'vc_row_inner' === tag ? rowInnerParams : {}
			};
			if ( this.prepend ) {
				params.order = 0;
				var shortcodeFirst = vc.shortcodes.findWhere({ parent_id: this.model.get( 'id' ) });
				if ( shortcodeFirst ) {
					params.order = shortcodeFirst.get( 'order' ) - 1;
				}
				vc.activity = 'prepend';
			} else if ( this.place_after_id ) {
				params.place_after_id = this.place_after_id;
			}

			this.builder.create( params );

			// extend default params with settings presets if there are any
			for ( i = this.builder.models.length - 1;
				i >= 0;
				i -- ) {
				// TODO: check if shortcode is used
				// eslint-disable-next-line no-unused-vars
				shortcode = this.builder.models[ i ].get( 'shortcode' );
			}

			if ( 'vc_row' === tag ) {
				this.builder.create({
					shortcode: 'vc_column',
					parent_id: this.builder.lastID(),
					params: columnParams
				});
			} else if ( 'vc_row_inner' === tag ) {
				columnParams = { width: '1/1' };

				this.builder.create({
					shortcode: 'vc_column_inner',
					parent_id: this.builder.lastID(),
					params: columnParams
				});
			}
			var mapped = vc.getMapped( tag );
			if ( _.isString( mapped.default_content ) && mapped.default_content.length ) {
				var newData = this.builder.parse({},
					mapped.default_content,
					this.builder.last().toJSON() );
				_.each( newData, function ( object ) {
					object.default_content = true;
					this.builder.create( object );
				}, this );
			}
			this.show_settings = !( _.isBoolean( mapped.show_settings_on_create ) && false === mapped.show_settings_on_create );
			_this = this;
			this.$el.one( 'hidden.bs.modal', function () {
				_this.preventDoubleExecution = false;
			}).modal( 'hide' );
		},
		_getNotIn: _.memoize( function ( tag ) {
			var selector = _.reduce( vc.map, function ( memo, shortcode ) {
				var separator = _.isEmpty( memo ) ? '' : ',';
				if ( _.isObject( shortcode.as_child ) ) {
					if ( _.isString( shortcode.as_child.only ) ) {
						if ( !_.contains( shortcode.as_child.only.replace( /\s/, '' ).split( ',' ), tag ) ) {
							memo += separator + '[data-element=' + shortcode.base + ']';
						}
					}
					if ( _.isString( shortcode.as_child.except ) ) {
						if ( _.contains( shortcode.as_child.except.replace( /\s/, '' ).split( ',' ), tag ) ) {
							memo += separator + '[data-element=' + shortcode.base + ']';
						}
					}
				} else if ( false === shortcode.as_child ) {
					memo += separator + '[data-element=' + shortcode.base + ']';
				}
				return memo;
			}, '' );
			return '[data-vc-ui-element="add-element-button"]:not(' + selector + ')';
		}),
		filterElements: function ( e ) {
			e.stopPropagation();
			e.preventDefault();
			var $control = $( e.currentTarget ),
				filter = '[data-vc-ui-element="add-element-button"]',
				nameFilter = $( '#vc_elements_name_filter' ).val();
			this.$content.removeClass( 'vc_filter-all' );
			if ( $control.is( '[data-filter]' ) ) {
				$( '.wpb-content-layouts-container .isotope-filter .active', this.$content ).removeClass( 'active' );
				$control.parent().addClass( 'active' );
				var filterValue = $control.data( 'filter' );
				filter += filterValue;
				if ( '*' === filterValue ) {
					this.$content.addClass( 'vc_filter-all' );
				} else {
					this.$content.removeClass( 'vc_filter-all' );
				}
				this.$content.attr( 'data-vc-ui-filter', filterValue.replace( '.js-category-', '' ) );
				$( '#vc_elements_name_filter' ).val( '' );
			} else if ( 0 < nameFilter.length ) {
				filter += ':containsi("' + nameFilter + '"):not(".vc_element-deprecated")';
				$( '.wpb-content-layouts-container .isotope-filter .active', this.$content ).removeClass( 'active' );
				this.$content.attr( 'data-vc-ui-filter', 'name:' + nameFilter );
			} else if ( !nameFilter.length ) {
				$( '.wpb-content-layouts-container .isotope-filter [data-filter="*"]' ).parent().addClass( 'active' );
				this.$content.attr( 'data-vc-ui-filter', '*' );
				this.$content.addClass( 'vc_filter-all' );
			}
			$( '.vc_visible', this.$content ).removeClass( 'vc_visible' );
			$( filter, this.$content ).addClass( 'vc_visible' );
		},
		shown: function () {
			if ( !vc.is_mobile ) {
				$( '#vc_elements_name_filter' ).trigger( 'focus' );
			}
		}
	});
})( window.jQuery );
