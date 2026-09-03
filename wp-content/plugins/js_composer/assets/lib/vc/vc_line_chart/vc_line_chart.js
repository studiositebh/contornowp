( function ( $ ) {
	'use strict';

	/**
	 * Generate line/bar charts
	 *
	 * Legend must be generated manually. If color is array (gradient), then legend won't show it.
	 */
	$.fn.vcLineChart = function () {
		var vcwaypoint = 'undefined' !== typeof ( $.fn.vcwaypoint );

		this.each( function () {
			var data, gradient, chart, i, $this, ctx, options;
			$this = $( this );
			ctx = $this.find( 'canvas' )[ 0 ].getContext( '2d' );
			options = {
				showTooltips: $this.data( 'vcTooltips' ),
				animation: {
					duration: 800,
					easing: $this.data( 'vcAnimation' ) || 'easeOutQuart'
				},
				datasetFill: true,
				scaleLabel: function ( object ) {
					return ' ' + object.value;
				},
				responsive: true,
				plugins: {}
			};
			if ( !$this.data( 'vcLegend' ) ) {
				options.plugins.legend = {
					display: false
				};
			}
			if ( !$this.data( 'vcTooltips' ) ) {
				options.plugins.tooltip = {
					enabled: false
				};
			}

			// If plugin has been called on already initialized element, reload it
			if ( $this.data( 'chart' ) ) {
				$this.data( 'chart' ).destroy();
				$this.removeData( 'animated' );
			}

			data = $this.data( 'vcValues' );

			ctx.canvas.width = $this.width();
			ctx.canvas.height = $this.width();

			// If color/highlight is array (of 2 colors), replace it with generated gradient
			// for each datasets
			for ( i = data.datasets.length - 1;
				0 <= i;
				i -- ) {
				if ( Array.isArray( data.datasets[ i ].backgroundColor ) ) {
					gradient = ctx.createLinearGradient( 0, 0, 0, ctx.canvas.height );
					gradient.addColorStop( 0, data.datasets[ i ].backgroundColor[ 0 ]);
					gradient.addColorStop( 1, data.datasets[ i ].backgroundColor[ 1 ]);
					data.datasets[ i ].backgroundColor = gradient;
				}
			}

			function addchart () {
				if ( $this.data( 'animated' ) ) {
					return;
				}

				var type = 'line';
				if ( 'bar' === $this.data( 'vcType' ) ) {
					type = 'bar';
				}
				chart = new Chart( ctx, {
					type: type,
					data: data,
					options: options
				});

				$this.data( 'vcChartId', chart.id );

				// We can later access chart to call methods on it
				$this.data( 'chart', chart );

				$this.data( 'animated', true );
			}

			if ( vcwaypoint ) {
				$this.vcwaypoint( $.proxy( addchart, $this ), { offset: '85%' });
			} else {
				addchart();
			}
		});

		return this;
	};

	/**
	 * Allows users to rewrite function inside theme.
	 */
	if ( 'function' !== typeof ( window.vc_line_charts ) ) {
		window.vc_line_charts = function ( model_id ) {
			var selector = '.vc_line-chart';
			if ( 'undefined' !== typeof ( model_id ) ) {
				selector = '[data-model-id="' + model_id + '"] ' + selector;
			}
			$( selector ).vcLineChart();
		};
	}
	$( document ).ready( function () {
		if ( !window.vc_iframe ) {
			vc_line_charts();
		}
	});
}( jQuery ) );
