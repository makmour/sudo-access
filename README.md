# Sudo Access

![License](https://img.shields.io/badge/license-GPLv2-blue.svg)
![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892BF.svg)
![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.0-21759B.svg)

**Give Access. Keep Control. Stay Secure.**

Sudo Access is the professional tool for agencies, developers, and security-conscious site owners. It allows you to create secure, temporary login links ("Sudo Links") for support agents or external developers without sharing passwords.

Unlike other plugins, Sudo Access is built with a **Security First** mindset. We don't just let people in; we track what they do.

---

## 🔥 Key Features

* **One-Click Sudo Links:** Generate a secure magic link that logs users in automatically.
* **Auto-Expiration:** Set links to expire in 1 hour, 4 hours, 24 hours, or 7 days.
* **Auto-Delete Users:** Automatically delete the temporary user account when the link expires (keeps your user table clean).
* **Security Audit Log:** Track critical actions taken by the temporary user (e.g., `sudo_login_success`, plugin changes, etc.).
* **Log Retention Policy:** Automatically purge old logs (Weekly/Monthly) to keep your database optimized.
* **Clean Uninstall:** Option to wipe all Sudo Access data and logs upon deletion.
* **Dedicated Dashboard:** Manage active links and view logs from a clean, modern UI.

---

## 💻 WP-CLI Integration

Sudo Access treats WP-CLI as a first-class citizen. You can manage the entire lifecycle of temporary users directly from your terminal.

### 1. Create a Sudo Link
Create a new temporary user or generate a link for an existing one.

```bash
wp sudo create <username> [--email=<email>] [--role=<role>] [--expiry=<hours>]

### 2. List Active Users
View all active temporary users and their current Sudo Links.

```bash
wp sudo list
# Or get JSON output
wp sudo list --format=json

### 3. Get User Info
Get details and the active link for a specific user.

```bash
wp sudo info <username>

### 4. Revoke Access
Immediately delete a temporary user and revoke access.

```bash
wp sudo revoke <username>

### 5. Configuration & Maintenance
Manage plugin settings and hygiene via CLI.

```bash
# Enable data wipe on uninstall
wp sudo config delete_data true

# DANGER: Clear all security logs and revoke users immediately
wp sudo purge

---

## 📥 Installation

### 1. Clone this repository into your wp-content/plugins/ directory:

```bash
git clone [https://github.com/makmour/Sudo Access.git](https://github.com/makmour/Sudo Access.git)

### 2. Activate the plugin through the 'Plugins' screen in WordPress.

### 3. Go to the Sudo Access menu in your dashboard to create your first link.

---

## 🛡️ Security
Sudo Access does not store passwords. It generates secure, time-limited authentication tokens using cryptographic random bytes. All temporary users created by the plugin can be automatically deleted upon expiration.

## 📜 License
This project is licensed under the GPLv2 or later.
