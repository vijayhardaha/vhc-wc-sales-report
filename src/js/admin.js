/* global vhc_wc_sales_report_params */
( function( $ ) {
	/**
	 * Sales report class.
	 */
	class VHC_Sales_Report {
		/**
		 * Class constructor.
		 */
		constructor() {
			// Initialize notice Timeout.
			this.noticeTimeout = false;

			// Register notice events.
			this.registerNoticeEvents();

			// Register datepicker events.
			this.registerDatePickerEvents();

			// Toggle custom date range on load.
			this.togglePeriods();

			// Register form events.
			this.registerFormEvents();
		}

		/**
		 * Returns jQuery DOM element.
		 *
		 * @param {string} selector Selector name.
		 * @return {Object} DomElement object.
		 */
		getElement( selector ) {
			return $( document ).find( selector );
		}

		/**
		 * Show notice box.
		 *
		 * @param {string}  type     Notice type.
		 * @param {string}  text     Notice text message.
		 * @param {boolean} autohide Auto hide if true.
		 */
		showNotice( type = 'info', text = '', autohide = true ) {
			const self = this;

			clearTimeout( self.noticeTimeout );
			self.getElement( '.notice-wrap' ).remove();

			const path = type === 'success' ? 'M9 19.414l-6.707-6.707 1.414-1.414L9 16.586 20.293 5.293l1.414 1.414' : 'M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z';
			const html = `<div class="notice-wrap"><div class="notice-alert notice-${ type }"><span class="notice-icon"><svg height="24" width="24" viewBox="0 0 24 24"><g><path d="${ path }"></path></g></svg></span><span class="notice-content"><span class="notice-text">${ text }</span></span><span role="button" tabindex="0" class="dismiss"><svg height="24" width="24" viewBox="0 0 24 24"><g><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"></path></g></svg></span></div></div>`;

			self.getElement( '.vhc-wc-sr-container' ).append( html );

			if ( autohide ) {
				self.noticeTimeout = setTimeout( function() {
					self.getElement( '.notice-wrap' ).fadeOut().remove();
				}, 3000 );
			}
		}

		/**
		 * Clear all the notices.
		 */
		clearNotice() {
			const self = this;
			self.getElement( '.notice-wrap' ).remove();
		}

		/**
		 * Register notice events.
		 */
		registerNoticeEvents() {
			const self = this;

			$( document ).on( 'click', '.notice-alert .dismiss', function( e ) {
				e.preventDefault();
				$( this ).parents( '.notice-alert' ).hide().remove();
				if ( self.getElement( '.notice-wrap' ).length && self.getElement( '.notice-wrap' ).html().trim() === '' ) {
					self.getElement( '.notice-wrap' ).remove();
				}
			} );
		}

		/**
		 * Register datepicker events.
		 */
		registerDatePickerEvents() {
			const self = this;

			if ( self.getElement( '.setting-field .datepicker' ).length ) {
				self.getElement( '.setting-field .datepicker' ).datepicker( {
					dateFormat: 'yy-mm-dd',
					changeMonth: true,
					changeYear: true,
					yearRange: '-100:+1',
					maxDate: '0',
				} );
			}
		}

		/**
		 * Register form events.
		 */
		registerFormEvents() {
			const self = this;
			$( document )
				.on( 'click', '.vhc-wc-sr-form .view-report', function( e ) {
					e.preventDefault();

					const form = $( e.currentTarget ).closest( 'form' );
					form.find( 'input[name=\'download\']' ).val( 0 );

					self.clearNotice();
					$( '.vhc-wc-sr-container .report-preview' ).empty();

					$.ajax( {
						type: 'POST',
						url: vhc_wc_sales_report_params.ajax_url,
						data: form.serialize(),
						dataType: 'json',
						beforeSend() {
							form.find( '[type="button"]' ).attr( 'disabled', true );
							self.showNotice( 'info', vhc_wc_sales_report_params.i18n_generating_report, false );
						},
						success( response ) {
							self.clearNotice();
							if ( response.success ) {
								$( '.vhc-wc-sr-container .report-preview' ).html( response.data.html );
								$( '.vhc-wc-sr-container .report-preview table' ).DataTable( {
									aLengthMenu: [
										[ 10, 15, 20, 25, 30 ],
										[ 10, 15, 20, 25, 30 ],
									],
									iDisplayLength: 15,
									order: [],
								} );
								$( 'html, body' ).animate( {
									scrollTop: $( '.vhc-wc-sr-container .report-preview' ).offset().top - 50,
								} );
							} else {
								self.showNotice( 'error', vhc_wc_sales_report_params.i18n_something_wrong );
							}
						},
						error() {
							form.find( '[type="button"]' ).attr( 'disabled', false );
							self.showNotice( 'error', vhc_wc_sales_report_params.i18n_something_wrong );
						},
						complete() {
							form.find( '[type="button"]' ).attr( 'disabled', false );
						},
					} );
				} )

				.on( 'click', '.vhc-wc-sr-form .download-report', function( e ) {
					e.preventDefault();

					const form = $( e.currentTarget ).closest( 'form' );
					form.find( 'input[name=download]' ).val( 1 );

					self.clearNotice();
					$( '.vhc-wc-sr-container .report-preview' ).empty();

					$.ajax( {
						type: 'POST',
						url: vhc_wc_sales_report_params.ajax_url,
						data: form.serialize(),
						dataType: 'json',
						beforeSend() {
							form.find( '[type=button]' ).attr( 'disabled', true );
							self.showNotice( 'info', vhc_wc_sales_report_params.i18n_generating_csv, false );
						},
						success( response ) {
							if ( response.success ) {
								document.querySelector( '.vhc-wc-sr-form .download-url' ).setAttribute( 'href', response.data.download_url );
								document.querySelector( '.vhc-wc-sr-form .download-url' ).click();
								self.showNotice( 'success', vhc_wc_sales_report_params.i18n_csv_downloaded );
							} else {
								self.showNotice( 'error', vhc_wc_sales_report_params.i18n_something_wrong );
							}
						},
						error() {
							form.find( '[type=button]' ).attr( 'disabled', false );
							self.showNotice( 'error', vhc_wc_sales_report_params.i18n_something_wrong );
						},
						complete() {
							form.find( '[type="button"]' ).attr( 'disabled', false );
						},
					} );
				} )

				.on( 'change', '.vhc-wc-sr-form :input#setting-period', function( ) {
					self.togglePeriods( $( this ).val() );
				} );
		}

		togglePeriods( value = '' ) {
			if ( value === '' ) {
				value = this.getElement( '.vhc-wc-sr-form :input#setting-period' ).val();
			}

			if ( value === 'custom' ) {
				this.getElement( '.vhc-wc-sr-form #setting-row-date-range' ).slideDown();
			} else {
				this.getElement( '.vhc-wc-sr-form #setting-row-date-range' ).slideUp();
			}
		}
	}

	new VHC_Sales_Report();
}( jQuery ) );
