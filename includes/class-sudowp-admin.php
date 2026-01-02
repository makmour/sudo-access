<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sudo_Access_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		
		// Updated Action Hooks
		add_action( 'admin_post_sudo_access_create_link', array( $this, 'handle_create_link' ) );
		add_action( 'admin_post_sudo_access_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_sudo_access_manual_purge', array( $this, 'handle_manual_purge' ) );
		
		// NEW: Bulk Revoke Action
		add_action( 'admin_post_sudo_access_revoke_selected', array( $this, 'handle_revoke_selected' ) );

		// Inject JS for Copy functionality & Bulk Select
		add_action( 'admin_footer', array( $this, 'print_copy_script' ) );
	}

	public function add_admin_menu() {
		add_menu_page(
			'Sudo Access',
			'Sudo Access',
			'manage_options',
			'sudo-access',
			array( $this, 'render_dashboard' ),
			'dashicons-shield',
			99
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_sudo-access' !== $hook ) {
			return;
		}
		
		wp_enqueue_style( 'sudo-access-admin-css', SUDO_ACCESS_URL . 'css/sudo-access-admin.css', array(), SUDO_ACCESS_VERSION );
	}

	/**
	 * Output JavaScript for Copy to Clipboard & Select All functionality
	 */
	public function print_copy_script() {
		$screen = get_current_screen();
		if ( ! $screen || 'toplevel_page_sudo-access' !== $screen->id ) {
			return;
		}
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// 1. Copy via Button Click
			$('.sudo-copy-btn').on('click', function(e) {
				e.preventDefault();
				var btn = $(this);
				var text = btn.data('link');
				var originalText = btn.html();

				if (!text) return;

				navigator.clipboard.writeText(text).then(function() {
					btn.html('<span class="dashicons dashicons-yes" style="line-height:1.3"></span> Copied!');
					setTimeout(function() {
						btn.html(originalText);
					}, 2000);
				}, function(err) {
					console.error('Async: Could not copy text: ', err);
				});
			});

			// 2. Auto-Copy on Input Click (Creation Tab)
			$('.sudo-auto-select').on('click', function() {
				$(this).select();
				var text = $(this).val();
				navigator.clipboard.writeText(text).then(function() { console.log('Copied'); });
			});

			// 3. Select All Checkbox
			$('#cb-select-all-1').on('click', function() {
				var checked = $(this).prop('checked');
				$('.sudo-user-cb').prop('checked', checked);
			});
		});
		</script>
		<?php
	}

	public function render_dashboard() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'create_link';
		?>
		<div class="wrap sudo-access-wrap">
			<h1>Sudo Access <span class="version">v<?php echo esc_html( SUDO_ACCESS_VERSION ); ?></span></h1>
			
			<h2 class="nav-tab-wrapper">
				<a href="?page=sudo-access&tab=create_link" class="nav-tab <?php echo $active_tab == 'create_link' ? 'nav-tab-active' : ''; ?>">Create Sudo Link</a>
				<a href="?page=sudo-access&tab=active_users" class="nav-tab <?php echo $active_tab == 'active_users' ? 'nav-tab-active' : ''; ?>">Active Users</a>
				<a href="?page=sudo-access&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>">Security Logs</a>
				<a href="?page=sudo-access&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
			</h2>

			<div class="sudo-access-content">
				<?php
				switch ( $active_tab ) {
					case 'active_users':
						$this->render_active_users_tab();
						break;
					case 'logs':
						$this->render_logs_tab();
						break;
					case 'settings':
						$this->render_settings_tab();
						break;
					default:
						$this->render_create_link_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	private function render_create_link_tab() {
		// --- SHOW GENERATED LINK ---
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['msg'] ) && 'created' === $_GET['msg'] ) {
			$transient_key = 'sudo_access_new_link_' . get_current_user_id();
			$new_link      = get_transient( $transient_key );

			if ( $new_link ) {
				delete_transient( $transient_key ); 
				?>
				<div class="notice notice-success is-dismissible" style="margin-left: 0; margin-bottom: 20px; border-left-color: #00a32a;">
					<p><strong>✅ Sudo Link Generated Successfully!</strong></p>
					<p>Click below to copy:</p>
					<div style="display:flex; gap:10px; align-items:center; max-width:800px;">
						<input type="text" class="large-text code sudo-auto-select" value="<?php echo esc_attr( $new_link ); ?>" readonly style="flex:1; padding: 10px; font-size: 14px;">
						
						<button type="button" class="button button-primary sudo-copy-btn" data-link="<?php echo esc_attr( $new_link ); ?>">
							<span class="dashicons dashicons-admin-links" style="line-height: 1.3;"></span> Copy Link
						</button>
					</div>
					<p class="description">The link has been copied to your clipboard (if supported).</p>
				</div>
				<?php
			} else {
				echo '<div class="notice notice-success is-dismissible"><p>User created successfully.</p></div>';
			}
		}
		// ---------------------------
		?>
		<div class="sudo-access-card">
			<h2>Generate New Temporary Access</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sudo_access_create_link">
				<?php wp_nonce_field( 'sudo_access_create_action', 'sudo_access_nonce' ); ?>
				
				<table class="form-table">
					<tr>
						<th><label for="sudo_access_username">Username</label></th>
						<td><input type="text" name="sudo_access_username" id="sudo_access_username" class="regular-text" required></td>
					</tr>
					<tr>
						<th><label for="sudo_access_email">Email</label></th>
						<td><input type="email" name="sudo_access_email" id="sudo_access_email" class="regular-text" required></td>
					</tr>
					<tr>
						<th><label for="sudo_access_role">Role</label></th>
						<td>
							<select name="sudo_access_role" id="sudo_access_role">
								<option value="administrator">Administrator</option>
								<option value="editor">Editor</option>
								<option value="author">Author</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="sudo_access_expiry">Expires In</label></th>
						<td>
							<select name="sudo_access_expiry" id="sudo_access_expiry">
								<option value="1">1 Hour</option>
								<option value="4">4 Hours</option>
								<option value="24" selected>24 Hours</option>
								<option value="168">7 Days</option>
							</select>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary">Generate Sudo Link</button>
				</p>
			</form>
		</div>
		<?php
	}

	private function render_active_users_tab() {
		// phpcs:ignore WordPress.DB.SlowDBQuery
		$users = get_users( array( 'meta_key' => '_sudo_access_is_temporary', 'meta_value' => true ) );
		
		?>
		<div class="sudo-access-card">
			<h2>Active Temporary Users</h2>
			
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sudo_access_revoke_selected">
				<?php wp_nonce_field( 'sudo_access_revoke_action', 'sudo_access_nonce' ); ?>

				<table class="widefat fixed striped sudo-access-table">
					<thead>
						<tr>
							<td id="cb" class="manage-column column-cb check-column">
								<input id="cb-select-all-1" type="checkbox">
							</td>
							<th>User</th>
							<th>Email</th>
							<th>Role</th>
							<th style="width:140px;">Action</th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! empty( $users ) ) : ?>
							<?php foreach ( $users as $user ) : 
								$link = Sudo_Access_Auth::get_active_link( $user->ID );
							?>
								<tr>
									<th scope="row" class="check-column">
										<input type="checkbox" name="sudo_users[]" class="sudo-user-cb" value="<?php echo esc_attr( $user->ID ); ?>">
									</th>
									<td><?php echo esc_html( $user->user_login ); ?></td>
									<td><?php echo esc_html( $user->user_email ); ?></td>
									<td><?php echo esc_html( implode( ', ', $user->roles ) ); ?></td>
									<td>
										<?php if ( $link ) : ?>
											<button type="button" class="button button-secondary sudo-copy-btn" data-link="<?php echo esc_attr( $link ); ?>">
												<span class="dashicons dashicons-admin-links" style="line-height: 1.3;"></span> Copy Link
											</button>
										<?php else : ?>
											<span class="sudo-access-badge">Expired</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr><td colspan="5">No active temporary users.</td></tr>
						<?php endif; ?>
					</tbody>
				</table>
				
				<?php if ( ! empty( $users ) ) : ?>
					<div class="tablenav bottom" style="margin-top: 15px;">
						<div class="alignleft actions">
							<button type="submit" class="button button-link-delete" onclick="return confirm('Are you sure you want to delete the selected users? This action cannot be undone.');">Revoke Selected</button>
						</div>
					</div>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	public function handle_revoke_selected() {
		check_admin_referer( 'sudo_access_revoke_action', 'sudo_access_nonce' );

		if ( ! current_user_can( 'delete_users' ) ) {
			wp_die( 'Unauthorized' );
		}

		if ( ! empty( $_POST['sudo_users'] ) && is_array( $_POST['sudo_users'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			
			$count = 0;
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			foreach ( $_POST['sudo_users'] as $user_id ) {
				$user_id = intval( $user_id );
				
				// Double check this is actually a temporary user before deleting!
				$is_temp = get_user_meta( $user_id, '_sudo_access_is_temporary', true );
				if ( $is_temp ) {
					if ( wp_delete_user( $user_id ) ) {
						$count++;
						// Log deletion
						if ( class_exists( 'Sudo_Access_Logger' ) ) {
							Sudo_Access_Logger::log( get_current_user_id(), 'manual_user_revoke', "Manually revoked user ID: $user_id" );
						}
					}
				}
			}
			
			$msg = $count > 0 ? 'revoked' : 'error';
			wp_safe_redirect( admin_url( 'admin.php?page=sudo-access&tab=active_users&msg=' . $msg . '&count=' . $count ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=sudo-access&tab=active_users' ) );
		exit;
	}

	private function render_settings_tab() {
		$retention   = get_option( 'sudo_access_log_retention', 'never' );
		$delete_data = get_option( 'sudo_access_delete_data', false );
		?>
		<div class="sudo-access-card settings">
			<h2>Plugin Configuration</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sudo_access_save_settings">
				<?php wp_nonce_field( 'sudo_access_save_settings_action', 'sudo_access_settings_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th><label for="sudo_access_log_retention">Log Retention Policy</label></th>
						<td>
							<select name="sudo_access_log_retention" id="sudo_access_log_retention">
								<option value="never" <?php selected( $retention, 'never' ); ?>>Keep Logs Forever</option>
								<option value="weekly" <?php selected( $retention, 'weekly' ); ?>>Delete older than 7 days</option>
								<option value="monthly" <?php selected( $retention, 'monthly' ); ?>>Delete older than 30 days</option>
							</select>
							<p class="description">Automatically clean up old audit logs to save database space.</p>
						</td>
					</tr>
					<tr>
						<th><label for="sudo_access_delete_data">Uninstall Cleanup</label></th>
						<td>
							<label>
								<input type="checkbox" name="sudo_access_delete_data" value="1" <?php checked( $delete_data, 1 ); ?>>
								Delete all data and logs when plugin is deleted.
							</label>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary">Save Changes</button>
				</p>
			</form>

			<hr>

			<h3>Danger Zone</h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Are you sure? This will delete ALL security logs.');">
				<input type="hidden" name="action" value="sudo_access_manual_purge">
				<?php wp_nonce_field( 'sudo_access_manual_purge_action', 'sudo_access_purge_nonce' ); ?>
				<button type="submit" class="button button-link-delete">Purge All Logs Now</button>
			</form>
		</div>
		<?php
	}

	public function handle_save_settings() {
		check_admin_referer( 'sudo_access_save_settings_action', 'sudo_access_settings_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		if ( isset( $_POST['sudo_access_log_retention'] ) ) {
			update_option( 'sudo_access_log_retention', sanitize_text_field( wp_unslash( $_POST['sudo_access_log_retention'] ) ) );
		}

		$delete_data = isset( $_POST['sudo_access_delete_data'] ) ? 1 : 0;
		update_option( 'sudo_access_delete_data', $delete_data );

		wp_safe_redirect( admin_url( 'admin.php?page=sudo-access&tab=settings&msg=saved' ) );
		exit;
	}

	public function handle_manual_purge() {
		check_admin_referer( 'sudo_access_manual_purge_action', 'sudo_access_purge_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'sudo_access_logs';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query( "TRUNCATE TABLE {$table_name}" );

		wp_safe_redirect( admin_url( 'admin.php?page=sudo-access&tab=settings&msg=purged' ) );
		exit;
	}

	public function handle_create_link() {
		check_admin_referer( 'sudo_access_create_action', 'sudo_access_nonce' );

		if ( ! current_user_can( 'create_users' ) ) {
			wp_die( 'Unauthorized' );
		}

		$username = isset( $_POST['sudo_access_username'] ) ? sanitize_user( wp_unslash( $_POST['sudo_access_username'] ) ) : '';
		$email    = isset( $_POST['sudo_access_email'] ) ? sanitize_email( wp_unslash( $_POST['sudo_access_email'] ) ) : '';
		$role     = isset( $_POST['sudo_access_role'] ) ? sanitize_text_field( wp_unslash( $_POST['sudo_access_role'] ) ) : 'administrator';
		$expiry   = isset( $_POST['sudo_access_expiry'] ) ? intval( $_POST['sudo_access_expiry'] ) : 24;

		$seconds    = $expiry * HOUR_IN_SECONDS;
		
		// Use centralized Auth Logic (Class name updated)
		$result = Sudo_Access_Auth::get_or_create_user( $username, $email, $role, $seconds );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		$user = $result['user'];
		$token = Sudo_Access_Auth::generate_token( $user->ID, $seconds );
		$link  = add_query_arg( 'sudo_token', $token, site_url() );
		
		// Optional: Send email explicitly if not handled inside create logic
		Sudo_Access_Auth::send_access_email( $user, $link, $expiry );

		// Store link in transient for display
		set_transient( 'sudo_access_new_link_' . get_current_user_id(), $link, 60 );

		wp_safe_redirect( admin_url( 'admin.php?page=sudo-access&msg=created' ) );
		exit;
	}
	
	private function render_logs_tab() {
		global $wpdb;
		$logs_table = $wpdb->prefix . 'sudo_access_logs';
		
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.SlowDBQuery
		$logs = $wpdb->get_results( "SELECT * FROM {$logs_table} ORDER BY id DESC LIMIT 50" );
		
		echo '<div class="sudo-access-card"><h2>Security Logs</h2><table class="widefat fixed striped sudo-access-table"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead><tbody>';
		if ( ! empty( $logs ) ) {
			foreach ( $logs as $log ) {
				echo '<tr>';
				echo '<td>' . esc_html( $log->created_at ) . '</td>';
				
				// --- CHANGED: Use stored username first. Fallback to ID look up. ---
				$display_name = 'Unknown';
				if ( ! empty( $log->username ) ) {
					$display_name = $log->username;
				} elseif ( $log->user_id > 0 ) {
					$u = get_userdata( $log->user_id );
					$display_name = $u ? $u->user_login : 'Deleted User (ID ' . $log->user_id . ')';
				} elseif ( '0' == $log->user_id ) { // Loose comparison intended
					$display_name = 'System';
				}

				echo '<td><strong>' . esc_html( $display_name ) . '</strong></td>';
				echo '<td>' . esc_html( $log->action ) . '</td>';
				echo '<td>' . esc_html( $log->details ) . '</td>';
				echo '<td>' . esc_html( $log->ip_address ) . '</td>';
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="5">No logs found.</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}