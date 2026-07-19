<?php
// M-Pesa Daraja API Integration
// ===============================
// Set SIMULATE_ONLY = true for demo without credentials
// Set to false + fill credentials for live Safaricom API

define('MPESA_SIMULATE_ONLY', true); // Set false when you have real credentials
define('MPESA_CONSUMER_KEY', '');
define('MPESA_CONSUMER_SECRET', '');
define('MPESA_PASSKEY', '');
define('MPESA_SHORTCODE', '174379'); // Safaricom test shortcode
define('MPESA_ENV', 'sandbox'); // 'sandbox' or 'production'
define('MPESA_CALLBACK_URL', 'http://localhost/isp-system/mpesa_callback.php');

function mpesaGetAccessToken(): string {
    if (MPESA_SIMULATE_ONLY) return 'simulated_token_' . bin2hex(random_bytes(8));

    $url = MPESA_ENV === 'sandbox'
        ? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
        : 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET)],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http === 200) {
        $data = json_decode($res, true);
        return $data['access_token'] ?? '';
    }
    return '';
}

function mpesaSTKPush(string $phone, float $amount, string $accountRef, string $transactionDesc = 'ISP Payment'): array {
    // Normalize phone: 254XXXXXXXXX
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) === '0') $phone = '254' . substr($phone, 1);
    if (substr($phone, 0, 1) === '7' || substr($phone, 0, 1) === '1') $phone = '254' . $phone;
    if (strlen($phone) !== 12 || substr($phone, 0, 3) !== '254') {
        return ['success' => false, 'message' => 'Invalid phone number. Use 254XXXXXXXXX format.'];
    }

    if (MPESA_SIMULATE_ONLY) {
        // Simulated success
        $code = 'SIM_' . strtoupper(bin2hex(random_bytes(4)));
        return [
            'success' => true,
            'simulated' => true,
            'CheckoutRequestID' => $code,
            'MerchantRequestID' => 'MR_' . $code,
            'message' => 'Simulated STK Push sent to ' . $phone . ' (M-Pesa sandbox mode)',
        ];
    }

    $token = mpesaGetAccessToken();
    if (!$token) {
        return ['success' => false, 'message' => 'Failed to get access token.'];
    }

    $amount = (int) round($amount);
    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

    $payload = [
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phone,
        'PartyB' => MPESA_SHORTCODE,
        'PhoneNumber' => $phone,
        'CallBackURL' => MPESA_CALLBACK_URL,
        'AccountReference' => substr($accountRef, 0, 12),
        'TransactionDesc' => substr($transactionDesc, 0, 13),
    ];

    $url = MPESA_ENV === 'sandbox'
        ? 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
        : 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($res, true);
    if ($http === 200 && ($data['ResponseCode'] ?? '1') === '0') {
        return [
            'success' => true,
            'simulated' => false,
            'CheckoutRequestID' => $data['CheckoutRequestID'] ?? '',
            'MerchantRequestID' => $data['MerchantRequestID'] ?? '',
            'message' => 'STK Push sent. Check your phone to enter PIN.',
        ];
    }

    return [
        'success' => false,
        'simulated' => false,
        'message' => $data['errorMessage'] ?? ($data['ResponseDescription'] ?? 'M-Pesa request failed.'),
    ];
}
