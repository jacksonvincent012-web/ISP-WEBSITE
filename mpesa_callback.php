<?php
// M-Pesa Callback — Safaricom sends POST here after STK Push completes
// For sandbox/simulated, this is called manually by admin to confirm

require_once 'config/db.php';
require_once 'config/functions.php';
require_once 'config/mpesa.php';
sendSecurityHeaders();

// Log the raw callback for debugging
$raw = file_get_contents('php://input');
$logDir = __DIR__ . '/mpesa_logs';
if (!is_dir($logDir)) mkdir($logDir, 0777, true);
file_put_contents($logDir . '/callback_' . date('Ymd_His') . '.json', $raw ?: '{}');

// In production: verify Safaricom HMAC signature
if (!MPESA_SIMULATE_ONLY) {
    $signature = $_SERVER['HTTP_X_MESSAGE_SIGNATURE'] ?? '';
    $signedData = $raw;
    $expected = base64_encode(hash_hmac('sha256', $signedData, MPESA_CONSUMER_SECRET, true));
    if (!hash_equals($expected, $signature)) {
        logSuspicious('mpesa_hmac_failed', 'Invalid HMAC signature on M-Pesa callback');
        http_response_code(403);
        echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid signature']);
        exit;
    }
}

$data = json_decode($raw, true);
$result = $data['Body']['stkCallback'] ?? [];

if (!empty($result['CheckoutRequestID'])) {
    $txnRef = $result['CheckoutRequestID'];
    $code = $result['ResultCode'] ?? 1;

    if ($code === 0) {
        // Payment successful
        $amount = $result['CallbackMetadata']['Item'][0]['Value'] ?? 0;
        $mpesaReceipt = '';
        $phone = '';
        $firstName = '';
        $middleName = '';
        $lastName = '';
        foreach ($result['CallbackMetadata']['Item'] ?? [] as $item) {
            if ($item['Name'] === 'MpesaReceiptNumber') $mpesaReceipt = $item['Value'];
            if ($item['Name'] === 'PhoneNumber') $phone = $item['Value'];
            if ($item['Name'] === 'FirstName') $firstName = $item['Value'];
            if ($item['Name'] === 'MiddleName') $middleName = $item['Value'];
            if ($item['Name'] === 'LastName') $lastName = $item['Value'];
        }

        $stmt = $pdo->prepare("UPDATE payments SET status = 'completed', transaction_ref = CONCAT(transaction_ref, '/', ?) WHERE transaction_ref = ?");
        $stmt->execute([$mpesaReceipt, $txnRef]);

        // Mark the linked invoice as paid
        $invStmt = $pdo->prepare("SELECT i.id FROM invoices i JOIN payments p ON p.subscription_id = i.subscription_id WHERE p.transaction_ref LIKE ? AND p.user_id = i.user_id AND p.status = 'completed' LIMIT 1");
        $invStmt->execute(['%' . $txnRef . '%']);
        $inv = $invStmt->fetch();
        if ($inv) {
            $pdo->prepare("UPDATE invoices SET status = 'paid', paid_at = NOW() WHERE id = ?")->execute([$inv['id']]);
        }

        // Auto-fill full_name from M-Pesa subscriber data if user has no name
        $fullMpesaName = trim(implode(' ', array_filter([$firstName, $middleName, $lastName])));
        if ($fullMpesaName !== '') {
            $payStmt = $pdo->prepare("SELECT user_id FROM payments WHERE transaction_ref LIKE ? AND status = 'completed' LIMIT 1");
            $payStmt->execute(['%' . $txnRef . '%']);
            $pay = $payStmt->fetch();
            if ($pay) {
                $pdo->prepare("UPDATE users SET full_name = COALESCE(NULLIF(full_name, ''), ?) WHERE id = ?")->execute([$fullMpesaName, $pay['user_id']]);
            }
        }

        logAudit('mpesa_payment_completed', 'payment', 0, 'M-Pesa receipt: ' . $mpesaReceipt);
    }
}

http_response_code(200);
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
