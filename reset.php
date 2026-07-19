<?php
require_once 'config/db.php';
require_once 'config/functions.php';
secureSessionStart(); sendSecurityHeaders();

$msg = '';
$type = 'error';
$showForm = false;
$token = '';

if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {
        $showForm = true;
    } else {
        $msg = 'Invalid or expired reset token. Request a new one.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    if (checkHoneypot()) {
        $msg = 'Invalid request.';
    } else {
    resolveAllFields();
    verifyCsrf();
    $token = trim($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!empty(checkPasswordStrength($password))) {
        $msg = 'Password must be at least 8 characters with uppercase, lowercase, number, and special character.';
    } elseif ($password !== $confirm) {
        $msg = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?")->execute([$hash, $user['id']]);
            logAudit('password_reset', 'user', $user['id']);
            $msg = 'Password reset successfully! You can now log in.';
            $type = 'success';
            $showForm = false;
        } else {
            $msg = 'Invalid or expired token.';
        }
    }
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — NetConnect ISP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#060d1a,#0f1b33,#060d1a);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.card{background:rgba(255,255,255,0.05);backdrop-filter:blur(30px);border:1px solid rgba(255,255,255,0.08);border-radius:20px;padding:45px 35px;max-width:420px;width:100%;text-align:center;}
.icon{width:60px;height:60px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;box-shadow:0 8px 25px rgba(59,130,246,0.3);}
.icon i{font-size:24px;color:#fff;}
h2{color:#fff;font-size:24px;margin-bottom:6px;}
p{color:rgba(255,255,255,0.5);font-size:14px;margin-bottom:20px;}
.msg{padding:12px;border-radius:12px;margin-bottom:18px;font-size:13px;}
.success{background:rgba(34,197,94,0.12);color:#86efac;border:1px solid rgba(34,197,94,0.2);}
.error{background:rgba(239,68,68,0.12);color:#fca5a5;border:1px solid rgba(239,68,68,0.2);}
.input-group{margin-bottom:14px;text-align:left;}
.input-group label{display:block;color:rgba(255,255,255,0.7);font-size:13px;margin-bottom:5px;}
input[type=password]{width:100%;padding:13px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:12px;color:#fff;font-size:15px;outline:none;}
input:focus{border-color:#3b82f6;}
.btn{width:100%;padding:14px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;margin-top:6px;transition:all 0.3s;}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(59,130,246,0.4);}
.link{color:rgba(255,255,255,0.4);font-size:13px;text-decoration:none;display:inline-block;margin-top:16px;}
</style>
</head>
<body>
<div class="card">
    <div class="icon"><i class="fas fa-key"></i></div>
    <h2>Reset Password</h2>
    <p><?= $showForm ? 'Choose a new password for your account.' : '' ?></p>

    <?php if ($msg): ?><div class="msg <?= $type ?>"><?= safe($msg) ?></div><?php endif; ?>

    <?php if ($showForm): ?>
    <form method="POST">
        <?= csrfField() . honeypotField() ?>
        <input type="hidden" name="token" value="<?= safe($token) ?>">
        <div class="input-group"><label>New Password</label><input type="password" name="password" placeholder="Min 6 characters" required minlength="6"></div>
        <div class="input-group"><label>Confirm Password</label><input type="password" name="confirm" placeholder="Repeat password" required></div>
        <button type="submit" class="btn"><i class="fas fa-check"></i> Reset Password</button>
    </form>
    <?php endif; ?>

    <p><a href="login.php" class="link"><i class="fas fa-arrow-left"></i> Back to Login</a></p>
</div>
</body>
</html>
