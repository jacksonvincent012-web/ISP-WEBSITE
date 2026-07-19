<?php
require_once 'config/db.php';
require_once 'config/functions.php';
secureSessionStart(); sendSecurityHeaders();

$msg = '';
$type = 'success';
$verified = false;

if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE verify_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $pdo->prepare("UPDATE users SET email_verified = 1, verify_token = NULL WHERE id = ?")->execute([$user['id']]);
        logAudit('email_verified', 'user', $user['id'], 'Email: ' . $user['email']);
        $msg = 'Email verified successfully! You can now log in.';
        $verified = true;
    } else {
        $msg = 'Invalid or expired verification link.';
        $type = 'error';
    }
} else {
    redirect('login.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Email Verified — NetConnect ISP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#060d1a,#0f1b33,#060d1a);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.card{background:rgba(255,255,255,0.05);backdrop-filter:blur(30px);border:1px solid rgba(255,255,255,0.08);border-radius:20px;padding:45px 35px;max-width:420px;width:100%;text-align:center;}
.icon{width:70px;height:70px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:30px;}
.icon.success{background:rgba(34,197,94,0.15);color:#22c55e;}
.icon.error{background:rgba(239,68,68,0.15);color:#fca5a5;}
.card h2{color:#fff;font-size:24px;margin-bottom:8px;}
.card p{color:rgba(255,255,255,0.5);font-size:14px;margin-bottom:20px;}
.btn{display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;border-radius:10px;text-decoration:none;font-weight:600;transition:all 0.3s;}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(59,130,246,0.4);}
</style>
</head>
<body>
<div class="card">
    <div class="icon <?= $type ?>"><i class="fas <?= $verified ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i></div>
    <h2><?= $verified ? 'Verified!' : 'Verification Failed' ?></h2>
    <p><?= safe($msg) ?></p>
    <?php if ($verified): ?>
    <a href="login.php" class="btn"><i class="fas fa-arrow-right"></i> Go to Login</a>
    <?php else: ?>
    <a href="login.php" class="btn"><i class="fas fa-arrow-left"></i> Back to Login</a>
    <?php endif; ?>
</div>
</body>
</html>
