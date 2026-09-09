<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/includes/config.php';

// Simulate POST to register.php logic
$name = 'Test User';
$email = 'test@example.com';
$password = '123456';
$hash = password_hash($password, PASSWORD_DEFAULT);
$otp = (string) random_int(100000, 999999);

$_SESSION['pending_reg'] = [
    'name' => $name,
    'email' => $email,
    'password' => $hash,
    'otp' => $otp,
    'expires' => time() + 600
];

echo "Simulated Registration. OTP is: $otp\n";

// Simulate verify-otp.php POST
$pending = $_SESSION['pending_reg'];
$inputOtp = $otp; // correct OTP

if ($inputOtp === $pending['otp']) {
    echo "OTP Matched!\n";
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    if ($stmt->execute([$pending['name'], $pending['email'], $pending['password']])) {
        echo "User inserted successfully!\n";
        $newId = $pdo->lastInsertId();
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$newId]);
        echo "Test user cleaned up.\n";
    } else {
        echo "Failed to insert user.\n";
    }
} else {
    echo "OTP Mismatch! Input: '$inputOtp', Expected: '{$pending['otp']}'\n";
}
