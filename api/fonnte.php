<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/includes/config.php';

// Membaca payload dari webhook Fonnte
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    $data = $_POST;
}

$sender = $data['sender'] ?? ''; // Nomor pengirim
$message = $data['message'] ?? ''; // Isi pesan teks

// Pastikan pesan valid
if (!$sender || !$message) {
    echo json_encode(['status' => false, 'reason' => 'Invalid data. Sender or message missing.']);
    exit;
}

// 1. Teruskan pesan ke logika utama PahamFin melalui api/webhook.php secara internal
$ch = curl_init(PahamFin_BASE_URL . '/api/webhook.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'phone' => $sender,
    'message' => $message,
    'sender' => $data['name'] ?? ''
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-PahamFin-Key: ' . PahamFin_webhook_secret()
]);
$resultJson = curl_exec($ch);
curl_close($ch);

$resultData = json_decode($resultJson, true);
$replyText = $resultData['message'] ?? 'Terjadi kesalahan sistem saat memproses pesan.';

// 2. Kirim balasan kembali melalui API Fonnte
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://api.fonnte.com/send',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => array(
        'target' => $sender,
        'message' => $replyText,
        'typing' => false,
        'delay' => '1',
    ),
    CURLOPT_HTTPHEADER => array(
        'Authorization: ' . PahamFin_FONNTE_TOKEN
    ),
));

$response = curl_exec($curl);
curl_close($curl);

echo json_encode([
    'status' => true, 
    'message' => 'Processed via PahamFin Webhook',
    'fonnte_response' => json_decode($response, true)
]);
