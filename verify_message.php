<?php
require_once 'config/db.php';
require_once 'config/functions.php';
secureSessionStart(); sendSecurityHeaders();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$msg = '';
$type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mpesa_message'])) {
    $raw = trim($_POST['mpesa_message'] ?? '');
    if ($raw === '') {
        $msg = 'Please paste your M-Pesa confirmation message.';
    } else {
        $parsed = parseMpesaMessage($raw);
        if (!$parsed) {
            $msg = 'Could not read this message. Make sure you paste the full M-Pesa confirmation SMS.';
        } else {
            // Look for a payment matching this transaction code or amount+date
            $code = $parsed['code'];
            $amount = $parsed['amount'];
            $date = $parsed['date'];

            $stmt = $pdo->prepare("SELECT p.*, u.full_name, u.username, u.status as user_status FROM payments p JOIN users u ON p.user_id = u.id WHERE (p.transaction_ref LIKE ? OR p.transaction_ref LIKE ?) AND p.status = 'completed' LIMIT 1");
            $stmt->execute(["%$code%", "%$code%"]);
            $payment = $stmt->fetch();

            if (!$payment && $amount > 0) {
                // Try matching by amount + date
                $stmt = $pdo->prepare("SELECT p.*, u.full_name, u.username, u.status as user_status FROM payments p JOIN users u ON p.user_id = u.id WHERE p.amount = ? AND DATE(p.paid_at) = ? AND p.status = 'completed' LIMIT 1");
                $stmt->execute([$amount, $date]);
                $payment = $stmt->fetch();
            }

            if (!$payment) {
                $msg = 'No matching payment found. Please check the message and try again, or contact support.';
            } elseif ($payment['user_status'] === 'suspended') {
                $msg = 'Your account has been suspended. Please contact support.';
            } else {
                // Check subscription status
                $subStmt = $pdo->prepare("SELECT id, status, end_date FROM subscriptions WHERE user_id = ? ORDER BY end_date DESC LIMIT 1");
                $subStmt->execute([$payment['user_id']]);
                $sub = $subStmt->fetch();

                $subActive = $sub && $sub['status'] === 'active' && strtotime($sub['end_date']) > time();

                if ($subActive) {
                    // Normal connection — sub is active
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $payment['user_id'];
                    $_SESSION['user_name'] = $payment['full_name'] ?: $payment['username'];
                    $_SESSION['user_email'] = $payment['email'];
                    $_SESSION['user_role'] = 'user';
                    $_SESSION['user_status'] = 'active';
                    setRememberToken($payment['user_id']);
                    logAudit('message_connect', 'user', $payment['user_id'], 'Connected via M-Pesa message');
                    header('Location: dashboard.php?msg=' . urlencode('Connected successfully! Your internet is active.') . '&type=success');
                    exit;
                } else {
                    // Expired subscription — cheat code grants brief access then blast
                    cheatCodeAccess($payment['user_id'], 'expired');
                    logAudit('cheat_code_used', 'user', $payment['user_id'], 'Expired sub cheat code used via message');
                    // Redirect to dashboard — checkSubscriptionExpiry() will nuke them
                    header('Location: dashboard.php');
                    exit;
                }
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify M-Pesa — NetConnect KE</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;min-height:100vh;background:#080d1a;display:flex;align-items:center;justify-content:center;padding:30px 16px;position:relative;overflow-x:hidden;}
body::before{content:'';position:fixed;width:600px;height:600px;background:radial-gradient(circle,rgba(59,130,246,0.12),transparent 70%);top:-200px;right:-200px;animation:float 12s ease-in-out infinite;pointer-events:none;}
body::after{content:'';position:fixed;width:500px;height:500px;background:radial-gradient(circle,rgba(99,102,241,0.08),transparent 70%);bottom:-150px;left:-150px;animation:float 15s ease-in-out infinite reverse;pointer-events:none;}
@keyframes float{0%,100%{transform:translate(0,0) scale(1);}33%{transform:translate(30px,-30px) scale(1.05);}66%{transform:translate(-20px,20px) scale(0.95);}}
.card{background:rgba(18,25,45,0.88);backdrop-filter:blur(40px);border:1px solid rgba(255,255,255,0.07);border-radius:28px;padding:36px 32px;max-width:480px;width:100%;position:relative;z-index:2;box-shadow:0 40px 80px rgba(0,0,0,0.6);}
.logo{text-align:center;margin-bottom:20px;}
.logo .icon{width:52px;height:52px;margin:0 auto 12px;background:linear-gradient(135deg,#3b82f6,#6366f1);border-radius:14px;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 28px rgba(59,130,246,0.35);}
.logo .icon i{font-size:22px;color:#fff;}
.logo h1{color:#fff;font-size:20px;font-weight:800;letter-spacing:-0.3px;}
.logo h1 span{background:linear-gradient(135deg,#60a5fa,#818cf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.logo p{color:rgba(255,255,255,0.3);font-size:12px;margin-top:2px;}
.msg{padding:10px 14px;margin-bottom:14px;border-radius:10px;text-align:center;font-size:12px;display:flex;align-items:center;justify-content:center;gap:6px;}
.error{background:rgba(239,68,68,0.08);color:#fca5a5;border:1px solid rgba(239,68,68,0.12);}
.success{background:rgba(16,185,129,0.08);color:#6ee7b7;border:1px solid rgba(16,185,129,0.12);}
.info{background:rgba(59,130,246,0.08);color:#93c5fd;border:1px solid rgba(59,130,246,0.12);}
.inp-group{margin-bottom:14px;}
.inp-group label{display:flex;align-items:center;gap:6px;color:rgba(255,255,255,0.5);font-size:12px;font-weight:500;margin-bottom:6px;}
.inp-group label i{color:rgba(255,255,255,0.2);}
textarea{width:100%;padding:14px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);border-radius:12px;color:#e2e8f0;font-size:13px;font-family:inherit;outline:none;transition:all 0.3s;resize:vertical;min-height:120px;}
textarea:focus{border-color:rgba(96,165,250,0.4);background:rgba(255,255,255,0.08);box-shadow:0 0 0 3px rgba(59,130,246,0.08);}
textarea::placeholder{color:rgba(255,255,255,0.15);}
.hint{color:rgba(255,255,255,0.2);font-size:11px;margin-bottom:14px;line-height:1.5;padding:12px;border-radius:8px;background:rgba(255,255,255,0.02);}
.hint b{color:rgba(255,255,255,0.35);}
.hint code{color:#60a5fa;font-size:10px;word-break:break-all;}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:12px 18px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.3s;border:none;font-family:inherit;position:relative;overflow:hidden;width:100%;}
.btn-primary{background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff;box-shadow:0 6px 20px rgba(59,130,246,0.25);}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 10px 30px rgba(59,130,246,0.35);}
.btn-primary::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,transparent 30%,rgba(255,255,255,0.1) 50%,transparent 70%);transform:translateX(-100%);transition:transform 0.6s;pointer-events:none;}
.btn-primary:hover::before{transform:translateX(100%);}
.back{text-align:center;margin-top:16px;}
.back a{color:rgba(255,255,255,0.25);font-size:12px;text-decoration:none;transition:color 0.3s;}
.back a:hover{color:#60a5fa;}
@media(max-width:480px){.card{padding:24px 16px;border-radius:20px;}textarea{font-size:12px;}}
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="icon"><i class="fas fa-sms"></i></div>
        <h1>M-Pesa <span>Verification</span></h1>
        <p>Paste your M-Pesa confirmation message to connect</p>
    </div>

    <?php if ($msg): ?>
    <div class="msg <?= $type ?>">
        <i class="fas <?= $type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
        <?= safe($msg) ?>
    </div>
    <?php endif; ?>

    <div class="hint">
        <b>How to get your M-Pesa message:</b><br>
        Open the M-Pesa confirmation SMS from Safaricom and copy the full message, then paste it below.<br><br>
        <b>Example:</b><br>
        <code>ABC123XYZ1 Confirmed on 15/7/26 at 2:30pm Ksh 1,000.00 sent to NETCONNECT KE for account. New M-Pesa balance is Ksh 5,000.00.</code>
    </div>

    <form method="POST">
        <div class="inp-group">
            <label><i class="fas fa-comment-alt"></i> M-Pesa Confirmation Message</label>
            <textarea name="mpesa_message" placeholder="Paste your full M-Pesa SMS here..." required><?= safe($_POST['mpesa_message'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Verify &amp; Connect</button>
    </form>

    <div class="back"><a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a></div>
</div>
</body>
</html>