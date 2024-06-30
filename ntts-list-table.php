<?php
/*
 * Plugin Name: NTTS: Extending WP_List_Table Example
 * Description: A WordPress plugin that demonstrates how to extend and use the WP_List_Table class on data in a custom database table.
 * Text Domain: nttslt
 * Author: Phillip Dodson @ No Time To Shave
 * Author URI: https://notimetoshave.com
 */

/**
 * Class that bootstraps the plugin. This is intentionally kept minimal.
 */
final class NTTS_Plugin_List_Table_Bootstrap
{
	/**
	 * The database table for the dummy data.
	 */
	const DB_TABLE = 'ntts_sample_events';

	/**
	 * Callback for register_activation_hook().
	 * 
	 * Creates a custom table and populates that table
	 * with random dummy data that we can use to
	 * populate our WP_List_Table table.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public static function create_dummy_data() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::DB_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "
			CREATE TABLE {$table_name} (
				ID bigint(20) NOT NULL AUTO_INCREMENT,
				wp_user bigint(20) NOT NULL,
				date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				event varchar(255) DEFAULT NULL,
				status varchar(20) DEFAULT 'publish' NOT NULL,
				PRIMARY KEY  (ID),
				KEY wp_user (wp_user),
				KEY event (event)
			) {$charset_collate};";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		/**
		 * Create random dummy data.
		 */
		
		// Get an assortment of actual users that we can JOIN against.
		$data_user_ids = get_users( [
			'number' => 5,
			'fields' => 'ID',
		] );

		// Sample events.
		$data_events = [
			'User logged in.',
			'User logged out.',
			'User viewed post.',
			'User deleted post.',
			'User created a new post.',
			'User edited a post.',
		];

		$num_entries = 350;
		for ( $i = 0; $i < $num_entries; $i++ ) {
			$wpdb->insert(
				$table_name,
				[
					// Random user ID.
					'wp_user' => (int) $data_user_ids[ array_rand( $data_user_ids ) ],
					// Random date between a year ago from now and now.
					'date' => date(
						'Y-m-d H:i:s',
						mt_rand(
							strtotime( date( 'Y-m-d', strtotime( '-1 year' ) ) ),
							strtotime( date( 'Y-m-d' ) )
						)
					),
					// Random event.
					'event' => $data_events[ array_rand( $data_events ) ],
				]
			);
		}
	}

	/**
	 * Callback for register_deactivation_hook().
	 *
	 * Deletes the custom table.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public static function delete_dummy_data() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::DB_TABLE;
		$sql = "DROP TABLE IF EXISTS $table_name";
		$wpdb->query( $sql );
	}
}

register_activation_hook( __FILE__, [ 'NTTS_Plugin_List_Table_Bootstrap', 'create_dummy_data' ] );
register_deactivation_hook( __FILE__, [ 'NTTS_Plugin_List_Table_Bootstrap', 'delete_dummy_data' ] );

// Admin. functions for the table.
require_once 'class-ntts-events-table-admin.php';

// Custom table class that extends WP_List_Table.
require_once 'class-ntts-events-table.php';
