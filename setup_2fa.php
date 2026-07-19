<?php
require_once 'config/db.php';
require_once 'config/functions.php';
secureSessionStart(); sendSecurityHeaders();
requireAdmin();

$userId = $_SESSION['user_id'];
$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$userId]);
$user = $user->fetch();

// Decrypt existing secret or generate a new one (stored encrypted)
$twofaSecret = $user['twofa_secret'] ? decryptField($user['twofa_secret'], $userId) : '';
if (empty($twofaSecret)) {
    $twofaSecret = generate2FASecret();
    $encrypted = encryptField($twofaSecret, $userId);
    $pdo->prepare("UPDATE users SET twofa_secret = ? WHERE id = ?")->execute([$encrypted, $userId]);
}

$uri = totpUri($twofaSecret, $user['email']);
$msg = '';
$type = 'success';

// Enable 2FA after verifying a code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'enable') {
    verifyCsrf();
    $code = trim($_POST['code'] ?? '');
    if (totpCode($twofaSecret) === $code) {
        $pdo->prepare("UPDATE users SET twofa_enabled = 1 WHERE id = ?")->execute([$userId]);
        logAudit('2fa_enabled', 'user', $userId);
        $msg = 'Two-factor authentication enabled successfully!';
    } else {
        $msg = 'Invalid code. Try again.';
        $type = 'error';
    }
}

// Disable 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'disable') {
    verifyCsrf();
    $pdo->prepare("UPDATE users SET twofa_enabled = 0 WHERE id = ?")->execute([$userId]);
    logAudit('2fa_disabled', 'user', $userId);
    $msg = 'Two-factor authentication disabled.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup 2FA — NetConnect ISP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:#060d1a;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:20px;padding:40px;max-width:480px;width:100%;text-align:center;}
.card h2{font-size:22px;margin-bottom:8px;}
.card p{color:rgba(255,255,255,0.5);font-size:14px;margin-bottom:20px;}
.qr{background:#fff;padding:16px;border-radius:12px;display:inline-block;margin-bottom:16px;}
.secret{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:10px;font-family:monospace;font-size:14px;color:#60a5fa;margin-bottom:20px;word-break:break-all;}
.msg{padding:12px 16px;border-radius:12px;margin-bottom:20px;font-size:14px;}
.error{background:rgba(239,68,68,0.12);color:#fca5a5;border:1px solid rgba(239,68,68,0.2);}
.success{background:rgba(34,197,94,0.12);color:#86efac;border:1px solid rgba(34,197,94,0.2);}
input[type=text]{width:100%;padding:12px 14px;text-align:center;font-size:18px;letter-spacing:4px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;outline:none;}
input[type=text]:focus{border-color:#3b82f6;}
.btn{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;border-radius:10px;border:none;font-weight:600;font-size:14px;cursor:pointer;transition:all 0.2s;}
.btn-primary{background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(59,130,246,0.4);}
.btn-danger{background:rgba(239,68,68,0.15);color:#fca5a5;border:1px solid rgba(239,68,68,0.2);}
.back{margin-top:20px;}
.back a{color:rgba(255,255,255,0.4);font-size:13px;text-decoration:none;}
.status{display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;margin-bottom:16px;}
.status-on{background:rgba(34,197,94,0.15);color:#22c55e;}
.status-off{background:rgba(234,179,8,0.15);color:#eab308;}
</style>
</head>
<body>
<div class="card">
    <h2><i class="fas fa-shield-alt" style="color:#3b82f6;"></i> Two-Factor Authentication</h2>
    <p>Scan this QR code with Google Authenticator, then enter the 6-digit code.</p>

    <div class="status <?= $user['twofa_enabled'] ? 'status-on' : 'status-off' ?>">
        <?= $user['twofa_enabled'] ? 'Enabled' : 'Not Enabled' ?>
    </div>

    <?php if ($msg): ?><div class="msg <?= $type ?>"><?= safe($msg) ?></div><?php endif; ?>

    <div class="qr">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($uri) ?>" alt="2FA QR" width="200" height="200">
    </div>
    <div class="secret"><?= safe($twofaSecret) ?></div>

    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="step" value="enable">
        <input type="text" name="code" inputmode="numeric" maxlength="6" placeholder="000000" required>
        <div style="margin-top:16px;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Enable 2FA</button>
        </div>
    </form>

    <?php if ($user['twofa_enabled']): ?>
    <form method="POST" style="margin-top:10px;">
        <?= csrfField() ?>
        <input type="hidden" name="step" value="disable">
        <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Disable</button>
    </form>
    <?php endif; ?>

    <div class="back"><a href="admin.php"><i class="fas fa-arrow-left"></i> Back to Admin</a></div>
</div>
</body>
</html>
