<?php
require_once 'config/db.php';
require_once 'config/functions.php';
secureSessionStart(); sendSecurityHeaders();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin.php');
}

verifyCsrf();

// Rate limit admin actions — max 10 per IP per minute
$rl = checkRateLimit('admin_action', 10, 60);
if ($rl !== null) {
    redirect('admin.php', $rl, 'error');
}
hitRateLimit('admin_action');

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);
$paymentId = (int)($_POST['payment_id'] ?? 0);

if ($id <= 0) {
    redirect('admin.php', 'Invalid request.', 'error');
}

try {
    switch ($action) {
        case 'confirm_payment':
            $customerName = trim($_POST['customer_name'] ?? '');
            if ($paymentId <= 0) redirect('admin.php?tab=payments', 'Invalid payment.', 'error');
            $payStmt = $pdo->prepare("SELECT p.*, u.full_name FROM payments p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
            $payStmt->execute([$paymentId]);
            $pay = $payStmt->fetch();
            if (!$pay || $pay['status'] !== 'pending') redirect('admin.php?tab=payments', 'Payment not found or already confirmed.', 'error');
            $pdo->prepare("UPDATE payments SET status = 'completed', paid_at = NOW() WHERE id = ?")->execute([$paymentId]);
            // Update user's full_name if empty
            if (!$pay['full_name'] && $customerName !== '') {
                $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?")->execute([$customerName, $pay['user_id']]);
            }
            // Activate linked subscription if any
            if ($pay['subscription_id']) {
                $pdo->prepare("UPDATE subscriptions SET status = 'active' WHERE id = ? AND status = 'pending'")->execute([$pay['subscription_id']]);
            }
            logAudit('confirm_payment', 'payment', $paymentId, 'Confirmed by admin');
            redirect('admin.php?tab=payments', 'Payment confirmed. User name updated.');

        case 'suspend_user':
            $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role != 'admin'")->execute([$id]);
            logAudit('suspend_user', 'user', $id);
            redirect('admin.php?tab=users', 'User suspended.');
        case 'activate_user':
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$id]);
            logAudit('activate_user', 'user', $id);
            redirect('admin.php?tab=users', 'User activated.');
        case 'delete_user':
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'")->execute([$id]);
            logAudit('delete_user', 'user', $id);
            redirect('admin.php?tab=users', 'User deleted.');
        case 'cancel_sub':
            $pdo->prepare("UPDATE subscriptions SET status = 'cancelled' WHERE id = ?")->execute([$id]);
            logAudit('cancel_subscription', 'subscription', $id);
            redirect('admin.php?tab=subscriptions', 'Subscription cancelled.');
        case 'reply_ticket':
            $reply = trim($_POST['reply'] ?? '');
            $tid = (int)($_POST['ticket_id'] ?? 0);
            if ($tid > 0 && $reply !== '') {
                $pdo->prepare("UPDATE support_tickets SET admin_reply = ?, status = 'resolved', updated_at = NOW() WHERE id = ?")->execute([$reply, $tid]);
                logAudit('reply_ticket', 'ticket', $tid);
            }
            redirect('admin.php?tab=tickets', 'Reply sent. Ticket resolved.');
        default:
            redirect('admin.php', 'Unknown action.', 'error');
    }
} catch (PDOException $e) {
    redirect('admin.php', 'Action failed. Please try again.', 'error');
}
