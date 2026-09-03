( function ( window ) {
	'use strict';
	if ( ! window.vc ) {
		window.vc = {};
	}
	window.vc.utils = {
		DOMPurify: window.DOMPurify || {},
		fixUnclosedTags: function ( string ) {
			// Replace opening < and closing </ with respective entities to avoid editor breaking
			return string
				.replace( /<\/([^>]+)$/g, '&#60;/$1' ) // Replace closing </
				.replace( /<([^>]+)?$/g, '&#60;$1' ); // Replace opening < or lone <
		},
		validateHtml: function ( string ) {
			// Skip if no HTML tags found
			if ( !string.includes( '<' ) ) {
				return string;
			}
			try {
				var parser = new DOMParser();
				var doc = parser.parseFromString( string, 'text/html' );

				return doc.body.innerHTML;
			} catch ( err ) {
				console.error( 'Failed to execute window.vc.utils.validateHtml function: ', err );
				// If parsing fails, return the original string with basic entity encoding
				return string
					.replace( /</g, '&lt;' )
					.replace( />/g, '&gt;' );
			}
		},
		fallbackCopyTextToClipboard: function ( text ) {
			var textArea = document.createElement( 'textarea' );
			textArea.value = text;
			// Avoid scrolling to bottom
			textArea.style.top = '0';
			textArea.style.left = '0';
			textArea.style.position = 'fixed';
			document.body.appendChild( textArea );
			textArea.focus();
			textArea.select();
			try {
				document.execCommand( 'copy' );
			} catch ( err ) {
				console.error( 'Unable to copy', err );
			}
		},
		copyTextToClipboard: function ( text ) {
			if ( !navigator.clipboard ) {
				this.fallbackCopyTextToClipboard.call( this, text );
				return;
			}
			navigator.clipboard.writeText( text );
		},
		slugify: function ( string ) {
			string = string || '';
			return string.toString().toLowerCase()
				.replace( /[^a-z0-9\s-]/g, ' ' ) // Remove all non-alphanumeric characters except spaces and hyphens
				.replace( /[\s_-]+/g, '-' ) // Replace spaces, underscores, and multiple hyphens with a single hyphen
				.replace( /^-+|-+$/g, '' ); // Trim leading and trailing hyphens
		},
		stripHtmlTags: function ( string ) {
			return string.replace( /(<([^>]+)>)/ig, '' );
		},
		isBase64: function ( string ) {
			return /^[A-Za-z0-9+/]+={0,2}$/.test( string ) && string.length % 4 === 0;
		},
		/**
		 * Returns a new order value to insert a shortcode right after the given model.
		 * Used in clone and paste operations to maintain correct element positioning.
		 *
		 * @param {Backbone.Model} model - The shortcode model after which the new element should be inserted.
		 * @param {boolean} isPasteToColumn - Whether the paste operation is happening inside a column.
		 * @returns {number} A numeric `order` value that will place a new element immediately after the given model.
		 */
		getMidpointOrder: function ( model, isPasteToColumn ) {
			var currentOrder = parseFloat( model.get( 'order' ) );
			var parentId = model.get( 'parent_id' );

			var siblings = vc.shortcodes.where({ parent_id: parentId })
				.sort( function ( a, b ) { return parseFloat( a.get( 'order' ) ) - parseFloat( b.get( 'order' ) ); });

			if ( isPasteToColumn ) {
				var lastOrder = 0;
				var children = vc.shortcodes.where({ parent_id: model.get( 'id' ) });
				if ( children.length ) {
					var lastIndex = children.length - 1;
					var lastOrder = children[lastIndex].get( 'order' );
					if ( lastOrder === undefined || isNaN( lastOrder ) ) {
						lastOrder = 0;
					}
				}
				return lastOrder + 1;
			}
			var currentIndex = siblings.findIndex( function ( sibling ) { return sibling.id === model.id; });
			var next = siblings[currentIndex + 1];

			if ( next ) {
				var nextOrder = parseFloat( next.get( 'order' ) );
				// If the two orders are too close (floating point precision issue), normalize all orders
				if ( Math.abs( nextOrder - currentOrder ) < 1e-10 ) {
					siblings.forEach( function ( model, i ) {
						model.set( 'order', i + 1 ); // set to 1, 2, 3, ...
					});
					return vc.utils.getMidpointOrder( model ); // retry after normalization
				}
				return ( currentOrder + nextOrder ) / 2;
			} else {
				return currentOrder + 1;
			}
		},
		sanitizeString: function ( string ) {
			if ( typeof string !== 'string' ) {
				return string;
			}
			return this.DOMPurify.sanitize( string, {
				FORBID_TAGS: [
					'script',
					'iframe',
					'object',
					'embed',
					'link',
					'meta',
					'style',
					'base',
					'form',
					'input',
					'textarea',
					'button',
					'select',
					'option',
					'applet',
					'frame',
					'frameset',
					'noscript',
					'svg',
					'math',
					'template',
					'a',
					'img',
					'audio',
					'video'
				],
				FORBID_ATTR: [
					'onabort',
					'onblur',
					'onchange',
					'onclick',
					'oncontextmenu',
					'ondblclick',
					'onerror',
					'onfocus',
					'oninput',
					'onkeydown',
					'onkeypress',
					'onkeyup',
					'onload',
					'onloadstart',
					'onmousedown',
					'onmouseenter',
					'onmouseleave',
					'onmousemove',
					'onmouseout',
					'onmouseover',
					'onmouseup',
					'onreset',
					'onscroll',
					'onselect',
					'onsubmit',
					'onunload',
					'formaction',
					'src',
					'srcdoc',
					'data',
					'xlink:href',
					'target'
				]
			});
		}
	};
})( window );
