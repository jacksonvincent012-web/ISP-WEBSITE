<?php
require_once 'config/db.php';
require_once 'config/functions.php';
secureSessionStart(); sendSecurityHeaders();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}

$phone = trim($_POST['phone'] ?? $_POST['voucher'] ?? '');

if ($phone === '') {
    redirect('login.php', 'Please enter your phone number or voucher code.', 'error');
}

// Normalize phone: remove non-digits
$phoneClean = preg_replace('/[^0-9]/', '', $phone);

// Try to find user by phone (search both raw and encrypted)
$stmt = $pdo->prepare("SELECT id, full_name, username, email, role, status, phone, phone_encrypted FROM users WHERE phone LIKE ? OR phone LIKE ? OR phone LIKE ?");
$stmt->execute(["%$phoneClean%", "%$phone%", "%" . substr($phoneClean, -9) . "%"]);
$user = $stmt->fetch();

if (!$user && $phoneClean !== '') {
    // Try matching last 9 digits against encrypted phone (decrypt and match)
    $allUsers = $pdo->query("SELECT id, full_name, username, email, role, status, phone, phone_encrypted FROM users WHERE phone_encrypted IS NOT NULL")->fetchAll();
    foreach ($allUsers as $u) {
        $decrypted = decryptField($u['phone_encrypted'], $u['id']);
        $decryptedClean = preg_replace('/[^0-9]/', '', $decrypted);
        if (strpos($decryptedClean, substr($phoneClean, -9)) !== false) {
            $user = $u;
            break;
        }
    }
}

if (!$user) {
    redirect('login.php', 'No account found with that phone number. Please register first.', 'error');
}

if ($user['status'] === 'suspended') {
    redirect('login.php', 'Your account has been suspended. Contact support.', 'error');
}

// Auto-login user
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['full_name'] ?: $user['username'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_status'] = $user['status'];
setRememberToken($user['id']);

logAudit('reconnect', 'user', $user['id'], 'Connected via phone: ' . substr($phoneClean, -4));

header('Location: dashboard.php?msg=' . urlencode('Welcome back! You are now connected.') . '&type=success');
exit;