/**
 * Add Element panel view for the backend editor.
 * Extends PanelView to provide functionality for adding elements in the Visual Composer backend editor.
 */

/* global vc, i18nLocale */
( function ( $ ) {
	'use strict';

	window.vc.element_start_index = 0;

	window.vc.AddElementUIPanelBackendEditor = vc.PanelView
		.vcExtendUI( vc.HelperPanelViewHeaderFooter )
		.vcExtendUI( vc.HelperPanelTabs )
		.extend({
			el: '#vc_ui-panel-add-element',
			searchSelector: '#vc_elements_name_filter',
			prepend: false,
			builder: '',
			events: {
				'click [data-vc-ui-element="button-close"]': 'hide',
				'touchstart [data-vc-ui-element="button-close"]': 'hide',
				'click .vc_shortcode-link': 'createElement',
				'keyup #vc_elements_name_filter': 'handleFiltering',
				'search #vc_elements_name_filter': 'handleFiltering',
				'cut #vc_elements_name_filter': 'handleFiltering',
				'paste #vc_elements_name_filter': 'handleFiltering',
				'click [data-vc-manage-elements]': 'openPresetWindow',
				'click [data-vc-ui-element="panel-tab-control"]': 'changeTab'
			},
			changeTab: function ( e ) {
				if ( e && e.preventDefault ) {
					e.preventDefault();
				}
				var tab = $( e.currentTarget );
				if ( !tab.parent().hasClass( 'vc_active' ) ) {
					this.switchActiveTab( this.$el, tab );
					this.trigger( 'tabChange' );

					if ( this.$tabsMenu ) {
						this.$tabsMenu.vcTabsLine( 'checkDropdownContainerActive' );
					}
				}

				this.handleFiltering( e );
			},
			initialize: function () {
				window.vc.AddElementUIPanelBackendEditor.__super__.initialize.call( this );
				window.vc.events.on( 'vc:savePreset', this.updateAddElementPopUp.bind( this ) );
				window.vc.events.on( 'vc:deletePreset', this.removePresetFromAddElementPopUp.bind( this ) );
			},
			render: function ( model, prepend ) {
				if ( !_.isUndefined( vc.ShortcodesBuilder ) ) {
					this.builder = new vc.ShortcodesBuilder();
				}

				if ( this.$el.is( ':hidden' ) ) {
					window.vc.closeActivePanel();
				}
				window.vc.active_panel = this;
				this.prepend = _.isBoolean( prepend ) ? prepend : false;
				this.place_after_id = _.isString( prepend ) ? prepend : false;
				this.model = _.isObject( model ) ? model : false;
				this.$content = this.$el.find( '[data-vc-ui-element="panel-add-element-list"]' );
				this.$buttons = $( '[data-vc-ui-element="add-element-button"]', this.$content );

				this.buildFiltering();

				this.$el.find( '[data-vc-ui-element="panel-tab-control"]' ).eq( 0 ).click();

				this.show();

				// must be after show()
				this.$el.find( '[data-vc-ui-element="panel-tabs-controls"]' ).vcTabsLine( 'moveTabs' );

				if ( !vc.is_mobile ) {
					$( this.searchSelector ).trigger( 'focus' );
				}

				return vc.AddElementUIPanelBackendEditor.__super__.render.call( this );
			},
			buildFiltering: function () {
				var itemSelector, tag, notIn, asParent, parentSelector;

				itemSelector = '[data-vc-ui-element="add-element-button"]';
				notIn = this._getNotIn( this.model ? this.model.get( 'shortcode' ) : '' );

				$( this.searchSelector ).val( '' );
				this.$content.addClass( 'vc_filter-all' );
				this.$content.attr( 'data-vc-ui-filter', '*' );

				tag = this.model ? this.model.get( 'shortcode' ) : 'vc_column';
				asParent = tag && !_.isUndefined( vc.getMapped( tag ).as_parent ) ? vc.getMapped( tag ).as_parent : false;

				if ( _.isObject( asParent ) ) {
					parentSelector = [];
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
				} else if ( notIn ) {
					itemSelector = notIn;
				}

				if ( false !== tag && !_.isUndefined( vc.getMapped( tag ).allowed_container_element ) ) {
					if ( false === vc.getMapped( tag ).allowed_container_element ) {
						itemSelector += ':not([data-is-container=true])';
					} else if ( _.isString( vc.getMapped( tag ).allowed_container_element ) ) {
						itemSelector += ':not([data-is-container=true]), [data-element=' + vc.getMapped( tag ).allowed_container_element + ']';
					}
				}

				this.$buttons.removeClass( 'vc_visible' ).addClass( 'vc_inappropriate' );
				$( itemSelector, this.$content ).removeClass( 'vc_inappropriate' ).addClass( 'vc_visible' );

				this.hideEmptyFilters();
			},
			hideEmptyFilters: function () {
				var _this = this;

				this.$el.find( '[data-vc-ui-element="panel-add-element-tab"].vc_active' ).removeClass( 'vc_active' );
				this.$el.find( '[data-vc-ui-element="panel-add-element-tab"]:first' ).addClass( 'vc_active' );
				this.$el.find( '[data-filter]' ).each( function () {
					if ( !$( $( this ).data( 'filter' ) + '.vc_visible:not(.vc_inappropriate)',
						_this.$content ).length ) {
						$( this ).parent().hide();
					} else {
						$( this ).parent().show();
					}
				});
			},
			_getNotIn: _.memoize( function ( tag ) {
				var selector;

				selector = _.reduce( vc.map, function ( memo, shortcode ) {
					var separator;

					separator = _.isEmpty( memo ) ? '' : ',';

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
			handleFiltering: function ( e ) {
				if ( 'cut' == e.type || 'paste' === e.type ) {
					setTimeout( function () {
						this.filterElements ( e );
					}.bind( this ), 0 );
				} else {
					if ( e ) {
						if ( e.preventDefault ) {
							e.preventDefault();
						}
						if ( e.stopPropagation ) {
							e.stopPropagation();
						}
					} else {
						e = window.event;
					}
					this.filterElements( e );
				}
			},
			filterElements: function ( e ) {
				var filterValue, $visibleElements, $control, filter, nameFilter;

				$control = $( e.currentTarget );
				filter = '[data-vc-ui-element="add-element-button"]';
				nameFilter = $( this.searchSelector ).val();

				this.$content.removeClass( 'vc_filter-all' );
				var $parent = $control.closest( '.vc_ui-tabs-line' );

				$parent.parent().find( '[data-vc-ui-element="panel-add-element-tab"].vc_active' ).removeClass( 'vc_active' );

				if ( $control.is( '[data-filter]' ) ) {
					$control.parent().addClass( 'vc_active' );

					filterValue = $control.data( 'filter' );
					filter += filterValue;

					if ( '*' === filterValue ) {
						this.$content.addClass( 'vc_filter-all' );
					} else {
						this.$content.removeClass( 'vc_filter-all' );
					}

					this.$content.attr( 'data-vc-ui-filter', filterValue.replace( '.js-category-', '' ) );

					$( this.searchSelector ).val( '' );
				} else if ( nameFilter.length ) {
					filter += ':containsi("' + nameFilter + '"):not(".vc_element-deprecated")';

					this.$content.attr( 'data-vc-ui-filter', 'name:' + nameFilter );
				} else if ( !nameFilter.length ) {
					$( '[data-vc-ui-element="panel-tab-control"][data-filter="*"]' ).parent().addClass( 'vc_active' );

					this.$content
						.attr( 'data-vc-ui-filter', '*' )
						.addClass( 'vc_filter-all' );
				}

				$( '.vc_visible', this.$content ).removeClass( 'vc_visible' );
				$( filter, this.$content ).addClass( 'vc_visible' );

				// if user has pressed enter into search box and only one item is visible, simulate click
				if ( nameFilter.length ) {
					if ( 13 === ( e.keyCode || e.which ) ) {
						$visibleElements = $( '.vc_visible:not(.vc_inappropriate)', this.$content );
						if ( 1 === $visibleElements.length ) {
							$visibleElements.find( '[data-vc-clickable]' ).click();
						}
					}
				}

				// Hide section title in case there are no filtered elements in a section
				var anyVisible = false;
				var $noResultsMessage = $( '.vc-panel-no-results-message' );

				this.$content.find( '.wpb-content-layouts' ).each( function () {
					var $section = $( this );
					var hasVisibleItems = $section.find( '.vc_visible' ).length > 0;

					if ( !hasVisibleItems ) {
						$section.closest( '.vc_clearfix' ).hide();
					} else {
						$section.closest( '.vc_clearfix' ).show();
						anyVisible = true;
					}
				});

				// Show error message if there are no elements in any section
				if ( !anyVisible ) {
					$noResultsMessage.show();
				} else {
					$noResultsMessage.hide();
				}
			},
			createElement: function ( e ) {
				var options;
				if ( e && e.preventDefault ) {
					e.preventDefault();
				}

				var model,
					column,
					row,
					showSettings,
					shortcode,
					rowParams,
					innerRowParams,
					columnParams,
					innerColumnParams,
					tag,
					$control,
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

				if ( false === this.model ) {
					window.vc.storage.lock();
					if ( 'vc_section' === tag ) {
						var modelOptions = {
							shortcode: tag
						};
						if ( preset && 'vc_section' === presetType ) {
							modelOptions.preset = preset;
						}
						model = vc.shortcodes.create( modelOptions );

					} else {
						var rowOptions = {
							shortcode: 'vc_row',
							params: rowParams
						};
						if ( preset && presetType === tag ) {
							rowOptions.preset = preset;
						}
						row = vc.shortcodes.create( rowOptions );

						var columnOptions = {
							shortcode: 'vc_column',
							params: columnParams,
							parent_id: row.id,
							root_id: row.id
						};
						if ( preset && 'vc_column' === presetType ) {
							columnOptions.preset = preset;
						}
						column = vc.shortcodes.create( columnOptions );

						model = row;

						if ( 'vc_row' !== tag ) {
							options = {
								shortcode: tag,
								parent_id: column.id,
								root_id: row.id
							};

							if ( preset && presetType === tag ) {
								options.preset = preset;
							}

							model = vc.shortcodes.create( options );
						}
					}
				} else {
					if ( 'vc_row' === tag ) {
						if ( 'vc_section' === this.model.get( 'shortcode' ) ) {
							// we can add real row!
							window.vc.storage.lock();

							row = vc.shortcodes.create({
								shortcode: 'vc_row',
								params: rowParams,
								parent_id: this.model.id,
								order: ( this.prepend ? this.getFirstPositionIndex() : vc.shortcodes.getNextOrder() )
							});

							column = vc.shortcodes.create({
								shortcode: 'vc_column',
								params: columnParams,
								parent_id: row.id,
								root_id: row.id
							});

							model = row;
						} else {
							// we can add only row_inner!
							innerRowParams = {};

							innerColumnParams = { width: '1/1' };

							window.vc.storage.lock();
							row = vc.shortcodes.create({
								shortcode: 'vc_row_inner',
								params: innerRowParams,
								parent_id: this.model.id,
								order: ( this.prepend ? this.getFirstPositionIndex() : vc.shortcodes.getNextOrder() )
							});
							column = vc.shortcodes.create({
								shortcode: 'vc_column_inner',
								params: innerColumnParams,
								parent_id: row.id,
								root_id: row.id
							});
							model = row;
						}
					} else {
						options = {
							shortcode: tag,
							parent_id: this.model.id,
							order: ( this.prepend ? this.getFirstPositionIndex() : vc.shortcodes.getNextOrder() ),
							root_id: this.model.get( 'root_id' )
						};

						if ( preset && presetType === tag ) {
							options.preset = preset;
						}
						model = vc.shortcodes.create( options );
					}
				}

				this.model = model;
				window.vc.latestAddedElement = model;

				showSettings = !( _.isBoolean( vc.getMapped( tag ).show_settings_on_create ) && false === vc.getMapped(
					tag ).show_settings_on_create );

				// extend default params with settings presets if there are any
				// TODO: check if shortcode is used
				// eslint-disable-next-line no-unused-vars
				shortcode = this.model.get( 'shortcode' );

				this.hide();

				if ( showSettings ) {
					this.showEditForm();
				}
			},
			getFirstPositionIndex: function () {
				window.vc.element_start_index -= 1;

				return vc.element_start_index;
			},
			show: function () {
				this.$el.addClass( 'vc_active' );
				this.trigger( 'show' );
			},
			hide: function () {
				this.$el.removeClass( 'vc_active' );
				window.vc.active_panel = false;
				this.trigger( 'hide' );
			},
			showEditForm: function () {
				window.vc.edit_element_block_view.render( this.model, false, true );
			},
			updateAddElementPopUp: function ( id, shortcode, title, data ) {
				// element pop up box
				var $presetShortcode = this.$el.find( '[data-element="' + shortcode + '"]:first' );
				var $newPreset = $presetShortcode.clone( true );
				window.vc_all_presets[ id ] = data;

				$newPreset.find( '[data-vc-shortcode-name]' ).text( title );
				$newPreset.find( '.vc_element-description' ).text( '' );
				$newPreset.attr( 'data-preset', id );
				$newPreset.addClass( 'js-category-_my_elements_' );
				$newPreset.insertAfter( this.$el.find( '[data-element="' + shortcode + '"]:last' ) );

				this.$el.find( '[data-filter="js-category-_my_elements_"]' ).show();

				// preset settings panel
				var $samplePreset = ( this.$body.find( '[data-vc-ui-element="panel-preset"] [data-vc-presets-list-content] .vc_ui-template:first' ) );
				var $anotherNewPreset = $samplePreset.clone( true );
				$anotherNewPreset.find( '[data-vc-ui-element="template-title"]' ).attr( 'title', title ).text( title );
				$anotherNewPreset.find( '[data-vc-ui-delete="preset-title"]' ).attr( 'data-preset', id ).attr( 'data-preset-parent', shortcode );
				$anotherNewPreset.find( '[data-vc-ui-add-preset]' ).attr( 'data-preset', id ).attr( 'id', shortcode ).attr( 'data-tag', shortcode );
				$anotherNewPreset.show();
				$anotherNewPreset.insertAfter( this.$body.find( '[data-vc-ui-element="panel-preset"] [data-vc-presets-list-content] .vc_ui-template:last' ) );
			},
			removePresetFromAddElementPopUp: function ( id ) {
				this.$el.find( '[data-preset="' + id + '"]' ).remove();
			},
			openPresetWindow: function ( e ) {
				if ( e && e.preventDefault ) {
					e.preventDefault();
				}
				window.vc.preset_panel_view.render().show();
			}
		});

})( window.jQuery );
