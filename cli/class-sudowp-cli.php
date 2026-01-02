<?php

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }

class Sudo_Access_CLI_Command extends WP_CLI_Command {

	/**
	 * Create a temporary sudo link.
	 *
	 * ## OPTIONS
	 *
	 * <username>
	 * : The username.
	 *
	 * [--email=<email>]
	 * : The email (required if creating a new user).
	 *
	 * [--role=<role>]
	 * : The role for the new user. Default: administrator.
	 *
	 * [--expiry=<hours>]
	 * : Hours until link expires. Default: 24.
	 *
	 * [--ip=<ip_address>]
	 * : Restrict login to a specific IP.
	 *
	 * ## EXAMPLES
	 *
	 * wp sudo create support_user --email=support@agency.com
	 *
	 * @when after_wp_load
	 */
	public function create( $args, $assoc_args ) {
		$username = $args[0];
		$email    = isset( $assoc_args['email'] ) ? $assoc_args['email'] : '';
		$role     = isset( $assoc_args['role'] ) ? $assoc_args['role'] : 'administrator';
		$hours    = isset( $assoc_args['expiry'] ) ? (int) $assoc_args['expiry'] : 24;
		$ip       = isset( $assoc_args['ip'] ) ? $assoc_args['ip'] : '';
		
		$seconds    = $hours * HOUR_IN_SECONDS;

		// Use the centralized Auth logic (Updated Class)
		$result = Sudo_Access_Auth::get_or_create_user( $username, $email, $role, $seconds );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( "Error: " . $result->get_error_message() );
		}

		$user = $result['user'];
		$new_user_created = $result['created_new'];

		// Generate Token
		$token = Sudo_Access_Auth::generate_token( $user->ID, $seconds, $ip );
		$link  = add_query_arg( 'sudo_token', $token, site_url() );

		// Send Email
		Sudo_Access_Auth::send_access_email( $user, $link, $hours );

		// Output
		WP_CLI::success( "Sudo Access Link Created & Emailed!" );
		WP_CLI::log( "----------------------------------------" );
		WP_CLI::log( "User: " . $user->user_login );
		WP_CLI::log( "URL: " . $link );
		WP_CLI::log( "Expires: In $hours hours" );
		if ( $new_user_created ) {
			WP_CLI::log( "Action: User will be DELETED automatically after expiry." );
		}
		WP_CLI::log( "----------------------------------------" );
	}

	/**
	 * List all active Sudo temporary users.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 * wp sudo list
	 * wp sudo list --format=json
	 *
	 * @subcommand list
	 */
	public function list_users( $args, $assoc_args ) {
		// Updated meta key
		$users = get_users( array(
			'meta_key'   => '_sudo_access_is_temporary', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => true, // phpcs:ignore WordPress.DB.SlowDBQuery
		) );

		if ( empty( $users ) ) {
			WP_CLI::warning( "No active temporary Sudo Access users found." );
			return;
		}

		$data = array();

		foreach ( $users as $user ) {
			$link = Sudo_Access_Auth::get_active_link( $user->ID );
			
			$data[] = array(
				'ID'     => $user->ID,
				'Login'  => $user->user_login,
				'Email'  => $user->user_email,
				'Role'   => implode( ', ', $user->roles ),
				'Link'   => $link ? $link : 'Expired',
			);
		}

		WP_CLI\Utils\format_items( $assoc_args['format'], $data, array( 'ID', 'Login', 'Email', 'Role', 'Link' ) );
	}

	/**
	 * Get info and active link for a specific Sudo user.
	 * * ## OPTIONS
	 * <user>
	 * : The username or email.
	 */
	public function info( $args, $assoc_args ) {
		$user_fetch = $args[0];
		
		$user = get_user_by( 'login', $user_fetch );
		if ( ! $user ) {
			$user = get_user_by( 'email', $user_fetch );
		}

		if ( ! $user ) {
			WP_CLI::error( "User not found." );
		}

		// phpcs:ignore WordPress.DB.SlowDBQuery
		$is_temp = get_user_meta( $user->ID, '_sudo_access_is_temporary', true );
		$link    = Sudo_Access_Auth::get_active_link( $user->ID );

		WP_CLI::log( "----------------------------------------" );
		WP_CLI::log( "User ID: " . $user->ID );
		WP_CLI::log( "Username: " . $user->user_login );
		WP_CLI::log( "Email: " . $user->user_email );
		WP_CLI::log( "Type: " . ( $is_temp ? "Temporary Sudo Access User" : "Standard User" ) );
		
		if ( $link ) {
			WP_CLI::log( "Active Link: " . $link );
		} else {
			WP_CLI::log( "Active Link: None / Expired" );
		}
		WP_CLI::log( "----------------------------------------" );
	}

	/**
	 * Revoke and delete a temporary Sudo user.
	 * * ## OPTIONS
	 * <user>
	 * : The username or email.
	 */
	public function revoke( $args, $assoc_args ) {
		$user_fetch = $args[0];
		
		$user = get_user_by( 'login', $user_fetch );
		if ( ! $user ) {
			$user = get_user_by( 'email', $user_fetch );
		}

		if ( ! $user ) {
			WP_CLI::error( "User not found." );
		}

		// phpcs:ignore WordPress.DB.SlowDBQuery
		$is_temp = get_user_meta( $user->ID, '_sudo_access_is_temporary', true );

		if ( ! $is_temp ) {
			WP_CLI::error( "User is NOT a temporary Sudo Access user. Cannot delete." );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		
		if ( wp_delete_user( $user->ID, 1 ) ) {
			Sudo_Access_Logger::log( 0, 'sudo_user_revoked', "CLI: Deleted temporary user ID: {$user->ID}" );
			WP_CLI::success( "Temporary user deleted." );
		} else {
			WP_CLI::error( "Failed to delete user." );
		}
	}

	/**
	 * Manually purge all Sudo Access data (Logs & Tables).
	 * WARNING: This cannot be undone. Users will be revoked.
	 *
	 * ## EXAMPLES
	 *
	 * wp sudo purge
	 *
	 * @when after_wp_load
	 */
	public function purge( $args, $assoc_args ) {
		WP_CLI::confirm( "Are you sure you want to delete ALL Sudo Access logs and database tables? Users will be revoked." );

		global $wpdb;

		// 1. Revoke Users
		$users = get_users( array( 
			'meta_key'   => '_sudo_access_is_temporary', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => true // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		
		$count = 0;
		if ( ! empty( $users ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			foreach ( $users as $user ) {
				wp_delete_user( $user->ID, 1 );
				$count++;
			}
		}
		WP_CLI::log( "Revoked " . $count . " temporary users." );

		// 2. Drop Tables
		$table_name = $wpdb->prefix . 'sudo_access_logs';
		
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		WP_CLI::log( "Dropped logs table." );

		WP_CLI::success( "System purged successfully." );
	}
}

WP_CLI::add_command( 'sudo', 'Sudo_Access_CLI_Command' );