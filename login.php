<?php require_once 'config/functions.php'; require_once 'config/db.php'; secureSessionStart(); sendSecurityHeaders();
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }

// Auto-login via remember token
$autoUser = tryAutoLogin();
if ($autoUser) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $autoUser['id'];
    $_SESSION['user_name'] = $autoUser['full_name'] ?: $autoUser['username'];
    $_SESSION['user_email'] = $autoUser['email'];
    $_SESSION['user_role'] = $autoUser['role'];
    $_SESSION['user_status'] = $autoUser['status'];
    if ($autoUser['role'] === 'admin') header('Location: admin.php');
    else header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NetConnect KE — Internet Provider</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--accent:#3b82f6;}
*{margin:0;padding:0;box-sizing:border-box;}
body{
    font-family:'Inter',sans-serif;min-height:100vh;
    background:#080d1a;display:flex;align-items:flex-start;justify-content:center;
    position:relative;overflow-x:hidden;padding:30px 16px;
}
body::before{
    content:'';position:fixed;width:700px;height:700px;
    background:radial-gradient(circle,rgba(59,130,246,0.15),transparent 70%);
    top:-200px;right:-200px;animation:float 12s ease-in-out infinite;
    pointer-events:none;
}
body::after{
    content:'';position:fixed;width:600px;height:600px;
    background:radial-gradient(circle,rgba(99,102,241,0.1),transparent 70%);
    bottom:-180px;left:-180px;animation:float 15s ease-in-out infinite reverse;
    pointer-events:none;
}
@keyframes float{
    0%,100%{transform:translate(0,0) scale(1);}
    33%{transform:translate(30px,-30px) scale(1.05);}
    66%{transform:translate(-20px,20px) scale(0.95);}
}
.grid-overlay{
    position:fixed;inset:0;pointer-events:none;
    background-image:linear-gradient(rgba(255,255,255,0.02) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.02) 1px,transparent 1px);
    background-size:60px 60px;z-index:0;
}
.card{
    background:rgba(18,25,45,0.88);backdrop-filter:blur(40px);-webkit-backdrop-filter:blur(40px);
    border:1px solid rgba(255,255,255,0.07);
    border-radius:28px;padding:36px 32px;max-width:440px;width:100%;
    position:relative;z-index:2;
    box-shadow:0 40px 80px rgba(0,0,0,0.6),inset 0 1px 0 rgba(255,255,255,0.06);
}
.glow{
    position:absolute;inset:-1px;border-radius:28px;z-index:-1;pointer-events:none;
    background:linear-gradient(135deg,rgba(59,130,246,0.2),transparent 40%,transparent 60%,rgba(99,102,241,0.2));
    opacity:0;transition:opacity 0.5s;
}
.card:hover .glow{opacity:1;}
.logo{text-align:center;margin-bottom:20px;}
.logo .icon{
    width:52px;height:52px;margin:0 auto 12px;
    background:linear-gradient(135deg,#3b82f6,#6366f1);
    border-radius:14px;display:flex;align-items:center;justify-content:center;
    box-shadow:0 10px 28px rgba(59,130,246,0.35);
}
.logo .icon i{font-size:22px;color:#fff;}
.logo .icon::after{
    content:'';position:absolute;inset:0;border-radius:14px;
    background:linear-gradient(135deg,transparent 40%,rgba(255,255,255,0.15));
}
.logo h1{color:#fff;font-size:20px;font-weight:800;letter-spacing:-0.3px;}
.logo h1 span{background:linear-gradient(135deg,#60a5fa,#818cf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.logo p{color:rgba(255,255,255,0.3);font-size:12px;margin-top:2px;}
.tagline{text-align:center;margin-bottom:18px;}
.tagline p{color:rgba(255,255,255,0.2);font-size:12px;}

/* ─── Connect Section ─── */
.connect-box{
    background:rgba(16,185,129,0.05);border:1px solid rgba(16,185,129,0.1);
    border-radius:16px;padding:18px;margin-bottom:16px;text-align:center;
    transition:border-color 0.3s;
}
.connect-box:hover{border-color:rgba(16,185,129,0.2);}
.connect-box .hdr{display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:12px;}
.connect-box .hdr i{color:#34d399;font-size:16px;}
.connect-box .hdr span{color:rgba(255,255,255,0.6);font-size:13px;font-weight:600;}
.connect-box .row{display:flex;gap:8px;}
.connect-box .row input{flex:1;}
.connect-box .row button{flex-shrink:0;}

/* ─── Admin Login (collapsible) ─── */
.admin-toggle{
    display:flex;align-items:center;justify-content:center;gap:6px;
    color:rgba(255,255,255,0.45);font-size:12px;cursor:pointer;
    padding:10px;border-radius:8px;transition:all 0.2s;user-select:none;border:none;background:none;width:100%;font-family:inherit;
}
.admin-toggle:hover{color:rgba(255,255,255,0.4);background:rgba(255,255,255,0.03);}
.admin-toggle i{font-size:10px;transition:transform 0.3s;}
.admin-toggle.open i{transform:rotate(180deg);}
.admin-form{display:none;margin-top:12px;}
.admin-form.show{display:block;}

/* ─── Forms ─── */
.inp-group{margin-bottom:12px;}
.inp-group label{display:flex;align-items:center;gap:6px;color:rgba(255,255,255,0.5);font-size:11px;font-weight:500;margin-bottom:5px;}
.inp-wrap{position:relative;}
.inp-wrap i{
    position:absolute;left:12px;top:50%;transform:translateY(-50%);
    color:rgba(255,255,255,0.18);font-size:12px;transition:color 0.3s;
}
.inp-wrap:focus-within i{color:#60a5fa;}
input{
    width:100%;padding:11px 12px 11px 36px;
    background:rgba(255,255,255,0.05);
    border:1px solid rgba(255,255,255,0.08);
    border-radius:10px;color:#e2e8f0;font-size:13px;
    font-family:inherit;outline:none;transition:all 0.3s;
}
input:focus{
    border-color:rgba(96,165,250,0.4);
    background:rgba(255,255,255,0.08);
    box-shadow:0 0 0 3px rgba(59,130,246,0.08);
}
input::placeholder{color:rgba(255,255,255,0.12);}

.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:11px 18px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;transition:all 0.3s;border:none;font-family:inherit;position:relative;overflow:hidden;}
.btn-primary{
    background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff;
    box-shadow:0 6px 20px rgba(59,130,246,0.25);
}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 10px 30px rgba(59,130,246,0.35);}
.btn-primary::before{
    content:'';position:absolute;inset:0;
    background:linear-gradient(135deg,transparent 30%,rgba(255,255,255,0.1) 50%,transparent 70%);
    transform:translateX(-100%);transition:transform 0.6s;pointer-events:none;
}
.btn-primary:hover::before{transform:translateX(100%);}
.btn-success{
    background:linear-gradient(135deg,#10b981,#059669);color:#fff;
    box-shadow:0 6px 20px rgba(16,185,129,0.25);
}
.btn-success:hover{transform:translateY(-1px);box-shadow:0 10px 30px rgba(16,185,129,0.35);}
.btn-success::before{
    content:'';position:absolute;inset:0;
    background:linear-gradient(135deg,transparent 30%,rgba(255,255,255,0.12) 50%,transparent 70%);
    transform:translateX(-100%);transition:transform 0.6s;pointer-events:none;
}
.btn-success:hover::before{transform:translateX(100%);}
.btn-block{width:100%;justify-content:center;}
.btn-sm{padding:9px 14px;font-size:12px;border-radius:8px;}

.msg{
    padding:10px 14px;margin-bottom:14px;border-radius:10px;
    text-align:center;font-size:12px;display:flex;align-items:center;justify-content:center;gap:6px;
}
.error{background:rgba(239,68,68,0.08);color:#fca5a5;border:1px solid rgba(239,68,68,0.12);}
.success{background:rgba(16,185,129,0.08);color:#6ee7b7;border:1px solid rgba(16,185,129,0.12);}
.info{background:rgba(59,130,246,0.08);color:#93c5fd;border:1px solid rgba(59,130,246,0.12);}

.divider{display:flex;align-items:center;gap:10px;margin:16px 0 14px;}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:rgba(255,255,255,0.04);}

/* ─── Troubleshooting ─── */
.ts-area{margin-top:10px;}
.ts-hdr{display:flex;align-items:center;gap:6px;margin-bottom:10px;color:rgba(255,255,255,0.25);font-size:10px;text-transform:uppercase;letter-spacing:1px;font-weight:600;}
.ts-hdr i{font-size:11px;color:rgba(255,255,255,0.15);}
.ts-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;}
.ts-item{
    padding:10px;border-radius:8px;border:1px solid rgba(255,255,255,0.03);
    background:rgba(255,255,255,0.01);transition:all 0.2s;text-align:center;
}
.ts-item:hover{background:rgba(255,255,255,0.03);border-color:rgba(255,255,255,0.06);}
.ts-item i{color:var(--accent);font-size:14px;margin-bottom:3px;display:block;}
.ts-item .tt{color:rgba(255,255,255,0.5);font-size:11px;font-weight:500;}
.ts-item .td{color:rgba(255,255,255,0.2);font-size:10px;margin-top:1px;}

/* ─── Footer ─── */
.footer{text-align:center;margin-top:16px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.03);}
.footer p{color:rgba(255,255,255,0.12);font-size:10px;}
.footer .links{display:flex;justify-content:center;gap:14px;margin-bottom:4px;}
.footer .links a{color:rgba(255,255,255,0.2);font-size:11px;text-decoration:none;transition:color 0.3s;}
.footer .links a:hover{color:#60a5fa;}

@media(max-width:480px){
    .card{padding:24px 16px;border-radius:20px;}
    .logo .icon{width:44px;height:44px;border-radius:12px;}
    .logo .icon i{font-size:18px;}
    .logo h1{font-size:17px;}
    body::before,body::after{animation:none;}
    .connect-box .row{flex-direction:column;}
    .connect-box .row input{width:100%;}
    .connect-box .row button{width:100%;justify-content:center;}
    .ts-grid{grid-template-columns:1fr 1fr;}
    input{padding:10px 10px 10px 32px;font-size:12px;}
    .inp-wrap i{left:10px;font-size:11px;}
    .btn{padding:10px 14px;font-size:12px;}
}
</style>
</head>
<body>
<div class="grid-overlay"></div>
<div class="card">
    <div class="glow"></div>
    <div class="logo">
        <div class="icon"><i class="fas fa-wifi"></i></div>
        <h1>Net<span>Connect</span></h1>
        <p>Fast. Reliable. Affordable.</p>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="msg <?= safe($_GET['type'] ?? 'error') ?>">
        <i class="fas <?= ($_GET['type'] ?? '') === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
        <?= safe($_GET['msg']) ?>
    </div>
    <?php endif; ?>

    <!-- ═══ Quick Connect ═══ -->
    <div class="connect-box">
        <div class="hdr"><i class="fas fa-plug"></i><span>Already have an active package?</span></div>
        <form action="reconnect.php" method="POST">
            <div class="row">
                <input type="tel" name="phone" placeholder="Phone number (e.g. 0712345678)" required>
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-wifi"></i> Connect</button>
            </div>
        </form>
    </div>

    <div class="connect-box" style="background:rgba(59,130,246,0.05);border-color:rgba(59,130,246,0.1);">
        <div class="hdr"><i class="fas fa-undo"></i><span>Down / Lost voucher connection?</span></div>
        <form action="reconnect.php" method="POST">
            <div class="row">
                <input type="text" name="voucher" placeholder="Voucher code or phone number" required>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-sync"></i> Reconnect</button>
            </div>
        </form>
    </div>

    <div class="connect-box" style="background:rgba(16,185,129,0.05);border-color:rgba(16,185,129,0.1);">
        <div class="hdr"><i class="fas fa-sms"></i><span>M-Pesa confirmation message?</span></div>
        <div style="padding:0 2px;">
            <p style="color:rgba(255,255,255,0.35);font-size:12px;margin-bottom:8px;">Paste your M-Pesa SMS and we'll verify &amp; connect you automatically.</p>
            <a href="verify_message.php" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Verify Message</a>
        </div>
    </div>

    <!-- ═══ Admin Login ═══ -->
    <div class="divider"></div>
    <button class="admin-toggle" onclick="this.classList.toggle('open');document.querySelector('.admin-form').classList.toggle('show')">
        <i class="fas fa-chevron-down"></i> Staff / Administrator Access <i class="fas fa-shield-alt"></i>
    </button>
    <div class="admin-form">
        <form action="auth.php" method="POST">
            <?= csrfField() . honeypotField() ?>
            <div class="inp-group">
                <label><i class="fas fa-user" style="color:rgba(255,255,255,0.3);"></i> Email or Username</label>
                <div class="inp-wrap">
                    <i class="fas fa-user"></i>
                    <input type="text" name="identity" placeholder="admin@isp.com" required>
                </div>
            </div>
            <div class="inp-group">
                <label><i class="fas fa-lock" style="color:rgba(255,255,255,0.3);"></i> Password</label>
                <div class="inp-wrap">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Enter password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-sm"><i class="fas fa-arrow-right"></i> Sign In</button>
        </form>
        <div class="footer" style="border:none;padding-top:8px;margin-top:0;">
            <div class="links">
                <a href="forgot.php">Forgot Password</a>
                <a href="resend.php">Resend Verification</a>
            </div>
        </div>
    </div>

    <!-- ═══ Troubleshooting ═══ -->
    <div class="ts-area">
        <div class="ts-hdr"><i class="fas fa-tools"></i> Troubleshooting</div>
        <div class="ts-grid">
            <div class="ts-item">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="tt">Can't Connect</div>
                <div class="td">Check cables & restart modem</div>
            </div>
            <div class="ts-item">
                <i class="fas fa-tachometer-alt"></i>
                <div class="tt">Slow Speed</div>
                <div class="td">Run a speed test & contact us</div>
            </div>
            <div class="ts-item" onclick="window.location.href='verify_message.php'" style="cursor:pointer;">
                <i class="fas fa-sms"></i>
                <div class="tt">Verify M-Pesa</div>
                <div class="td">Paste your SMS to auto-connect</div>
            </div>
            <div class="ts-item" onclick="window.open('https://wa.me/254110869425','_blank')" style="cursor:pointer;">
                <i class="fab fa-whatsapp"></i>
                <div class="tt">Need Help?</div>
                <div class="td">Chat on WhatsApp +254 110 869 425</div>
            </div>
        </div>
    </div>

    <!-- ═══ Footer ═══ -->
    <div class="footer">
        <div class="links">
            <a href="register.php"><i class="fas fa-user-plus"></i> New Customer? Register</a>
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
        </div>
        <p>&copy; <?= date('Y') ?> NetConnect KE. All rights reserved.</p>
    </div>
</div>
</body>
</html>