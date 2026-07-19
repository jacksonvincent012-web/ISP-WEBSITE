<?php
require_once 'config/db.php';
require_once 'config/functions.php';
secureSessionStart(); sendSecurityHeaders();
requireLogin();

$userId = $_SESSION['user_id'];
$user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$userId]);
$user = $user->fetch();
if (!$user) { session_destroy(); redirect('login.php', 'User not found.', 'error'); }

$subscription = $pdo->prepare("SELECT s.*, p.name as plan_name, p.speed, p.download_speed, p.upload_speed, p.price, p.data_cap, p.features FROM subscriptions s JOIN plans p ON s.plan_id = p.id WHERE s.user_id = ? AND s.status = 'active' ORDER BY s.start_date DESC LIMIT 1");
$subscription->execute([$userId]);
$subscription = $subscription->fetch();

$payments = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY paid_at DESC LIMIT 5");
$payments->execute([$userId]);
$payments = $payments->fetchAll();

$invoices = $pdo->prepare("SELECT * FROM invoices WHERE user_id = ? ORDER BY issued_at DESC LIMIT 5");
$invoices->execute([$userId]);
$invoices = $invoices->fetchAll();

$usage = $pdo->prepare("SELECT COALESCE(SUM(data_used_mb), 0) as total_mb FROM usage_logs WHERE user_id = ?");
$usage->execute([$userId]);
$usage = $usage->fetch();

$totalTickets = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ?");
$totalTickets->execute([$userId]);
$totalTickets = $totalTickets->fetchColumn();

$unpaidCount = 0;
foreach ($invoices as $inv) { if ($inv['status'] === 'unpaid') $unpaidCount++; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — NetConnect ISP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:#080d1a;color:#e2e8f0;display:flex;min-height:100vh;}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:0.4;}}

/* ─── Sidebar ─── */
.sidebar{
    width:260px;background:rgba(14,22,40,0.95);border-right:1px solid rgba(255,255,255,0.04);
    padding:24px 0;position:fixed;top:0;left:0;height:100vh;overflow-y:auto;z-index:100;
    backdrop-filter:blur(20px);
}
.sidebar .logo{
    padding:0 20px 22px;border-bottom:1px solid rgba(255,255,255,0.04);
    margin-bottom:12px;display:flex;align-items:center;gap:12px;
}
.sidebar .logo i{color:#3b82f6;font-size:24px;}
.sidebar .logo .brand-text{font-size:19px;font-weight:800;letter-spacing:-0.5px;}
.sidebar .logo .brand-text span{background:linear-gradient(135deg,#60a5fa,#818cf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.sidebar .logo .sub{color:rgba(255,255,255,0.25);font-size:11px;font-weight:400;}
.side-nav{list-style:none;padding:0 10px;}
.side-nav li{margin-bottom:1px;}
.side-nav a{
    display:flex;align-items:center;gap:12px;padding:11px 14px;
    color:rgba(255,255,255,0.4);text-decoration:none;border-radius:10px;
    font-weight:500;font-size:14px;transition:all 0.2s;position:relative;
}
.side-nav a i{width:18px;text-align:center;font-size:15px;}
.side-nav a:hover{background:rgba(59,130,246,0.08);color:rgba(255,255,255,0.7);}
.side-nav a.active{
    background:rgba(59,130,246,0.12);color:#60a5fa;
    box-shadow:inset 3px 0 0 #3b82f6;
}
.side-nav .divider{margin-top:16px;border-top:1px solid rgba(255,255,255,0.04);padding-top:8px;}

/* ─── Main ─── */
.main{margin-left:260px;flex:1;padding:28px 36px;max-width:1200px;}

.main-header{
    display:flex;justify-content:space-between;align-items:center;
    margin-bottom:28px;flex-wrap:wrap;gap:15px;
}
.main-header h2{font-size:26px;font-weight:800;letter-spacing:-0.5px;}
.main-header .greeting{color:rgba(255,255,255,0.35);font-size:14px;margin-top:2px;}
.main-header .user-badge{
    display:flex;align-items:center;gap:10px;
    background:rgba(255,255,255,0.04);padding:6px 16px 6px 6px;
    border-radius:40px;border:1px solid rgba(255,255,255,0.05);
    transition:all 0.2s;
}
.main-header .user-badge:hover{background:rgba(255,255,255,0.07);}
.main-header .user-badge .avatar{
    width:34px;height:34px;
    background:linear-gradient(135deg,#3b82f6,#6366f1);
    border-radius:50%;display:flex;align-items:center;justify-content:center;
    font-weight:700;font-size:13px;color:#fff;
}

/* ─── Stats Grid ─── */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
.stat-card{
    padding:20px;border-radius:16px;
    border:1px solid rgba(255,255,255,0.05);
    background:rgba(14,22,40,0.6);transition:all 0.3s;
    backdrop-filter:blur(10px);
}
.stat-card:hover{transform:translateY(-3px);border-color:rgba(255,255,255,0.1);box-shadow:0 12px 32px rgba(0,0,0,0.3);}
.stat-card .hdr{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;}
.stat-card .hdr .icon{
    width:42px;height:42px;border-radius:12px;
    display:flex;align-items:center;justify-content:center;font-size:18px;
}
.stat-card .hdr .icon.blue{background:rgba(59,130,246,0.12);color:#60a5fa;}
.stat-card .hdr .icon.green{background:rgba(16,185,129,0.12);color:#34d399;}
.stat-card .hdr .icon.orange{background:rgba(245,158,11,0.12);color:#fbbf24;}
.stat-card .hdr .icon.purple{background:rgba(139,92,246,0.12);color:#a78bfa;}
.stat-card .val{font-size:26px;font-weight:800;letter-spacing:-0.5px;}
.stat-card .lbl{color:rgba(255,255,255,0.3);font-size:11px;font-weight:500;text-transform:uppercase;letter-spacing:0.8px;margin-top:2px;}

/* ─── Panels ─── */
.panels{display:grid;grid-template-columns:2fr 1fr;gap:18px;margin-bottom:28px;}
.panel{
    background:rgba(14,22,40,0.6);border:1px solid rgba(255,255,255,0.05);
    border-radius:18px;padding:24px;backdrop-filter:blur(10px);transition:border-color 0.3s;
}
.panel:hover{border-color:rgba(255,255,255,0.08);}
.panel h3{
    font-size:16px;font-weight:600;margin-bottom:16px;
    display:flex;align-items:center;justify-content:space-between;
    color:rgba(255,255,255,0.8);
}
.panel h3 span{color:rgba(255,255,255,0.2);font-size:11px;font-weight:400;text-transform:uppercase;letter-spacing:0.5px;}

/* ─── Subscription Card ─── */
.sub-card{
    padding:20px;border-radius:14px;
    background:linear-gradient(135deg,rgba(59,130,246,0.1),rgba(99,102,241,0.05));
    border:1px solid rgba(59,130,246,0.12);
}
.sub-card h4{font-size:22px;font-weight:800;letter-spacing:-0.3px;}
.sub-card .speed-label{color:rgba(255,255,255,0.45);font-size:13px;margin-top:2px;}
.sub-card .meta{display:flex;gap:16px;margin-top:12px;font-size:13px;color:rgba(255,255,255,0.35);}
.sub-card .meta i{color:rgba(255,255,255,0.2);}
.progress-bar{height:5px;background:rgba(255,255,255,0.06);border-radius:99px;margin:12px 0 5px;overflow:hidden;}
.progress-bar .fill{height:5px;background:linear-gradient(90deg,#3b82f6,#818cf8);border-radius:99px;transition:width 0.5s;}
.sub-card .pct-label{font-size:11px;color:rgba(255,255,255,0.25);}

/* ─── Rows ─── */
.row-strip{
    display:flex;justify-content:space-between;align-items:center;
    padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.04);
}
.row-strip:last-child{border-bottom:none;}
.row-strip .l{color:rgba(255,255,255,0.4);font-size:13px;display:flex;align-items:center;gap:8px;}
.row-strip .l i{color:rgba(255,255,255,0.15);width:14px;}
.row-strip .r{font-size:13px;font-weight:500;}

/* ─── Badges ─── */
.badge{
    padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block;
}
.badge-completed,.badge-paid,.badge-resolved,.badge-active{background:rgba(16,185,129,0.12);color:#34d399;}
.badge-pending,.badge-unpaid,.badge-open{background:rgba(245,158,11,0.12);color:#fbbf24;}
.badge-overdue,.badge-failed,.badge-suspended{background:rgba(239,68,68,0.12);color:#f87171;}
.badge-in_progress{background:rgba(59,130,246,0.12);color:#60a5fa;}

/* ─── Buttons ─── */
.btn{
    display:inline-flex;align-items:center;gap:6px;padding:8px 18px;
    border-radius:10px;border:1px solid rgba(255,255,255,0.06);
    background:rgba(255,255,255,0.04);color:#e2e8f0;
    text-decoration:none;font-size:13px;transition:all 0.2s;cursor:pointer;
}
.btn:hover{background:rgba(255,255,255,0.08);border-color:rgba(255,255,255,0.1);}
.btn-primary{
    background:linear-gradient(135deg,#3b82f6,#6366f1);border-color:transparent;
    font-weight:500;
}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(59,130,246,0.3);}
.btn-danger{background:rgba(239,68,68,0.12);border-color:rgba(239,68,68,0.15);color:#f87171;}

.empty-state{text-align:center;padding:24px 16px;color:rgba(255,255,255,0.2);}
.empty-state i{font-size:36px;margin-bottom:8px;opacity:0.4;}
.empty-state p{font-size:14px;}

/* ─── Forms ─── */
.form-group{margin-bottom:14px;}
.form-group label{display:block;color:rgba(255,255,255,0.6);font-size:13px;margin-bottom:5px;font-weight:500;}
.form-group input,.form-group select,.form-group textarea{
    width:100%;padding:11px 14px;background:rgba(255,255,255,0.05);
    border:1px solid rgba(255,255,255,0.08);border-radius:12px;
    color:#e2e8f0;font-size:14px;font-family:inherit;outline:none;transition:all 0.2s;
}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{
    border-color:rgba(96,165,250,0.4);background:rgba(255,255,255,0.08);
    box-shadow:0 0 0 3px rgba(59,130,246,0.08);
}

/* ─── Responsive ─── */
@media(max-width:900px){
.sidebar{width:64px;}
.sidebar .logo{padding:0 12px 18px;justify-content:center;}
.sidebar .logo .brand-text,.sidebar .logo .sub,.side-nav a span{display:none;}
.side-nav{padding:0 6px;}
.side-nav a{justify-content:center;padding:11px;}
.side-nav a i{width:auto;margin:0;}
.main{margin-left:64px;padding:20px;}
.stats{grid-template-columns:repeat(2,1fr);}.panels{grid-template-columns:1fr;}
}
@media(max-width:480px){
.sidebar{width:52px;padding:16px 0;}
.sidebar .logo{padding:0 8px 14px;}
.side-nav{padding:0 4px;}
.side-nav a{padding:9px;}
.main{margin-left:52px;padding:14px;}
.main-header h2{font-size:20px;}
.stats{grid-template-columns:repeat(2,1fr);gap:10px;}
.stat-card{padding:14px;}
.stat-card .val{font-size:20px;}
.stat-card .lbl{font-size:10px;}
.stat-card .hdr .icon{width:34px;height:34px;font-size:15px;}
.panel{padding:16px;border-radius:14px;}
.panel h3{font-size:14px;}
.sub-card{padding:16px;}
.sub-card h4{font-size:18px;}
.row-strip{padding:7px 0;}
.row-strip .l,.row-strip .r{font-size:12px;}
}
</style>
</head>
<body>

<div class="sidebar">
    <div class="logo">
        <i class="fas fa-wifi"></i>
        <div><div class="brand-text">Net<span>Connect</span></div><div class="sub">client portal</div></div>
    </div>
    <ul class="side-nav">
        <li><a href="dashboard.php" class="active"><i class="fas fa-chart-pie"></i><span>Dashboard</span></a></li>
        <li><a href="dashboard.php?tab=profile"><i class="fas fa-user"></i><span>Profile</span></a></li>
        <li><a href="dashboard.php?tab=invoices"><i class="fas fa-file-invoice"></i><span>Invoices</span></a></li>
        <li><a href="dashboard.php?tab=support"><i class="fas fa-life-ring"></i><span>Support</span></a></li>
        <li><a href="network.php"><i class="fas fa-signal"></i><span>Network</span></a></li>
        <li><a href="faq.php"><i class="fas fa-question-circle"></i><span>FAQ</span></a></li>
        <li class="divider"><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
    </ul>
</div>

<div class="main">
    <div class="main-header">
        <div>
            <h2>Dashboard</h2>
            <p class="greeting">Welcome back, <?= safe($user['full_name'] ?: $user['username']) ?>!</p>
        </div>
        <div class="user-badge">
            <div class="avatar"><?= strtoupper(($user['full_name'] ?: $user['username'])[0]) ?></div>
            <div>
                <div style="font-size:13px;font-weight:500;"><?= safe($user['full_name'] ?: $user['username']) ?></div>
                <div style="font-size:11px;color:rgba(255,255,255,0.3);text-transform:capitalize;"><?= safe($user['role']) ?></div>
            </div>
        </div>
    </div>

    <?php
    $tab = $_GET['tab'] ?? '';
    if ($tab === 'profile'):
    ?>
    <div class="panel" style="grid-column:1/-1;">
        <h3><i class="fas fa-id-card" style="color:#3b82f6;margin-right:8px;"></i> Profile Details</h3>
        <?php
        $fields = [
            ['Full Name', $user['full_name'] ?: $user['username'], 'fa-user'],
            ['Username', $user['username'], 'fa-at'],
            ['Email', $user['email'], 'fa-envelope'],
            ['Phone', ($user['phone_encrypted'] ? decryptField($user['phone_encrypted'], $userId) : $user['phone']) ?: '—', 'fa-phone'],
            ['Address', ($user['address_encrypted'] ? decryptField($user['address_encrypted'], $userId) : $user['address']) ?: '—', 'fa-map-marker-alt'],
            ['Status', ucfirst($user['status']), 'fa-toggle-on'],
            ['Member Since', date('F j, Y', strtotime($user['created_at'])), 'fa-calendar'],
        ];
        foreach ($fields as $f) {
            echo '<div class="row-strip"><span class="l"><i class="fas '.$f[2].'" style="width:18px;color:rgba(255,255,255,0.25);"></i> '.$f[0].'</span><span class="r">'.safe($f[1]).'</span></div>';
        }
        ?>
    </div>

    <div class="panel" style="grid-column:1/-1;margin-top:20px;">
        <h3><i class="fas fa-key" style="color:#3b82f6;margin-right:8px;"></i> Change Password</h3>
        <form method="POST" action="auth.php?action=change_password" style="max-width:400px;margin-top:12px;">
            <?= csrfField() . honeypotField() ?>
            <div class="form-group" style="margin-bottom:12px;">
                <label style="display:block;color:rgba(255,255,255,0.7);font-size:13px;margin-bottom:4px;">Current Password</label>
                <input type="password" name="current_password" required style="width:100%;padding:10px 14px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;font-size:14px;outline:none;">
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label style="display:block;color:rgba(255,255,255,0.7);font-size:13px;margin-bottom:4px;">New Password</label>
                <input type="password" name="new_password" required minlength="8" style="width:100%;padding:10px 14px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;font-size:14px;outline:none;">
            </div>
            <div class="form-group" style="margin-bottom:12px;">
                <label style="display:block;color:rgba(255,255,255,0.7);font-size:13px;margin-bottom:4px;">Confirm New Password</label>
                <input type="password" name="confirm_password" required minlength="8" style="width:100%;padding:10px 14px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;font-size:14px;outline:none;">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Password</button>
        </form>
    </div>

    <?php elseif ($tab === 'invoices'): ?>
    <h3 style="font-size:18px;margin-bottom:15px;">Billing &amp; Invoices</h3>
    <div class="panel" style="grid-column:1/-1;">
        <?php if ($invoices): foreach ($invoices as $inv): ?>
        <div class="row-strip">
            <span class="l"><?= safe($inv['invoice_no']) ?> — KSh <?= number_format($inv['amount'],0) ?></span>
            <span><span class="badge badge-<?= $inv['status'] ?>"><?= $inv['status'] ?></span> <span style="color:rgba(255,255,255,0.3);font-size:12px;">due <?= safe($inv['due_date']) ?></span></span>
        </div>
        <?php endforeach; else: ?>
        <div class="empty-state"><i class="fas fa-file-invoice"></i><p>No invoices yet.</p></div>
        <?php endif; ?>
    </div>

    <?php elseif ($tab === 'support'): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;flex-wrap:wrap;gap:10px;">
        <h3 style="font-size:18px;">Support Tickets</h3>
        <div style="display:flex;gap:8px;">
            <a href="dashboard.php?tab=new_ticket" class="btn btn-primary"><i class="fas fa-plus"></i> New Ticket</a>
        </div>
    </div>
    <?php
    $tickets = $pdo->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $tickets->execute([$userId]);
    $tickets = $tickets->fetchAll();
    ?>
    <div class="panel" style="grid-column:1/-1;">
        <?php if ($tickets): foreach ($tickets as $t): ?>
        <div class="row-strip">
            <span class="l"><i class="fas fa-tag" style="color:rgba(255,255,255,0.25);width:16px;"></i> <?= safe($t['subject']) ?></span>
            <span><span class="badge badge-<?= $t['status'] ?>"><?= $t['status'] ?></span> <span style="color:rgba(255,255,255,0.3);font-size:12px;"><?= date('M j', strtotime($t['created_at'])) ?></span></span>
        </div>
        <?php endforeach; else: ?>
        <div class="empty-state"><i class="fas fa-life-ring"></i><p>No support tickets yet.</p></div>
        <?php endif; ?>
    </div>

    <?php elseif ($tab === 'new_ticket'): ?>
    <h3 style="font-size:18px;margin-bottom:15px;">Open a New Ticket</h3>
    <div class="panel" style="grid-column:1/-1;">
        <form method="POST" action="ticket.php">
            <?= csrfField() ?>
            <div style="margin-bottom:14px;">
                <label style="display:block;color:rgba(255,255,255,0.7);font-size:13px;margin-bottom:5px;">Subject</label>
                <input type="text" name="subject" required placeholder="Brief description of your issue" style="width:100%;padding:12px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;font-size:14px;outline:none;">
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block;color:rgba(255,255,255,0.7);font-size:13px;margin-bottom:5px;">Priority</label>
                <select name="priority" style="width:100%;padding:12px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;font-size:14px;outline:none;">
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block;color:rgba(255,255,255,0.7);font-size:13px;margin-bottom:5px;">Message</label>
                <textarea name="message" required rows="5" placeholder="Describe your issue in detail" style="width:100%;padding:12px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;font-size:14px;outline:none;resize:vertical;"></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Ticket</button>
        </form>
    </div>

    <?php else: /* default dashboard */ ?>
    <div class="stats">
        <div class="stat-card">
            <div class="hdr"><div class="icon blue"><i class="fas fa-wifi"></i></div></div>
            <div class="val"><?= $subscription ? safe($subscription['speed']) : 'None' ?></div>
            <div class="lbl">Current Plan</div>
        </div>
        <div class="stat-card">
            <div class="hdr"><div class="icon green"><i class="fas fa-cloud-upload-alt"></i></div></div>
            <div class="val"><?= number_format($usage['total_mb'] / 1024, 2) ?> GB</div>
            <div class="lbl">Total Data Used</div>
        </div>
        <div class="stat-card">
            <div class="hdr"><div class="icon orange"><i class="fas fa-file-invoice-dollar"></i></div></div>
            <div class="val"><?= $unpaidCount ?></div>
            <div class="lbl">Unpaid Invoices</div>
        </div>
        <div class="stat-card">
            <div class="hdr"><div class="icon purple"><i class="fas fa-headset"></i></div></div>
            <div class="val"><?= $totalTickets ?></div>
            <div class="lbl">Support Tickets</div>
        </div>
    </div>

    <div class="panels">
        <div class="panel">
            <h3>Active Subscription <span><?= $subscription ? safe($subscription['plan_name']) : 'None' ?></span></h3>
            <?php if ($subscription): ?>
            <div class="sub-card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                    <div>
                        <h4><?= safe($subscription['speed']) ?></h4>
                        <p><?= safe($subscription['download_speed']) ?> download / <?= safe($subscription['upload_speed']) ?> upload</p>
                    </div>
                    <span style="display:flex;align-items:center;gap:5px;background:rgba(16,185,129,0.12);color:#34d399;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;"><span style="width:6px;height:6px;background:#34d399;border-radius:50%;box-shadow:0 0 8px rgba(52,211,153,0.6);animation:pulse 2s infinite;"></span> Connected</span>
                </div>
                <div class="meta">
                    <span><i class="fas fa-calendar"></i> <?= safe($subscription['start_date']) ?> — <?= safe($subscription['end_date']) ?></span>
                </div>
                <?php
                $start = strtotime($subscription['start_date']);
                $end = strtotime($subscription['end_date']);
                $now = time();
                $total = $end - $start;
                $elapsed = $now - $start;
                $pct = $total > 0 ? min(100, max(0, ($elapsed / $total) * 100)) : 0;
                ?>
                <div class="progress-bar"><div class="fill" style="width:<?= $pct ?>%;"></div></div>
                <div class="pct-label"><?= round($pct) ?>% of billing period used</div>
            </div>
            <?php else: ?>
            <div class="empty-state"><i class="fas fa-wifi"></i><p>No active subscription.</p><a href="index.php#plans" class="btn btn-primary" style="margin-top:10px;">View Plans</a></div>
            <?php endif; ?>
        </div>
        <div class="panel">
            <h3>Recent Activity <span>last 5</span></h3>
            <?php
            $logs = $pdo->prepare("SELECT * FROM usage_logs WHERE user_id = ? ORDER BY session_start DESC LIMIT 5");
            $logs->execute([$userId]);
            $logs = $logs->fetchAll();
            ?>
            <?php if ($logs): foreach ($logs as $log): ?>
            <div class="row-strip">
                <span class="l"><i class="fas fa-clock"></i> <?= date('M j, g:i a', strtotime($log['session_start'])) ?></span>
                <span class="r"><?= formatBytes($log['data_used_mb'] * 1024 * 1024) ?></span>
            </div>
            <?php endforeach; else: ?>
            <div class="empty-state"><i class="fas fa-inbox"></i><p>No usage logs yet.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panels" style="grid-template-columns:1fr 1fr;">
        <div class="panel">
            <h3>Recent Payments <span>last 5</span></h3>
            <?php if ($payments): foreach ($payments as $p): ?>
            <div class="row-strip">
                <span class="l"><i class="fas fa-receipt"></i> <?= safe($p['transaction_ref']) ?></span>
                <span class="r" style="font-weight:700;">$<?= number_format($p['amount'],2) ?> <span class="badge badge-<?= $p['status'] ?>"><?= $p['status'] ?></span></span>
            </div>
            <?php endforeach; else: ?>
            <div class="empty-state"><i class="fas fa-credit-card"></i><p>No payments yet.</p></div>
            <?php endif; ?>
        </div>
        <div class="panel">
            <h3>Invoices <span>last 5</span></h3>
            <?php if ($invoices): foreach ($invoices as $inv): ?>
            <div class="row-strip">
                <span class="l"><?= safe($inv['invoice_no']) ?></span>
                <span><span class="badge badge-<?= $inv['status'] ?>"><?= $inv['status'] ?></span> $<?= number_format($inv['amount'],2) ?></span>
            </div>
            <?php endforeach; else: ?>
            <div class="empty-state"><i class="fas fa-file-invoice"></i><p>No invoices yet.</p></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
let q = window.location.search;
if (q.includes('profile')) document.querySelectorAll('.side-nav a')[1].classList.add('active');
else if (q.includes('invoices')) document.querySelectorAll('.side-nav a')[2].classList.add('active');
else if (q.includes('support') || q.includes('new_ticket')) document.querySelectorAll('.side-nav a')[3].classList.add('active');
</script>
</body>
</html>
