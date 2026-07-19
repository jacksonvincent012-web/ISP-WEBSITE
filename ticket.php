<?php
require_once 'config/db.php';
require_once 'config/functions.php';
secureSessionStart(); sendSecurityHeaders();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php?tab=support');
}

verifyCsrf();

$subject = trim($_POST['subject'] ?? '');
$priority = trim($_POST['priority'] ?? 'medium');
$message = trim($_POST['message'] ?? '');

if ($subject === '' || $message === '') {
    redirect('dashboard.php?tab=new_ticket', 'Please fill in all fields.', 'error');
}

try {
    $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, subject, message, priority, status) VALUES (?, ?, ?, ?, 'open')");
    $stmt->execute([$_SESSION['user_id'], $subject, $message, $priority]);
    redirect('dashboard.php?tab=support', 'Ticket created successfully!');
} catch (PDOException $e) {
    redirect('dashboard.php?tab=new_ticket', 'Failed to create ticket. Try again.', 'error');
}
