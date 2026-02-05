# Security Documentation

## Overview
This document outlines the security measures implemented in the Sudo Access WordPress plugin and provides guidelines for secure usage.

## Security Features

### 1. Rate Limiting
**Protection Against:** Brute force attacks on temporary access tokens

**Implementation:**
- Maximum 5 failed login attempts per IP address
- 15-minute lockout period after exceeding limit
- All failed attempts are logged for audit purposes
- Rate limit tracked using WordPress transients

**Code Location:** `includes/class-sudowp-auth.php` (line 118+)

### 2. Session Security
**Protection Against:** Session fixation attacks

**Implementation:**
- Old sessions are destroyed before setting new authentication cookie
- Sessions are properly regenerated using `wp_destroy_current_session()`
- WordPress login action is triggered for proper session handling

**Code Location:** `includes/class-sudowp-auth.php` (line 168+)

### 3. IP Restriction Validation
**Protection Against:** Invalid IP data and bypasses

**Implementation:**
- IP addresses are validated using PHP's `filter_var()` with `FILTER_VALIDATE_IP`
- Invalid IPs return WP_Error and prevent token generation
- IP mismatches are logged and trigger rate limiting

**Code Location:** `includes/class-sudowp-auth.php` (line 60+)

### 4. Email Security
**Protection Against:** Email header injection and spam relay

**Implementation:**
- All email components are sanitized (to, subject, body)
- Uses `sanitize_email()` for recipient addresses
- Uses `sanitize_text_field()` for subject and site name
- Uses `esc_url_raw()` for links in email body
- Uses `absint()` for numeric values

**Code Location:** `includes/class-sudowp-auth.php` (line 99+)

### 5. Privilege Escalation Protection
**Protection Against:** Unauthorized administrator account creation

**Implementation:**
- Validates role against whitelist (administrator, editor, author)
- On multisite: requires `manage_network` capability to create administrators
- Requires `create_users` capability for all operations
- User confirmation dialog for administrator role creation

**Code Location:** `includes/class-sudowp-admin.php` (line 386+)

### 6. HTTPS Warning
**Protection Against:** Token interception via Man-in-the-Middle attacks

**Implementation:**
- Visual warning displayed when site is not using HTTPS
- Encourages secure connections for token transmission
- Warning appears on link creation page

**Code Location:** `includes/class-sudowp-admin.php` (line 127+)

### 7. CSRF Protection
**Protection Against:** Cross-Site Request Forgery

**Implementation:**
- WordPress nonces used on all forms
- Nonce verification via `check_admin_referer()`
- Different nonces for different actions
- All admin POST actions require valid nonce

**Code Locations:**
- Create Link: `includes/class-sudowp-admin.php` (line 160, 387)
- Settings: `includes/class-sudowp-admin.php` (line 311, 353)
- Revoke Users: `includes/class-sudowp-admin.php` (line 211, 267)
- Purge Logs: `includes/class-sudowp-admin.php` (line 345, 371)

### 8. Input Sanitization
**Protection Against:** XSS and SQL Injection

**Implementation:**
- All user inputs are sanitized using WordPress functions
- `sanitize_user()` for usernames
- `sanitize_email()` for email addresses
- `sanitize_text_field()` for text inputs
- `intval()` for numeric inputs
- Output escaping with `esc_html()`, `esc_url()`, `esc_attr()`

**Code Location:** Throughout all files

### 9. Capability Checks
**Protection Against:** Unauthorized access

**Implementation:**
- `manage_options` required for settings changes
- `create_users` required for link creation
- `delete_users` required for user revocation
- Checks performed before any privileged operation

**Code Locations:**
- Settings: `includes/class-sudowp-admin.php` (line 355, 373)
- Create: `includes/class-sudowp-admin.php` (line 389)
- Revoke: `includes/class-sudowp-admin.php` (line 269)

### 10. Security Audit Logging
**Protection Against:** Undetected security incidents

**Implementation:**
- All login attempts (successful and failed) are logged
- Rate limit violations are logged
- IP mismatches are logged
- User creation and deletion events are logged
- Manual revocations are logged
- Logs include: timestamp, user, action, details, IP address

**Code Location:** `includes/class-sudowp-logger.php`

## Security Best Practices for Users

### For Site Administrators

1. **Use HTTPS**: Always use SSL/TLS for your WordPress site
2. **Limit Expiry Times**: Use the shortest practical expiry time
3. **Use IP Restrictions**: When possible, restrict access to specific IPs
4. **Monitor Logs**: Regularly review security logs in the plugin dashboard
5. **Minimum Privileges**: Only grant administrator role when absolutely necessary
6. **Delete Unused Accounts**: Use the revoke feature to immediately remove access
7. **Configure Log Retention**: Set appropriate log retention policy in settings

### For Support Teams

1. **Verify Identity**: Confirm the recipient's identity before sending links
2. **Use Secure Channels**: Send links via encrypted channels when possible
3. **Time-Limited Access**: Request only the minimum necessary access duration
4. **Single Purpose**: Create separate accounts for different tasks/users
5. **Report Issues**: Contact site admin if links don't work as expected

## Known Limitations

1. **PHP Random Bytes**: Requires PHP 7.0+ for `random_bytes()` function
2. **WordPress Transients**: Token storage relies on WordPress transient API
3. **Email Delivery**: Link delivery depends on WordPress email configuration
4. **Scheduled Tasks**: User deletion requires WordPress cron to be functioning
5. **Database Performance**: Large log tables may impact performance

## Reporting Security Issues

If you discover a security vulnerability in this plugin, please email security@wprepublic.com with:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if available)

Please allow 48 hours for initial response and do not publicly disclose until a fix is available.

## Security Changelog

### Version 0.2.0+
- Added rate limiting (5 attempts per 15 minutes)
- Added IP address validation for restrictions
- Added session fixation protection
- Added email header injection protection
- Added administrator creation safeguards
- Added HTTPS usage warnings
- Improved audit logging for security events
- Enhanced input sanitization
- Added multisite administrator checks

## Compliance Notes

### GDPR Considerations
- User emails are stored temporarily for access purposes
- IP addresses are logged for security auditing
- Temporary users are automatically deleted
- Log retention can be configured
- All data can be deleted on plugin uninstall

### Data Retention
- Temporary user accounts: Deleted upon link expiry
- Access tokens: Stored in transients with automatic expiration
- Security logs: Configurable (7 days, 30 days, or forever)
- Plugin data: Can be wiped on uninstall (configurable)

## Security Checklist

Before deploying Sudo Access in production:

- [ ] Ensure site is using HTTPS
- [ ] Configure appropriate log retention policy
- [ ] Review and adjust default expiry times if needed
- [ ] Test email delivery for link distribution
- [ ] Verify WordPress cron is functioning
- [ ] Set up regular log monitoring
- [ ] Train users on security best practices
- [ ] Document your access control policies
- [ ] Plan for emergency access revocation

## Technical Security Details

### Token Generation
- Uses PHP's `random_bytes(32)` for cryptographic randomness
- 64-character hexadecimal tokens (256 bits of entropy)
- Tokens stored in WordPress transients with automatic expiration
- No tokens stored in database permanently

### Password Handling
- Temporary users created with 32-character random passwords
- Passwords never transmitted or displayed
- Users log in via magic link, not password
- Original passwords become irrelevant after user deletion

### Database Security
- Uses WordPress `$wpdb` prepared statements
- Table names use WordPress prefix
- Input sanitization on all queries
- Proper escaping in phpcs comments where needed

## Additional Resources

- [WordPress Plugin Security Best Practices](https://developer.wordpress.org/plugins/security/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
