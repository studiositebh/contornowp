/**
 * RowLayoutEditorPanelView manages row and column layout editing in the editor.
 *
 * It handles layout changes, column width calculations, and layout validation for rows.
 */

( function ( $ ) {
	'use strict';
	if ( _.isUndefined( window.vc ) ) {
		window.vc = {};
	}

	var events = {
		'click [data-vc-ui-element="button-save"]': 'save',
		'click [data-vc-ui-element="button-close"]': 'hide',
		'touchstart [data-vc-ui-element="button-close"]': 'hide',
		'click [data-vc-ui-element="button-minimize"]': 'toggleOpacity',
		'click [data-vc-ui-element="button-layout"]': 'setLayout',
		'click [data-vc-ui-element="button-update-layout"]': 'updateFromInput'
	};

	vc.RowLayoutEditorPanelView = vc.PanelView.extend({
		events: {
			'click [data-dismiss=panel]': 'hide',
			'click [data-transparent=panel]': 'toggleOpacity',
			'mouseover [data-transparent=panel]': 'addOpacity',
			'mouseout [data-transparent=panel]': 'removeOpacity',
			'click .vc_layout-btn': 'setLayout',
			'click #vc_row-layout-update': 'updateFromInput'
		},
		_builder: false,
		render: function ( model ) {
			this.$input = $( '#vc_row-layout' );
			if ( model ) {
				this.model = model;
			}
			this.addCurrentLayout();
			this.resetMinimize();
			vc.column_trig_changes = true;
			$( '.edit-form-info' ).initializeTooltips( '.vc_ui-panel-content' );
			return this;
		},
		builder: function () {
			if ( !this._builder ) {
				this._builder = new vc.ShortcodesBuilder();
			}
			return this._builder;
		},
		addCurrentLayout: function () {
			vc.shortcodes.sort();
			var string = _.map( vc.shortcodes.where({ parent_id: this.model.get( 'id' ) }), function ( model ) {
				var width = model.getParam( 'width' );
				return width ? width : '1/1';
			}, '', this ).join( ' + ' );
			this.$input.val( string );
		},
		isBuildComplete: function () {
			return this.builder().isBuildComplete();
		},
		setLayout: function ( e ) {
			if ( e && e.preventDefault ) {
				e.preventDefault();
			}
			if ( !this.isBuildComplete() ) {
				return false;
			}
			var $control = $( e.currentTarget ),
				layout = $control.attr( 'data-cells' ),
				columns = this.model.view.convertRowColumns( layout, this.builder() );
			this.$input.val( columns.join( ' + ' ) );
		},
		updateFromInput: function ( e ) {
			// TODO: Check for deprecated #vc_row-layout-update
			if ( e && e.preventDefault ) {
				e.preventDefault();
			}
			if ( !this.isBuildComplete() ) {
				return false;
			}
			var layout,
				cells = this.$input.val();
			if ( false !== ( layout = this.validateCellsList( cells ) ) ) {
				this.model.view.convertRowColumns( layout, this.builder() );
			} else {
				window.alert( window.i18nLocale.wrong_cells_layout );
			}
		},
		validateCellsList: function ( cells ) {
			var returnCells, split, b, num, denom;
			returnCells = [];
			split = cells.replace( /\s/g, '' ).split( '+' );
			var sum = _.reduce( _.map( split, function ( c ) {
				if ( c.match( /^[vc\_]{0,1}span\d{1,2}$/ ) ) {
					var convertedC = vc_convert_column_span_size( c );
					if ( false === convertedC ) {
						return 1000;
					}
					b = convertedC.split( /\// );
					returnCells.push( b[ 0 ] + '' + b[ 1 ]);
					return 12 * parseInt( b[ 0 ], 10 ) / parseInt( b[ 1 ], 10 );
				} else if ( c.match( /^[1-9]|1[0-2]\/[1-9]|1[0-2]$/ ) ) {
					b = c.split( /\// );
					num = parseInt( b[ 0 ], 10 );
					denom = parseInt( b[ 1 ], 10 );
					if ( ( 5 !== denom && 0 !== 12 % denom ) || num > denom ) {
						return 1000;
					}
					returnCells.push( num + '' + denom );
					if ( 5 === denom ) {
						return num;
					} else {
						return 12 * num / denom;
					}
				}
				return 1000;

			}), function ( num, memo ) {
				memo += num;
				return memo;
			}, 0 );
			if ( 1000 <= sum ) {
				return false;
			}
			return returnCells.join( '_' );
		}
	});

	vc.RowLayoutUIPanelFrontendEditor = vc.RowLayoutEditorPanelView
		.vcExtendUI( vc.HelperPanelViewHeaderFooter )
		.vcExtendUI( vc.HelperPanelViewDraggable )
		.extend({
			panelName: 'rowLayouts',
			events: events
		});

})( window.jQuery );
