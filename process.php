<?php
require_once 'config/db.php';
require_once 'config/functions.php';
secureSessionStart(); sendSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('register.php');
}

// Immune system
$ban = checkIpBlacklist();
if ($ban !== null) {
    http_response_code(429); die($ban);
}
if (checkHoneypot()) {
    incrementViolationCounter();
    adaptiveDelay('register');
    redirect('register.php', 'Invalid request.', 'error');
}
adaptiveDelay('register');

resolveAllFields();
verifyCsrf();

// Rate limit — max 3 registrations per IP per hour
$rl = checkRateLimit('register', 3, 3600);
if ($rl !== null) {
    redirect('register.php', $rl, 'error');
}

$name     = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$plan_id  = trim($_POST['plan_id'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';

if ($username === '' || $email === '' || $password === '' || $confirm === '') {
    hitRateLimit('register');
    redirect('register.php', 'Please fill in all required fields.', 'error');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    hitRateLimit('register');
    redirect('register.php', 'Invalid email format.', 'error');
}

$pwErrors = checkPasswordStrength($password);
if (!empty($pwErrors)) {
    hitRateLimit('register');
    redirect('register.php', implode(' ', $pwErrors), 'error');
}

if ($password !== $confirm) {
    hitRateLimit('register');
    redirect('register.php', 'Passwords do not match.', 'error');
}

if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    redirect('register.php', 'Username must be at least 3 characters (letters, numbers, underscores only).', 'error');
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        redirect('register.php', 'Account creation unavailable. Please check your details and try again.', 'error');
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    if ($stmt->fetch()) {
        redirect('register.php', 'Account creation unavailable. Please check your details and try again.', 'error');
    }

    $pdo->beginTransaction();

    $stmt =     $pdo->prepare("INSERT INTO users (username, email, password, full_name, phone, role, status, email_verified) VALUES (:username, :email, :password, :full_name, :phone, 'user', 'active', 1)");
    $stmt->execute([
        ':username'  => $username,
        ':email'     => $email,
        ':password'  => password_hash($password, PASSWORD_DEFAULT),
        ':full_name' => $name,
        ':phone'     => $phone,
    ]);
    $userId = $pdo->lastInsertId();
    if ($phone) {
        $pdo->prepare("UPDATE users SET phone_encrypted = ? WHERE id = ?")->execute([encryptField($phone, $userId), $userId]);
    }

    if ($plan_id) {
        $pStmt = $pdo->prepare("SELECT * FROM plans WHERE id = ?");
        $pStmt->execute([$plan_id]);
        $plan = $pStmt->fetch();
        if ($plan) {
            $start = date('Y-m-d H:i:s');
            $hours = $plan['duration_hours'] ?: ($plan['duration_months'] * 720);
            $end   = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
            $pdo->prepare("INSERT INTO subscriptions (user_id, plan_id, start_date, end_date, status) VALUES (?, ?, ?, ?, 'active')")->execute([$userId, $plan_id, $start, $end]);

            $subId = $pdo->lastInsertId();
            $invNo = 'INV-'.date('Y').'-'.str_pad($subId, 4, '0', STR_PAD_LEFT);
            $pdo->prepare("INSERT INTO invoices (user_id, subscription_id, invoice_no, amount, due_date, status) VALUES (?, ?, ?, ?, ?, 'unpaid')")->execute([$userId, $subId, $invNo, $plan['price'], $start]);

            $txnRef = 'TXN-' . date('Y') . str_pad($subId, 4, '0', STR_PAD_LEFT);
            $pdo->prepare("INSERT INTO payments (user_id, subscription_id, amount, payment_method, status, transaction_ref) VALUES (?, ?, ?, 'credit_card', 'completed', ?)")->execute([$userId, $subId, $plan['price'], $txnRef]);
        }
    }

    $pdo->commit();

    clearViolations();
    resetAdaptiveDelay('register');

    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $name ?: $username;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role'] = 'user';
    $_SESSION['user_status'] = 'active';
    setRememberToken($userId);

    // Welcome email (no verification required — auto-verified)
    sendVerifyEmail($userId, $email, $name ?: $username);

    header('Location: dashboard.php?welcome=1');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    redirect('register.php', 'Sorry, something went wrong. Please try again.', 'error');
}
