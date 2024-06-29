<?php
/**
 * 
 * Some of these features are inspired by:
 * @link https://blog.caercam.org/2014/04/03/a-way-to-implement-ajax-in-wp_list_table/
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class NTTS_List_Table extends WP_List_Table {
	
	/**
	 * Table name that contains data.
	 *
	 * All core classes that extend WP_List_Table use core functions
	 * (get_posts(), get_users() etc.) to query data, but we're
	 * running queries inside of here, so store a reference to the table.
	 */
	const DB_TABLE = NTTS_Plugin_List_Table_Bootstrap::DB_TABLE;

	/**
	 * Constructor.
	 * 
	 * @see WP_List_Table.__construct()
	 *
	 * @param array|string $args {
	 *     Array or string of arguments.
	 *
	 *     @type string $plural   Plural value used for labels and the objects being listed.
	 *                            This affects things such as CSS class-names and nonces used
	 *                            in the list table, e.g. 'posts'. Default empty.
	 *     @type string $singular Singular label for an object being listed, e.g. 'post'.
	 *                            Default empty
	 *     @type bool   $ajax     Whether the list table supports Ajax. This includes loading
	 *                            and sorting data, for example. If true, the class will call
	 *                            the _js_vars() method in the footer to provide variables
	 *                            to any scripts handling Ajax events. Default false.
	 *     @type string $screen   String containing the hook name used to determine the current
	 *                            screen. If left null, the current screen will be automatically set.
	 *                            Default null.
	 * }
	 */
	public function __construct( $args = [] ) {
		parent::__construct( [
			'plural' => 'events',
			'singular' => 'event',
			//'ajax' => true,
			//'screen' => 'nttslt_screen_events',
		] );
	}

	/**
	 * Prepares the list of items for displaying.
	 * 
	 * @see WP_List_Table->prepare_items()
	 */
	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden = get_hidden_columns( $this->screen );
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = [ $columns, $hidden, $sortable ];

		$this->set_pagination_args( [
			'total_items' => $this->record_count(),
			'per_page' => $this->get_items_per_page( 'ntts_per_page', 20 ),
			// Set ordering for AJAX
			'orderby' => ! empty( $_REQUEST['orderby'] ) ? sanitize_title( $_REQUEST['orderby'] ) : '',
			'order' => ! empty( $_REQUEST['order'] ) ? sanitize_title( $_REQUEST['order'] ) : '',
		] );

		$this->items = $this->get_entries();
	}

	/**
	 * Gets a list of columns.
	 *
	 * The format is:
	 * - `'internal-name' => 'Title'`
	 *
	 * @see WP_List_Table->get_columns()
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = [
			'cb' => '<input type="checkbox">',
			'date' => __( 'Date', 'nttslt' ),
			'wp_user' => __( 'User', 'nttslt' ),
			'event' => __( 'Event', 'nttslt' ),
		];

		return $columns;
	}

	/**
	 * Columns to make sortable.
	 * 
	 * This is not the most intuitive function. This controls the UI of the sortable column headers.
	 * It does not, by itself, do anything with the table ordering until the headers
	 * are clicked. To make the initial state of the UI reflect the default ordering
	 * of the query in $this->get_entries(), supply proper values here to match the query.
	 * 
	 * Here is how I believe that these values work:
	 * 
	 *     @var string 'column' => [
	 *         @var string      ORDER BY value added to the header sorting URL query string in the href attribute.
	 *         @var bool|string ORDER value added to the header sorting URL query string in the href attribute.
	 *         @var string      The 'abbr' attribute added to the <th> tag.
	 *                          ex: If your column label is 'Lang.' then the 'abbr' attribute could be 'Language'.
	 *         @var string      ORDER BY text. The table has a hidden .screen-reader-only caption that is used
	 *                          to communicate the table's current sorting. This string represents this column.
	 *         @var string      The CSS class applied to the <th> tag that should match any default sorting values.
	 *                          This makes the UI reflect the default and is case sensitive because CSS
	 *                          classes are case sensitive. 'desc' | 'asc'
	 * ]
	 *
	 * @see WP_List_Table->get_sortable_columns()
	 * 
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = [
			'date' => [ 'date', 'asc', '', __( 'Date', 'nttslt' ), 'desc' ],
			'event' => [ 'event', 'asc' ],
			'wp_user' => [ 'wp_user', 'asc' ],
		];

		return $sortable_columns;
	}

	/**
	 * Retrieve database entries.
	 * 
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return mixed
	 */
	public function get_entries() {
		global $wpdb;

		$per_page = $this->get_pagination_arg( 'per_page' );

		/**
		 * Create base SQL query.
		 */
		$sql = $wpdb->prepare(
			'SELECT
				%i.`ID`, `date`, `event`, `user_login` AS `wp_user`
			FROM
				%i
			LEFT JOIN
				%i ON %i.`wp_user`=%i.`ID`',
			$wpdb->prefix . self::DB_TABLE,
			$wpdb->prefix . self::DB_TABLE,
			$wpdb->users,
			$wpdb->prefix . self::DB_TABLE,
			$wpdb->users
		);

		/**
		 * Add WHERE clause.
		 */
		$sql .= $this->sql_where();

		/**
		 * Add ORDER BY/ORDER clause.
		 * 
		 * Because these requests aren't being filtered through core functions and are user-supplied,
		 * check any supplied values against allowed values to prevent tampering.
		 *
		 * We're defaulting to 'date' and 'asc' so make sure that your get_sortable_columns()
		 * value for 'date' reflects this.
		 */
		$allowed_orderby = $this->get_sortable_columns();
		$allowed_order = ['asc', 'desc'];

		// Default to 'date'
		$orderby = ! empty( $_REQUEST['orderby'] ) && array_key_exists( $_REQUEST['orderby'], $allowed_orderby ) ? $_REQUEST['orderby'] : 'date';
		// Default to 'desc'
		$order = ! empty( $_REQUEST['order'] ) && in_array( $_REQUEST['order'], $allowed_order ) ? $_REQUEST['order'] : 'desc';

		$sql .= $wpdb->prepare(
			' ORDER BY %i ' . strtoupper( $order ),
			[
				$orderby
			]
		);	

		/**
		 * Add LIMIT and OFFSET clauses for pagination.
		 */
		$sql .= " LIMIT {$per_page}";
		$sql .= ' OFFSET ' . ( $this->get_pagenum() - 1 ) * $per_page;

		//echo $sql;

		return $wpdb->get_results( $sql, 'ARRAY_A' );
	}

	/**
	 * Get the total record count.
	 * 
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return string|null
	 */
	public function record_count() {
		global $wpdb;

		/**
		 * Create base SQL query.
		 */
		$sql = $wpdb->prepare(
			'SELECT
				COUNT(*)
			FROM
				%i
			LEFT JOIN
				%i ON %i.`wp_user`=%i.`ID`',
			$wpdb->prefix . self::DB_TABLE,
			$wpdb->users,
			$wpdb->prefix . self::DB_TABLE,
			$wpdb->users
		);

		// Get WHERE
		$sql .= $this->sql_where();
		
		return $wpdb->get_var( $sql );
	}

	/**
	 * Get the record count per status, independent of any query vars.
	 * 
	 * @param null|string $status Which status to use. null is all, 'publish' | 'trash'
	 * @return string|null
	 */
	public function record_count_status( $status = null ) {
		global $wpdb;

		$allowed_status = [
			'publish',
			'trash',
		];

		$sql = $wpdb->prepare(
			'SELECT
				COUNT(*)
			FROM
				%i',
			$wpdb->prefix . self::DB_TABLE,
		);

		if ( in_array( $status, $allowed_status ) ) {
			$sql .= $wpdb->prepare( 'WHERE `status` = %s', $status );
		}

		return $wpdb->get_var( $sql );
	}

	/**
	 * Create the base WHERE SQL clause.
	 * 
	 * $this->get_entries() and $this->record_count() use the same
	 * WHERE clause, so instead of duplicating all of the conditions,
	 * both methods pull the WHERE from here.
	 * 
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * 
	 * @return string SQL
	 */
	private function sql_where() {
		global $wpdb;

		$where = ' WHERE 1=1';

		// View: status
		$status = $_REQUEST['status'] ?? 'publish';
		$where .= $wpdb->prepare(
			' AND `status`=%s',
			$status
		);

		$search = ! empty( $_REQUEST['s'] ) ? trim( wp_unslash( $_REQUEST['s'] ) ) : '';
		if ( ! empty( $search ) ) {
			$where .= $wpdb->prepare(
				' AND ( %i.`user_login` LIKE %s
					OR `event` LIKE %s )',
				$wpdb->users,
				'%' . $wpdb->esc_like( $search ) . '%',
				'%' . $wpdb->esc_like( $search ) . '%'
			);
		}

		// Filter: user
		if ( ! empty( $_REQUEST['filter_user'] ) && $_REQUEST['filter_user'] != '-1' ) {
			$where .= $wpdb->prepare(
				' AND `user_login`=%s',
				$_REQUEST['filter_user']
			);
		}

		// Filter: event
		if ( ! empty( $_REQUEST['filter_event'] ) && $_REQUEST['filter_event'] != '-1' ) {
			$where .= $wpdb->prepare(
				' AND `event`=%s',
				$_REQUEST['filter_event']
			);
		}

		return $where;
	}

	/**
	 * Text to display when no records are available.
	 * 
	 * @see WP_List_Table->no_items()
	 */
	public function no_items() {
		_e( 'No events available.', 'nttslt' );
	}

	/**
	 * Render a column when no column-specific method exist.
	 * 
	 * @see WP_List_Table->column_default()
	 *
	 * @param object|array $item        Row of data.
	 * @param string       $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'wp_user':
			case 'event':
				return $item[ $column_name ];
			default:
				return print_r( $item, true ); // To make it obvious that a column was missed.
		}
	}

	/**
	 * Get the value for the 'date' column.
	 * 
	 * Note that all method names beginning with 'column_*' are dynamic
	 * with the second part of the method matching the column key.
	 * If a dynamic method doesn't exist for a column, the value
	 * will come from $this->column_default().
	 * 
	 * @see WP_List_Table->single_row_columns() conditionals
	 *
	 * @param array $item Row of data. Contains the full row, not just this column.
	 * @return string
	 */
	protected function column_date( $item ) {
		$date_string = $item[ 'date' ];
		$date_time = DateTime::createFromFormat( 'Y-m-d H:i:s', $date_string );
		$date_formatted = $date_time->format( 'D, M j, Y @ g:i a' );

		return $date_formatted;
	}

	/**
	 * Get the value for the checkbox column.
	 */
	protected function column_cb( $item ) {
		if ( current_user_can( 'edit_users' ) ) {
			$date_time = DateTime::createFromFormat( 'Y-m-d H:i:s', $item['date'] );

			return sprintf(
				'<input type="checkbox" name="%1$s[]" value="%2$s" id="cb-select-%2$s">' .
				'<label for="cb-select-%2$s"><span class="screen-reader-text">%3$s</span></label>',
				$this->_args['singular'] . '_ids',
				$item['ID'],
				/* translators: Hidden accessibility text. %1$s: Date. %2$s User. */
				sprintf( 
					__( 'Select event on %s for %s', 'nttslt' ),
					$date_time->format( 'D, M j, Y' ),
					$item['wp_user'],
				)
			);
		}

		return '&nbsp;';
	}

	/**
	 * Generates content for a single row of the table.
	 *
	 * There's a bug with the bulk select checkbox where they won't work correctly
	 * if the rows don't have an 'iedit' class. Check for the 'cb' column and if it
	 * exists, add the 'iedit' class, else call the parent method.
	 * 
	 * @link https://wordpress.stackexchange.com/a/425192/124494
	 *
	 * @see WP_Posts_List_Table()->single_row()
	 *
	 * @param object|array $item The current item
	 */
	public function single_row( $item ) {
		if ( array_key_exists( 'cb', $this->get_columns() ) ) {
			echo '<tr class="iedit">';
			echo $this->single_row_columns( $item );
			echo '</tr>';
		} else {
			parent::single_row( $item );
		}
	}

	/**
	 * Gets the list of views available on this table.
	 * 
	 *  * The format is an associative array:
	 * - `'id' => 'link'`
	 * 
	 * See the "All (10) | Published (9) | Draft (1) …" links above the Posts table for an example.
	 * If implemented, will need to have args accounted for in $this->sql_where()
	 * 
	 * @see WP_Posts_List_Table()->get_views()
	 * 
	 * @return array {}
	 */
	protected function get_views() {
		$views = [];

		$allowed_status = [
			'publish',
			'trash',
		];

		$current_url = admin_url( 'admin.php?page=' . $_REQUEST['page'] );
		$current_status = isset( $_REQUEST['status'] ) && in_array( $_REQUEST['status'], $allowed_status ) ? $_REQUEST['status'] : 'publish';
		
		// All view
		$status = 'publish';
		$count = $this->record_count_status( $status );
		$views[ $status ] = [
			'url' => $current_url,
			'label' => sprintf(
				/* translators: %d: Number of events. */
				_nx(
					'All <span class="count">(%d)</span>',
					'All <span class="count">(%d)</span>',
					$count,
					'events',
					'nttslt'
				),
				number_format_i18n( $count )
			),
			'current' => $current_status === $status,
		];

		// Trash view
		$status = 'trash';
		$count = $this->record_count_status( $status );
		if ( $count > 0 ) {
			$views[ $status ] = [
				'url' => add_query_arg(
					[
						'status' => $status
					],
					$current_url
				),
				'label' => sprintf(
					/* translators: %d: Number of events. */
					_nx(
						'Trashed <span class="count">(%d)</span>',
						'Trashed <span class="count">(%d)</span>',
						$count,
						'events',
						'nttslt'
					),
					number_format_i18n( $count )
				),
				'current' => $current_status === $status,
			];
		}

		return $this->get_views_links( $views );
	}

	/**
	 * Displays extra controls between bulk actions and pagination.
	 * 
	 * See the "All dates" and "All Categories" filters above the Posts table for an example.
	 * 
	 * @see WP_Posts_List_Table()->extra_tablenav()
	 * 
	 * @param string $which Position of nav relative to table. 'top' | 'bottom'
	 */
	protected function extra_tablenav( $which ) {
		echo '<div class="alignleft actions">';

		ob_start();

		if ( 'top' === $which ) {
			$this->users_dropdown();
			$this->events_dropdown();
		}

		$output = ob_get_clean();

		if ( ! empty( $output ) ) {
			echo $output;
			submit_button( __( 'Filter', 'nttslt' ), '', 'filter_action', false, [ 'id' => 'post-query-submit' ] );
		}

		echo '</div>';
	}

	/**
	 * Retrieves the list of bulk actions available for this table.
	 *
	 * The format is an associative array where each element represents either a top level option value and label, or
	 * an array representing an optgroup and its options.
	 *
	 * For a standard option, the array element key is the field value and the array element value is the field label.
	 *
	 * For an optgroup, the array element key is the label and the array element value is an associative array of
	 * options as above.
	 *
	 * Example:
	 *
	 *     [
	 *         'edit'         => 'Edit',
	 *         'delete'       => 'Delete',
	 *         'Change State' => [
	 *             'feature' => 'Featured',
	 *             'sale'    => 'On Sale',
	 *         ]
	 *     ]
	 *
	 * @see WP_Posts_List_Table()->get_bulk_actions()
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		$actions = [];

		$allowed_status = [
			'publish',
			'trash',
		];

		$current_status = isset( $_REQUEST['status'] ) && in_array( $_REQUEST['status'], $allowed_status ) ? $_REQUEST['status'] : 'publish';

		if ( $current_status === 'trash' ) {
			$actions['untrash'] = __( 'Restore', 'nttslt' );
			$actions['delete'] = __( 'Delete permanently', 'nttslt' );
		} else {
			$actions['trash'] = __( 'Move to Trash', 'nttslt' );
		}

		return $actions;
	}

	/**
	 * Displays a dropdown for filtering items in the list table by users.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	protected function users_dropdown() {
		global $wpdb;
		
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DISTINCT
					`user_login`
				FROM 
					%i
				LEFT JOIN
					%i ON %i.`wp_user`=%i.`ID`
				ORDER BY
					`user_login` ASC',
				$wpdb->prefix . self::DB_TABLE,
				$wpdb->users,
				$wpdb->prefix . self::DB_TABLE,
				$wpdb->users
			),
			'ARRAY_A'
		);
		$count = count( $entries );

		if ( ! $count || ( 1 == $count ) ) {
			return;
		}
		
		$filter_user = isset( $_REQUEST['filter_user'] ) ? $_REQUEST['filter_user'] : '-1';
		?>
		<label for="filter-by-user" class="screen-reader-text"><?= __( 'Filter by user', 'nttslt' ); ?></label>
		<select name="filter_user" id="filter-by-user">
			<option<?php selected( $filter_user, '-1' ); ?> value="-1"><?php _e( 'All users', 'nttslt' ); ?></option>
			<?php
				foreach ( $entries as $entry ) {
					printf(
						"<option %s value='%s'>%s</option>\n",
						selected( $filter_user, $entry['user_login'], false ),
						esc_attr( $entry['user_login'] ),
						$entry['user_login']
					);
				}
			?>
		</select>
		<?php
	}

	/**
	 * Displays a dropdown for filtering items in the list table by events.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	protected function events_dropdown() {
		global $wpdb;
		
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DISTINCT
					`event`
				FROM 
					%i
				ORDER BY
					`event` ASC',
				$wpdb->prefix . self::DB_TABLE,
			),
			'ARRAY_A'
		);
		$count = count( $entries );

		if ( ! $count || ( 1 == $count ) ) {
			return;
		}
		
		$filter_event = isset( $_REQUEST['filter_event'] ) ? $_REQUEST['filter_event'] : '-1';
		?>
		<label for="filter-by-event" class="screen-reader-text"><?= __( 'Filter by event', 'nttslt' ); ?></label>
		<select name="filter_event" id="filter-by-event">
			<option<?php selected( $filter_event, '-1' ); ?> value="-1"><?php _e( 'All events', 'nttslt' ); ?></option>
			<?php
				foreach ( $entries as $entry ) {
					printf(
						"<option %s value='%s'>%s</option>\n",
						selected( $filter_event, $entry['event'], false ),
						esc_attr( $entry['event'] ),
						$entry['event']
					);
				}
			?>
		</select>
		<?php
	}

	/**
	 * Generates and displays row action links.
	 * 
	 * @param array $item         Post being acted upon.
	 * @param string $column_name Current column name.
	 * @param string $primary     Primary column name.
	 * @return string Row actions output for posts, or an empty string
	 *                if the current column is not the primary column.
	 * 
	 * @see WP_List_Table->handle_row_actions() 
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		//var_dump($item);
		if ( $primary !== $column_name ) {
			return '';
		}

		$actions = [];

		$actions['trash'] = sprintf(
			'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
			esc_url( wp_nonce_url( "admin.php?page={$_REQUEST['page']}&action=trash&event_ids[]={$item['ID']}", 'bulk-events' ) ),
			/* translators: %s: Post title. */
			esc_attr( sprintf( __( 'Move &#8220;%s&#8221; to the Trash' ), '$title' ) ),
			_x( 'Trash', 'verb' )
		);

		return $this->row_actions( $actions );
	}

	/**
	 * Displays the table.
	 * 
	 * @see WP_List_Table->display()
	 */
	public function display() {
		/**
		 * Add additional values to the form, useful for pagination, AJAX, etc.
		 * We're implementing Javascript AJAX features so we need a way to grab
		 * values that wouldn't normally exist in the form. This allows us to use just
		 * the form data to build the fetch() request and not have to pull some
		 * data from the URL query string.
		 */

		// Nonce
		//wp_nonce_field( 'nttslt-fetch-wp-list-table-events', '_wpnonce_nttslt-fetch-wp-list-table-events' );
		
		// Add page.
		echo '<input type="hidden" name="page" value="' . esc_attr( sanitize_title( $_REQUEST['page'] ) ) . '">';

		// Add Views status.
		echo '<input type="hidden" name="status" value="' . esc_attr( sanitize_title( $_REQUEST['status'] ?? 'publish' ) ) . '">';
		
		echo '<input type="hidden" name="order" value="' . $this->_pagination_args['order'] . '">';
		echo '<input type="hidden" name="orderby" value="' . $this->_pagination_args['orderby'] . '">';
		
		parent::display();
	}

	/**
	 * @todo
	 * Handles an incoming ajax request (called from admin-ajax.php)
	 * 
	 * This rebuilds the table as HTML then converts it to JSON to be processed via Javascript.
	 * Ideally we wouldn't use output buffer, but we'd have to extend all functions to get output
	 * proper for an AJAX response, so this ends up being a bit of a cleaner solution.
	 * 
	 * @see WP_List_Table->ajax_response()
	 */
	/*
	public function ajax_response() {
		// Validate nonce
		check_ajax_referer( 'nttslt-fetch-wp-list-table-events', '_wpnonce_nttslt-fetch-wp-list-table-events' );

		$this->prepare_items();
		
		ob_start();

		// Get rows
		if ( ! empty( $_REQUEST['no_placeholder'] ) ) {
			$this->display_rows();
		} else {
			$this->display_rows_or_placeholder();
		}
		$rows = ob_get_clean();

		// Get column headers
		ob_start();
		$this->print_column_headers();
		$headers = ob_get_clean();

		// Get top pagination
		ob_start();
		$this->pagination( 'top' );
		$pagination_top = ob_get_clean();

		// Get bottom pagination
		ob_start();
		$this->pagination( 'bottom' );
		$pagination_bottom = ob_get_clean();

		// Build response.
		$response = [
			'rows' => $rows,
			'pagination' => [
				'top' => $pagination_top,
				'bottom' => $pagination_bottom,
			],
			'column_headers' => $headers,
		];

		// Do counts
		if ( isset( $this->_pagination_args['total_items'] ) ) {
			$response['total_items_i18n'] = sprintf( _n( '1 item', '%s items', $this->_pagination_args['total_items'] ), number_format_i18n( $this->_pagination_args['total_items'] ) );
		}

		if ( isset( $this->_pagination_args['total_pages'] ) ) {
			$response['total_pages'] = $this->_pagination_args['total_pages'];
			$response['total_pages_i18n'] = number_format_i18n( $this->_pagination_args['total_pages'] );
		}

		// Output JSON
		die( json_encode( $response ) );
	}
	*/
}
