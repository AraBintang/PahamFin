<?php
require_once __DIR__ . '/config.php';

/**
 * Tripay API Helper Class / Functions
 */

function PahamFin_tripay_api_url(string $endpoint = ''): string
{
    $baseUrl = PahamFin_TRIPAY_IS_PRODUCTION 
        ? 'https://tripay.co.id/api/' 
        : 'https://tripay.co.id/api-sandbox/';
    return $baseUrl . ltrim($endpoint, '/');
}

function PahamFin_tripay_headers(): array
{
    return [
        'Authorization: Bearer ' . PahamFin_TRIPAY_API_KEY,
        'Content-Type: application/json',
    ];
}

/**
 * Ambil daftar Payment Channel dari Tripay (QRIS, BRIVA, MANDIRIVA, BCAVA, dll)
 */
function PahamFin_tripay_get_channels(): array
{
    $url = PahamFin_tripay_api_url('merchant/payment-channel');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, PahamFin_tripay_headers());
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || !$res) {
        return [];
    }

    $data = json_decode($res, true);
    return ($data && isset($data['data'])) ? $data['data'] : [];
}

/**
 * Buat transaksi pembayaran baru di Tripay
 */
function PahamFin_tripay_create_transaction(string $merchantRef, float $amount, string $method, array $user, array $plan): array
{
    $merchantCode = PahamFin_TRIPAY_MERCHANT_CODE;
    $privateKey   = PahamFin_TRIPAY_PRIVATE_KEY;
    $amtInt       = (int) round($amount);

    // Signature Tripay: SHA256 HMAC (merchant_code + merchant_ref + amount)
    $signature = hash_hmac('sha256', $merchantCode . $merchantRef . $amtInt, $privateKey);

    $payload = [
        'method'         => $method,
        'merchant_ref'   => $merchantRef,
        'amount'         => $amtInt,
        'customer_name'  => $user['name'] ?? 'Pengguna PahamFin',
        'customer_email' => $user['email'] ?? 'user@pahamfin.com',
        'customer_phone' => $user['phone_number'] ?? '081234567890',
        'order_items'    => [
            [
                'sku'      => 'PLAN-' . $plan['id'],
                'name'     => 'Langganan PahamFin - ' . $plan['name'],
                'price'    => $amtInt,
                'quantity' => 1,
            ]
        ],
        'callback_url'   => PahamFin_BASE_URL . '/api/tripay_callback.php',
        'return_url'     => PahamFin_BASE_URL . '/pages/pricing.php',
        'expired_time'   => (time() + (24 * 3600)), // 24 jam
        'signature'      => $signature,
    ];

    $url = PahamFin_tripay_api_url('transaction/create');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, PahamFin_tripay_headers());
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['success' => false, 'message' => 'Gagal menghubungi Tripay: ' . $err];
    }

    $result = json_decode($res, true);
    if ($result && isset($result['success']) && $result['success'] && isset($result['data'])) {
        return [
            'success' => true,
            'data'    => $result['data']
        ];
    }

    $msg = $result['message'] ?? 'Gagal membuat transaksi Tripay.';
    return ['success' => false, 'message' => $msg];
}
