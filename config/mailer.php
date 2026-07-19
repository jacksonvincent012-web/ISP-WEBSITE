<?php
// Pluggable Mailer — writes to sent/ folder for demo, swap to SMTP for production
define('MAIL_MODE', 'file'); // 'file' = logs to sent/ directory. Change to 'smtp' for real emails.

define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', 'noreply@isp.com');

function sendMail(string $to, string $subject, string $body): bool {
    $dir = __DIR__ . '/../sent';
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $time = date('Y-m-d_H-i-s_');
    $hash = substr(md5($to . $time), 0, 8);
    $file = "$dir/{$time}{$hash}.html";

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($subject) . '</title></head><body style="font-family:Arial,sans-serif;padding:20px;background:#f4f4f4;">';
    $html .= '<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;padding:30px;box-shadow:0 2px 10px rgba(0,0,0,0.1);">';
    $html .= $body;
    $html .= '</div><p style="text-align:center;color:#999;font-size:12px;margin-top:20px;">Sent by NetConnect ISP Mailer</p></body></html>';

    file_put_contents($file, $html);
    return true;
}
