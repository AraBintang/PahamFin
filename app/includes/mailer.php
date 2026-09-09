<?php
require_once __DIR__ . '/config.php';

/**
 * Mengirim email menggunakan Socket SMTP Murni (tanpa PHPMailer).
 * Mendukung SSL (Port 465).
 */
function PahamFin_send_otp_email($toEmail, $otpCode) {
    $host = PahamFin_SMTP_HOST;
    $port = (int) PahamFin_SMTP_PORT;
    $user = PahamFin_SMTP_USER;
    $pass = PahamFin_SMTP_PASS;

    if (empty($pass)) {
        return false; // SMTP belum dikonfigurasi
    }

    $timeout = 10;
    // Koneksi SSL
    $socket = @fsockopen('ssl://' . $host, $port, $errno, $errstr, $timeout);
    
    if (!$socket) {
        return false;
    }

    // Membaca respon
    function read_smtp_response($socket) {
        $response = '';
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == ' ') break;
        }
        return $response;
    }

    // Mengirim perintah
    function send_smtp_command($socket, $command, $expected_code) {
        fwrite($socket, $command . "\r\n");
        $response = read_smtp_response($socket);
        if (substr($response, 0, 3) != $expected_code) {
            return false;
        }
        return true;
    }

    read_smtp_response($socket); // read greeting

    if (!send_smtp_command($socket, "EHLO " . $host, 250)) return false;
    if (!send_smtp_command($socket, "AUTH LOGIN", 334)) return false;
    if (!send_smtp_command($socket, base64_encode($user), 334)) return false;
    if (!send_smtp_command($socket, base64_encode($pass), 235)) return false; // 235 Authentication successful
    
    if (!send_smtp_command($socket, "MAIL FROM:<" . $user . ">", 250)) return false;
    if (!send_smtp_command($socket, "RCPT TO:<" . $toEmail . ">", 250)) return false;
    if (!send_smtp_command($socket, "DATA", 354)) return false;

    // Body Email
    $subject = "Kode OTP Registrasi PahamFin";
    $boundary = md5(time());
    
    $htmlContent = "
    <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f5;'>
        <div style='max-width: 500px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 10px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
            <h2 style='color: #0ea5e9; margin-top: 0;'>PahamFin</h2>
            <p style='color: #3f3f46; font-size: 16px;'>Terima kasih telah mendaftar di PahamFin. Untuk menyelesaikan proses pendaftaran, silakan masukkan kode OTP berikut:</p>
            <div style='margin: 30px 0;'>
                <span style='background-color: #f0f9ff; color: #0369a1; padding: 15px 25px; font-size: 32px; font-weight: bold; border-radius: 8px; letter-spacing: 5px;'>{$otpCode}</span>
            </div>
            <p style='color: #71717a; font-size: 14px;'>Kode ini berlaku selama 10 menit. Jika Anda tidak merasa mendaftar di PahamFin, abaikan email ini.</p>
        </div>
    </div>";

    $headers = "From: PahamFin <" . $user . ">\r\n";
    $headers .= "To: " . $toEmail . "\r\n";
    $headers .= "Subject: " . $subject . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $message = $headers . "\r\n" . $htmlContent . "\r\n.\r\n";

    if (!send_smtp_command($socket, $message, 250)) return false;
    send_smtp_command($socket, "QUIT", 221);
    fclose($socket);

    return true;
}
