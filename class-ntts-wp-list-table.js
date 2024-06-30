/**
 * AJAX functionality for the table.
 *
 * I tried to write this class so that you could reuse it for any class
 * that follows the pattern of the NTTS_Events_Table class.
 * I didn't want to have to write a custom version of this script for each table.
 */
( function() {
	class NTTS_WPListTable {
		// Reference to the form element that contains the table.
		el;
		
		// Timer for setTimeout
		timer;
		
		// Object that holds data to be sent via JavaScript
		searchArgs;

		constructor( el ) {
			// Save element.
			this.el = el;

			// Delegated 'click' event.
			this.el.addEventListener( 'click', event => {
				const anchor = event.target.closest( 'a' );
				
				/**
				 * Applicable Links.
				 *
				 * Link href attributes contain all needed info for AJAX queries,
				 * so we just need to supply the query to the endpoint.
				 * Adding the Views links here caused bugs with other link generation
				 * such as row actions that I didn't have time to dig into so 
				 * those will stay as non-AJAX links.
				 *
				 *     .tablenav-pages a = Pagination first/prev/next/last links.
				 *     .manage-column a  = Sortable column header link.
				 */
				if ( anchor && anchor.matches( '.tablenav-pages a, .manage-column.sortable a, .manage-column.sorted a' ) ) {
					// Any of the above links.
					event.preventDefault();
					this.PrepareFetch_FromLink( anchor.href );
				} else if ( event.target.matches( 'input[name="filter_action"]' ) ) {
					// Filter button.
					event.preventDefault();
					this.PrepareFetch_FromSubmit();
				}
			} );

			// Delegated 'keyup' event.
			this.el.addEventListener( 'keyup', event => {
				if ( event.target.matches( 'input[name=paged]' ) ) {
					// If user hits enter, we don't want to submit the form since this.timer will do that automatically.
					if ( 13 == event.which ) {
						event.preventDefault();
					}

					clearTimeout( this.timer );
					this.timer = setTimeout( () => {						
						this.PrepareFetch_FromSubmit( true );
					}, 500 );
				}
			} );
		}

		/**
		 * Set searchArgs based on all form inputs.
		 *
		 * Because we're using all form inputs to init this.searchArgs,
		 * we should remove unnecessary/noisy values.
		 * 
		 * @param bool isPaged If the form is not submitted from the
		 *                     paged (custom page input) text field.
		 */
		PrepareFetch_FromSubmit( isPaged = false ) {
			this.searchArgs = new URLSearchParams( new FormData( this.el ) );

			// Clean up request
			this.searchArgs.delete( 'page' ); // Causes 403 if left in place.
			this.searchArgs.delete( 'ajax_hook' );
			this.searchArgs.delete( '_wp_http_referer' );
			this.searchArgs.delete( '_wpnonce' );
			this.searchArgs.delete( 'action2' );
			if ( ! isPaged ) {
				this.searchArgs.delete( 'paged' );
			}

			this.Fetch();
		}

		/**
		 * Set searchArgs based on link URL.
		 *
		 * Because of the way the links are generated, we can simply pass most
		 * of those query values directly to the script.
		 * 
		 * @param string url Href attribute of the link that was clicked.
		 */
		PrepareFetch_FromLink( url ) {
			this.searchArgs = new URLSearchParams( url.split( '?' )[1] );

			// Clean up request
			this.searchArgs.delete( 'page' ); // Causes 403 if left in place.
			
			this.Fetch();
		}

		/**
		 * Performs a GET fetch() request using the data in this.searchArgs.
		 */
		async Fetch() {			
			// Nonce
			const nonce_field = this.el.querySelector( 'input[type="hidden"][name^="_wpnonce_fetch"]' );
			this.searchArgs.set( nonce_field.name, nonce_field.value );

			// AJAX Hook
			this.searchArgs.set( 'action', 'fetch_table_' + this.el.querySelector( 'input[name="ajax_hook"]' ).value );

			try {
				const response = await fetch( ajaxurl + '?' + this.searchArgs.toString() );
				if ( ! response.ok ) {
					throw new Error( 'Network response was not OK' );
				}
				const data = await response.json();
				this.UpdateDOM( data );
			} catch ( error ) {
				console.error( 'There has been a problem with your fetch operation:', error );
			}
		}

		/**
		 * Update the DOM using data received from fetch() requests.
		 * 
		 * @param object data Object created from JSON response.
		 */
		UpdateDOM( data ) {
			// Add the new rows.
			if ( data.rows.length ) {
				document.getElementById('the-list').innerHTML = data.rows;
			}

			// Update column headers for sorting.
			if ( data.column_headers.length ) {
				document.querySelector('thead tr, tfoot tr').innerHTML = data.column_headers;
			}

			// Update pagination in navigation.
			if ( data.pagination.bottom.length ) {
				document.querySelector('.tablenav.top .tablenav-pages').innerHTML = data.pagination.top;
			}
			
			if ( data.pagination.top.length ) {
				document.querySelector('.tablenav.bottom .tablenav-pages').innerHTML = data.pagination.bottom;
			}
		}
	}
	
	/**
	 * Instantiate class if the table is found and 'ajax' => true for the table
	 */
	const nttsListTable = document.querySelector( '.js-ntts-wp-list-table' );
	if ( nttsListTable && window.list_args ) {
		/**
		 * Table is found and is using AJAX.
		 * window.list_args will be set if the table is constructed with 'ajax' => true.
		 */
		new NTTS_WPListTable( nttsListTable );
	}
} )()
