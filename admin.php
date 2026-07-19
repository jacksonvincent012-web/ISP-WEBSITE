<?php
require_once 'config/db.php';
require_once 'config/functions.php';
require_once 'config/brand.php';
secureSessionStart(); sendSecurityHeaders();
requireAdmin();

$brand = loadBrand();
$cur = brandCurrency($brand);
$curSym = $cur === 'KES' ? 'KSh ' : '$';

$allUsers = $pdo->query("SELECT * FROM users WHERE id != 999 ORDER BY created_at DESC")->fetchAll();
$allSubs  = $pdo->query("SELECT s.*, u.full_name, p.name as plan_name FROM subscriptions s JOIN users u ON s.user_id = u.id JOIN plans p ON s.plan_id = p.id ORDER BY s.created_at DESC")->fetchAll();
$todayStart = date('Y-m-d 00:00:00');
$thisMonthStart = date('Y-m-01 00:00:00');
$stats = [
    'users'         => $pdo->query("SELECT COUNT(*) FROM users WHERE id != 999")->fetchColumn(),
    'active_users'  => $pdo->query("SELECT COUNT(*) FROM users WHERE id != 999 AND status='active'")->fetchColumn(),
    'active_subs'   => $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='active'")->fetchColumn(),
    'expired_subs'  => $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='expired' OR (end_date < CURDATE() AND status='active')")->fetchColumn(),
    'offline_subs'  => $pdo->query("SELECT COUNT(*) FROM subscriptions s WHERE s.status='active' AND NOT EXISTS (SELECT 1 FROM usage_logs u WHERE u.user_id = s.user_id AND u.session_start >= NOW() - INTERVAL 1 DAY)")->fetchColumn(),
    'revenue'       => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed'")->fetchColumn(),
    'revenue_today' => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed' AND paid_at >= '$todayStart'")->fetchColumn(),
    'revenue_month' => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed' AND paid_at >= '$thisMonthStart'")->fetchColumn(),
    'open_tickets'  => $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status='open' OR status='in_progress'")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel — NetConnect ISP</title>
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
.side-nav a{display:flex;align-items:center;gap:12px;padding:11px 14px;color:rgba(255,255,255,0.4);text-decoration:none;border-radius:10px;font-weight:500;font-size:14px;transition:all 0.2s;}
.side-nav a i{width:18px;text-align:center;}
.side-nav a:hover{background:rgba(59,130,246,0.08);color:rgba(255,255,255,0.7);}
.side-nav .divider{margin-top:16px;border-top:1px solid rgba(255,255,255,0.04);padding-top:8px;}
.main{margin-left:260px;flex:1;padding:28px 36px;}
.main-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;flex-wrap:wrap;gap:15px;}
.main-header h2{font-size:26px;font-weight:800;letter-spacing:-0.5px;}
.main-header .sub{color:rgba(255,255,255,0.35);font-size:14px;}
.main-header .badge{background:rgba(59,130,246,0.12);color:#60a5fa;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:28px;}
.stat-card{padding:18px;border-radius:16px;border:1px solid rgba(255,255,255,0.05);background:rgba(14,22,40,0.6);transition:all 0.3s;backdrop-filter:blur(10px);}
.stat-card:hover{transform:translateY(-2px);border-color:rgba(255,255,255,0.1);box-shadow:0 8px 24px rgba(0,0,0,0.3);}
.stat-card .val{font-size:26px;font-weight:800;letter-spacing:-0.5px;}
.stat-card .lbl{color:rgba(255,255,255,0.3);font-size:11px;text-transform:uppercase;letter-spacing:0.6px;margin-top:2px;font-weight:500;}
.section{margin-bottom:28px;}
.section h3{font-size:17px;font-weight:600;margin-bottom:14px;color:rgba(255,255,255,0.85);}
.glass-table-wrap{background:rgba(14,22,40,0.6);border:1px solid rgba(255,255,255,0.05);border-radius:18px;overflow:hidden;backdrop-filter:blur(10px);}
table{width:100%;border-collapse:collapse;}
thead th{padding:12px 16px;text-align:left;color:rgba(255,255,255,0.35);font-size:11px;text-transform:uppercase;letter-spacing:1px;background:rgba(255,255,255,0.02);border-bottom:1px solid rgba(255,255,255,0.05);font-weight:600;}
tbody td{padding:10px 16px;color:rgba(255,255,255,0.75);font-size:13px;border-bottom:1px solid rgba(255,255,255,0.03);}
tbody tr:last-child td{border-bottom:none;}
tbody tr:hover{background:rgba(255,255,255,0.03);}
.badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block;}
.badge-active,.badge-completed,.badge-resolved{background:rgba(16,185,129,0.12);color:#34d399;}
.badge-pending{background:rgba(245,158,11,0.12);color:#fbbf24;}
.badge-suspended,.badge-expired,.badge-cancelled{background:rgba(239,68,68,0.12);color:#f87171;}
.badge-in_progress{background:rgba(59,130,246,0.12);color:#60a5fa;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;border:1px solid rgba(255,255,255,0.06);background:rgba(255,255,255,0.04);color:#e2e8f0;text-decoration:none;font-size:12px;transition:all 0.2s;cursor:pointer;}
.btn:hover{background:rgba(255,255,255,0.08);}
.btn-sm{padding:4px 10px;font-size:11px;}
.btn-danger{background:rgba(239,68,68,0.1);border-color:rgba(239,68,68,0.15);color:#f87171;}
.btn-green{background:rgba(16,185,129,0.1);border-color:rgba(16,185,129,0.15);color:#34d399;}
.action-form{display:inline;}
input[type=text],input[type=password],input[type=email],select,textarea{background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);border-radius:10px;color:#e2e8f0;font-size:13px;font-family:inherit;outline:none;transition:border-color 0.2s;}
input:focus,select:focus,textarea:focus{border-color:rgba(96,165,250,0.4);}
@media(max-width:900px){.sidebar{width:64px;}.sidebar .logo{padding:0 12px 18px;justify-content:center;}.sidebar .logo .brand-text,.sidebar .logo .sub,.side-nav a span{display:none;}.side-nav{padding:0 6px;}.side-nav a{justify-content:center;padding:11px;}.side-nav a i{width:auto;}.main{margin-left:64px;padding:20px;}}
@media(max-width:640px){.stats{grid-template-columns:repeat(2,1fr);gap:10px;}.stat-card{padding:14px;}.stat-card .val{font-size:20px;}.stat-card .lbl{font-size:10px;}.glass-table-wrap{overflow-x:auto;}.glass-table-wrap thead th,.glass-table-wrap tbody td{padding:8px 10px;white-space:nowrap;}.main-header h2{font-size:20px;}}
</style>
</head>
<body>
<div class="sidebar">
    <div class="logo">
        <i class="fas fa-shield-alt"></i>
        <div><div class="brand-text">Net<span>Connect</span></div><div class="sub">admin panel</div></div>
    </div>
    <ul class="side-nav">
        <li><a href="admin.php" class="active"><i class="fas fa-chart-bar"></i><span>Dashboard</span></a></li>
        <li><a href="admin.php?tab=users"><i class="fas fa-users"></i><span>Users</span></a></li>
        <li><a href="admin.php?tab=subscriptions"><i class="fas fa-tags"></i><span>Subscriptions</span></a></li>
        <li><a href="admin.php?tab=payments"><i class="fas fa-credit-card"></i><span>Payments</span></a></li>
        <li><a href="admin.php?tab=tickets"><i class="fas fa-life-ring"></i><span>Tickets</span></a></li>
        <li><a href="setup_2fa.php"><i class="fas fa-shield-alt"></i><span>Security (2FA)</span></a></li>
        <li><a href="audit.php"><i class="fas fa-history"></i><span>Audit Log</span></a></li>
        <li><a href="emergency.php" style="color:#ef4444;"><i class="fas fa-exclamation-triangle"></i><span>Emergency</span></a></li>
        <li class="divider"><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
    </ul>
</div>
<div class="main">
    <div class="main-header">
        <div>
            <h2>Admin Dashboard</h2>
            <span class="sub"><?= safe($_SESSION['user_name']) ?></span>
        </div>
        <span class="badge"><i class="fas fa-shield-alt"></i> Administrator</span>
    </div>

    <div class="stats">
        <div class="stat-card"><div class="val"><?= $stats['users'] ?></div><div class="lbl">Total Users</div></div>
        <div class="stat-card"><div class="val"><?= $stats['active_users'] ?></div><div class="lbl">Active Users</div></div>
        <div class="stat-card"><div class="val"><?= $stats['active_subs'] ?></div><div class="lbl">Active Subs</div></div>
        <div class="stat-card"><div class="val"><?= $stats['expired_subs'] ?></div><div class="lbl">Expired Subs</div></div>
        <div class="stat-card"><div class="val"><?= $stats['offline_subs'] ?></div><div class="lbl">Subs Offline</div></div>
        <div class="stat-card"><div class="val"><?= $curSym ?><?= number_format($stats['revenue'], 0) ?></div><div class="lbl">Total Revenue</div></div>
        <div class="stat-card"><div class="val"><?= $curSym ?><?= number_format($stats['revenue_today'], 0) ?></div><div class="lbl">Today Earnings</div></div>
        <div class="stat-card"><div class="val"><?= $curSym ?><?= number_format($stats['revenue_month'], 0) ?></div><div class="lbl">This Month</div></div>
        <div class="stat-card"><div class="val"><?= $stats['open_tickets'] ?></div><div class="lbl">Open Tickets</div></div>
    </div>

    <?php $tab = $_GET['tab'] ?? '';
    if ($tab === 'users'): ?>
    <div class="section"><h3><i class="fas fa-users" style="color:#3b82f6;margin-right:8px;"></i> All Users (<?= count($allUsers) ?>)</h3>
    <div class="glass-table-wrap"><table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Username</th><th>Phone</th><th>Role</th><th>Status</th><th>Joined</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($allUsers as $u): ?>
    <tr>
        <td><?= $u['id'] ?></td>
        <td><?= safe($u['full_name'] ?: $u['username']) ?></td>
        <td><?= safe($u['email']) ?></td>
        <td><?= safe($u['username']) ?></td>
        <td><?= safe($u['phone_encrypted'] ? decryptField($u['phone_encrypted'], $u['id']) : $u['phone']) ?></td>
        <td style="text-transform:capitalize;"><?= $u['role'] ?></td>
        <td><span class="badge badge-<?= $u['status'] ?>"><?= $u['status'] ?></span></td>
        <td style="color:rgba(255,255,255,0.4);font-size:12px;"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
        <td>
            <?php if ($u['role'] !== 'admin'): ?>
            <form method="POST" action="admin_action.php" class="action-form">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <?php if ($u['status'] === 'active'): ?>
                    <button type="submit" name="action" value="suspend_user" class="btn btn-danger btn-sm"><i class="fas fa-ban"></i></button>
    <?php elseif ($tab === 'payments'):
    $allPayments = $pdo->query("SELECT p.*, u.full_name, u.username FROM payments p JOIN users u ON p.user_id = u.id ORDER BY p.paid_at DESC, p.created_at DESC")->fetchAll();
    ?>
    <div class="section"><h3><i class="fas fa-credit-card" style="color:#3b82f6;margin-right:8px;"></i> Payments (<?= count($allPayments) ?>)</h3>
    <div class="glass-table-wrap"><table><thead><tr><th>ID</th><th>User</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($allPayments as $p): ?>
    <tr>
        <td><?= $p['id'] ?></td>
        <td><?= safe($p['full_name'] ?: $p['username']) ?></td>
        <td><?= $curSym ?><?= number_format($p['amount'],0) ?></td>
        <td style="text-transform:capitalize;"><?= $p['payment_method'] ?></td>
        <td><span class="badge badge-<?= $p['status'] ?>"><?= $p['status'] ?></span></td>
        <td style="font-size:12px;color:rgba(255,255,255,0.4);"><?= $p['paid_at'] ? date('M j, g:i a', strtotime($p['paid_at'])) : date('M j, g:i a', strtotime($p['created_at'])) ?></td>
        <td>
            <?php if ($p['status'] === 'pending'): ?>
            <form method="POST" action="admin_action.php" class="action-form" style="display:flex;gap:6px;align-items:center;">
                <?= csrfField() ?>
                <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                <input type="hidden" name="action" value="confirm_payment">
                <?php if (!$p['full_name']): ?>
                <input type="text" name="customer_name" placeholder="Enter full name" required style="padding:4px 8px;border-radius:6px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.06);color:#fff;font-size:11px;width:120px;">
                <?php endif; ?>
                <button type="submit" class="btn btn-green btn-sm"><i class="fas fa-check"></i> Confirm</button>
            </form>
            <?php else: ?><span style="color:rgba(255,255,255,0.2);font-size:11px;">—</span><?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div></div>

    <?php else: ?>
                    <button type="submit" name="action" value="activate_user" class="btn btn-green btn-sm"><i class="fas fa-check"></i></button>
                <?php endif; ?>
                <button type="submit" name="action" value="delete_user" class="btn btn-danger btn-sm" onclick="return confirm('Delete this user?')"><i class="fas fa-trash"></i></button>
            </form>
            <?php else: ?><span style="color:rgba(255,255,255,0.3);font-size:11px;">protected</span><?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div></div>

    <?php elseif ($tab === 'subscriptions'): ?>
    <div class="section"><h3><i class="fas fa-tags" style="color:#3b82f6;margin-right:8px;"></i> All Subscriptions</h3>
    <div class="glass-table-wrap"><table><thead><tr><th>ID</th><th>User</th><th>Plan</th><th>Start</th><th>End</th><th>Status</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($allSubs as $s): ?>
    <tr>
        <td><?= $s['id'] ?></td>
        <td><?= safe($s['full_name']) ?></td>
        <td><?= safe($s['plan_name']) ?></td>
        <td style="font-size:12px;"><?= $s['start_date'] ?></td>
        <td style="font-size:12px;"><?= $s['end_date'] ?></td>
        <td><span class="badge badge-<?= $s['status'] ?>"><?= $s['status'] ?></span></td>
        <td>
            <?php if ($s['status'] === 'active'): ?>
            <form method="POST" action="admin_action.php" class="action-form">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                <button type="submit" name="action" value="cancel_sub" class="btn btn-danger btn-sm"><i class="fas fa-times"></i> Cancel</button>
            </form>
            <?php else: ?><span style="color:rgba(255,255,255,0.3);font-size:11px;">—</span><?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div></div>

    <?php elseif ($tab === 'tickets'):
    $allTickets = $pdo->query("SELECT t.*, u.full_name, u.email FROM support_tickets t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC")->fetchAll();
    ?>
    <div class="section"><h3><i class="fas fa-life-ring" style="color:#3b82f6;margin-right:8px;"></i> Support Tickets (<?= count($allTickets) ?>)</h3>
    <div class="glass-table-wrap"><table><thead><tr><th>ID</th><th>User</th><th>Subject</th><th>Priority</th><th>Status</th><th>Date</th><th>Reply</th></tr></thead><tbody>
    <?php foreach ($allTickets as $t): ?>
    <tr>
        <td>#<?= $t['id'] ?></td>
        <td><?= safe($t['full_name']) ?></td>
        <td><?= safe($t['subject']) ?><br><span style="color:rgba(255,255,255,0.3);font-size:11px;"><?= safe($t['message']) ?></span></td>
        <td style="text-transform:capitalize;"><?= $t['priority'] ?></td>
        <td><span class="badge badge-<?= $t['status'] ?>"><?= str_replace('_',' ',$t['status']) ?></span></td>
        <td style="font-size:12px;color:rgba(255,255,255,0.4);"><?= date('M j, g:i a', strtotime($t['created_at'])) ?></td>
        <td>
            <form method="POST" action="admin_action.php" class="action-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="reply_ticket">
                <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                <input type="text" name="reply" placeholder="Type reply..." style="padding:4px 8px;border-radius:6px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.06);color:#fff;font-size:12px;width:150px;">
                <button type="submit" class="btn btn-sm"><i class="fas fa-paper-plane"></i></button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div></div>

    <?php else: ?>
    <div class="section"><h3><i class="fas fa-clock" style="color:#3b82f6;margin-right:8px;"></i> Recent Users</h3>
    <div class="glass-table-wrap"><table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th><th>Joined</th></tr></thead><tbody>
    <?php foreach (array_slice($allUsers, 0, 5) as $u): ?>
    <tr><td><?= $u['id'] ?></td><td><?= safe($u['full_name'] ?: '—') ?></td><td><?= safe($u['email']) ?></td><td><span class="badge badge-<?= $u['status'] ?>"><?= $u['status'] ?></span></td><td style="color:rgba(255,255,255,0.4);font-size:12px;"><?= date('M j, Y', strtotime($u['created_at'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div>
    <?php endif; ?>
</div>
</body>
</html>
