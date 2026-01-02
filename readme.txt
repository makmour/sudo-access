=== Sudo Access – Secure Temporary Login & Audit Log ===
Contributors: wprepublic, thewebcitizen
Tags: temporary login, audit log, security, wp-cli, support access
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 0.2.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The professional way to grant temporary admin access to developers and support agents. Includes Audit Logs and full WP-CLI integration.

== Description ==

**Give Access. Keep Control. Stay Secure.**

Sudo Access is the ultimate tool for agencies, developers, and security-conscious site owners. It allows you to create secure, temporary login links ("Sudo Links") for support agents or external developers without sharing passwords.

Unlike other plugins, Sudo Access is built with a **Security First** mindset. We don't just let people in; we track what they do.

### 🔥 KEY FEATURES

* **One-Click Sudo Links:** Generate a secure magic link that logs users in automatically.
* **Auto-Expiration:** Set links to expire in 1 hour, 4 hours, 24 hours, or 7 days.
* **Auto-Delete Users:** Automatically delete the temporary user account when the link expires (keeps your user table clean).
* **Security Audit Log:** Track critical actions taken by the temporary user (e.g., "sudo_login_success", plugin changes, etc.).
* **Log Retention Policy:** Automatically purge old logs (Weekly/Monthly) to keep your database optimized.
* **Clean Uninstall:** Option to wipe all plugin data and logs upon deletion.
* **Dedicated Dashboard:** Manage active links and view logs from a clean, modern UI.

### 💻 WP-CLI INTEGRATION (FOR PROS)

Sudo Access treats WP-CLI as a first-class citizen. You can manage the entire lifecycle of temporary users directly from your terminal. See the **WP-CLI Commands** section below for details.

== WP-CLI Commands ==

The plugin comes with a comprehensive suite of commands for system administrators.

**1. Create a Sudo Link**
Create a new temporary user or generate a link for an existing one.
`wp sudo create <username> [--email=<email>] [--role=<role>] [--expiry=<hours>]`

**2. List Active Users**
View all active temporary users and their current Sudo Links.
`wp sudo list`

**3. Revoke Access**
Immediately delete a temporary user and revoke access.
`wp sudo revoke <username>`

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/sudo-access` directory.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Go to the **Sudo Access** menu in your dashboard to create your first link.

== Frequently Asked Questions ==

= Does this plugin store passwords? =
No. The plugin generates secure, time-limited authentication tokens. It does not store or share passwords.

= What happens when a temporary user expires? =
If the user was created as a temporary user, their account is automatically deleted from WordPress to ensure security.

== Screenshots ==

1. **Dashboard:** Create new links and view active temporary users easily.
2. **Security Logs:** Detailed audit trail of user actions.

== Changelog ==

= 0.2.0 =
* REBRAND: Changed name to Sudo Access.
* ADDED: Settings Tab for plugin configuration.
* SECURITY: Enhanced sanitization and nonce checks.

= 0.1.0 =
* Initial Release.