/**
 * Modal dialog implementation for editor.
 * Handles events, sizing, messages, and modal integration.
 *
 * @type {*}
 */

( function ( $ ) {
	'use strict';
	if ( _.isUndefined( window.vc ) ) {
		window.vc = {};
	}
	vc.ModalView = Backbone.View.extend({
		message_box_timeout: false,
		events: {
			'hidden.bs.modal': 'hide',
			'shown.bs.modal': 'shown'
		},
		initialize: function () {
			_.bindAll( this, 'setSize', 'hide' );
		},
		setSize: function () {
			var height = $( window ).height() - 150;
			this.$content.css( 'maxHeight', height );
			this.trigger( 'setSize' );
		},
		render: function () {
			$( window ).on( 'resize.ModalView', this.setSize );
			this.setSize();
			vc.closeActivePanel();
			this.$el.modal( 'show' );
			return this;
		},
		showMessage: function ( text, type ) {
			if ( this.message_box_timeout && this.$el.find( '.vc_message' ).remove() ) {
				window.clearTimeout( this.message_box_timeout );
			}
			this.message_box_timeout = false;
			var $messageBox = $( '<div class="vc_message type-' + type + '"></div>' );
			this.$el.find( '.vc_modal-body' ).prepend( $messageBox );
			$messageBox.text( text ).fadeIn();
			this.message_box_timeout = window.setTimeout( function () {
				$messageBox.remove();
			}, 6000 );
		},
		hide: function () {
			$( window ).off( 'resize.ModalView' );
		},
		shown: function () {
		}
	});
})( window.jQuery );
