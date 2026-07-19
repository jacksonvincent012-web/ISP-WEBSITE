<?php
require_once 'config/db.php';
require_once 'config/functions.php';
require_once 'config/mpesa.php';
secureSessionStart(); sendSecurityHeaders();
requireLogin();

$userId = $_SESSION['user_id'];
$msg = '';
$type = 'error';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php?tab=invoices');
}

verifyCsrf();

$invoiceId = (int)($_POST['invoice_id'] ?? 0);
$phone = trim($_POST['phone'] ?? '');

if ($invoiceId <= 0 || $phone === '') {
    redirect('dashboard.php?tab=invoices', 'Missing invoice or phone number.', 'error');
}

// Verify the invoice belongs to this user
$stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND user_id = ? AND status = 'unpaid' LIMIT 1");
$stmt->execute([$invoiceId, $userId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    redirect('dashboard.php?tab=invoices', 'Invoice not found or already paid.', 'error');
}

// Initiate M-Pesa payment
$accountRef = $invoice['invoice_no'];
$result = mpesaSTKPush($phone, $invoice['amount'], $accountRef);

if ($result['success']) {
    // Save the transaction reference
    $txnRef = $result['CheckoutRequestID'] ?? 'SIM_' . strtoupper(bin2hex(random_bytes(4)));
    $subId = $invoice['subscription_id'] ?: null;

    // If simulated, insert a pending payment
    if ($result['simulated'] ?? false) {
        $pdo->prepare("INSERT INTO payments (user_id, subscription_id, amount, payment_method, status, transaction_ref) VALUES (?, ?, ?, 'mpesa', 'pending', ?)")
            ->execute([$userId, $subId, $invoice['amount'], $txnRef]);
        $msg = 'Simulated M-Pesa STK Push sent to ' . safe($phone) . '. Admin will confirm payment.';
        $type = 'success';
    } else {
        // Real flow: insert pending payment
        $pdo->prepare("INSERT INTO payments (user_id, subscription_id, amount, payment_method, status, transaction_ref) VALUES (?, ?, ?, 'mpesa', 'pending', ?)")
            ->execute([$userId, $subId, $invoice['amount'], $txnRef]);
        $msg = $result['message'];
        $type = 'success';
    }
} else {
    $msg = $result['message'];
}

redirect('dashboard.php?tab=invoices', $msg, $type);
