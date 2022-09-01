/* global vhc_wc_sales_report_select_params */
( function( $ ) {
	function getEnhancedSelectFormatString() {
		return {
			language: {
				errorLoading() {
					// Workaround for https://github.com/select2/select2/issues/4355 instead of i18n_ajax_error.
					return vhc_wc_sales_report_select_params.i18n_searching;
				},
				inputTooLong( args ) {
					const overChars = args.input.length - args.maximum;

					if ( 1 === overChars ) {
						return vhc_wc_sales_report_select_params.i18n_input_too_long_1;
					}

					return vhc_wc_sales_report_select_params.i18n_input_too_long_n.replace( '%qty%', overChars );
				},
				inputTooShort( args ) {
					const remainingChars = args.minimum - args.input.length;

					if ( 1 === remainingChars ) {
						return vhc_wc_sales_report_select_params.i18n_input_too_short_1;
					}

					return vhc_wc_sales_report_select_params.i18n_input_too_short_n.replace( '%qty%', remainingChars );
				},
				loadingMore() {
					return vhc_wc_sales_report_select_params.i18n_load_more;
				},
				maximumSelected( args ) {
					if ( args.maximum === 1 ) {
						return vhc_wc_sales_report_select_params.i18n_selection_too_long_1;
					}

					return vhc_wc_sales_report_select_params.i18n_selection_too_long_n.replace( '%qty%', args.maximum );
				},
				noResults() {
					return vhc_wc_sales_report_select_params.i18n_no_matches;
				},
				searching() {
					return vhc_wc_sales_report_select_params.i18n_searching;
				},
			},
		};
	}

	try {
		$( document.body )

			.on( 'enhanced-select-init', function() {
				// Regular select boxes
				$( ':input.enhanced-select, :input.chosen_select' ).filter( ':not(.enhanced)' ).each( function() {
					const select2_args = $.extend( {
						minimumResultsForSearch: 10,
						allowClear: $( this ).data( 'allow_clear' ) ? true : false,
						placeholder: $( this ).data( 'placeholder' ),
					}, getEnhancedSelectFormatString() );

					$( this ).select2( select2_args ).addClass( 'enhanced' );
				} );

				$( ':input.enhanced-select-nostd, :input.chosen_select_nostd' ).filter( ':not(.enhanced)' ).each( function() {
					const select2_args = $.extend( {
						minimumResultsForSearch: 10,
						allowClear: true,
						placeholder: $( this ).data( 'placeholder' ),
					}, getEnhancedSelectFormatString() );

					$( this ).select2( select2_args ).addClass( 'enhanced' );
				} );

				function display_result( self, select2_args ) {
					select2_args = $.extend( select2_args, getEnhancedSelectFormatString() );

					$( self ).select2( select2_args ).addClass( 'enhanced' );
				}

				// Ajax product search box
				$( ':input.product-search' ).filter( ':not(.enhanced)' ).each( function() {
					const select2_args = {
						allowClear: $( this ).data( 'allow_clear' ) ? true : false,
						placeholder: $( this ).data( 'placeholder' ),
						minimumInputLength: $( this ).data( 'minimum_input_length' ) ? $( this ).data( 'minimum_input_length' ) : '3',
						escapeMarkup( m ) {
							return m;
						},
						ajax: {
							url: vhc_wc_sales_report_select_params.ajax_url,
							dataType: 'json',
							delay: 250,
							data( params ) {
								return {
									term: params.term,
									action: $( this ).data( 'action' ) || 'woocommerce_json_search_products_and_variations',
									security: vhc_wc_sales_report_select_params.search_products_nonce,
								};
							},
							processResults( data ) {
								const terms = [];
								if ( data ) {
									$.each( data, function( id, text ) {
										terms.push( { id, text } );
									} );
								}
								return {
									results: terms,
								};
							},
							cache: true,
						},
					};

					display_result( this, select2_args );
				} );

				// Ajax category search boxes
				$( ':input.term-search' ).filter( ':not(.enhanced)' ).each( function() {
					const select2_args = $.extend( {
						allowClear: $( this ).data( 'allow_clear' ) ? true : false,
						placeholder: $( this ).data( 'placeholder' ),
						minimumInputLength: $( this ).data( 'minimum_input_length' ) ? $( this ).data( 'minimum_input_length' ) : 3,
						escapeMarkup( m ) {
							return m;
						},
						ajax: {
							url: vhc_wc_sales_report_select_params.ajax_url,
							dataType: 'json',
							delay: 250,
							data( params ) {
								return {
									term: params.term,
									action: 'vhc_wc_sales_report_json_search_terms',
									taxonomy: $( this ).data( 'taxonomy' ) || '',
									security: vhc_wc_sales_report_select_params.search_terms_nonce,
								};
							},
							processResults( data ) {
								const terms = [];
								if ( data ) {
									$.each( data, function( id, text ) {
										terms.push( { id, text } );
									} );
								}
								return {
									results: terms,
								};
							},
							cache: true,
						},
					}, getEnhancedSelectFormatString() );

					$( this ).select2( select2_args ).addClass( 'enhanced' );
				} );
			} )

			.trigger( 'enhanced-select-init' );

		$( 'html' ).on( 'click', function( event ) {
			if ( this === event.target ) {
				$( '.enhanced-select, :input.product-search, :input.term-search' ).filter( '.select2-hidden-accessible' )
					.select2( 'close' );
			}
		} );
	} catch ( err ) {
		// If select2 failed (conflict?) log the error but don't stop other scripts breaking.
		window.console.log( err );
	}
}( jQuery ) );
