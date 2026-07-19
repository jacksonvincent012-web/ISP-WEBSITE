<?php
require_once 'config/db.php';
require_once 'config/functions.php';
secureSessionStart(); sendSecurityHeaders();
requireAdmin();

$dir = __DIR__ . '/sent';
$files = is_dir($dir) ? array_diff(scandir($dir), ['.', '..']) : [];
rsort($files);
$current = isset($_GET['view']) ? basename($_GET['view']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sent Mail — NetConnect ISP</title>
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
.layout{display:flex;gap:20px;height:calc(100vh - 130px);}
.files{width:320px;flex-shrink:0;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:12px;overflow-y:auto;}
.files .item{padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.04);cursor:pointer;display:block;color:rgba(255,255,255,0.7);text-decoration:none;font-size:13px;transition:all 0.2s;}
.files .item:hover,.files .item.active{background:rgba(59,130,246,0.1);color:#fff;}
.files .item .t{font-size:11px;color:rgba(255,255,255,0.3);margin-top:2px;}
.preview-box{flex:1;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:12px;overflow:hidden;}
.preview-box iframe{width:100%;height:100%;border:none;background:#fff;border-radius:12px;}
.empty{text-align:center;padding:60px;color:rgba(255,255,255,0.25);}
.empty i{font-size:50px;margin-bottom:10px;}
@media(max-width:900px){.sidebar{width:60px;}.sidebar .logo span,.sidebar .logo .sub,.side-nav a span{display:none;}.main{margin-left:60px;padding:20px;}.files{width:200px;}}
</style>
</head>
<body>
<div class="sidebar">
    <div class="logo"><i class="fas fa-cog"></i><div><span>Net<span style="color:#3b82f6;">Connect</span></span><div class="sub">admin panel</div></div></div>
    <ul class="side-nav">
        <li><a href="admin.php"><i class="fas fa-chart-bar"></i><span>Dashboard</span></a></li>
        <li><a href="admin.php?tab=users"><i class="fas fa-users"></i><span>Users</span></a></li>
        <li><a href="admin_settings.php"><i class="fas fa-paint-brush"></i><span>Branding</span></a></li>
        <li><a href="sent.php" class="active"><i class="fas fa-envelope-open-text"></i><span>Sent Mail</span></a></li>
        <li class="divider"><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
    </ul>
</div>
<div class="main">
    <div class="main-header">
        <h2>Sent Emails (Demo Viewer)</h2>
        <p>Emails are logged here in demo mode. Replace mailer with SMTP for production.</p>
    </div>
    <div class="layout">
        <div class="files">
            <?php if (empty($files)): ?>
            <div style="padding:20px;text-align:center;color:rgba(255,255,255,0.3);font-size:13px;">No sent emails yet.<br>Register a user to trigger one.</div>
            <?php else: foreach ($files as $f): ?>
            <a href="sent.php?view=<?= urlencode($f) ?>" class="item <?= $current === $f ? 'active' : '' ?>">
                <?= htmlspecialchars($f) ?>
                <div class="t"><?= date('M j, Y g:i a', filemtime("$dir/$f")) ?></div>
            </a>
            <?php endforeach; endif; ?>
        </div>
        <div class="preview-box">
            <?php if ($current && file_exists("$dir/$current")): ?>
            <div style="width:100%;height:100%;overflow:auto;background:#fff;border-radius:12px;padding:20px;">
                <?= file_get_contents("$dir/$current") ?>
            </div>
            <?php else: ?>
            <div class="empty"><i class="fas fa-envelope-open-text"></i><p>Select an email to preview</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
