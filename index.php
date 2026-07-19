<?php
require_once 'config/functions.php'; secureSessionStart(); sendSecurityHeaders();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
require_once 'config/db.php';
require_once 'config/brand.php';
$stmt = $pdo->query("SELECT * FROM plans ORDER BY price ASC");
$plans = $stmt->fetchAll();
$brand = loadBrand();
$cur = brandCurrency($brand);
$curSym = $cur === 'KES' ? 'KSh ' : '$';
$name = brandName($brand);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NetConnect ISP — High-Speed Internet</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Inter',sans-serif; background:#060d1a; color:#fff; overflow-x:hidden; }

/* Nav */
.navbar {
    display:flex; justify-content:space-between; align-items:center;
    padding:20px 60px; position:fixed; top:0; left:0; right:0; z-index:100;
    background:rgba(6,13,26,0.85); backdrop-filter:blur(20px); border-bottom:1px solid rgba(255,255,255,0.06);
}
.navbar .logo { display:flex; align-items:center; gap:10px; font-size:22px; font-weight:700; }
.navbar .logo i { color:#3b82f6; font-size:26px; }
.navbar .logo span { color:#3b82f6; }
.navbar .nav-links { display:flex; align-items:center; gap:30px; }
.navbar .nav-links a { color:rgba(255,255,255,0.7); text-decoration:none; font-size:14px; font-weight:500; transition:color 0.3s; }
.navbar .nav-links a:hover { color:#fff; }
.navbar .nav-links .btn-login {
    padding:10px 24px; background:linear-gradient(135deg,#3b82f6,#1d4ed8);
    border-radius:10px; color:#fff !important; font-weight:600;
    transition:all 0.3s; box-shadow:0 4px 20px rgba(59,130,246,0.3);
}
.navbar .nav-links .btn-login:hover { transform:translateY(-2px); box-shadow:0 8px 30px rgba(59,130,246,0.5); }

/* Hero */
.hero {
    padding:160px 60px 100px; display:flex; align-items:center;
    justify-content:space-between; max-width:1300px; margin:0 auto; position:relative;
}
.hero::before {
    content:''; position:absolute; width:600px; height:600px;
    background:radial-gradient(circle,rgba(59,130,246,0.12),transparent 70%);
    top:-200px; right:-100px; pointer-events:none;
}
.hero-content { max-width:540px; position:relative; z-index:1; }
.hero-content .badge {
    display:inline-block; padding:6px 16px; border-radius:20px;
    background:rgba(59,130,246,0.15); color:#60a5fa; font-size:12px;
    font-weight:600; margin-bottom:20px; border:1px solid rgba(59,130,246,0.2);
}
.hero-content h1 { font-size:52px; font-weight:800; line-height:1.15; margin-bottom:16px; }
.hero-content h1 span { color:#3b82f6; }
.hero-content p { color:rgba(255,255,255,0.5); font-size:18px; line-height:1.7; margin-bottom:30px; }
.hero-content .hero-btns { display:flex; gap:12px; }
.hero-content .hero-btns .btn-primary {
    padding:14px 32px; background:linear-gradient(135deg,#3b82f6,#1d4ed8);
    border-radius:12px; color:#fff; text-decoration:none; font-weight:600;
    transition:all 0.3s; box-shadow:0 8px 25px rgba(59,130,246,0.35);
    display:inline-flex; align-items:center; gap:8px;
}
.hero-content .hero-btns .btn-primary:hover { transform:translateY(-2px); box-shadow:0 12px 35px rgba(59,130,246,0.5); }
.hero-content .hero-btns .btn-secondary {
    padding:14px 32px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1);
    border-radius:12px; color:#fff; text-decoration:none; font-weight:600;
    transition:all 0.3s; display:inline-flex; align-items:center; gap:8px;
}
.hero-content .hero-btns .btn-secondary:hover { background:rgba(255,255,255,0.1); }
.hero-visual { position:relative; z-index:1; }
.hero-visual .globe {
    width:420px; height:420px;
    background:radial-gradient(circle at 30% 30%, rgba(59,130,246,0.2), rgba(30,58,138,0.1) 50%, transparent 70%);
    border-radius:50%; position:relative;
    display:flex; align-items:center; justify-content:center;
    border:1px solid rgba(59,130,246,0.15);
    animation: pulseGlow 4s ease-in-out infinite;
}
@keyframes pulseGlow {
    0%,100% { box-shadow:0 0 60px rgba(59,130,246,0.1); }
    50% { box-shadow:0 0 100px rgba(59,130,246,0.25); }
}
.hero-visual .globe i { font-size:80px; color:#3b82f6; opacity:0.6; }
.hero-visual .stat-ring {
    position:absolute; top:-20px; left:-20px; right:-20px; bottom:-20px;
    border-radius:50%; border:1px dashed rgba(59,130,246,0.15); animation: spin 30s linear infinite;
}
@keyframes spin { to { transform:rotate(360deg); } }

/* Features */
.features {
    padding:80px 60px; max-width:1300px; margin:0 auto;
}
.features .section-title { text-align:center; margin-bottom:50px; }
.features .section-title h2 { font-size:36px; font-weight:700; margin-bottom:8px; }
.features .section-title p { color:rgba(255,255,255,0.4); font-size:16px; }
.features-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; }
.feature-card {
    padding:30px; background:rgba(255,255,255,0.04);
    border:1px solid rgba(255,255,255,0.06); border-radius:16px;
    transition:all 0.3s;
}
.feature-card:hover { background:rgba(255,255,255,0.07); transform:translateY(-3px); border-color:rgba(59,130,246,0.2); }
.feature-card .icon {
    width:50px; height:50px; background:rgba(59,130,246,0.12);
    border-radius:12px; display:flex; align-items:center; justify-content:center;
    margin-bottom:16px;
}
.feature-card .icon i { font-size:20px; color:#3b82f6; }
.feature-card h3 { font-size:18px; font-weight:600; margin-bottom:8px; }
.feature-card p { color:rgba(255,255,255,0.4); font-size:14px; line-height:1.6; }

/* Plans Section */
.plans-section {
    padding:80px 60px 100px; max-width:1300px; margin:0 auto;
}
.plans-section .section-title { text-align:center; margin-bottom:50px; }
.plans-section .section-title h2 { font-size:36px; font-weight:700; margin-bottom:8px; }
.plans-section .section-title p { color:rgba(255,255,255,0.4); font-size:16px; }
.plans-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:20px; }
.plan-card {
    padding:30px; background:rgba(255,255,255,0.04);
    border:1px solid rgba(255,255,255,0.06); border-radius:16px;
    text-align:center; transition:all 0.3s; position:relative;
}
.plan-card:hover { transform:translateY(-5px); border-color:rgba(59,130,246,0.3); box-shadow:0 20px 40px rgba(0,0,0,0.3); }
.plan-card.popular { border-color:#3b82f6; background:rgba(59,130,246,0.06); }
.plan-card .popular-badge {
    position:absolute; top:-12px; left:50%; transform:translateX(-50%);
    background:linear-gradient(135deg,#3b82f6,#1d4ed8); padding:4px 16px;
    border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase;
    letter-spacing:1px;
}
.plan-card .plan-name { color:rgba(255,255,255,0.5); font-size:13px; font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px; }
.plan-card .plan-speed { font-size:28px; font-weight:700; margin-bottom:4px; }
.plan-card .plan-speed-sub { color:rgba(255,255,255,0.3); font-size:13px; margin-bottom:16px; }
.plan-card .plan-price { margin-bottom:20px; }
.plan-card .plan-price .amount { font-size:36px; font-weight:800; color:#3b82f6; }
.plan-card .plan-price .period { color:rgba(255,255,255,0.3); font-size:14px; }
.plan-card .plan-features { text-align:left; margin-bottom:20px; }
.plan-card .plan-features div { padding:6px 0; font-size:13px; color:rgba(255,255,255,0.6); display:flex; align-items:center; gap:8px; }
.plan-card .plan-features div i { color:#22c55e; font-size:12px; }
.plan-card .btn-plan {
    display:block; padding:12px; border-radius:10px;
    background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1);
    color:#fff; text-decoration:none; font-weight:600; font-size:14px;
    transition:all 0.3s;
}
.plan-card .btn-plan:hover { background:#3b82f6; border-color:#3b82f6; }
.plan-card.popular .btn-plan { background:linear-gradient(135deg,#3b82f6,#1d4ed8); border-color:transparent; }

/* Footer */
.footer {
    text-align:center; padding:40px; border-top:1px solid rgba(255,255,255,0.05);
    color:rgba(255,255,255,0.3); font-size:13px;
}

@media(max-width:768px) {
    .navbar { padding:15px 20px; }
    .navbar .nav-links { gap:15px; }
    .navbar .nav-links a:not(.btn-login) { display:none; }
    .hero { flex-direction:column; padding:120px 20px 60px; text-align:center; }
    .hero-content h1 { font-size:32px; }
    .hero-content .hero-btns { justify-content:center; }
    .hero-visual .globe { width:250px; height:250px; }
    .hero-visual .globe i { font-size:50px; }
    .features { padding:40px 20px; }
    .features-grid { grid-template-columns:1fr; }
    .plans-section { padding:40px 20px; }
    .plans-grid { grid-template-columns:1fr; }
}
</style>
</head>
<body>
<?= htmlShield() ?>
<nav class="navbar">
    <div class="logo"><i class="fas fa-wifi"></i><?= safe($name) ?></div>
    <div class="nav-links">
        <a href="#features">Features</a>
        <a href="#plans">Plans</a>
        <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> Client Login</a>
    </div>
</nav>

<section class="hero">
    <div class="hero-content">
        <div class="badge"><i class="fas fa-bolt"></i> From <?= $curSym ?>10</div>
        <h1>Fast Internet<br>for <span>Everyone</span></h1>
        <p>Reliable high-speed internet with flexible bundles. Daily, weekly, or monthly passes starting at <?= $curSym ?>10. Sign up in minutes.</p>
        <div class="hero-btns">
            <a href="#plans" class="btn-primary"><i class="fas fa-rocket"></i> View Plans</a>
            <a href="register.php" class="btn-secondary"><i class="fas fa-user-plus"></i> Get Started</a>
        </div>
    </div>
    <div class="hero-visual">
        <div class="globe"><i class="fas fa-globe"></i></div>
    </div>
</section>

<section class="features" id="features">
    <div class="section-title">
        <h2>Why Choose NetConnect?</h2>
        <p>Experience the best internet service with our premium features</p>
    </div>
    <div class="features-grid">
        <div class="feature-card">
            <div class="icon"><i class="fas fa-tachometer-alt"></i></div>
            <h3>Blazing Speeds</h3>
            <p>From 5 Mbps to 500 Mbps — choose the speed that fits your lifestyle.</p>
        </div>
        <div class="feature-card">
            <div class="icon"><i class="fas fa-shield-alt"></i></div>
            <h3>99.9% Uptime</h3>
            <p>Enterprise-grade infrastructure ensuring you stay connected always.</p>
        </div>
        <div class="feature-card">
            <div class="icon"><i class="fas fa-headset"></i></div>
            <h3>24/7 Support</h3>
            <p>Our experts are always ready to help you with any issue.</p>
        </div>
        <div class="feature-card">
            <div class="icon"><i class="fas fa-wifi"></i></div>
            <h3>Free Installation</h3>
            <p>Professional setup at no extra cost. We handle everything.</p>
        </div>
        <div class="feature-card">
            <div class="icon"><i class="fas fa-lock"></i></div>
            <h3>Secure Connection</h3>
            <p>Built-in security features to protect your data and privacy.</p>
        </div>
        <div class="feature-card">
            <div class="icon"><i class="fas fa-infinity"></i></div>
            <h3>Unlimited Data</h3>
            <p>Most plans come with unlimited data. No hidden caps.</p>
        </div>
    </div>
</section>

<section class="plans-section" id="plans">
    <div class="section-title">
        <h2>Choose Your Plan</h2>
        <p>Flexible plans designed for every need and budget</p>
    </div>
    <div class="plans-grid">
        <?php foreach ($plans as $i => $plan): ?>
        <div class="plan-card <?= $i === 2 ? 'popular' : '' ?>">
            <?php if ($i === 2): ?><div class="popular-badge">Most Popular</div><?php endif; ?>
            <div class="plan-name"><?= safe($plan['name']) ?></div>
            <div class="plan-speed"><?= safe($plan['speed'] ?? $plan['download_speed']) ?></div>
            <div class="plan-speed-sub"><?= safe($plan['upload_speed']) ?> upload</div>
            <div class="plan-price">
                <div class="amount"><?= $curSym ?><?= number_format($plan['price'], $cur === 'KES' ? 0 : 2) ?></div>
                <div class="period"><?= $plan['duration_months'] > 0 ? '/month' : '/pass' ?></div>
            </div>
            <div class="plan-features">
                <div><i class="fas fa-check"></i> <?= safe($plan['data_cap']) ?> Data</div>
                <div><i class="fas fa-check"></i> <?= safe($plan['download_speed']) ?> Download</div>
                <div><i class="fas fa-check"></i> <?= safe($plan['upload_speed']) ?> Upload</div>
                <?php if ($plan['features']): foreach (explode(',', $plan['features']) as $f): ?>
                <div><i class="fas fa-check"></i> <?= safe(trim($f)) ?></div>
                <?php endforeach; endif; ?>
            </div>
            <a href="register.php?plan=<?= $plan['id'] ?>" class="btn-plan">Choose Plan</a>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<footer class="footer">
    <p>&copy; 2026 <?= safe($name) ?>. All rights reserved. | <a href="login.php" style="color:rgba(255,255,255,0.4);text-decoration:none;">Client Login</a></p>
</footer>
</body>
</html>
