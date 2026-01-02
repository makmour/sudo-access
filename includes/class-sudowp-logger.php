<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Sudo_Access_Logger {

	public static function log( $user_id, $action, $details = '' ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'sudo_access_logs';

		$ip = '';
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		} elseif ( defined( 'WP_CLI' ) && WP_CLI ) {
			$ip = 'CLI';
		}

		// --- SNAPSHOT USERNAME ---
		// Find the username NOW, so we have it even if the user is deleted later.
		$username = 'Unknown';
		if ( 0 === $user_id ) {
			$username = 'System';
		} else {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$username = $user->user_login;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table_name,
			array(
				'user_id'    => $user_id,
				'username'   => sanitize_user( $username ), // Save permanently
				'action'     => sanitize_text_field( $action ),
				'details'    => sanitize_textarea_field( $details ),
				'ip_address' => $ip
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
	}
}