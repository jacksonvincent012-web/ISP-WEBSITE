<?php
require_once 'config/db.php';
require_once 'config/functions.php';
secureSessionStart(); sendSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}

// ─── Change Password (authenticated users) ────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'change_password') {
    requireLogin();
    $current = $_POST['current_password'] ?? '';
    $newPw = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($current === '' || $newPw === '' || $confirm === '') {
        redirect('dashboard.php?tab=profile', 'All fields are required.', 'error');
    }
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $hash = $stmt->fetchColumn();
    if (!$hash || !password_verify($current, $hash)) {
        redirect('dashboard.php?tab=profile', 'Current password is incorrect.', 'error');
    }
    $pwErrors = checkPasswordStrength($newPw);
    if (!empty($pwErrors)) {
        redirect('dashboard.php?tab=profile', implode(' ', $pwErrors), 'error');
    }
    if ($newPw !== $confirm) {
        redirect('dashboard.php?tab=profile', 'New passwords do not match.', 'error');
    }
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($newPw, PASSWORD_DEFAULT), $_SESSION['user_id']]);
    logAudit('change_password', 'user', $_SESSION['user_id']);
    redirect('dashboard.php?tab=profile', 'Password updated successfully!', 'success');
}

// Immune system: IP blacklist, honeypot, adaptive delay
$ban = checkIpBlacklist();
if ($ban !== null) {
    http_response_code(429); die($ban);
}
if (checkHoneypot()) {
    incrementViolationCounter();
    adaptiveDelay('auth');
    redirect('login.php', 'Invalid request.', 'error');
}
adaptiveDelay('auth');

// Rate limiting — block brute force
$lock = checkLoginLockout();
if ($lock !== null) {
    redirect('login.php', $lock, 'error');
}

resolveAllFields();
// CSRF protection
verifyCsrf();

$identity = trim($_POST['identity'] ?? '');
$password = $_POST['password'] ?? '';

if ($identity === '' || $password === '') {
    redirect('login.php', 'Please enter email/username and password.', 'error');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :identity OR username = :identity2 LIMIT 1");
    $stmt->execute([':identity' => $identity, ':identity2' => $identity]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        registerLoginFailure();
        redirect('login.php', 'Invalid email/username or password.', 'error');
    }

    if ($user['status'] === 'suspended') {
        redirect('login.php', 'Your account has been suspended. Contact support.', 'error');
    }

    if (!$user['email_verified']) {
        redirect('login.php', 'Please verify your email before logging in. Check your inbox.', 'error');
    }

    clearLoginFailures();
    clearViolations();
    resetAdaptiveDelay('auth');

    logAudit('login_success', 'user', $user['id']);
    trackLoginIP($user['id'], $_SERVER['REMOTE_ADDR'] ?? '');

    // Rotate session ID on privilege change (anti session-fixation)
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['full_name'] ?: $user['username'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_status'] = $user['status'];

    // Set remember token for auto-login (unless admin)
    if ($user['role'] !== 'admin') {
        setRememberToken($user['id']);
    }

    // 2FA challenge if enabled
    if ($user['twofa_enabled']) {
        $_SESSION['2fa_pending'] = true;
        redirect('2fa.php');
    }

    if ($user['role'] === 'admin') {
        redirect('admin.php', 'Welcome back, Admin!');
    }
    redirect('dashboard.php', 'Login successful!');

} catch (PDOException $e) {
    redirect('login.php', 'Something went wrong. Please try again.', 'error');
}
