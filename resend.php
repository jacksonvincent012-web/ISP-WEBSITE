<?php
require_once 'config/db.php';
require_once 'config/functions.php';
secureSessionStart(); sendSecurityHeaders();

$msg = '';
$type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '');
    $stmt = $pdo->prepare("SELECT id, full_name, email_verified FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && !$user['email_verified']) {
        sendVerifyEmail($user['id'], $email, $user['full_name']);
        $msg = 'Verification email resent. Check your inbox.';
        $type = 'success';
    } else {
        $msg = 'If that email exists and is unverified, a new link has been sent.';
        $type = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Resend Verification — NetConnect ISP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#060d1a,#0f1b33,#060d1a);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.card{background:rgba(255,255,255,0.05);backdrop-filter:blur(30px);border:1px solid rgba(255,255,255,0.08);border-radius:20px;padding:45px 35px;max-width:400px;width:100%;text-align:center;}
.icon{width:60px;height:60px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;box-shadow:0 8px 25px rgba(59,130,246,0.3);}
.icon i{font-size:24px;color:#fff;}
h2{color:#fff;font-size:22px;margin-bottom:6px;}
p{color:rgba(255,255,255,0.5);font-size:13px;margin-bottom:18px;}
.msg{padding:12px;border-radius:12px;margin-bottom:15px;font-size:13px;}
.success{background:rgba(34,197,94,0.12);color:#86efac;border:1px solid rgba(34,197,94,0.2);}
.error{background:rgba(239,68,68,0.12);color:#fca5a5;border:1px solid rgba(239,68,68,0.2);}
input[type=email]{width:100%;padding:13px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:12px;color:#fff;font-size:14px;outline:none;}
input:focus{border-color:#3b82f6;}
.btn{width:100%;padding:14px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;margin-top:14px;transition:all 0.3s;}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(59,130,246,0.4);}
.link{color:rgba(255,255,255,0.4);font-size:13px;text-decoration:none;display:inline-block;margin-top:14px;}
</style>
</head>
<body>
<div class="card">
    <div class="icon"><i class="fas fa-envelope"></i></div>
    <h2>Resend Verification</h2>
    <p>Enter your email to receive a new verification link.</p>
    <?php if ($msg): ?><div class="msg <?= $type ?>"><?= safe($msg) ?></div><?php endif; ?>
    <form method="POST">
        <?= csrfField() ?>
        <input type="email" name="email" placeholder="your@email.com" required>
        <button type="submit" class="btn"><i class="fas fa-paper-plane"></i> Resend</button>
    </form>
    <p><a href="login.php" class="link"><i class="fas fa-arrow-left"></i> Back to Login</a></p>
</div>
</body>
</html>
