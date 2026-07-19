<?php
require_once 'config/db.php';
require_once 'config/functions.php';
secureSessionStart(); sendSecurityHeaders();

// Must have passed password first
if (empty($_SESSION['2fa_pending']) || empty($_SESSION['user_id'])) {
    redirect('login.php', 'Please login first.', 'error');
}

$userId = $_SESSION['user_id'];
$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$userId]);
$user = $user->fetch();

// Decrypt the 2FA secret (stored encrypted in DB)
$twofaSecret = $user['twofa_secret'] ? decryptField($user['twofa_secret'], $userId) : '';

$msg = '';
$type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    // Rate limit 2FA — max 5 attempts per IP per 15 minutes
    $rl = checkRateLimit('2fa', 5, 900);
    if ($rl !== null) {
        $msg = $rl;
    } else {
        hitRateLimit('2fa');
        $code = trim($_POST['code'] ?? '');
        if ($twofaSecret !== '' && totpCode($twofaSecret) === $code) {
            clearRateLimit('2fa');
            unset($_SESSION['2fa_pending']);
            logAudit('2fa_verified', 'user', $userId);
            if ($user['role'] === 'admin') {
                redirect('admin.php', 'Welcome back, Admin!');
            }
            redirect('dashboard.php', 'Login successful!');
        } else {
            logAudit('2fa_failed', 'user', $userId);
            $msg = 'Invalid code. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>2FA Verification — NetConnect ISP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#060d1a,#0f1b33,#060d1a);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.card{background:rgba(255,255,255,0.05);backdrop-filter:blur(30px);border:1px solid rgba(255,255,255,0.08);border-radius:20px;padding:40px;max-width:400px;width:100%;text-align:center;}
.icon{width:60px;height:60px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;box-shadow:0 8px 25px rgba(59,130,246,0.3);}
.icon i{font-size:24px;color:#fff;}
.card h2{font-size:22px;margin-bottom:6px;}
.card p{color:rgba(255,255,255,0.5);font-size:13px;margin-bottom:20px;}
.msg{padding:12px 16px;border-radius:12px;margin-bottom:18px;font-size:13px;}
.error{background:rgba(239,68,68,0.12);color:#fca5a5;border:1px solid rgba(239,68,68,0.2);}
input[type=text]{width:100%;padding:14px;text-align:center;font-size:22px;letter-spacing:6px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;outline:none;}
input[type=text]:focus{border-color:#3b82f6;}
.btn{width:100%;padding:14px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:600;cursor:pointer;margin-top:16px;transition:all 0.3s;}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(59,130,246,0.4);}
.back a{color:rgba(255,255,255,0.4);font-size:13px;text-decoration:none;}
</style>
</head>
<body>
<div class="card">
    <div class="icon"><i class="fas fa-mobile-alt"></i></div>
    <h2>Two-Factor Authentication</h2>
    <p>Enter the 6-digit code from your authenticator app</p>

    <?php if ($msg): ?><div class="msg error"><?= safe($msg) ?></div><?php endif; ?>

    <form method="POST">
        <?= csrfField() ?>
        <input type="text" name="code" inputmode="numeric" maxlength="6" placeholder="000000" autofocus required>
        <button type="submit" class="btn"><i class="fas fa-shield-alt"></i> Verify</button>
    </form>
    <p style="margin-top:16px;"><a class="back" href="logout.php"><i class="fas fa-sign-out-alt"></i> Cancel</a></p>
</div>
</body>
</html>
