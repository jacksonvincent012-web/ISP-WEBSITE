<?php
// Daily Security Cron — runs automatically via Windows Task Scheduler
// Rotates keys, checks integrity, cleans threats, emails report

define('CRON_MODE', true);
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/functions.php';

$report = [];
$report[] = '=== Security Cron Report ===';
$report[] = 'Time: ' . date('Y-m-d H:i:s');

// 1. Daily key rotation (always rotates once per day)
$lastRotate = @file_get_contents(sys_get_temp_dir() . '/isp_last_rotate');
$today = date('Y-m-d');
if ($lastRotate !== $today) {
    rotateEncryptionKeys();
    file_put_contents(sys_get_temp_dir() . '/isp_last_rotate', $today);
    $report[] = 'Master encryption key rotated (daily rotation)';
} else {
    $report[] = 'Key already rotated today, skipping';
}

// 2. Clean expired rate-limit & blacklist files
$deleted = 0;
foreach (glob(sys_get_temp_dir() . '/isp_*.json') as $f) {
    $data = json_decode(file_get_contents($f), true);
    if (!$data) { @unlink($f); $deleted++; continue; }
    $until = $data['until'] ?? 0;
    if ($until > 0 && time() > $until) { @unlink($f); $deleted++; }
}
$report[] = "Cleaned $deleted expired threat files";

// 3. File integrity check — detect unauthorized modifications
$integrityFile = __DIR__ . '/.file_hashes.json';
$phpFiles = glob(__DIR__ . '/*.php');
$phpFiles = array_merge($phpFiles, glob(__DIR__ . '/config/*.php'));
$currentHashes = [];
foreach ($phpFiles as $f) {
    $currentHashes[basename(dirname($f)) . '/' . basename($f)] = md5_file($f);
}
$changed = [];
if (file_exists($integrityFile)) {
    $saved = json_decode(file_get_contents($integrityFile), true) ?: [];
    foreach ($currentHashes as $file => $hash) {
        if (isset($saved[$file]) && $saved[$file] !== $hash) {
            $changed[] = $file;
        }
    }
} else {
    $report[] = 'Integrity baseline created (first run)';
}
file_put_contents($integrityFile, json_encode($currentHashes));
if (!empty($changed)) {
    $report[] = 'WARNING: File integrity changed: ' . implode(', ', $changed);
    logSuspicious('integrity_change', 'Files modified: ' . implode(', ', $changed));
} else {
    $report[] = 'File integrity OK — no unauthorized changes';
}

// 4. Auto-block persistent violators
$violators = $pdo->query("SELECT ip_address, COUNT(*) as cnt FROM audit_log WHERE created_at >= NOW() - INTERVAL 1 DAY AND actor_id = 0 GROUP BY ip_address HAVING cnt >= 10")->fetchAll();
$blocked = 0;
foreach ($violators as $v) {
    $file = sys_get_temp_dir() . '/isp_ban_' . md5($v['ip_address']) . '.json';
    if (!file_exists($file)) {
        file_put_contents($file, json_encode(['count' => 15, 'until' => time() + 3600, 'last' => time()]));
        $blocked++;
    }
}
$report[] = "Auto-blocked $blocked persistent violator IPs for 1 hour";

// 5. Security summary
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeSubs = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='active'")->fetchColumn();
$openTickets = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status='open' OR status='in_progress'")->fetchColumn();
$failedLogins = $pdo->query("SELECT COUNT(*) FROM audit_log WHERE action='login_success' AND created_at >= NOW() - INTERVAL 1 DAY")->fetchColumn();
$suspicious = $pdo->query("SELECT COUNT(*) FROM audit_log WHERE actor_id = 0 AND created_at >= NOW() - INTERVAL 1 DAY")->fetchColumn();

$report[] = '';
$report[] = '=== System Health ===';
$report[] = "Users: $totalUsers | Active Subs: $activeSubs | Open Tickets: $openTickets";
$report[] = "Logins today: $failedLogins | Suspicious events: $suspicious";

// 6. Email report to admins
$body = '<h2>Daily Security Report</h2><pre style="font-family:monospace;background:#f4f4f4;padding:16px;border-radius:8px;">' . htmlspecialchars(implode("\n", $report)) . '</pre>';
$admins = $pdo->query("SELECT email, full_name FROM users WHERE role='admin'")->fetchAll();
require_once __DIR__ . '/config/mailer.php';
foreach ($admins as $a) {
    sendMail($a['email'], 'Security Report — ' . date('Y-m-d'), $body);
}

echo implode("\n", $report) . "\nReport sent to administrators.\n";