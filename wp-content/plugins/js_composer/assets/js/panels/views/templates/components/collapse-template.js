/**
 * Collapse Template is extension of vcAccordion
 * It is used to collapse the template preview in the Templates panel
 */

/* global vc, i18nLocale */
( function ( $ ) {
	'use strict';

	$.fn.vcAccordion.Constructor.prototype.collapseTemplate = function ( showCallback ) {
		var $allTriggers;
		var $activeTriggers;
		var $this,
			$triggers;

		$this = this.$element;
		var i;
		i = 0;
		$allTriggers = this.getContainer().find( '[data-vc-preview-handler]' ).each( function () {
			var accordion, $this;
			$this = $( this );
			accordion = $this.data( 'vc.accordion' );
			if ( 'undefined' === typeof ( accordion ) ) {
				$this.vcAccordion();
				accordion = $this.data( 'vc.accordion' );
			}
			if ( accordion && accordion.setIndex ) {
				accordion.setIndex( i ++ );
			}
		});

		$activeTriggers = $allTriggers.filter( function () {
			var $this, accordion;
			$this = $( this );
			accordion = $this.data( 'vc.accordion' );

			return accordion.getTarget().hasClass( accordion.activeClass );
		});

		$triggers = $activeTriggers.filter( function () {
			return $this[ 0 ] !== this;
		});

		if ( $triggers.length ) {
			$.fn.vcAccordion.call( $triggers, 'hide' );
		}
		// toggle preview
		if ( this.isActive() ) {
			$.fn.vcAccordion.call( $this, 'hide' );
		} else {
			$.fn.vcAccordion.call( $this, 'show' );
			var $triggerPanel = $this.closest( '.vc_ui-list-bar-item' );
			var $wrapper = $this.closest( '[data-template_id]' );
			var $panel = $wrapper.closest( '[data-vc-ui-element=panel-content]' ).parent();
			setTimeout( function () {
				if ( Math.round( $wrapper.offset().top - $panel.offset().top ) < 0 ) {
					var posit = Math.round( $wrapper.offset().top - $panel.offset().top + $panel.scrollTop() - $triggerPanel.height() );
					$panel.animate({ scrollTop: posit }, 400 );
				}
				if ( 'function' === typeof showCallback ) {
					showCallback( $wrapper, $panel );
				}
			}, 400 );
		}
	};
})( window.jQuery );
