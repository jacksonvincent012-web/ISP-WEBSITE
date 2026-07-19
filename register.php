<?php require_once 'config/functions.php'; secureSessionStart(); sendSecurityHeaders();
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
require_once 'config/db.php';
$selectedPlan = isset($_GET['plan']) ? (int)$_GET['plan'] : 0;
$plans = $pdo->query("SELECT * FROM plans ORDER BY price ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — NetConnect ISP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family:'Inter',sans-serif; min-height:100vh;
    background:linear-gradient(135deg,#060d1a,#0f1b33,#060d1a);
    display:flex; justify-content:center; align-items:center; padding:40px 20px;
    position:relative; overflow:auto;
}
body::before {
    content:''; position:fixed; width:500px; height:500px;
    background:radial-gradient(circle,rgba(59,130,246,0.1),transparent 70%);
    top:-150px; right:-150px; pointer-events:none;
}
.register-container {
    background:rgba(255,255,255,0.05);
    backdrop-filter:blur(30px); border:1px solid rgba(255,255,255,0.08);
    border-radius:24px; padding:45px 40px;
    max-width:480px; width:94%; position:relative; z-index:1;
    box-shadow:0 30px 60px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.08);
}
.logo-area { text-align:center; margin-bottom:28px; }
.logo-area .icon {
    width:55px; height:55px;
    background:linear-gradient(135deg,#3b82f6,#1d4ed8);
    border-radius:16px; display:flex; align-items:center; justify-content:center;
    margin:0 auto 12px; box-shadow:0 8px 25px rgba(59,130,246,0.3);
}
.logo-area .icon i { font-size:22px; color:#fff; }
.logo-area h1 { color:#fff; font-size:22px; font-weight:700; }
.logo-area h1 span { color:#3b82f6; }
.logo-area p { color:rgba(255,255,255,0.4); font-size:14px; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.form-group { margin-bottom:16px; }
.form-group label { display:block; color:rgba(255,255,255,0.7); font-size:13px; font-weight:500; margin-bottom:5px; }
.input-wrap { position:relative; }
.input-wrap i {
    position:absolute; left:14px; top:50%; transform:translateY(-50%);
    color:rgba(255,255,255,0.3); font-size:14px;
}
.input-wrap textarea ~ i { top:16px; transform:none; }
input, select, textarea {
    width:100%; padding:12px 14px 12px 40px;
    background:rgba(255,255,255,0.06);
    border:1px solid rgba(255,255,255,0.1);
    border-radius:10px; color:#fff; font-size:14px;
    font-family:inherit; outline:none; transition:all 0.3s;
}
select { padding-left:40px; cursor:pointer; appearance:none; }
select option { background:#0f1b33; color:#fff; }
textarea { padding-left:40px; resize:vertical; height:70px; }
input:focus, select:focus, textarea:focus { border-color:#3b82f6; background:rgba(255,255,255,0.1); box-shadow:0 0 0 3px rgba(59,130,246,0.15); }
input::placeholder, textarea::placeholder { color:rgba(255,255,255,0.25); }
.btn-register {
    width:100%; padding:14px;
    background:linear-gradient(135deg,#3b82f6,#1d4ed8);
    color:#fff; border:none; border-radius:12px;
    font-size:15px; font-weight:600; cursor:pointer;
    transition:all 0.3s;
    box-shadow:0 8px 25px rgba(59,130,246,0.3);
    display:flex; align-items:center; justify-content:center; gap:8px;
    margin-top:4px;
}
.btn-register:hover { transform:translateY(-2px); box-shadow:0 12px 35px rgba(59,130,246,0.5); }
.msg {
    padding:12px 16px; margin-bottom:18px; border-radius:12px;
    text-align:center; font-size:13px; backdrop-filter:blur(10px);
}
.error { background:rgba(239,68,68,0.12); color:#fca5a5; border:1px solid rgba(239,68,68,0.2); }
.success { background:rgba(34,197,94,0.12); color:#86efac; border:1px solid rgba(34,197,94,0.2); }
.bottom-links { text-align:center; margin-top:16px; }
.bottom-links a { color:rgba(255,255,255,0.4); font-size:13px; text-decoration:none; transition:color 0.3s; }
.bottom-links a:hover { color:#3b82f6; }
@media(max-width:480px) { .form-row { grid-template-columns:1fr; } .register-container { padding:30px 16px; } body{ padding:20px 10px; } .logo-area h1{font-size:18px;} body::before,body::after{display:none;} input,select,textarea{padding:12px 12px 12px 34px;font-size:13px;} .input-wrap i{left:10px;font-size:12px;} .btn-register{padding:12px;font-size:14px;} .pp-grid{grid-template-columns:1fr 1fr;} .pp-card{padding:10px;} }
/* ─── Plan Picker ─── */
.plan-picker{margin-bottom:20px;}
.pp-hdr{color:rgba(255,255,255,0.7);font-size:14px;font-weight:600;margin-bottom:10px;display:flex;align-items:center;gap:8px;}
.pp-hdr::before{content:'\f0e7';font-family:'Font Awesome 6 Free';font-weight:900;color:#f59e0b;font-size:13px;}
.pp-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;}
.pp-card{position:relative;background:linear-gradient(135deg,rgba(59,130,246,0.12),rgba(99,102,241,0.12));border:1px solid rgba(59,130,246,0.25);border-radius:10px;padding:12px 10px;cursor:pointer;text-align:center;transition:all 0.25s;display:block;}
.pp-card input{position:absolute;opacity:0;pointer-events:none;}
.pp-card .pp-name{color:#93c5fd;}
.pp-card .pp-price{color:#fff;}
.pp-card .pp-dur{color:rgba(147,197,253,0.6);}
.pp-card.selected{border-color:#60a5fa;background:linear-gradient(135deg,#1d4ed8,#3b82f6);box-shadow:0 0 20px rgba(59,130,246,0.35);}
.pp-card.selected .pp-name{color:#bfdbfe;}
.pp-card.selected .pp-price{color:#fff;}
.pp-card:hover{border-color:#3b82f6;background:linear-gradient(135deg,rgba(59,130,246,0.2),rgba(99,102,241,0.2));}
.pp-card.premium{border-color:#3b82f6;background:linear-gradient(135deg,#1d4ed8,#3b82f6);box-shadow:0 4px 16px rgba(59,130,246,0.3);}
.pp-card.premium .pp-name{color:#bfdbfe;}
.pp-card.premium .pp-price{color:#fff;}
.pp-card.premium .pp-dur{color:#dbeafe;}
.pp-card.premium.selected{box-shadow:0 0 24px rgba(59,130,246,0.5);border-color:#60a5fa;}
.pp-name{color:rgba(255,255,255,0.5);font-size:10px;font-weight:500;margin-bottom:2px;text-transform:uppercase;letter-spacing:0.3px;}
.pp-price{color:rgba(255,255,255,0.85);font-size:16px;font-weight:800;}
.pp-dur{color:rgba(255,255,255,0.2);font-size:10px;margin-top:2px;}
@media(max-width:600px){.pp-grid{grid-template-columns:1fr 1fr;}}
</style>
</head>
<body>
<div class="register-container">
    <div class="logo-area">
        <div class="icon"><i class="fas fa-user-plus"></i></div>
        <h1>Create Your <span>Account</span></h1>
        <p>Join NetConnect and get connected</p>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="msg <?= safe($_GET['type'] ?? 'error') ?>">
        <i class="fas <?= ($_GET['type'] ?? '') === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
        <?= safe($_GET['msg']) ?>
    </div>
    <?php endif; ?>

    <form action="process.php" method="POST">
        <?= csrfField() . honeypotField() ?>
        <div class="plan-picker">
            <div class="pp-hdr">Choose Your Plan</div>
            <div class="pp-grid">
                <?php foreach ($plans as $plan):
                    $sel = $selectedPlan == $plan['id'] ? 'selected' : '';
                    $dur = $plan['duration_hours'];
                    if ($dur >= 720) $label = round($dur/720).' mo';
                    elseif ($dur >= 168) $label = round($dur/168).' wk';
                    elseif ($dur >= 24) $label = round($dur/24).' d';
                    else $label = $dur.' h';
                    $premium = $plan['price'] >= 500 ? 'premium' : '';
                ?>
                <label class="pp-card <?= $sel ?> <?= $premium ?>" data-id="<?= $plan['id'] ?>">
                    <input type="radio" name="plan_id" value="<?= $plan['id'] ?>" <?= $sel ? 'checked' : '' ?>>
                    <div class="pp-name"><?= safe($plan['name']) ?></div>
                    <div class="pp-price">KSh <?= number_format($plan['price'],0) ?></div>
                    <div class="pp-dur"><?= $label ?></div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Username</label>
                <div class="input-wrap">
                    <i class="fas fa-at"></i>
                    <input type="text" name="username" placeholder="johndoe" required>
                </div>
            </div>
            <div class="form-group">
                <label>Email</label>
                <div class="input-wrap">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="john@example.com" required>
                </div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Phone</label>
                <div class="input-wrap">
                    <i class="fas fa-phone"></i>
                    <input type="text" name="phone" placeholder="+254 712 345 678">
                </div>
            </div>
            <div class="form-group">
                <label>Password</label>
                <div class="input-wrap">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Min 8 chars, upper, lower, number, special" required minlength="8">
                </div>
                <div class="pw-meter" style="margin-top:6px;"><meter max="100" low="40" high="80" optimum="100" value="0" style="width:100%;height:6px;border-radius:3px;"></meter><span style="font-size:11px;color:rgba(255,255,255,0.4);">Weak</span></div>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <div class="input-wrap">
                    <i class="fas fa-check-circle"></i>
                    <input type="password" name="confirm_password" placeholder="Repeat password" required>
                </div>
            </div>
        </div>
        <button type="submit" class="btn-register"><i class="fas fa-rocket"></i> Create Account</button>
    </form>

    <div class="bottom-links">
        <a href="login.php">Already have an account? Sign in</a> &middot;
        <a href="index.php">Back to Home</a>
    </div>
</div>
<script>
(function(){var p=document.querySelector('input[name=password]'),m=document.querySelector('.pw-meter meter'),s=document.querySelector('.pw-meter span');if(!p)return;p.addEventListener('input',function(){var v=p.value,score=0;if(v.length>=8)score+=25;if(/[A-Z]/.test(v))score+=25;if(/[a-z]/.test(v))score+=25;if(/[0-9]/.test(v))score+=12;if(/[^A-Za-z0-9]/.test(v))score+=13;m.value=score;if(score<40)s.textContent='Weak';else if(score<80)s.textContent='Medium';else s.textContent='Strong';});})();
</script>
</body>
</html>
