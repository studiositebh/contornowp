( function ( $ ) {
	'use strict';

	/**
	 * Generate pie/doughnut charts
	 *
	 * Legend must be generated manually. If color is array (gradient), then legend won't show it.
	 */
	$.fn.vcRoundChart = function () {
		var vcwaypoint = 'undefined' !== typeof ( $.fn.vcwaypoint );

		this.each( function () {
			var data,
				gradient,
				chart,
				$this = $( this ),
				ctx = $this.find( 'canvas' )[ 0 ].getContext( '2d' ),
				stroke_width = $this.data( 'vcStrokeWidth' ) ? parseInt( $this.data( 'vcStrokeWidth' ), 10 ) : 0,
				options = {
					showTooltips: $this.data( 'vcTooltips' ),
					animation: {
						duration: 800,
						easing: $this.data( 'vcAnimation' ) || 'easeOutQuart'
					},
					segmentStrokeColor: $this.data( 'vcStrokeColor' ),
					borderColor: $this.data( 'vcStrokeColor' ),
					segmentShowStroke: 0 !== stroke_width,
					segmentStrokeWidth: stroke_width,
					strokeWidth: stroke_width,
					borderWidth: stroke_width,
					responsive: true,
					plugins: {
						legend: {
							display: true
						}
					}
				};
			if ( !$this.data( 'vcLegend' ) ) {
				options.plugins.legend = {
					display: false
				};
			}
			if ( options.plugins.legend.display && $this.data( 'vcLegendColor' ) ) {
				options.plugins.legend.labels = {
					color: $this.data( 'vcLegendColor' )
				};
			}
			// if options.plugins.legend.display true then check for position
			if ( options.plugins.legend.display && $this.data( 'vcLegendPosition' ) ) {
				options.plugins.legend.position = $this.data( 'vcLegendPosition' );
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
			data.datasets[ 0 ].backgroundColor.forEach( function ( color, i ) {
				if ( Array.isArray( color ) ) {
					gradient = ctx.createLinearGradient( 0, 0, 0, ctx.canvas.height );
					gradient.addColorStop( 0, color[ 0 ]);
					gradient.addColorStop( 1, color[ 1 ]);
					data.datasets[ 0 ].backgroundColor[ i ] = gradient;
				}
			});

			function addchart () {
				if ( $this.data( 'animated' ) ) {
					return;
				}

				var type = 'pie';
				if ( 'doughnut' === $this.data( 'vcType' ) ) {
					type = 'doughnut';
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
	if ( 'function' !== typeof ( window.vc_round_charts ) ) {
		window.vc_round_charts = function ( model_id ) {
			var selector = '.vc_round-chart';
			if ( 'undefined' !== typeof ( model_id ) ) {
				selector = '[data-model-id="' + model_id + '"] ' + selector;
			}
			$( selector ).vcRoundChart();
		};
	}

	$( document ).ready( function () {
		if ( !window.vc_iframe ) {
			vc_round_charts();
		}
	});

}( jQuery ) );
