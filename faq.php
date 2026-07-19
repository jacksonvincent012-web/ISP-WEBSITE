<?php require_once 'config/db.php'; require_once 'config/functions.php'; secureSessionStart(); sendSecurityHeaders(); requireLogin(); $userId = $_SESSION['user_id']; $user = $pdo->prepare("SELECT * FROM users WHERE id = ?"); $user->execute([$userId]); $user = $user->fetch(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FAQ — NetConnect ISP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--bg:#080d1a;--surface:rgba(14,22,40,0.6);--border:rgba(255,255,255,0.05);--text:#e2e8f0;--text-dim:rgba(255,255,255,0.35);--accent:#3b82f6;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;}
.sidebar{width:260px;background:rgba(14,22,40,0.95);border-right:1px solid rgba(255,255,255,0.04);padding:24px 0;position:fixed;height:100vh;overflow-y:auto;backdrop-filter:blur(20px);}
.sidebar .logo{padding:0 20px 22px;border-bottom:1px solid rgba(255,255,255,0.04);margin-bottom:12px;display:flex;align-items:center;gap:12px;}
.sidebar .logo i{color:var(--accent);font-size:24px;}
.sidebar .logo .brand-text{font-size:19px;font-weight:800;letter-spacing:-0.5px;}
.sidebar .logo .brand-text span{background:linear-gradient(135deg,#60a5fa,#818cf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.sidebar .logo .sub{color:rgba(255,255,255,0.25);font-size:11px;}
.side-nav{list-style:none;padding:0 10px;}
.side-nav li{margin-bottom:1px;}
.side-nav a{display:flex;align-items:center;gap:12px;padding:11px 14px;color:rgba(255,255,255,0.4);text-decoration:none;border-radius:10px;font-weight:500;font-size:14px;transition:all 0.2s;}
.side-nav a i{width:18px;text-align:center;}
.side-nav a:hover,.side-nav a.active{background:rgba(59,130,246,0.08);color:rgba(255,255,255,0.7);}
.side-nav a.active{background:rgba(59,130,246,0.12);color:#60a5fa;box-shadow:inset 3px 0 0 var(--accent);}
.side-nav .divider{margin-top:16px;border-top:1px solid rgba(255,255,255,0.04);padding-top:8px;}
.main{margin-left:260px;flex:1;padding:28px 36px;max-width:900px;}
.main-header{margin-bottom:24px;}
.main-header h2{font-size:24px;font-weight:800;letter-spacing:-0.5px;}
.main-header p{color:var(--text-dim);font-size:14px;margin-top:2px;}
.faq-item{background:var(--surface);border:1px solid var(--border);border-radius:14px;margin-bottom:10px;overflow:hidden;backdrop-filter:blur(10px);transition:border-color 0.3s;}
.faq-item:hover{border-color:rgba(255,255,255,0.08);}
.faq-q{padding:16px 20px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;font-weight:500;font-size:14px;transition:all 0.2s;}
.faq-q:hover{background:rgba(255,255,255,0.03);}
.faq-q i{color:rgba(255,255,255,0.2);transition:transform 0.3s;}
.faq-q.active i{transform:rotate(180deg);color:var(--accent);}
.faq-a{padding:0 20px 16px;color:rgba(255,255,255,0.45);font-size:13px;line-height:1.7;display:none;}
.faq-a.show{display:block;}
@media(max-width:900px){.sidebar{width:64px;}.sidebar .logo{padding:0 12px 18px;justify-content:center;}.sidebar .logo .brand-text,.sidebar .logo .sub,.side-nav a span{display:none;}.side-nav{padding:0 6px;}.side-nav a{justify-content:center;padding:11px;}.side-nav a i{width:auto;}.main{margin-left:64px;padding:20px;}}
@media(max-width:480px){.main{padding:14px;}.main-header h2{font-size:20px;}.faq-q{padding:14px 16px;font-size:13px;}.faq-a{padding:0 16px 14px;font-size:12px;}}
</style>
</head>
<body>
<div class="sidebar">
    <div class="logo"><i class="fas fa-wifi"></i><div><div class="brand-text">Net<span>Connect</span></div><div class="sub">client portal</div></div></div>
    <ul class="side-nav">
        <li><a href="dashboard.php"><i class="fas fa-chart-pie"></i><span>Dashboard</span></a></li>
        <li><a href="dashboard.php?tab=profile"><i class="fas fa-user"></i><span>Profile</span></a></li>
        <li><a href="dashboard.php?tab=invoices"><i class="fas fa-file-invoice"></i><span>Invoices</span></a></li>
        <li><a href="dashboard.php?tab=support"><i class="fas fa-life-ring"></i><span>Support</span></a></li>
        <li><a href="network.php"><i class="fas fa-signal"></i><span>Network</span></a></li>
        <li><a href="faq.php" class="active"><i class="fas fa-question-circle"></i><span>FAQ</span></a></li>
        <li class="divider"><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
    </ul>
</div>
<div class="main">
    <div class="main-header"><h2><i class="fas fa-question-circle" style="color:#3b82f6;"></i> Frequently Asked Questions</h2><p>Find answers to common questions about your NetConnect service</p></div>

    <?php
    $faqs = [
        ['How do I purchase a data plan?', 'Go to your Dashboard and click on the plan you want. You can pay via M-Pesa (STK Push) or credit card. Once payment is confirmed, your subscription activates immediately.'],
        ['How do I top up using M-Pesa?', 'On the Dashboard, select a plan and choose "M-Pesa" as payment method. Enter your M-Pesa registered phone number and submit. You will receive an STK push on your phone — enter your PIN to complete payment.'],
        ['What happens when my pass expires?', 'When your time-based pass expires, your internet access will be paused. You can purchase a new pass anytime from your Dashboard. Any unused data from unlimited plans does not roll over.'],
        ['Can I have multiple active subscriptions?', 'Yes, you can purchase multiple passes. Each pass runs independently. For example, you can buy a 1 Day Pass while an active Weekly Pass is still running.'],
        ['How do I check my data usage?', 'Your data usage is displayed on the Dashboard under your active subscription. Usage is updated in real-time and includes both upload and download activity.'],
        ['How do I reset my password?', 'Click "Forgot Password" on the login page. Enter your registered email and we\'ll send you a password reset link. The link expires in 1 hour for security.'],
        ['How do I contact support?', 'Open a support ticket from the Support tab on your Dashboard. Describe your issue and one of our team members will respond within 24 hours. You can also email support@netconnect.co.ke.'],
        ['Is there a refund policy?', 'All purchases are non-refundable. If you experience technical issues that prevent service usage, please contact support and we will investigate and may issue a credit at our discretion.'],
        ['How do I enable 2FA on my account?', 'Two-factor authentication is currently available for admin accounts. Contact support to request 2FA setup for your account.'],
        ['What speeds can I expect?', 'Speeds depend on your plan. Entry-level passes offer up to 10 Mbps, Premium Monthly up to 100 Mbps. Actual speeds may vary based on network congestion and signal strength.'],
    ];
    $i = 0; foreach ($faqs as $faq): $i++; ?>
    <div class="faq-item">
        <div class="faq-q" onclick="this.classList.toggle('active');this.nextElementSibling.classList.toggle('show');"><span><?= safe($faq[0]) ?></span><i class="fas fa-chevron-down"></i></div>
        <div class="faq-a"><?= safe($faq[1]) ?></div>
    </div>
    <?php endforeach; ?>
</div>
</body>
</html>
