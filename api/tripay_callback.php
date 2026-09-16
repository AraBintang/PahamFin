<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';
require_once __DIR__ . '/../app/includes/tripay.php';

header('Content-Type: application/json');

// Ambil raw input & callback signature dari Tripay
$rawInput = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_X_CALLBACK_SIGNATURE'] ?? '';

if (!$rawInput || !$signatureHeader) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Callback Payload']);
    exit;
}

// Verifikasi Signature HMAC SHA256 dari Tripay
$privateKey = PahamFin_TRIPAY_PRIVATE_KEY;
$localSignature = hash_hmac('sha256', $rawInput, $privateKey);

if (!hash_equals($localSignature, $signatureHeader)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid Signature']);
    exit;
}

$data = json_decode($rawInput, true);
$event = $_SERVER['HTTP_X_CALLBACK_EVENT'] ?? '';

if ($data && isset($data['status']) && $data['status'] === 'PAID') {
    $merchantRef = (string) ($data['merchant_ref'] ?? '');
    
    // Format merchant_ref: SUB-{userId}-{planId}-{timestamp}
    if (preg_match('/^SUB-(\d+)-(\d+)-/', $merchantRef, $matches)) {
        $userId = (int) $matches[1];
        $planId = (int) $matches[2];
        $payMethod = (string) ($data['payment_method'] ?? 'TRIPAY');

        $result = PahamFin_activate_user_subscription($pdo, $userId, $planId, 'TRIPAY_' . $payMethod);

        // Kirim notifikasi ke dashboard user
        try {
            $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'payment', ?)")
                ->execute([$userId, "🎉 Pembayaran langganan Tripay berhasil! Paket Anda telah otomatis aktif."]);
        } catch (Throwable $e) {}

        echo json_encode(['success' => true, 'message' => 'Payment Processed & Subscription Activated']);
        exit;
    }
}

echo json_encode(['success' => true, 'message' => 'Callback Received']);
