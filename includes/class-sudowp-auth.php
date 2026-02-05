<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Sudo_Access_Auth {

	public function __construct() {
		// Listen for the sudo link parameter
		add_action( 'init', array( $this, 'handle_sudo_login' ) );
	}

	/**
	 * Helper: Find existing user or create a temporary one.
	 */
	public static function get_or_create_user( $username, $email = '', $role = 'administrator', $expiry_seconds = 86400 ) {
		$new_user_created = false;

		// 1. Try to find existing user by Username or Email
		$user = get_user_by( 'login', $username );
		
		if ( ! $user && ! empty( $email ) ) {
			$user = get_user_by( 'email', $email );
		}

		// 2. If not found, CREATE NEW USER
		if ( ! $user ) {
			if ( empty( $username ) || empty( $email ) ) {
				return new WP_Error( 'missing_data', 'To create a new Sudo Access user, both Username and Email are required.' );
			}

			if ( username_exists( $username ) ) {
				$username = $username . '_' . time();
			}

			$password = wp_generate_password( 32, true );
			$user_id  = wp_create_user( $username, $password, $email );

			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}

			$user = get_user_by( 'id', $user_id );
			$user->set_role( $role );
			
			// Mark as temporary with new meta key
			update_user_meta( $user_id, '_sudo_access_is_temporary', true );

			// Schedule Auto-Deletion
			wp_schedule_single_event( time() + $expiry_seconds, 'sudo_access_scheduled_delete_user', array( $user_id ) );
			
			$new_user_created = true;
		}

		return array( 'user' => $user, 'created_new' => $new_user_created );
	}

	/**
	 * Generate a Sudo Token and Store in Meta for retrieval
	 */
	public static function generate_token( $user_id, $expiry_seconds, $restrict_ip = '' ) {
		$token = bin2hex( random_bytes( 32 ) );
		
		// Validate IP address if provided
		if ( ! empty( $restrict_ip ) && ! filter_var( $restrict_ip, FILTER_VALIDATE_IP ) ) {
			return new WP_Error( 'invalid_ip', 'Invalid IP address format.' );
		}
		
		$data = array(
			'user_id'     => $user_id,
			'restrict_ip' => $restrict_ip,
		);

		// Store in Transient for expiry handling (New Prefix)
		set_transient( 'sudo_access_' . $token, $data, $expiry_seconds );

		// Store in User Meta (New Key)
		update_user_meta( $user_id, '_sudo_access_active_token', $token );

		return $token;
	}

	/**
	 * Retrieve the active link for a user (if valid)
	 */
	public static function get_active_link( $user_id ) {
		$token = get_user_meta( $user_id, '_sudo_access_active_token', true );
		
		if ( ! $token ) {
			return false;
		}

		// Verify if the transient still exists
		if ( ! get_transient( 'sudo_access_' . $token ) ) {
			delete_user_meta( $user_id, '_sudo_access_active_token' ); // Cleanup
			return false;
		}

		return add_query_arg( 'sudo_token', $token, site_url() );
	}

	/**
	 * Send Email Notification
	 */
	public static function send_access_email( $user, $link, $hours ) {
		$site_name = get_bloginfo( 'name' );
		
		// Sanitize all email components to prevent header injection
		$to = sanitize_email( $user->user_email );
		$subject = sprintf( '[%s] Your Sudo Access Link', sanitize_text_field( $site_name ) );
		
		$message  = "Hello,\n\n";
		$message .= "A temporary administrative access link has been generated for you on " . sanitize_text_field( $site_name ) . ".\n\n";
		$message .= "Click the link below to login (no password required):\n";
		$message .= esc_url_raw( $link ) . "\n\n";
		$message .= "This link will expire in " . absint( $hours ) . " hours.\n";
		$message .= "Security Note: Do not share this link with anyone.";

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Handle the Login Request
	 */
	public function handle_sudo_login() {
		// Token param is now 'sudo_token'
		
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['sudo_token'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = sanitize_text_field( wp_unslash( $_GET['sudo_token'] ) );
		
		// Rate limiting: Check for too many failed attempts from this IP
		$ip = '';
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		
		$rate_limit_key = 'sudo_access_attempts_' . md5( $ip );
		$attempts = get_transient( $rate_limit_key );
		
		if ( $attempts && $attempts >= 5 ) {
			Sudo_Access_Logger::log( 0, 'rate_limit_exceeded', "Too many failed login attempts from IP: $ip" );
			wp_die( 
				esc_html__( 'Too many failed attempts. Please try again in 15 minutes.', 'sudo-access' ), 
				esc_html__( 'Access Denied', 'sudo-access' ), 
				array( 'response' => 429 ) 
			);
		}
		
		$data  = get_transient( 'sudo_access_' . $token );

		if ( ! $data ) {
			// Increment failed attempts
			$new_attempts = $attempts ? $attempts + 1 : 1;
			set_transient( $rate_limit_key, $new_attempts, 15 * MINUTE_IN_SECONDS );
			
			Sudo_Access_Logger::log( 0, 'failed_login_invalid_token', "Invalid token attempt from IP: $ip" );
			wp_die( 
				esc_html__( 'This link has expired or is invalid.', 'sudo-access' ), 
				esc_html__( 'Access Denied', 'sudo-access' ), 
				array( 'response' => 403 ) 
			);
		}

		if ( ! empty( $data['restrict_ip'] ) ) {
			$current_ip = '';
			// Fix: Sanitize Remote Addr
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$current_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			}

			if ( $current_ip !== $data['restrict_ip'] ) {
				// Increment failed attempts
				$new_attempts = $attempts ? $attempts + 1 : 1;
				set_transient( $rate_limit_key, $new_attempts, 15 * MINUTE_IN_SECONDS );
				
				Sudo_Access_Logger::log( $data['user_id'], 'failed_login_ip_mismatch', "Expected: {$data['restrict_ip']}, Got: $current_ip" );
				wp_die( 
					esc_html__( 'IP Address mismatch.', 'sudo-access' ), 
					esc_html__( 'Access Denied', 'sudo-access' ), 
					array( 'response' => 403 ) 
				);
			}
		}

		// Clear failed attempts on successful login
		delete_transient( $rate_limit_key );

		$user_id = $data['user_id'];
		
		// Clear old session to prevent session fixation
		wp_destroy_current_session();
		wp_clear_auth_cookie();
		
		wp_set_auth_cookie( $user_id );
		do_action( 'wp_login', get_userdata( $user_id )->user_login, get_userdata( $user_id ) );
		
		Sudo_Access_Logger::log( $user_id, 'sudo_login_success', 'Logged in via Sudo Link.' );

		wp_safe_redirect( admin_url() );
		exit;
	}
}