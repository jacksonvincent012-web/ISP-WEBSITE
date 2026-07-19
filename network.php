<?php require_once 'config/functions.php'; secureSessionStart(); sendSecurityHeaders(); require_once 'config/db.php'; requireLogin(); $userId = $_SESSION['user_id']; $user = $pdo->prepare("SELECT * FROM users WHERE id = ?"); $user->execute([$userId]); $user = $user->fetch(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Network Status — NetConnect ISP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:#080d1a;color:#e2e8f0;display:flex;min-height:100vh;}
.sidebar{width:260px;background:rgba(14,22,40,0.95);border-right:1px solid rgba(255,255,255,0.04);padding:24px 0;position:fixed;height:100vh;overflow-y:auto;backdrop-filter:blur(20px);}
.sidebar .logo{padding:0 20px 22px;border-bottom:1px solid rgba(255,255,255,0.04);margin-bottom:12px;display:flex;align-items:center;gap:12px;}
.sidebar .logo i{color:#3b82f6;font-size:24px;}
.sidebar .logo .brand-text{font-size:19px;font-weight:800;letter-spacing:-0.5px;}
.sidebar .logo .brand-text span{background:linear-gradient(135deg,#60a5fa,#818cf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.sidebar .logo .sub{color:rgba(255,255,255,0.25);font-size:11px;}
.side-nav{list-style:none;padding:0 10px;}
.side-nav li{margin-bottom:1px;}
.side-nav a{display:flex;align-items:center;gap:12px;padding:11px 14px;color:rgba(255,255,255,0.4);text-decoration:none;border-radius:10px;font-weight:500;font-size:14px;transition:all 0.2s;}
.side-nav a i{width:18px;text-align:center;}
.side-nav a:hover,.side-nav a.active{background:rgba(59,130,246,0.08);color:rgba(255,255,255,0.7);}
.side-nav a.active{background:rgba(59,130,246,0.12);color:#60a5fa;box-shadow:inset 3px 0 0 #3b82f6;}
.side-nav .divider{margin-top:16px;border-top:1px solid rgba(255,255,255,0.04);padding-top:8px;}
.main{margin-left:260px;flex:1;padding:28px 36px;max-width:1200px;}
.main-header{margin-bottom:24px;}
.main-header h2{font-size:24px;font-weight:800;letter-spacing:-0.5px;}
.main-header p{color:rgba(255,255,255,0.35);font-size:14px;margin-top:2px;}
.status-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-bottom:28px;}
.status-card{padding:22px;border-radius:16px;border:1px solid rgba(255,255,255,0.05);background:rgba(14,22,40,0.6);backdrop-filter:blur(10px);transition:all 0.3s;}
.status-card:hover{transform:translateY(-2px);border-color:rgba(255,255,255,0.1);box-shadow:0 8px 24px rgba(0,0,0,0.3);}
.status-card .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
.status-card .top .name{font-size:16px;font-weight:600;}
.status-card .top .dot{width:11px;height:11px;border-radius:50%;}
.dot.up{background:#34d399;box-shadow:0 0 12px rgba(52,211,153,0.4);}
.dot.down{background:#f87171;box-shadow:0 0 12px rgba(248,113,113,0.4);}
.dot.maintenance{background:#fbbf24;box-shadow:0 0 12px rgba(251,191,36,0.4);}
.status-card .desc{color:rgba(255,255,255,0.4);font-size:13px;}
.status-card .uptime{font-size:12px;color:rgba(255,255,255,0.25);margin-top:8px;}
.announcement{background:rgba(59,130,246,0.06);border:1px solid rgba(59,130,246,0.1);border-radius:14px;padding:20px;margin-bottom:18px;}
.announcement .date{color:rgba(255,255,255,0.25);font-size:12px;}
.announcement .title{font-weight:600;margin:4px 0;font-size:15px;}
.announcement .desc{color:rgba(255,255,255,0.4);font-size:13px;}
@media(max-width:900px){.sidebar{width:64px;}.sidebar .logo{padding:0 12px 18px;justify-content:center;}.sidebar .logo .brand-text,.sidebar .logo .sub,.side-nav a span{display:none;}.side-nav{padding:0 6px;}.side-nav a{justify-content:center;padding:11px;}.side-nav a i{width:auto;}.main{margin-left:64px;padding:20px;}}
@media(max-width:480px){.status-grid{grid-template-columns:1fr;}.main{padding:14px;}.main-header h2{font-size:20px;}}
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
        <li><a href="network.php" class="active"><i class="fas fa-signal"></i><span>Network</span></a></li>
        <li><a href="faq.php"><i class="fas fa-question-circle"></i><span>FAQ</span></a></li>
        <li class="divider"><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
    </ul>
</div>
<div class="main">
    <div class="main-header"><h2><i class="fas fa-signal" style="color:#3b82f6;"></i> Network Status</h2><p>Real-time service availability for all NetConnect services</p></div>

    <div class="status-grid">
        <?php
        $services = [
            ['Internet Access', 'up', 'All connections operational', '99.8%'],
            ['Wi-Fi Hotspots', 'up', 'All hotspots online', '99.5%'],
            ['Customer Portal', 'up', 'Web portal running normally', '100%'],
            ['M-Pesa Payments', 'up', 'Payment gateway operational', '99.9%'],
            ['DNS Servers', 'up', 'DNS resolving normally', '100%'],
            ['Email Services', 'maintenance', 'Scheduled maintenance Jul 20, 02:00-04:00 EAT', '—'],
        ];
        foreach ($services as $s): ?>
        <div class="status-card">
            <div class="top"><span class="name"><?= safe($s[0]) ?></span><span class="dot <?= $s[1] ?>"></span></div>
            <div class="desc"><?= safe($s[2]) ?></div>
            <div class="uptime">Uptime: <?= safe($s[3]) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <h3 style="font-size:18px;margin-bottom:15px;"><i class="fas fa-bullhorn" style="color:#3b82f6;margin-right:8px;"></i> Announcements</h3>
    <div class="announcement"><div class="date">July 18, 2026</div><div class="title">Maintenance Window — July 20</div><div class="desc">Scheduled maintenance on our email infrastructure from 02:00 to 04:00 EAT. Internet and portal will remain unaffected.</div></div>
    <div class="announcement"><div class="date">July 15, 2026</div><div class="title">Network Upgrade Complete</div><div class="desc">Our backbone network has been upgraded to 100 Gbps. Customers should experience improved speeds and lower latency.</div></div>
    <div class="announcement"><div class="date">July 10, 2026</div><div class="title">New Pricing Plans Launched</div><div class="desc">Check out our new affordable time-based passes starting from KSh 10.</div></div>
</div>
</body>
</html>
