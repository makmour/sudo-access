<?php
/**
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// 1. Always Revoke/Delete all temporary users first (Safety First)
// FIX: Use prefixed variables to avoid conflicts with global $users
$sudo_access_users = get_users( array(
	'meta_key'   => '_sudowp_is_temporary',
	'meta_value' => true, // phpcs:ignore WordPress.DB.SlowDBQuery -- Meta query is required here.
) );

if ( ! empty( $sudo_access_users ) ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $sudo_access_users as $sudo_access_user ) {
		// Reassign content to Admin (ID 1) is not needed for temp users usually, 
		// but standard wp_delete_user handles cleanup.
		wp_delete_user( $sudo_access_user->ID ); 
	}
}

// 2. Check if user opted to clean up data
// FIX: Ensure option name matches what is saved in settings (sudowp_delete_data)
$sudo_access_delete_data = get_option( 'sudowp_delete_data', false );

if ( $sudo_access_delete_data ) {
	global $wpdb;

	// Drop the Logs Table
	// FIX: Prefix variable
	$sudo_access_table = $wpdb->prefix . 'sudowp_logs';
	
	// FIX: Use braces for clarity. Added phpcs:ignore because DROP TABLE cannot be prepared.
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$sudo_access_table}" );

	// Delete Options
	delete_option( 'sudowp_log_retention' );
	delete_option( 'sudowp_delete_data' );
	delete_option( 'sudowp_db_version' );
	
	// Clean up any remaining transients (wildcard deletion via direct SQL)
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sudowp_%'" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sudowp_%'" );
}