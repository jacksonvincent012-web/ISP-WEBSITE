<?php
require_once 'config/db.php';
require_once 'config/functions.php';
require_once 'config/brand.php';
secureSessionStart(); sendSecurityHeaders();
requireAdmin();

$msg = ''; $type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $brandName = trim($_POST['brand_name'] ?? 'ISP');
    $supportEmail = trim($_POST['support_email'] ?? '');
    $currency = trim($_POST['currency'] ?? 'USD');
    $primaryColor = trim($_POST['primary_color'] ?? '#3b82f6');

    $pdo->prepare("UPDATE settings SET brand_name = ?, support_email = ?, currency = ?, primary_color = ? WHERE id = 1")
        ->execute([$brandName, $supportEmail, $currency, $primaryColor]);
    logAudit('brand_settings_updated', 'settings', 1, "Brand: $brandName");
    $msg = 'Settings saved successfully!';
}

$brand = loadBrand();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings — <?= safe($brand['brand_name'] ?? 'ISP') ?></title>
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
.main{margin-left:260px;flex:1;padding:30px 40px;max-width:800px;}
.main-header{margin-bottom:30px;}
.main-header h2{font-size:24px;font-weight:700;}
.main-header p{color:rgba(255,255,255,0.4);font-size:14px;}
.card{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:30px;}
.card h3{font-size:17px;font-weight:600;margin-bottom:20px;}
.form-group{margin-bottom:18px;}
.form-group label{display:block;color:rgba(255,255,255,0.7);font-size:13px;font-weight:500;margin-bottom:5px;}
input[type=text],input[type=email],input[type=color]{width:100%;padding:12px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;font-size:14px;outline:none;}
input:focus{border-color:#3b82f6;}
input[type=color]{height:48px;padding:4px;cursor:pointer;}
.btn{display:inline-flex;align-items:center;gap:8px;padding:12px 28px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;transition:all 0.3s;}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(59,130,246,0.4);}
.msg{padding:12px;border-radius:12px;margin-bottom:18px;font-size:13px;}
.success{background:rgba(34,197,94,0.12);color:#86efac;border:1px solid rgba(34,197,94,0.2);}
.error{background:rgba(239,68,68,0.12);color:#fca5a5;border:1px solid rgba(239,68,68,0.2);}
.preview{display:flex;align-items:center;gap:12px;margin-top:12px;padding:16px;background:rgba(255,255,255,0.04);border-radius:12px;}
.preview .swatch{width:30px;height:30px;border-radius:8px;}
@media(max-width:900px){.sidebar{width:60px;}.sidebar .logo span,.sidebar .logo .sub,.side-nav a span{display:none;}.main{margin-left:60px;padding:20px;}}
</style>
</head>
<body>
<div class="sidebar">
    <div class="logo"><i class="fas fa-cog"></i><div><span>Net<span style="color:#3b82f6;">Connect</span></span><div class="sub">admin panel</div></div></div>
    <ul class="side-nav">
        <li><a href="admin.php"><i class="fas fa-chart-bar"></i><span>Dashboard</span></a></li>
        <li><a href="admin.php?tab=users"><i class="fas fa-users"></i><span>Users</span></a></li>
        <li><a href="admin.php?tab=subscriptions"><i class="fas fa-tags"></i><span>Subscriptions</span></a></li>
        <li><a href="admin.php?tab=tickets"><i class="fas fa-life-ring"></i><span>Tickets</span></a></li>
        <li><a href="setup_2fa.php"><i class="fas fa-shield-alt"></i><span>Security (2FA)</span></a></li>
        <li><a href="audit.php"><i class="fas fa-history"></i><span>Audit Log</span></a></li>
        <li><a href="sent.php"><i class="fas fa-envelope-open-text"></i><span>Sent Mail</span></a></li>
        <li><a href="admin_settings.php" class="active"><i class="fas fa-paint-brush"></i><span>Branding</span></a></li>
        <li class="divider"><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
    </ul>
</div>
<div class="main">
    <div class="main-header">
        <h2>Branding & Settings</h2>
        <p>Customize the ISP portal for your business</p>
    </div>
    <?php if ($msg): ?><div class="msg <?= $type ?>"><?= safe($msg) ?></div><?php endif; ?>
    <div class="card">
        <h3><i class="fas fa-paint-brush" style="color:#3b82f6;margin-right:8px;"></i> White-Label Settings</h3>
        <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label>Brand Name</label>
                <input type="text" name="brand_name" value="<?= safe($brand['brand_name']) ?>" required>
            </div>
            <div class="form-group">
                <label>Support Email</label>
                <input type="email" name="support_email" value="<?= safe($brand['support_email']) ?>">
            </div>
            <div class="form-group">
                <label>Currency Code</label>
                <input type="text" name="currency" value="<?= safe($brand['currency']) ?>" maxlength="3" placeholder="USD, KES, EUR" style="text-transform:uppercase;width:100px;">
            </div>
            <div class="form-group">
                <label>Primary Color</label>
                <input type="color" name="primary_color" value="<?= safe($brand['primary_color']) ?>" style="width:60px;height:48px;padding:4px;">
                <span style="color:rgba(255,255,255,0.3);font-size:13px;margin-left:10px;"><?= safe($brand['primary_color']) ?></span>
                <div class="preview">
                    <div class="swatch" style="background:<?= safe($brand['primary_color']) ?>"></div>
                    <span style="font-size:13px;color:rgba(255,255,255,0.6);">Brand color preview</span>
                </div>
            </div>
            <button type="submit" class="btn"><i class="fas fa-save"></i> Save Settings</button>
        </form>
    </div>
</div>
</body>
</html>
