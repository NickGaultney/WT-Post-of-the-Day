<?php
/**
 * This file runs when the plugin in uninstalled (deleted).
 * This will not run when the plugin is deactivated.
 * Ideally you will add all your clean-up scripts here
 * that will clean-up unused meta, options, etc. in the database.
 *
 * @package WordPress Plugin Template/Uninstall
 */

// If plugin is not being uninstalled, exit (do nothing).
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load plugin class files.
require_once 'includes/class-wt-post-of-the-day.php';

// Do something here if plugin is being uninstalled.
$base = 'wt_';
delete_option($base . 'potd_category');
delete_option($base . 'potd_time_central_standard_time');
delete_option($base . 'potd_newsletter_list');

delete_transient('wt_potd_title');
delete_transient('wt_potd_content');

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS " . WT_Post_of_the_Day::table_name() );