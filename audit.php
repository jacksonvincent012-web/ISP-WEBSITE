<?php
require_once 'config/db.php';
require_once 'config/functions.php';
secureSessionStart(); sendSecurityHeaders();
requireAdmin();

$logs = $pdo->query("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 100")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Audit Log — NetConnect ISP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:#060d1a;color:#fff;display:flex;min-height:100vh;}
.sidebar{width:260px;background:rgba(255,255,255,0.03);border-right:1px solid rgba(255,255,255,0.06);padding:30px 0;position:fixed;height:100vh;overflow-y:auto;}
.sidebar .logo{padding:0 20px 25px;border-bottom:1px solid rgba(255,255,255,0.06);margin-bottom:15px;display:flex;align-items:center;gap:10px;}
.sidebar .logo i{color:#3b82f6;font-size:24px;}
.sidebar .logo span{font-size:20px;font-weight:700;}
.sidebar .logo .sub{color:rgba(255,255,255,0.3);font-size:12px;}
.side-nav{list-style:none;padding:0 12px;}
.side-nav a{display:flex;align-items:center;gap:12px;padding:12px 16px;color:rgba(255,255,255,0.5);text-decoration:none;border-radius:10px;font-weight:500;font-size:14px;transition:all 0.2s;}
.side-nav a:hover,.side-nav a.active{background:rgba(59,130,246,0.1);color:#fff;}
.side-nav a i{width:18px;text-align:center;}
.side-nav .divider{margin-top:20px;border-top:1px solid rgba(255,255,255,0.06);padding-top:10px;}
.main{margin-left:260px;flex:1;padding:30px 40px;}
.main-header{margin-bottom:30px;}
.main-header h2{font-size:24px;font-weight:700;}
.main-header p{color:rgba(255,255,255,0.4);font-size:14px;}
.section h3{font-size:18px;font-weight:600;margin-bottom:15px;}
.glass-table-wrap{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:16px;overflow:hidden;}
table{width:100%;border-collapse:collapse;}
thead th{padding:14px 18px;text-align:left;color:rgba(255,255,255,0.4);font-size:12px;text-transform:uppercase;letter-spacing:1px;background:rgba(255,255,255,0.02);border-bottom:1px solid rgba(255,255,255,0.06);}
tbody td{padding:12px 18px;color:rgba(255,255,255,0.8);font-size:13px;border-bottom:1px solid rgba(255,255,255,0.04);}
tbody tr:hover{background:rgba(255,255,255,0.04);}
.badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;}
.badge-action{background:rgba(59,130,246,0.15);color:#60a5fa;}
.empty-state{text-align:center;padding:40px;color:rgba(255,255,255,0.25);}
.empty-state i{font-size:40px;margin-bottom:10px;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.05);color:#fff;text-decoration:none;font-size:13px;}
@media(max-width:900px){.sidebar{width:60px;}.sidebar .logo span,.sidebar .logo .sub,.side-nav a span{display:none;}.main{margin-left:60px;padding:20px;}}
</style>
</head>
<body>
<div class="sidebar">
    <div class="logo">
        <i class="fas fa-shield-alt"></i>
        <div><span>Net<span style="color:#3b82f6;">Connect</span></span><div class="sub">admin panel</div></div>
    </div>
    <ul class="side-nav">
        <li><a href="admin.php"><i class="fas fa-chart-bar"></i><span>Dashboard</span></a></li>
        <li><a href="admin.php?tab=users"><i class="fas fa-users"></i><span>Users</span></a></li>
        <li><a href="admin.php?tab=subscriptions"><i class="fas fa-tags"></i><span>Subscriptions</span></a></li>
        <li><a href="admin.php?tab=tickets"><i class="fas fa-life-ring"></i><span>Tickets</span></a></li>
        <li><a href="setup_2fa.php"><i class="fas fa-shield-alt"></i><span>Security (2FA)</span></a></li>
        <li><a href="audit.php" class="active"><i class="fas fa-history"></i><span>Audit Log</span></a></li>
        <li class="divider"><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
    </ul>
</div>
<div class="main">
    <div class="main-header">
        <h2>Audit Log</h2>
        <p>All privileged actions are recorded here for accountability</p>
    </div>
    <div class="section">
        <h3><i class="fas fa-history" style="color:#3b82f6;margin-right:8px;"></i> Recent Activity (last 100)</h3>
        <div class="glass-table-wrap">
            <table>
                <thead><tr><th>#</th><th>Actor</th><th>Action</th><th>Target</th><th>Details</th><th>IP</th><th>Time</th></tr></thead>
                <tbody>
                <?php if ($logs): foreach ($logs as $l): ?>
                <tr>
                    <td><?= $l['id'] ?></td>
                    <td><?= safe($l['actor_name']) ?></td>
                    <td><span class="badge badge-action"><?= safe($l['action']) ?></span></td>
                    <td><?= safe($l['target_type']) ?: '—' ?><?= $l['target_id'] ? ' #'.$l['target_id'] : '' ?></td>
                    <td style="color:rgba(255,255,255,0.5);"><?= safe($l['details']) ?: '—' ?></td>
                    <td style="font-size:12px;color:rgba(255,255,255,0.4);"><?= safe($l['ip_address']) ?></td>
                    <td style="font-size:12px;color:rgba(255,255,255,0.4);"><?= date('M j, g:i a', strtotime($l['created_at'])) ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="7"><div class="empty-state"><i class="fas fa-inbox"></i><p>No audit records yet.</p></div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
