<?php
/**
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// 1. Always Revoke/Delete all temporary users first
$sudo_access_users = get_users( array(
	'meta_key'   => '_sudowp_is_temporary',
	'meta_value' => true, // phpcs:ignore WordPress.DB.SlowDBQuery
) );

if ( ! empty( $sudo_access_users ) ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $sudo_access_users as $sudo_access_user ) {
		wp_delete_user( $sudo_access_user->ID );
	}
}

// 2. Check if user opted to clean up data
$sudo_access_delete_data = get_option( 'sudowp_delete_data', false );

if ( $sudo_access_delete_data ) {
	global $wpdb;

	$sudo_access_table = $wpdb->prefix . 'sudowp_logs';

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DROP TABLE IF EXISTS {$sudo_access_table}" );

	delete_option( 'sudowp_log_retention' );
	delete_option( 'sudowp_delete_data' );
	delete_option( 'sudowp_db_version' );
}