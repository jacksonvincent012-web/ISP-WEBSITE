# NetConnect KE — ISP Management System

A complete ISP customer management system with time-based KES billing, M-Pesa integration, and multi-layered security.

## Features

### User Portal
- Registration with password strength meter & honeypot protection
- Login with CSRF + rate limiting + IP tracking
- Dashboard: active plan, data usage bar, invoices, payments, support tickets
- Profile management & password change
- Network status page, FAQ page

### Admin Panel
- User management (suspend/activate/delete)
- Subscription oversight (cancel, status tracking)
- Support ticket management with inline replies
- 9 stat cards: active users, expired subs, offline subs, revenue today/month
- Settings: brand name, logo, currency
- Emergency kill switch: lockdown, force-logout-all, breach alerts

### Billing
- 11 KES time-based plans: 2h (KSh 10) → Premium (KSh 999)
- M-Pesa Daraja API (simulated by default; flip to live with credentials)
- Invoice generation, payment tracking

---

## Security Architecture (by implementation level)

### Level 1 — Foundation (initial build)

| Feature | Detail |
|---------|--------|
| Password hashing | `password_hash()` with bcrypt |
| Session-based auth | PHP sessions with login check on every page |
| Role separation | `user` vs `admin` role checks (`requireLogin()`, `requireAdmin()`) |
| SQL injection prevention | All queries use PDO prepared statements |
| XSS prevention | `htmlspecialchars()` / `safe()` helper on all output |
| CSRF tokens | Per-session token on all state-changing forms |

### Level 2 — Transport & hardening

| Feature | Detail |
|---------|--------|
| HTTPS enforcement | HSTS header, `session.cookie_secure` |
| Security headers | `Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, removed `X-Powered-By` |
| Cache control | `no-store, no-cache, must-revalidate` on authenticated pages |
| Host header fix | `BASE_URL` sanitized against injection |
| Error suppression | `display_errors=0`, custom error handler |
| Directory protection | `.htaccess` blocks `config/`, `sent/`, `mpesa_logs/` |

### Level 3 — Encryption (adaptive per-user AES-256-CBC)

| Feature | Detail |
|---------|--------|
| Per-user derived keys | `HMAC-SHA256(master_key, "user_{id}")` so each user has a unique key |
| Encryption format | `base64(version + iv + ciphertext + hmac_tag)` with integrity verification |
| Backward compatible | Reads legacy plaintext fields seamlessly |
| Auto key rotation | Master key + all user data re-encrypted at threat thresholds (10, 25, 50, 100 violations) |
| 2FA secret encryption | TOTP secrets also stored encrypted per-user |

### Level 4 — Immune system

| Feature | Detail |
|---------|--------|
| Honeypot fields | Invisible fields on every form; bots that fill them get silently rejected |
| Dynamic CSRF field name | Field name is a random 12-char hex per session, rotated each request |
| Adaptive throttling | Progressive delay (0.5s → 2s → 5s) after repeated violations within a session |
| IP blacklist | 15 violations → 30-minute ban; auto-blocked IPs get a polite "too many requests" |
| Honeytoken DB trap | User `id=999` acts as a decoy; any login attempt on it triggers immediate alert |
| HTML noise injection | Random invisible elements in rendered HTML to confuse scrapers |
| Audit logging | Every suspicious event logged with IP, user agent, timestamp |

### Level 5 — Rate limiting & brute-force protection

| Feature | Detail |
|--------|--------|
| Login rate limit | 5 attempts per IP per 15 minutes |
| Registration rate limit | 2 registrations per IP per hour |
| Password reset rate limit | 3 requests per IP per 30 minutes |
| Admin action rate limit | 10 actions per minute |
| 2FA rate limit | 5 attempts per 15 minutes |
| Username enumeration fix | Generic error messages ("Invalid credentials") regardless of whether the user exists |
| Login IP tracking | New-device/new-IP login triggers email alert to user |

### Level 6 — Emergency & persistence

| Feature | Detail |
|---------|--------|
| Emergency kill switch | One-click lockdown blocks all non-admin users; toggle on/off from admin panel |
| Force logout all | Terminates all active sessions instantly |
| Breach alerts | Email sent to all admins when emergency actions are triggered |
| Lockdown persistence | Lock file stored at `%TEMP%\isp_lockdown.json`; checked on every `secureSessionStart()` |
| Daily security cron | Rotates encryption keys, cleans rate-limit files, checks file integrity, auto-blocks persistent violators, emails health report to admin |
| File integrity monitoring | MD5 hashes of all PHP files checked daily; changes trigger `integrity_change` alert |
| Probabilistic web trigger | Even without cron, maintenance runs via 0.5% page-load probability |

### Level 7 — Payment security

| Feature | Detail |
|---------|--------|
| M-Pesa callback HMAC | Callback payload verified with HMAC signature |
| Simulated mode | `MPESA_SIMULATE_ONLY = true` for development; flip to `false` + fill credentials for live |

---

## Default Admin

| Credential | Value |
|-----------|-------|
| URL | `http://localhost/isp-system/` |
| Admin email | `admin@isp.com` |
| Admin password | `Admin@NetConnect2026!` |
| Database | MySQL `isp_db` (XAMPP root / no password) |

> **Change the admin password immediately on first login.**

## Quick Start

1. Start Apache + MySQL in XAMPP
2. Import `setup.sql` into phpMyAdmin
3. Copy folder to `C:\xampp\htdocs\isp-system\`
4. Visit `http://localhost/isp-system/`
5. Log in as admin → setup plans, brand name, M-Pesa

## Going to Production

1. Get a real SSL certificate (Let's Encrypt via Certbot)
2. Fill M-Pesa credentials in `config/mpesa.php` → set `MPESA_SIMULATE_ONLY = false`
3. Swap mailer to SMTP in `config/mailer.php` → set `MAIL_MODE = 'smtp'`
4. Configure brand and currency via `admin_settings.php`
5. Install daily cron: `php security_cron.php` (Windows Task Scheduler or Linux crontab)
6. Backup database regularly

## Tech Stack

- **Server:** Apache + PHP 8.x
- **Database:** MySQL 8.x
- **Payments:** M-Pesa Daraja API (Safaricom)
- **Security:** AES-256-CBC, bcrypt, CSRF tokens, HSTS, CSP
- **UI:** Vanilla CSS (dark theme, glassmorphism, Inter font, Font Awesome 6)
