<?php
/**
 * Main plugin class that handles the bulk of the Admin functions.
 * 
 * This class gives us a place to define the admin screen that holds our table,
 * and hooks for any actions, such as bulk actions, that are required for our table to function.
 */

class NTTS_List_Table_Admin {
	/**
	 * Singleton instance of class.
	 *
	 * @var NTTS_List_Table_Admin
	 */
	protected static $single_instance = null;

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @return NTTS_List_Table_Admin A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$single_instance ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Hooks
		add_action( 'admin_menu', [ $this, 'add_admin_menus' ] );
		add_action( 'admin_init', [ $this, 'do_table_actions' ] );

		/**
		 * @todo
		 */
		//add_action( 'wp_ajax__ajax_fetch_wp_list_table_events', [ $this, '_ajax_fetch_wp_list_table' ] );
	}

	/**
	 * Add an 'Event Logs' dashboard menu that also creates an admin page for table.
	 */
	public function add_admin_menus() {
		add_menu_page(
			__( 'Event Logs', 'nttslt' ),
			__( 'Event Logs', 'nttslt' ),
			'list_users',
			'ntts_event_log',
			[ $this, 'admin_screen_display_table' ],
			'dashicons-editor-table'
		);
	}

	/**
	 * Handles all table actions, i.e. bulk trash, etc.
	 */
	public function do_table_actions() {
		global $wpdb;

		if ( ! isset( $_REQUEST['page'] ) || 'ntts_event_log' != $_REQUEST['page'] ) {
			return;
		}

		$list_table = new NTTS_List_Table();

		$doaction = $list_table->current_action();
		if ( $doaction ) {
			/**
			 * Doing actions.
			 */
			
			/**
			 * Nonce added by WP_List_Table->display_tablenav('top') using plural table value,
			 * and also added by various wp_nonce_url() calls to add the nonce to URLs in action links.
			 * I treat all actions on events as bulk events, even on single events, else I would
			 * need a separate set of logic for non-bulk events such as "Undo" links and "Trash"
			 * row links.
			 */
			check_admin_referer( 'bulk-events' );

			/**
			 * Create redirect URL
			 */
			$sendback = wp_get_referer();
			if ( ! $sendback ) {
				$sendback = admin_url();
			}
			$sendback = add_query_arg( 'paged', $_REQUEST['paged'] ?? 1, $sendback );
			
			$bulk_ids = $_REQUEST['event_ids'] ?? [];

			// If trying to do a bulk action without any IDs, stop.
			if ( empty( $bulk_ids ) ) {
				wp_redirect( $sendback );
				exit;
			}

			// Continue here
			switch ( $doaction ) {
				case 'trash':
					$trashed = 0;

					foreach ( (array) $bulk_ids as $bulk_id ) {
						$result = $wpdb->update(
							$wpdb->prefix . NTTS_Plugin_List_Table_Bootstrap::DB_TABLE,
							[ 'status' => 'trash', ],
							[ 'ID' => $bulk_id, ]
						);

						if ( false === $result ) {
							wp_die( __( 'Error in moving the item to Trash.', 'nttslt' ) );
						}

						$trashed += $result;
					}

					$sendback = add_query_arg(
						[
							'trashed' => $trashed,
							'event_ids' => $bulk_ids, // Allows for "undo" if we pass the event ids back
						],
						$sendback
					);
					break;
				case 'untrash':
					$untrashed = 0;

					foreach ( (array) $bulk_ids as $bulk_id ) {
						$result = $wpdb->update(
							$wpdb->prefix . NTTS_Plugin_List_Table_Bootstrap::DB_TABLE,
							[ 'status' => 'publish', ],
							[ 'ID' => $bulk_id, ],
							[ '%s' ],
							[ '%d' ],
						);

						if ( false === $result ) {
							wp_die( __( 'Error in restoring the item from Trash.', 'nttslt' ) );
						}

						$untrashed += $result;
					}

					$sendback = add_query_arg(
						[
							'untrashed' => $untrashed,
						],
						$sendback
					);

					// If trash is now empty, go to default 'status'.
					if ( 0 == $list_table->record_count_status( 'trash' ) ) {
						$sendback = remove_query_arg( 'status', $sendback );
					}

					break;
				case 'delete':
					$deleted = 0;

					foreach ( (array) $bulk_ids as $bulk_id ) {
						$result = $wpdb->delete(
							$wpdb->prefix . NTTS_Plugin_List_Table_Bootstrap::DB_TABLE,
							[ 'ID' => $bulk_id, ],
							[ '%d' ],
						);

						if ( false === $result ) {
							wp_die( __( 'Error in deleting the item.', 'nttslt' ) );
						}

						$deleted += $result;
					}

					$sendback = add_query_arg(
						[
							'deleted' => $deleted,
						],
						$sendback
					);

					// If trash is now empty, go to default 'status'.
					if ( 0 == $list_table->record_count_status( 'trash' ) ) {
						$sendback = remove_query_arg( 'status', $sendback );
					}

					break;
			}

			$sendback = remove_query_arg( [ 'action', 'action2' ], $sendback );

			wp_redirect( $sendback );
			exit;
		} elseif ( ! empty( $_REQUEST['_wp_http_referer'] ) ) {
			// If no actions, clean up query vars.
			wp_redirect( remove_query_arg( [ '_wp_http_referer', '_wpnonce' ], wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
			exit;
		}
	}

	/**
	 * Display page on which to show table.
	 */
	public function admin_screen_display_table() {
		global $pagenow, $typenow;

		$list_table = new NTTS_List_Table();
		$list_table->prepare_items();

		?>
			<div class="wrap nosubsub">
				<h1 class="wp-heading-inline"><?= esc_html( get_admin_page_title() ); ?></h1>

				<?php
					if ( isset( $_REQUEST['s'] ) && strlen( $_REQUEST['s'] ) ) {
						echo '<span class="subtitle">';
						printf(
							/* translators: %s: Search query. */
							__( 'Search results for: %s' ),
							'<strong>' . esc_html( wp_unslash( $_REQUEST['s'] ) ) . '</strong>'
						);
						echo '</span>';
					}
				?>

				<hr class="wp-header-end">

				<?php
					/**
					 * Admin notices: Bulk messages
					 */
					$bulk_counts = [
						'deleted' => isset( $_REQUEST['deleted'] ) ? absint( $_REQUEST['deleted'] ) : 0,
						'trashed' => isset( $_REQUEST['trashed'] ) ? absint( $_REQUEST['trashed'] ) : 0,
						'untrashed' => isset( $_REQUEST['untrashed'] ) ? absint( $_REQUEST['untrashed'] ) : 0,
					];
					$bulk_messages = [
						/* translators: %s: Number of events. */
						'deleted' => _n( '%s event permanently deleted.', '%s events permanently deleted.', $bulk_counts['deleted'] ),
						/* translators: %s: Number of events. */
						'trashed' => _n( '%s event moved to the Trash.', '%s events moved to the Trash.', $bulk_counts['trashed'] ),
						/* translators: %s: Number of events. */
						'untrashed' => _n( '%s event restored from the Trash.', '%s events restored from the Trash.', $bulk_counts['untrashed'] ),
					];
					// Remove empty counts
					$bulk_counts = array_filter( $bulk_counts );

					$messages = [];
					foreach ( $bulk_counts as $message => $count ) {
						// Display the status message.
						if ( isset( $bulk_messages[ $message ] ) ) {
							$messages[] = sprintf( $bulk_messages[ $message ], number_format_i18n( $count ) );
						}

						if ( 'trashed' === $message && isset( $_REQUEST['event_ids'] ) ) {
							// If trashed post, add "Undo" link that triggers the same action as the 'untrash' action.
							$bulk_ids = $_REQUEST['event_ids'] ?? [];
							$bulk_ids_str = '';
							foreach ( $bulk_ids as $id ) {
								$bulk_ids_str .= '&event_ids[]=' . $id;
							}

							$messages[] = sprintf(
								'<a href="%1$s">%2$s</a>',
								esc_url( wp_nonce_url( "admin.php?page={$_REQUEST['page']}&action=untrash{$bulk_ids_str}", 'bulk-events' ) ),
								__( 'Undo', 'ntts' )
							);
						}
					}

					if ( $messages ) {
						wp_admin_notice(
							implode( ' ', $messages ),
							[
								'id' => 'message',
								'additional_classes' => [ 'updated' ],
								'dismissible' => true,
							]
						);
					}
					// Clean-up
					unset( $messages );
					$_SERVER['REQUEST_URI'] = remove_query_arg( [ 'deleted', 'trashed', 'untrashed' ], $_SERVER['REQUEST_URI'] );
				?>
				<!--<div id="ajax-response"></div>-->

				<form class="wp-clearfix" method="get">
					<?php $list_table->search_box( __( 'Search', 'nttslt' ), 'events' ); ?>

					<?php
						$list_table->views();
						$list_table->display();
					?>
				</form>
			</div>
		<?php
	}

	/**
	 * @todo
	 * AJAX action for the NTTS_List_Table class.
	 * 
	 * Displays the table in a way to be processed by an AJAX request.
	 */
	/*
	public function _ajax_fetch_wp_list_table() {
		$wp_list_table = new NTTS_List_Table();
		$wp_list_table->ajax_response();
	}
	*/
}

/**
 * Grab this object and return it.
 * Wrapper for NTTS_List_Table_Admin::get_instance().
 *
 * @return NTTS_List_Table_Admin  Singleton instance of class.
 */
function NTTS_List_Table_Admin() {
	return NTTS_List_Table_Admin::get_instance();
}

// Do the thing.
NTTS_List_Table_Admin();
