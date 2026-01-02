<?php
/*
 Plugin Name: Sudo Access
 Plugin URI: https://github.com/makmour/sudo-access
 Description: Secure temporary login & audit logging for professionals.
 Version: 0.2.0
 Author: WP Republic
 Author URI: https://wprepublic.com/
 License: GPL-2.0+
 License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 Text Domain: sudo-access
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Constants
define( 'SUDO_ACCESS_VERSION', '0.2.0' );
define( 'SUDO_ACCESS_PATH', plugin_dir_path( __FILE__ ) );
define( 'SUDO_ACCESS_URL', plugin_dir_url( __FILE__ ) );

// Autoload classes
require_once SUDO_ACCESS_PATH . 'includes/class-sudowp-auth.php';
require_once SUDO_ACCESS_PATH . 'includes/class-sudowp-logger.php';

if ( is_admin() ) {
	require_once SUDO_ACCESS_PATH . 'includes/class-sudowp-admin.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once SUDO_ACCESS_PATH . 'cli/class-sudowp-cli.php';
}

/**
 * Main Class
 */
class Sudo_Access {

	public function __construct() {
		new Sudo_Access_Auth();

		if ( is_admin() ) {
			new Sudo_Access_Admin();
		}
		
		// Scheduled Hooks
		add_action( 'sudo_access_scheduled_delete_user', array( $this, 'delete_temporary_user' ) );
		add_action( 'sudo_access_daily_maintenance', array( $this, 'process_log_retention' ) );
	}

	/**
	 * 1. Auto-delete expired users
	 */
	public function delete_temporary_user( $user_id ) {
		if ( user_can( $user_id, 'manage_options' ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery
			$is_temp = get_user_meta( $user_id, '_sudo_access_is_temporary', true );
			if ( ! $is_temp ) {
				return;
			}
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id, 1 );
		
		// Log the system action via new Logger class
		if ( class_exists( 'Sudo_Access_Logger' ) ) {
			Sudo_Access_Logger::log( 0, 'system_user_cleanup', "Automatically deleted temporary user ID: $user_id" );
		}
	}

	/**
	 * 2. Auto-purge old logs based on settings
	 */
	public function process_log_retention() {
		$retention = get_option( 'sudo_access_log_retention', 'never' );

		if ( 'never' === $retention ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'sudo_access_logs';
		$days       = 0;

		if ( 'weekly' === $retention ) {
			$days = 7;
		} elseif ( 'monthly' === $retention ) {
			$days = 30;
		}

		if ( $days > 0 ) {
			// Combined ignore for all DB warnings
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days ) );
		}
	}

	/**
	 * Activation: Setup DB & Schedule Cron
	 */
	public static function activate() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'sudo_access_logs';
		$charset_collate = $wpdb->get_charset_collate();

		// Added 'username' column for Snapshotting
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			username varchar(60) NOT NULL,
			action varchar(100) NOT NULL,
			details text,
			ip_address varchar(45) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Schedule Daily Maintenance
		if ( ! wp_next_scheduled( 'sudo_access_daily_maintenance' ) ) {
			wp_schedule_event( time(), 'daily', 'sudo_access_daily_maintenance' );
		}
	}

	/**
	 * Deactivation: Cleanup Hooks & Users
	 */
	public static function deactivate() {
		// Clean up users marked as temporary (New Meta Key)
		$users = get_users( array(
			'meta_key'   => '_sudo_access_is_temporary', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => true, // phpcs:ignore WordPress.DB.SlowDBQuery
		) );

		if ( ! empty( $users ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			foreach ( $users as $user ) {
				wp_delete_user( $user->ID, 1 );
			}
		}
		
		// Clear scheduled hooks
		wp_clear_scheduled_hook( 'sudo_access_scheduled_delete_user' );
		wp_clear_scheduled_hook( 'sudo_access_daily_maintenance' );
	}
}

new Sudo_Access();

register_activation_hook( __FILE__, array( 'Sudo_Access', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Sudo_Access', 'deactivate' ) );