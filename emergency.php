<?php
require_once 'config/db.php';
require_once 'config/functions.php';
require_once 'config/brand.php';
secureSessionStart(); sendSecurityHeaders();
requireAdmin();

$brand = loadBrand();
$lockdown = isLockdownActive();
$msg = ''; $type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['emergency_action'] ?? '';
    if ($action === 'activate_lockdown') {
        toggleLockdown(true);
        $msg = 'EMERGENCY LOCKDOWN ACTIVATED — All user access has been blocked. Only admin can access the control panel.';
        $type = 'error';
        $lockdown = true;
    } elseif ($action === 'deactivate_lockdown') {
        toggleLockdown(false);
        $msg = 'Lockdown deactivated. System access has been restored.';
        $lockdown = false;
    } elseif ($action === 'force_logout') {
        forceLogoutAllUsers();
        $msg = 'All user sessions have been destroyed. Everyone must log in again.';
    } elseif ($action === 'send_alert') {
        $alertMsg = trim($_POST['alert_message'] ?? '');
        if ($alertMsg) {
            sendBreachAlert($alertMsg);
            $msg = 'Security alert sent to all administrators.';
        } else {
            $msg = 'Please enter an alert message.';
            $type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Emergency — <?= safe($brand['name'] ?? 'NetConnect ISP') ?></title>
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
.side-nav li{margin-bottom:2px;}
.side-nav a{display:flex;align-items:center;gap:12px;padding:12px 16px;color:rgba(255,255,255,0.5);text-decoration:none;border-radius:10px;font-weight:500;font-size:14px;transition:all 0.2s;}
.side-nav a i{width:18px;text-align:center;}
.side-nav a:hover,.side-nav a.active{background:rgba(255,255,255,0.1);color:#fff;}
.side-nav a.active{background:rgba(59,130,246,0.2);color:#3b82f6;border-left:none;}
.side-nav .divider{margin-top:20px;border-top:1px solid rgba(255,255,255,0.06);padding-top:10px;}
.main{margin-left:260px;flex:1;padding:30px 40px;max-width:1000px;}
.main-header{margin-bottom:30px;}
.main-header h2{font-size:24px;font-weight:700;}
.main-header p{color:rgba(255,255,255,0.4);font-size:14px;}
.msg{padding:14px 18px;border-radius:12px;margin-bottom:20px;font-size:14px;display:flex;align-items:center;gap:8px;}
.msg.error{background:rgba(239,68,68,0.15);color:#fca5a5;border:1px solid rgba(239,68,68,0.2);}
.msg.success{background:rgba(34,197,94,0.15);color:#86efac;border:1px solid rgba(34,197,94,0.2);}
.card{background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:28px;margin-bottom:20px;}
.card.danger{border-color:rgba(239,68,68,0.3);background:rgba(239,68,68,0.04);}
.card h3{font-size:17px;font-weight:600;margin-bottom:12px;display:flex;align-items:center;gap:8px;}
.card p{color:rgba(255,255,255,0.5);font-size:13px;margin-bottom:16px;line-height:1.5;}
.card .btn{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;border-radius:10px;border:none;font-weight:600;font-size:14px;cursor:pointer;transition:all 0.2s;text-decoration:none;}
.btn-danger{background:#ef4444;color:#fff;}
.btn-danger:hover{background:#dc2626;transform:translateY(-1px);}
.btn-warning{background:#eab308;color:#000;}
.btn-warning:hover{background:#ca8a04;transform:translateY(-1px);}
.btn-primary{background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(59,130,246,0.3);}
.btn-green{background:#22c55e;color:#fff;}
.btn-green:hover{background:#16a34a;transform:translateY(-1px);}
textarea{width:100%;padding:12px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;font-size:14px;outline:none;resize:vertical;min-height:100px;font-family:inherit;}
textarea:focus{border-color:#3b82f6;}
.lockdown-banner{background:rgba(239,68,68,0.15);border:2px solid #ef4444;border-radius:12px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.lockdown-banner .lbl{font-weight:700;color:#fca5a5;font-size:15px;}
.lockdown-banner .sub{color:rgba(255,255,255,0.4);font-size:12px;}
@media(max-width:900px){.sidebar{width:60px;}.sidebar .logo span,.sidebar .logo .sub,.side-nav a span{display:none;}.main{margin-left:60px;padding:20px;}}
</style>
</head>
<body>
<div class="sidebar">
    <div class="logo"><i class="fas fa-shield-alt" style="color:#ef4444;"></i><div><span><?= safe($brand['name'] ?? 'NetConnect') ?></span><div class="sub">emergency</div></div></div>
    <ul class="side-nav">
        <li><a href="admin.php"><i class="fas fa-chart-bar"></i><span>Dashboard</span></a></li>
        <li><a href="audit.php"><i class="fas fa-history"></i><span>Audit Log</span></a></li>
        <li><a href="emergency.php" class="active"><i class="fas fa-exclamation-triangle"></i><span>Emergency</span></a></li>
        <li class="divider"><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
    </ul>
</div>
<div class="main">
    <div class="main-header"><h2><i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i> Emergency Controls</h2><p>Critical security actions for incident response</p></div>

    <?php if ($msg): ?><div class="msg <?= $type ?>"><i class="fas <?= $type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i> <?= safe($msg) ?></div><?php endif; ?>

    <?php if ($lockdown): ?>
    <div class="lockdown-banner">
        <i class="fas fa-shield" style="color:#ef4444;font-size:24px;"></i>
        <div><div class="lbl">LOCKDOWN ACTIVE</div><div class="sub">All user access is blocked. Only administrators can access the system.</div></div>
        <form method="POST" style="margin-left:auto;">
            <?= csrfField() ?>
            <button type="submit" name="emergency_action" value="deactivate_lockdown" class="btn btn-green"><i class="fas fa-unlock"></i> Deactivate Lockdown</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card danger">
        <h3><i class="fas fa-lock" style="color:#ef4444;"></i> Emergency Lockdown</h3>
        <p>Immediately block ALL user access to the system. Only administrators can log in during lockdown. Use this if you detect a breach or ongoing attack.</p>
        <form method="POST">
            <?= csrfField() ?>
            <button type="submit" name="emergency_action" value="activate_lockdown" class="btn btn-danger" onclick="return confirm('This will block ALL users from accessing the system. Continue?')"><i class="fas fa-lock"></i> Activate Lockdown</button>
        </form>
    </div>

    <div class="card danger">
        <h3><i class="fas fa-users-slash" style="color:#eab308;"></i> Force Logout All Users</h3>
        <p>Destroy every active session in the system. Every user (including yourself) will be forced to log in again. Use this if you suspect session hijacking.</p>
        <form method="POST">
            <?= csrfField() ?>
            <button type="submit" name="emergency_action" value="force_logout" class="btn btn-warning" onclick="return confirm('This will log out EVERY user including yourself. Continue?')"><i class="fas fa-sign-out-alt"></i> Force Logout All</button>
        </form>
    </div>

    <div class="card">
        <h3><i class="fas fa-bell" style="color:#3b82f6;"></i> Send Security Alert to Admins</h3>
        <p>Send an urgent email notification to all administrators. Use this to coordinate incident response.</p>
        <form method="POST">
            <?= csrfField() ?>
            <textarea name="alert_message" placeholder="Describe the security incident and required actions..." required></textarea>
            <div style="margin-top:12px;">
                <button type="submit" name="emergency_action" value="send_alert" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Alert</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
