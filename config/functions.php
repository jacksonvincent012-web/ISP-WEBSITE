<?php
// ─── BASE URL (prevents Host header injection in emails) ───────────────────
$safeHost = filter_var($_SERVER['HTTP_HOST'] ?? 'localhost', FILTER_SANITIZE_URL);
$safeHost = preg_replace('/[^a-zA-Z0-9.:\[\]]/', '', $safeHost);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
define('BASE_URL', $scheme . '://' . $safeHost . '/isp-system');

// ─── ADAPTIVE ENCRYPTION (per-user keys + HMAC integrity + auto-rotation) ──
define('ENC_KEY_FILE', sys_get_temp_dir() . '/isp_enc_key.bin');
if (!file_exists(ENC_KEY_FILE)) {
    file_put_contents(ENC_KEY_FILE, random_bytes(32), LOCK_EX);
}
define('MASTER_KEY', file_get_contents(ENC_KEY_FILE));
define('ENC_VERSION', "\x01"); // key version — incremented on rotation
define('ENC_CIPHER', 'aes-256-cbc');
define('ENC_IV_LEN', 16);

// Derive a user-specific encryption key from the master key
function userKey(int $userId): string {
    return hash_hmac('sha256', MASTER_KEY, 'user_' . $userId, true);
}

// Encrypt with per-user key + HMAC integrity tag
// Output format: base64(version(1) + iv(16) + ciphertext + hmac(32))
function encryptField(string $plain, int $userId = 0): string {
    $key = $userId > 0 ? userKey($userId) : MASTER_KEY;
    $iv = random_bytes(ENC_IV_LEN);
    $cipher = openssl_encrypt($plain, ENC_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
    $hmac = hash_hmac('sha256', $iv . $cipher, $key, true);
    return base64_encode(ENC_VERSION . $iv . $cipher . $hmac);
}

// Decrypt + verify HMAC integrity. Auto-detects old and new format.
function decryptField(string $encoded, int $userId = 0): string {
    $decoded = base64_decode($encoded, true);
    if ($decoded === false || strlen($decoded) < 16) return '';
    $key = $userId > 0 ? userKey($userId) : MASTER_KEY;
    $first = ord($decoded[0]);

    // New format (version byte + IV + ciphertext + HMAC)
    if (strlen($decoded) >= 49 && $first === 1) {
        $iv = substr($decoded, 1, ENC_IV_LEN);
        $hmac = substr($decoded, -32);
        $cipher = substr($decoded, 1 + ENC_IV_LEN, -32);
        $expectedHmac = hash_hmac('sha256', $iv . $cipher, $key, true);
        if (hash_equals($expectedHmac, $hmac)) {
            $plain = openssl_decrypt($cipher, ENC_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
            if ($plain !== false) return $plain;
        }
        logSuspicious('tamper_detected', 'Encrypted data integrity check failed');
        return '';
    }

    // Legacy format (IV + ciphertext, no HMAC)
    $iv = substr($decoded, 0, ENC_IV_LEN);
    $cipher = substr($decoded, ENC_IV_LEN);
    $plain = openssl_decrypt($cipher, ENC_CIPHER, MASTER_KEY, OPENSSL_RAW_DATA, $iv);
    return $plain !== false ? $plain : '';
}

// Re-encrypt all sensitive data with a new master key (threat-driven rotation)
function rotateEncryptionKeys(): void {
    global $pdo;
    $oldKey = file_get_contents(ENC_KEY_FILE);
    $newKey = random_bytes(32);
    file_put_contents(ENC_KEY_FILE, $newKey, LOCK_EX);
    // Re-encrypt all user phone & address fields
    $users = $pdo->query("SELECT id, phone_encrypted, address_encrypted FROM users WHERE phone_encrypted IS NOT NULL OR address_encrypted IS NOT NULL")->fetchAll();
    foreach ($users as $u) {
        $phone = decryptFieldWithKey($u['phone_encrypted'] ?? '', $oldKey);
        $addr = decryptFieldWithKey($u['address_encrypted'] ?? '', $oldKey);
        // Temporarily restore master key for encryptField to pick up
        file_put_contents(ENC_KEY_FILE, $newKey, LOCK_EX);
        // Re-encrypt with new master key
        $pdo->prepare("UPDATE users SET phone_encrypted = ?, address_encrypted = ? WHERE id = ?")
            ->execute([$phone ? encryptField($phone, $u['id']) : '', $addr ? encryptField($addr, $u['id']) : '', $u['id']]);
    }
    // Re-encrypt 2FA secrets
    $users2fa = $pdo->query("SELECT id, twofa_secret FROM users WHERE twofa_secret IS NOT NULL AND twofa_secret != ''")->fetchAll();
    foreach ($users2fa as $u) {
        $secret = decryptFieldWithKey($u['twofa_secret'], $oldKey);
        if ($secret) {
            $pdo->prepare("UPDATE users SET twofa_secret = ? WHERE id = ?")->execute([encryptField($secret, $u['id']), $u['id']]);
        }
    }
    logAudit('key_rotation', 'system', 0, 'Master encryption key rotated');
}

// Internal: decrypt with a specific key (used during rotation)
function decryptFieldWithKey(string $encoded, string $key): string {
    $decoded = base64_decode($encoded, true);
    if ($decoded === false || strlen($decoded) < 49) return '';
    $iv = substr($decoded, 1, ENC_IV_LEN);
    $hmac = substr($decoded, -32);
    $cipher = substr($decoded, 1 + ENC_IV_LEN, -32);
    $expectedHmac = hash_hmac('sha256', $iv . $cipher, $key, true);
    if (!hash_equals($expectedHmac, $hmac)) return '';
    $plain = openssl_decrypt($cipher, ENC_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
    return $plain !== false ? $plain : '';
}

// Auto-trigger key rotation when threat crosses threshold
function checkThreatRotation(): void {
    $file = sys_get_temp_dir() . '/isp_threat_count.json';
    if (!file_exists($file)) return;
    $data = json_decode(file_get_contents($file), true);
    if (!$data || !isset($data['count'])) return;
    // Rotate at thresholds: 10, 25, 50, 100 violations
    $thresholds = [10, 25, 50, 100];
    $count = $data['count'];
    $lastRotate = $data['last_rotate'] ?? 0;
    foreach ($thresholds as $t) {
        if ($count >= $t && $lastRotate < $t) {
            rotateEncryptionKeys();
            $data['last_rotate'] = $t;
            file_put_contents($file, json_encode($data), LOCK_EX);
            break;
        }
    }
}

// ─── SECURITY HEADERS ──────────────────────────────────────────────────────
function sendSecurityHeaders(): void {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data: https://api.qrserver.com; connect-src 'self'; frame-src 'none'; object-src 'none'; base-uri 'self'; form-action 'self'");
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
    header_remove('X-Powered-By');
    // Error suppression & cache control
    ini_set('display_errors', 0);
    if (isset($_SESSION['user_id'])) {
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

// ─── SESSION (with idle timeout) ────────────────────────────────────────────
// ─── AUTO SECURITY MAINTENANCE (runs probabilistically on web traffic) ─────
function maybeRunCron(): void {
    $lastRun = @file_get_contents(sys_get_temp_dir() . '/isp_cron_last');
    $today = date('Y-m-d');
    if ($lastRun === $today) return;
    // Run with ~0.5% probability per page load to spread load
    if (random_int(1, 200) === 1) {
        file_put_contents(sys_get_temp_dir() . '/isp_cron_last', $today, LOCK_EX);
        try {
            @require_once __DIR__ . '/../security_cron.php';
        } catch (Exception $e) {}
    }
}

function secureSessionStart(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', 1800);
    ini_set('session.cookie_lifetime', 0);
    session_start();

    // Trigger daily security maintenance probabilistically
    maybeRunCron();

    // Emergency lockdown — blocks non-admin access immediately
    checkEmergencyLockdown();

    // IP blacklist check
    $banMsg = checkIpBlacklist();
    if ($banMsg !== null) {
        http_response_code(429);
        die('<html><body style="background:#060d1a;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;"><div style="text-align:center;"><h1 style="font-size:48px;color:#ef4444;">429</h1><p style="color:rgba(255,255,255,0.6);">' . htmlspecialchars($banMsg) . '</p></div></body></html>');
    }

    // Idle timeout — 30 minutes
    $idleMax = 1800;
    if (isset($_SESSION['_last_activity']) && (time() - $_SESSION['_last_activity']) > $idleMax) {
        $_SESSION = [];
        session_destroy();
        session_start();
        session_regenerate_id(true);
        $_SESSION['_expired'] = true;
    }
    $_SESSION['_last_activity'] = time();

    if (isset($_SESSION['_expired'])) {
        unset($_SESSION['_expired']);
        header('Location: login.php?msg=' . urlencode('Session expired due to inactivity. Please login again.') . '&type=error');
        exit;
    }

    if (!isset($_SESSION['_init'])) {
        session_regenerate_id(true);
        $_SESSION['_init'] = true;
    }

    // Auto-expire subscriptions & force logout if expired
    checkSubscriptionExpiry();
}

// ─── SUBSCRIPTION EXPIRY — auto-logout when plan runs out ──────────────────
function checkSubscriptionExpiry(): void {
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') === 'admin') return;
    global $pdo;

    $userId = $_SESSION['user_id'];
    $isCheat = $_SESSION['cheat_code'] ?? false;

    // Mark any active subscriptions past their end date as expired
    $pdo->prepare("UPDATE subscriptions SET status = 'expired' WHERE user_id = ? AND status = 'active' AND end_date < NOW()")->execute([$userId]);

    // If user has ever had a subscription but none are active, force logout
    $hasSub = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE user_id = ?");
    $hasSub->execute([$userId]);
    if ($hasSub->fetchColumn() > 0) {
        $active = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE user_id = ? AND status = 'active'");
        $active->execute([$userId]);
        if ($active->fetchColumn() == 0) {
            if ($isCheat) {
                // Cheat code — silently log and bounce with normal expiry message
                logSuspicious('cheat_code_expired', "Expired subscription bypass attempted by user $userId");
                $_SESSION = [];
                session_destroy();
                header('Location: login.php?msg=' . urlencode('Your subscription has expired. Please sign in to renew your plan.') . '&type=error');
                exit;
            }
            $_SESSION = [];
            session_destroy();
            header('Location: login.php?msg=' . urlencode('Your subscription has expired. Please sign in to renew your plan.') . '&type=error');
            exit;
        }
    }
}

// CSRF protection (rotates every request using dynamic field names) ---------
function csrfToken(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrfField(): string {
    initFieldMap();
    $name = fieldName('csrf_token');
    return '<input type="hidden" name="' . $name . '" value="' . csrfToken() . '">';
}

function verifyCsrf(): void {
    initFieldMap();
    $realName = fieldName('csrf_token');
    $sent = $_POST[$realName] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        logSuspicious('csrf_failed', 'CSRF validation failed');
        http_response_code(403);
        die('CSRF validation failed.');
    }
    // Rotate token after use
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// ─── REMEMBER TOKEN — automatic login for returning users ─────────────────
const REMEMBER_COOKIE = 'nc_remember';
const REMEMBER_DAYS = 365;

function generateRememberToken(): string {
    return bin2hex(random_bytes(32));
}

function setRememberToken(int $userId): void {
    global $pdo;
    $token = generateRememberToken();
    $hash = hash('sha256', $token);
    $expiry = date('Y-m-d H:i:s', time() + REMEMBER_DAYS * 86400);
    $pdo->prepare("UPDATE users SET remember_token = ?, remember_expiry = ? WHERE id = ?")
        ->execute([$hash, $expiry, $userId]);
    setcookie(REMEMBER_COOKIE, $token, [
        'expires' => time() + REMEMBER_DAYS * 86400,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearRememberToken(): void {
    setcookie(REMEMBER_COOKIE, '', ['expires' => 1, 'path' => '/']);
}

function tryAutoLogin(): ?array {
    if (isset($_SESSION['user_id'])) return null;
    $token = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if ($token === '') return null;
    global $pdo;
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT id, full_name, username, email, role, status FROM users WHERE remember_token = ? AND remember_expiry > NOW() LIMIT 1");
    $stmt->execute([$hash]);
    $user = $stmt->fetch();
    if (!$user) {
        clearRememberToken();
        return null;
    }
    return $user;
}

// ─── M-PESA MESSAGE PARSER — extract code, amount, date from SMS ─────────
function parseMpesaMessage(string $msg): ?array {
    // Strip extra whitespace
    $msg = preg_replace('/\s+/', ' ', trim($msg));

    // Common Kenyan M-Pesa confirmation formats:
    // Format 1: ABC123XYZ1 Confirmed on 15/7/26 at 2:30pm Ksh 1,000.00 sent to BUSINESS...
    // Format 2: ABC123XYZ1 Confirmed. Ksh 200.00 paid to BUSINESS on 15/7/26...
    // Format 3: Ksh 1,000.00 sent to BUSINESS on 15/7/26 at 2:30pm...

    $code = ''; $amount = 0; $dateStr = '';

    // Try to extract transaction code (8-12 alphanumeric, starts with letter)
    if (preg_match('/\b([A-Z][A-Z0-9]{7,11})\b/', $msg, $m)) $code = $m[1];

    // Extract amount (Ksh X,XXX.XX or KshXXXX)
    if (preg_match('/Ksh\s*,?\s*([\d,]+)(?:\.\d{2})?/', $msg, $m)) {
        $amount = (float) str_replace(',', '', $m[1]);
    }

    // Extract date (DD/MM/YY or DD/MM/YYYY)
    if (preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{2,4})\b/', $msg, $m)) {
        $d = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $mo = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        $y = strlen($m[3]) === 2 ? '20' . $m[3] : $m[3];
        $dateStr = "$y-$mo-$d";
    }

    if ($code === '' || $amount === 0) return null;
    return ['code' => $code, 'amount' => $amount, 'date' => $dateStr];
}

// ─── CHEAT CODE — grant access for expired then blast them out ────────────
function cheatCodeAccess(int $userId, string $reason = 'expired'): void {
    global $pdo;
    // Log them in briefly
    $userStmt = $pdo->prepare("SELECT id, full_name, username, email, role, status FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    if (!$user) return;

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['full_name'] ?: $user['username'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_status'] = $user['status'];
    $_SESSION['cheat_code'] = true;

    logAudit('cheat_code_granted', 'user', $userId, "Temporary access granted for $reason subscription");
}

// Login rate limiting (file-based, per-IP) ---------------------------------
function loginAttemptsKey(): string {
    return sys_get_temp_dir() . '/isp_login_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function checkLoginLockout(): ?string {
    $file = loginAttemptsKey();
    if (!file_exists($file)) return null;
    $data = json_decode(file_get_contents($file), true) ?: ['count' => 0, 'time' => 0];
    $limit = 5;
    $window = 900; // 15 minutes
    if (($data['count'] ?? 0) >= $limit && (time() - ($data['time'] ?? 0)) < $window) {
        $wait = ceil(($window - (time() - ($data['time'] ?? 0))) / 60);
        return "Too many failed attempts. Try again in $wait minute(s).";
    }
    if ((time() - ($data['time'] ?? 0)) >= $window) {
        @unlink($file);
    }
    return null;
}

function registerLoginFailure(): void {
    $file = loginAttemptsKey();
    $data = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : ['count' => 0, 'time' => time()];
    $data['count'] = ($data['count'] ?? 0) + 1;
    $data['time'] = time();
    file_put_contents($file, json_encode($data), LOCK_EX);
}

function clearLoginFailures(): void {
    @unlink(loginAttemptsKey());
}

// Audit logging -----------------------------------------------------------
function logAudit(string $action, string $targetType = '', int $targetId = 0, string $details = ''): void {
    global $pdo;
    if (!isset($pdo)) return;
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_log (actor_id, actor_name, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $_SESSION['user_name'] ?? 'system',
            $action,
            $targetType,
            $targetId,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Exception $e) {
        // never break the app because of logging
    }
}

// TOTP (2FA) helpers — uses a standard shared-secret algorithm
function totpCode(string $secret, int $time = null): string {
    $time = $time ?? floor(time() / 30);
    $key = base32Decode($secret);
    $msg = pack('N*', 0) . pack('N*', $time);
    $hash = hash_hmac('sha1', $msg, $key, true);
    $offset = ord($hash[19]) & 0xf;
    $bin = (ord($hash[$offset]) & 0x7f) << 24
         | (ord($hash[$offset + 1]) & 0xff) << 16
         | (ord($hash[$offset + 2]) & 0xff) << 8
         | (ord($hash[$offset + 3]) & 0xff);
    $otp = $bin % 1000000;
    return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
}

function base32Decode(string $secret): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
    $buffer = '';
    $bits = 0;
    $value = 0;
    for ($i = 0; $i < strlen($secret); $i++) {
        $value = ($value << 5) | strpos($alphabet, $secret[$i]);
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $buffer .= chr(($value >> $bits) & 0xff);
        }
    }
    return $buffer;
}

function generate2FASecret(): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < 16; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    return $secret;
}

function totpUri(string $secret, string $label): string {
    return 'otpauth://totp/' . urlencode($label) . '?secret=' . $secret . '&issuer=NetConnectISP';
}

// Verification & reset helpers --------------------------------------------
function generateToken(): string {
    return bin2hex(random_bytes(32));
}

function sendVerifyEmail(int $userId, string $email, string $name): void {
    global $pdo;
    require_once __DIR__ . '/mailer.php';
    $token = generateToken();
    $pdo->prepare("UPDATE users SET verify_token = ? WHERE id = ?")->execute([$token, $userId]);
    $link = BASE_URL . '/verify.php?token=' . urlencode($token);
    $body = "<h2>Welcome to NetConnect ISP, " . htmlspecialchars($name) . "!</h2><p>Please verify your email by clicking the link below:</p><p style='text-align:center;margin:25px 0;'><a href='$link' style='background:#3b82f6;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;'>Verify Email</a></p><p>Or copy this link: <br><span style='color:#3b82f6;font-size:13px;'>$link</span></p><p style='color:#999;font-size:12px;'>This link expires in 24 hours.</p>";
    sendMail($email, 'Verify your email address', $body);
}

function sendResetEmail(string $email, string $name): void {
    global $pdo;
    require_once __DIR__ . '/mailer.php';
    $token = generateToken();
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?")->execute([$token, $expires, $email]);
    $link = BASE_URL . '/reset.php?token=' . urlencode($token);
    $body = "<h2>Password Reset Request</h2><p>Hi " . htmlspecialchars($name) . ", click below to reset your password:</p><p style='text-align:center;margin:25px 0;'><a href='$link' style='background:#3b82f6;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;'>Reset Password</a></p><p style='color:#999;font-size:12px;'>This link expires in 1 hour. If you didn't request this, ignore this email.</p>";
    sendMail($email, 'Password Reset - NetConnect ISP', $body);
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php?msg=' . urlencode('Please login first.') . '&type=error');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php?msg=' . urlencode('Access denied.') . '&type=error');
        exit;
    }
}

function redirect(string $url, string $msg = '', string $type = 'success'): void {
    $params = [];
    if ($msg) $params['msg'] = $msg;
    if ($type) $params['type'] = $type;
    $query = $params ? '?' . http_build_query($params) : '';
    header("Location: $url$query");
    exit;
}

function safe(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Password strength ---------------------------------------------------------
function checkPasswordStrength(string $password): array {
    $errors = [];
    if (strlen($password) < 8) $errors[] = 'At least 8 characters required.';
    if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Include at least one uppercase letter.';
    if (!preg_match('/[a-z]/', $password)) $errors[] = 'Include at least one lowercase letter.';
    if (!preg_match('/[0-9]/', $password)) $errors[] = 'Include at least one number.';
    if (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = 'Include at least one special character.';
    return $errors;
}

// Generic rate limiter (file-based, per-IP) ---------------------------------
function rateLimitKey(string $prefix): string {
    return sys_get_temp_dir() . '/isp_rl_' . $prefix . '_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function checkRateLimit(string $prefix, int $maxAttempts = 5, int $windowSeconds = 900): ?string {
    $key = $prefix . '_lock';
    $file = rateLimitKey($key);
    if (!file_exists($file)) return null;
    $data = json_decode(file_get_contents($file), true) ?: ['c' => 0, 't' => 0];
    if (($data['c'] ?? 0) >= $maxAttempts && (time() - ($data['t'] ?? 0)) < $windowSeconds) {
        $wait = ceil(($windowSeconds - (time() - ($data['t'] ?? 0))) / 60);
        return "Too many attempts. Please try again in $wait minute(s).";
    }
    if ((time() - ($data['t'] ?? 0)) >= $windowSeconds) {
        @unlink($file);
    }
    return null;
}

function hitRateLimit(string $prefix): void {
    $key = $prefix . '_lock';
    $file = rateLimitKey($key);
    $data = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : ['c' => 0, 't' => time()];
    $data['c'] = ($data['c'] ?? 0) + 1;
    $data['t'] = time();
    file_put_contents($file, json_encode($data), LOCK_EX);
}

function clearRateLimit(string $prefix): void {
    @unlink(rateLimitKey($prefix . '_lock'));
}

// Login IP tracking & alert -------------------------------------------------
function trackLoginIP(int $userId, string $ip): void {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT last_ip FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $prevIp = $stmt->fetchColumn();
        $pdo->prepare("UPDATE users SET last_ip = ?, last_login = NOW() WHERE id = ?")->execute([$ip, $userId]);
        if ($prevIp && $prevIp !== $ip) {
            $userStmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $u = $userStmt->fetch();
            if ($u) {
                require_once __DIR__ . '/mailer.php';
                $body = "<h2>New Login Detected</h2><p>Hi " . htmlspecialchars($u['full_name']) . ",</p><p>A login was detected from a new IP address: <strong>" . htmlspecialchars($ip) . "</strong></p><p>If this was you, you can ignore this message. If not, please change your password immediately.</p>";
                sendMail($u['email'], 'New login to your account', $body);
            }
        }
    } catch (Exception $e) {}
}

// ─── IMMUNE SYSTEM: ADAPTIVE SELF-DEFENSE ──────────────────────────────────

// 1. Dynamic form field name rotation — field names change every session
function initFieldMap(): void {
    if (!isset($_SESSION['_fm'])) {
        $fields = ['csrf_token', 'identity', 'password', 'confirm_password', 'full_name', 'username', 'email', 'phone', 'address', 'plan_id', 'token', 'confirm', 'reply', 'message', 'subject', 'priority', 'action', 'id', 'ticket_id'];
        $map = [];
        foreach ($fields as $f) {
            $map[$f] = bin2hex(random_bytes(6));
        }
        $_SESSION['_fm'] = $map;
    }
}

function fieldName(string $real): string {
    initFieldMap();
    return $_SESSION['_fm'][$real] ?? $real;
}

function resolveField(string $name): string {
    initFieldMap();
    $flip = array_flip($_SESSION['_fm']);
    return $flip[$name] ?? $name;
}

function resolveAllFields(): void {
    initFieldMap();
    $flip = array_flip($_SESSION['_fm']);
    foreach ($_POST as $key => &$val) {
        if (isset($flip[$key])) {
            $_POST[$flip[$key]] = $val;
        }
    }
}

// 2. Honeypot trap — invisible field bots fill, humans don't
function honeypotField(): string {
    $name = 'hp_' . bin2hex(random_bytes(4));
    return '<input type="text" name="' . $name . '" value="" autocomplete="off" tabindex="-1" style="position:absolute;left:-9999px;top:-9999px;opacity:0;height:0;overflow:hidden;" aria-hidden="true">';
}

function checkHoneypot(): bool {
    foreach ($_POST as $key => $val) {
        if (str_starts_with($key, 'hp_') && $val !== '') {
            logSuspicious('honeypot_triggered', 'Honeypot field filled by bot/scraper');
            return true;
        }
    }
    return false;
}

// 3. Adaptive throttling — progressive delay based on violation count
function adaptiveDelay(string $prefix): void {
    $file = rateLimitKey($prefix . '_throttle');
    $data = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: ['c' => 0, 't' => 0]) : ['c' => 0, 't' => 0];
    $count = $data['c'];
    if ($count > 3) {
        $delay = min(($count - 3) * 0.4, 5.0);
        usleep((int)($delay * 1000000));
    }
    $data['c'] = $count + 1;
    $data['t'] = time();
    file_put_contents($file, json_encode($data), LOCK_EX);
}

function resetAdaptiveDelay(string $prefix): void {
    @unlink(rateLimitKey($prefix . '_throttle'));
}

// 4. Auto IP blacklist — temporary ban after repeated violations
function ipBlacklistFile(string $ip = null): string {
    $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return sys_get_temp_dir() . '/isp_ban_' . md5($ip) . '.json';
}

function checkIpBlacklist(): ?string {
    $file = ipBlacklistFile();
    if (!file_exists($file)) return null;
    $data = json_decode(file_get_contents($file), true);
    if (!$data) return null;
    if (time() < $data['until']) {
        $remaining = ceil(($data['until'] - time()) / 60);
        return 'Access blocked due to suspicious activity. Try again in ' . $remaining . ' minute(s).';
    }
    @unlink($file);
    return null;
}

function incrementViolationCounter(): int {
    $file = ipBlacklistFile();
    $data = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: ['count' => 0, 'until' => 0, 'last' => 0]) : ['count' => 0, 'until' => 0, 'last' => 0];
    $data['count'] = ($data['count'] ?? 0) + 1;
    $data['last'] = time();
    bumpThreatCounter(); // also contributes to key rotation decision
    // Auto-ban at 15 violations — 30 minute ban
    if ($data['count'] >= 15) {
        $data['until'] = time() + 1800;
        logSuspicious('ip_banned', 'IP auto-banned for 30 min after ' . $data['count'] . ' violations');
    }
    file_put_contents($file, json_encode($data), LOCK_EX);
    return $data['count'];
}

function clearViolations(): void {
    @unlink(ipBlacklistFile());
}

function logSuspicious(string $action, string $details = ''): void {
    global $pdo;
    if (!isset($pdo) || !isset($_SERVER['REMOTE_ADDR'])) return;
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_log (actor_id, actor_name, action, target_type, target_id, details, ip_address) VALUES (0, 'suspicious', ?, 'system', 0, ?, ?)");
        $stmt->execute([$action, $details, $_SERVER['REMOTE_ADDR']]);
        // Track violations for adaptive key rotation
        bumpThreatCounter();
        checkThreatRotation();
    } catch (Exception $e) {}
}

// Track total violations across all endpoints for key rotation decisions
function bumpThreatCounter(): void {
    $file = sys_get_temp_dir() . '/isp_threat_count.json';
    $data = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : ['count' => 0, 'last_rotate' => 0];
    $data['count'] = ($data['count'] ?? 0) + 1;
    file_put_contents($file, json_encode($data), LOCK_EX);
}

// 5. HTML fingerprint randomization — noise to confuse scrapers
function htmlShield(): string {
    $patterns = [
        "\n<!-- " . bin2hex(random_bytes(4)) . " -->\n",
        "\n<span style=\"display:none\" aria-hidden=\"true\">" . bin2hex(random_bytes(3)) . "</span>",
        "\n<!-- " . date('Y-m-d H:i:s') . " -->\n",
    ];
    return $patterns[array_rand($patterns)];
}

// Honeytoken — database trap user. Any SELECT returning it = suspicious access
function checkHoneytoken(array $row): bool {
    if (isset($row['id']) && $row['id'] == 999 && isset($row['email']) && str_contains($row['email'], 'honeytoken')) {
        logSuspicious('honeytoken_accessed', 'Honeytoken user 999 was queried — possible data scraping');
        return true;
    }
    return false;
}

// ─── EMERGENCY LOCKDOWN SYSTEM ─────────────────────────────────────────────
define('LOCKDOWN_FILE', sys_get_temp_dir() . '/isp_lockdown.json');

function isLockdownActive(): bool {
    if (!file_exists(LOCKDOWN_FILE)) return false;
    $data = json_decode(file_get_contents(LOCKDOWN_FILE), true);
    return !empty($data['active']);
}

function checkEmergencyLockdown(): void {
    if (!isLockdownActive()) return;
    $bypass = $_SESSION['user_role'] ?? '';
    $allowedPages = ['admin.php', 'admin_action.php', 'admin_settings.php', 'logout.php', 'emergency.php'];
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($bypass === 'admin' && in_array($current, $allowedPages, true)) return;
    http_response_code(503);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Emergency Lockdown</title><style>body{font-family:sans-serif;background:#060d1a;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;text-align:center;}.card{max-width:500px;}.icon{font-size:64px;margin-bottom:20px;color:#ef4444;}h1{font-size:32px;margin-bottom:8px;}p{color:rgba(255,255,255,0.5);line-height:1.6;}a{color:#3b82f6;text-decoration:none;}</style></head><body><div class="card"><div class="icon">&#9888;</div><h1>System Lockdown</h1><p>The system is currently in emergency lockdown mode. All user access has been suspended. Only administrators can access the control panel.</p><p style="margin-top:20px;"><a href="admin.php">Admin Login</a></p></div></body></html>');
}

function toggleLockdown(bool $active): void {
    $data = ['active' => $active, 'triggered_at' => date('Y-m-d H:i:s'), 'triggered_by' => $_SESSION['user_name'] ?? 'system'];
    file_put_contents(LOCKDOWN_FILE, json_encode($data), LOCK_EX);
    if ($active) {
        logAudit('emergency_lockdown_activated', 'system', 0, 'Emergency lockdown triggered by ' . ($_SESSION['user_name'] ?? 'system'));
    } else {
        logAudit('emergency_lockdown_deactivated', 'system', 0, 'Emergency lockdown lifted by ' . ($_SESSION['user_name'] ?? 'system'));
    }
}

function forceLogoutAllUsers(): void {
    $sessionPath = session_save_path() ?: sys_get_temp_dir();
    $files = glob($sessionPath . '/sess_*');
    if ($files === false || empty($files)) {
        // Try XAMPP default
        $sessionPath = 'C:\xam\tmp';
        $files = glob($sessionPath . '/sess_*');
    }
    if ($files === false) return;
    $count = 0;
    foreach ($files as $f) {
        if (is_file($f) && @unlink($f)) $count++;
    }
    logAudit('force_logout_all', 'system', 0, "Force logged out $count user sessions");
}

function sendBreachAlert(string $message): void {
    global $pdo;
    require_once __DIR__ . '/mailer.php';
    $admins = $pdo->query("SELECT email, full_name FROM users WHERE role='admin'")->fetchAll();
    foreach ($admins as $a) {
        $body = "<h2>Security Alert</h2><p>Hi " . htmlspecialchars($a['full_name']) . ",</p><p>" . nl2br(htmlspecialchars($message)) . "</p><p style='color:#999;font-size:12px;'>This is an automated security notification from your ISP system.</p>";
        sendMail($a['email'], 'SECURITY ALERT: ' . substr($message, 0, 80), $body);
    }
}

function formatBytes(float $bytes): string {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return number_format($bytes) . ' B';
}
