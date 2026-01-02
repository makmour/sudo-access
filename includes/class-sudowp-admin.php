<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SudoWP_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_sudowp_create_link', array( $this, 'handle_create_link' ) );
		add_action( 'admin_post_sudowp_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_sudowp_manual_purge', array( $this, 'handle_manual_purge' ) );
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
		wp_enqueue_style( 'sudowp-admin-css', SUDO_ACCESS_URL . 'assets/admin.css', array(), SUDO_ACCESS_VERSION );
	}

	public function render_dashboard() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'create_link';
		?>
		<div class="wrap sudowp-wrap">
			<h1>Sudo Access <span class="version">v<?php echo esc_html( SUDO_ACCESS_VERSION ); ?></span></h1>
			
			<h2 class="nav-tab-wrapper">
				<a href="?page=sudo-access&tab=create_link" class="nav-tab <?php echo $active_tab == 'create_link' ? 'nav-tab-active' : ''; ?>">Create Sudo Link</a>
				<a href="?page=sudo-access&tab=active_users" class="nav-tab <?php echo $active_tab == 'active_users' ? 'nav-tab-active' : ''; ?>">Active Users</a>
				<a href="?page=sudo-access&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>">Security Logs</a>
				<a href="?page=sudo-access&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
			</h2>

			<div class="sudowp-content">
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
		?>
		<div class="card">
			<h2>Generate New Temporary Access</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sudowp_create_link">
				<?php wp_nonce_field( 'sudowp_create_action', 'sudowp_nonce' ); ?>
				
				<table class="form-table">
					<tr>
						<th><label for="sudowp_username">Username</label></th>
						<td><input type="text" name="sudowp_username" id="sudowp_username" class="regular-text" required></td>
					</tr>
					<tr>
						<th><label for="sudowp_email">Email</label></th>
						<td><input type="email" name="sudowp_email" id="sudowp_email" class="regular-text" required></td>
					</tr>
					<tr>
						<th><label for="sudowp_role">Role</label></th>
						<td>
							<select name="sudowp_role" id="sudowp_role">
								<option value="administrator">Administrator</option>
								<option value="editor">Editor</option>
								<option value="author">Author</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="sudowp_expiry">Expires In</label></th>
						<td>
							<select name="sudowp_expiry" id="sudowp_expiry">
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

	private function render_settings_tab() {
		$retention   = get_option( 'sudowp_log_retention', 'never' );
		$delete_data = get_option( 'sudowp_delete_data', false );
		?>
		<div class="card">
			<h2>Plugin Configuration</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sudowp_save_settings">
				<?php wp_nonce_field( 'sudowp_save_settings_action', 'sudowp_settings_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th><label for="sudowp_log_retention">Log Retention Policy</label></th>
						<td>
							<select name="sudowp_log_retention" id="sudowp_log_retention">
								<option value="never" <?php selected( $retention, 'never' ); ?>>Keep Logs Forever</option>
								<option value="weekly" <?php selected( $retention, 'weekly' ); ?>>Delete older than 7 days</option>
								<option value="monthly" <?php selected( $retention, 'monthly' ); ?>>Delete older than 30 days</option>
							</select>
							<p class="description">Automatically clean up old audit logs to save database space.</p>
						</td>
					</tr>
					<tr>
						<th><label for="sudowp_delete_data">Uninstall Cleanup</label></th>
						<td>
							<label>
								<input type="checkbox" name="sudowp_delete_data" value="1" <?php checked( $delete_data, 1 ); ?>>
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
				<input type="hidden" name="action" value="sudowp_manual_purge">
				<?php wp_nonce_field( 'sudowp_manual_purge_action', 'sudowp_purge_nonce' ); ?>
				<button type="submit" class="button button-link-delete">Purge All Logs Now</button>
			</form>
		</div>
		<?php
	}

	public function handle_save_settings() {
		check_admin_referer( 'sudowp_save_settings_action', 'sudowp_settings_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		if ( isset( $_POST['sudowp_log_retention'] ) ) {
			update_option( 'sudowp_log_retention', sanitize_text_field( wp_unslash( $_POST['sudowp_log_retention'] ) ) );
		}

		$delete_data = isset( $_POST['sudowp_delete_data'] ) ? 1 : 0;
		update_option( 'sudowp_delete_data', $delete_data );

		wp_safe_redirect( admin_url( 'admin.php?page=sudo-access&tab=settings&msg=saved' ) );
		exit;
	}

	public function handle_manual_purge() {
		check_admin_referer( 'sudowp_manual_purge_action', 'sudowp_purge_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'sudowp_logs';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table_name}" );

		wp_safe_redirect( admin_url( 'admin.php?page=sudo-access&tab=settings&msg=purged' ) );
		exit;
	}

	public function handle_create_link() {
		check_admin_referer( 'sudowp_create_action', 'sudowp_nonce' );

		if ( ! current_user_can( 'create_users' ) ) {
			wp_die( 'Unauthorized' );
		}

		$username = isset( $_POST['sudowp_username'] ) ? sanitize_user( wp_unslash( $_POST['sudowp_username'] ) ) : '';
		$email    = isset( $_POST['sudowp_email'] ) ? sanitize_email( wp_unslash( $_POST['sudowp_email'] ) ) : '';
		$role     = isset( $_POST['sudowp_role'] ) ? sanitize_text_field( wp_unslash( $_POST['sudowp_role'] ) ) : 'administrator';
		$expiry   = isset( $_POST['sudowp_expiry'] ) ? intval( $_POST['sudowp_expiry'] ) : 24;

		// Logic to create user/link here (omitted for brevity, assume SudoWP_Auth call)
		// ...

		wp_safe_redirect( admin_url( 'admin.php?page=sudo-access&msg=created' ) );
		exit;
	}
	
	private function render_logs_tab() {
		global $wpdb;
		$logs_table = $wpdb->prefix . 'sudowp_logs';
		
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$logs = $wpdb->get_results( "SELECT * FROM {$logs_table} ORDER BY id DESC LIMIT 50" );
		
		// ... Render table HTML ...
	}
	
	// ... (render_active_users_tab omitted, similar fixes apply) ...
}