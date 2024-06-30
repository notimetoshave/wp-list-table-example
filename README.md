# Extending WP_List_Table, a Comprehensive Example

I wrote this plugin as a way to provide a base for extending the WP_List_Table class. I've found many examples online but most of them fell short in one way or another, whether by having bugs, not properly explaining what's happening, too much abstraction, and other issues that just left me wanting to know more.

## Plugin Highlights
- Uses a custom database table that demonstrates a `JOIN` on your `wp_users` table
- Has working row checkboxes and bulk actions for Trash, Restore, and Delete
- Demonstrates row actions, which are those links that appear in the first column when you hover over the row
- Has filters, pagination, and search functionality
- Can be AJAX-enabled for some actions like sorting, filtering, and pagination.

## Installation
- Download a ZIP of this repository.
- Inside of your WordPress dashboard, go to "Plugins > Add New Plugin > Upload Plugin" then drag this ZIP into your browser.
- Activate the plugin.
- There should now be a "NTTS: Events Log" menu item in your dashboard which will let you demo the table functionality.

## Details
- On activation, a custom table `[prefix]_ntts_sample_events` will be created and populated with random data that is loosely modeled on an Event Logger. The table will be deleted on deactivation and nothing outside of this custom table will be altered. If you deactivate the plugin but the custom table is still there, you can delete the table without hurting anything else. The table will be remade on activation.
- The custom table contains a relationship column to WordPress users via IDs to demonstrate SQL `JOIN` statements. It picks up to five random user IDs from user accounts that exist in your install to show the relationships. If you want to be able to use the "User" filter and you only have one WordPress user account in your installation, you can make a couple of additional users before activating the plugin. Any role is fine.
- Records can be trashed, restored, and deleting mainly as a way to demo multiple Views and Filters. There are no other "Editing" capabilities for records as that falls outside of the scope of what I was wanting to demo.
- AJAX can be enabled/disabled by editing the `ajax` value in the `NTTS_Events_Table` constructor. Currently column sorting, pagination, and filters will work without reloading the page while AJAX is enabled. I may add more later.
 
## Files
- `ntts-list-table.php`: Main plugin bootstrap file. Handles activation/deactivation but nothing super exciting.
- `class-ntts-events-table-admin.php`: The file that creates the framework for the table. This includes creating an admin page in which the table lives, displaying the table, and handling update and AJAX hooks. I tried to keep this as minimal as possible, so it does not contain all functionality that you can do on an admin page, just the stuff relevant for the table.
- `class-ntts-events-table.php`: The table class that extends the `WP_List_Item` class and contains all table-related stuff. "Official" methods that override the `WP_List_Item` class have a `@see @see WP_List_Table->[method]` comment in the DocBlock. All other methods, well, you can consider as helper/utility methods. I tried to document these as clearly as possible.
- `class-ntts-wp-list-table.js`: The JavaScript class that handles the AJAX stuff.