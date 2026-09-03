/**
 * Main Backbone View for editor panels.
 * Provides panel functionality including events, sizing, tabs, and positioning.
 */

( function ( $ ) {
	'use strict';
	if ( _.isUndefined( window.vc ) ) {
		window.vc = {};
	}

	vc.PanelView = vc.View.extend({
		mediaSizeClassPrefix: 'vc_media-',
		customMediaQuery: true,
		panelName: 'panel',
		draggable: false,
		$body: false,
		$tabs: false,
		$content: false,
		events: {
			'click [data-dismiss=panel]': 'hide',
			'mouseover [data-transparent=panel]': 'addOpacity',
			'click [data-transparent=panel]': 'toggleOpacity',
			'mouseout [data-transparent=panel]': 'removeOpacity',
			'click .vc_panel-tabs-link': 'changeTab'
		},
		_vcUIEventsHooks: [
			{ 'resize': 'setResize' }
		],
		options: {
			startTab: 0
		},
		clicked: false,
		showMessageDisabled: true, // disabled in 4.7 due to button and new ui.
		initialize: function () {
			this.clicked = false;
			this.$el.removeClass( 'vc_panel-opacity' );
			this.$body = $( 'body' );
			this.$content = this.$el.find( '.vc_panel-body' );
			_.bindAll( this, 'setSize', 'fixElContainment', 'changeTab', 'setTabsSize' );
			this.on( 'show', this.setSize, this );
			this.on( 'setSize', this.setResize, this );
			this.on( 'render', this.resetMinimize, this );
		},
		toggleOpacity: function () {
			this.clicked = !this.clicked;
		},
		addOpacity: function () {
			if ( !this.clicked ) {
				this.$el.addClass( 'vc_panel-opacity' );
			}
		},
		removeOpacity: function () {
			if ( !this.clicked ) {
				this.$el.removeClass( 'vc_panel-opacity' );
			}
		},
		message_box_timeout: false,
		init: function () {
		},
		render: function () {
			this.trigger( 'render' );
			this.trigger( 'afterRender' );
			return this;
		},
		show: function () {
			if ( this.$el.hasClass( 'vc_active' ) ) {
				return;
			}

			vc.closeActivePanel();
			this.init();
			vc.active_panel = this;
			this.clicked = false;
			this.$el.removeClass( 'vc_panel-opacity' );
			var $tabs = this.$el.find( '.vc_panel-tabs' );
			if ( $tabs.length ) {
				this.$tabs = $tabs;
				this.setTabs();
			}
			this.$el.addClass( 'vc_active' );
			if ( !this.draggable ) {
				$( window ).trigger( 'resize' );
			} else {
				this.initDraggable();
			}
			this.fixElContainment();
			this.trigger( 'show' );
		},
		hide: function ( e ) {
			if ( e && e.preventDefault ) {
				e.preventDefault();
			}
			if ( this.model ) {
				this.model = null;
			}
			vc.active_panel = false;
			this.$el.removeClass( 'vc_active' );
		},
		content: function () {
			return this.$el.find( '.panel-body' );
		},
		setResize: function () {
			if ( this.customMediaQuery ) {
				this.setMediaSizeClass();
			}
		},
		setMediaSizeClass: function () {
			var modalWidth, classes;
			modalWidth = this.$el.width();
			classes = {
				xs: true,
				sm: false,
				md: false,
				lg: false
			};
			if ( 525 <= modalWidth ) {
				classes.sm = true;
			}
			if ( 745 <= modalWidth ) {
				classes.md = true;
			}
			if ( 945 <= modalWidth ) {
				classes.lg = true;
			}
			_.each( classes, function ( value, key ) {
				if ( value ) {
					this.$el.addClass( this.mediaSizeClassPrefix + key );
				} else {
					this.$el.removeClass( this.mediaSizeClassPrefix + key );
				}
			}, this );
		},
		fixElContainment: function () {
			if ( !this.$body ) {
				this.$body = $( 'body' );
			}
			var elW = this.$el.width(),
				containerW = this.$body.width(),
				containerH = this.$body.height();

			// To be sure that containment always correct, even after resize
			var containment = [
				- elW + 20,
				0,
				containerW - 20,
				containerH - 30
			];
			var positions = this.$el.position();
			var newPositions = {};
			if ( positions.left < containment[ 0 ]) {
				newPositions.left = containment[ 0 ];
			}
			if ( 0 > positions.top ) {
				newPositions.top = 0;
			}
			if ( positions.left > containment[ 2 ]) {
				newPositions.left = containment[ 2 ];
			}
			if ( positions.top > containment[ 3 ]) {
				newPositions.top = containment[ 3 ];
			}
			this.$el.css( newPositions );
			this.trigger( 'fixElContainment' );
			this.setSize();
		},
		/**
		 * Init draggable feature for panels to allow it Moving, also allow moving only in proper containment
		 */
		initDraggable: function () {
			this.$el.draggable({
				iframeFix: true,
				handle: '.vc_panel-heading',
				start: this.fixElContainment,
				stop: this.fixElContainment
			});
			this.draggable = true;
		},
		setSize: function () {
			this.trigger( 'setSize' );
		},
		setTabs: function () {
			if ( this.$tabs.length ) {
				this.$tabs.find( '.vc_panel-tabs-control' ).removeClass( 'vc_active' ).eq( this.options.startTab ).addClass(
					'vc_active' );
				this.$tabs.find( '.vc_panel-tab' ).removeClass( 'vc_active' ).eq( this.options.startTab ).addClass(
					'vc_active' );
				window.setTimeout( this.setTabsSize, 100 );
			}
		},
		setTabsSize: function () {
			if ( this.$tabs ) {
				this.$tabs.parents( '.vc_with-tabs.vc_panel-body' ).css( 'margin-top', this.$tabs.find( '.vc_panel-tabs-menu' ).outerHeight() );
			}
		},
		changeTab: function ( e ) {
			if ( e && e.preventDefault ) {
				e.preventDefault();
			}
			if ( e.target && this.$tabs ) {
				var $tab = $( e.target );
				this.$tabs.find( '.vc_active' ).removeClass( 'vc_active' );
				$tab.parent().addClass( 'vc_active' );
				this.$el.find( $tab.data( 'target' ) ).addClass( 'vc_active' );
				window.setTimeout( this.setTabsSize, 100 );
			}
		},
		showMessage: function ( text, type ) {
			if ( this.showMessageDisabled ) {
				return false;
			}
			if ( this.message_box_timeout ) {
				this.$el.find( '.vc_panel-message' ).remove();
				window.clearTimeout( this.message_box_timeout );
			}

			this.message_box_timeout = false;
			var $messageBox = $( '<div class="vc_panel-message type-' + type + '"></div>' ).appendTo( this.$el.find( '.vc_ui-panel-content-container' ) );
			$messageBox.text( text ).fadeIn();
			this.message_box_timeout = window.setTimeout( function () {
				$messageBox.remove();
			}, 6000 );
		},
		isVisible: function () {
			return this.$el.is( ':visible' );
		},
		resetMinimize: function () {
			this.$el.removeClass( 'vc_panel-opacity' );
		}
	});

})( window.jQuery );
