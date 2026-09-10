<?php
/**
 * ============================================
 * PahamFin - Konfigurasi Sentral Bot & Aplikasi
 * ============================================
 * Semua pengaturan terkait bot (WhatsApp & Telegram),
 * API, dan tautan akses dikelola dari sini.
 *
 * IMPORTANT: Jangan commit file ini jika berisi token asli.
 * Gunakan variabel lingkungan (getenv) bila perlu.
 */

/* ------------------------------------------------------------------
 * TAUTAN APLIKASI (base URL web PahamFin)
 * ------------------------------------------------------------------ */
// Base URL aplikasi ini tanpa garis miring di akhir.
// Dipakai untuk membangun tautan webhook & dashboard di balasan bot.
define('PahamFin_BASE_URL', getenv('PahamFin_BASE_URL') ?: 'http://pahamfin.softwaremahasiswa.com');

// URL endpoint webhook yang menerima data dari bot (WA & Telegram).
define('PahamFin_WEBHOOK_URL', getenv('PahamFin_WEBHOOK_URL') ?: PahamFin_BASE_URL . '/webhook.php');

// URL dashboard untuk ditampilkan di pesan bot.
define('PahamFin_DASHBOARD_URL', getenv('PahamFin_DASHBOARD_URL') ?: PahamFin_BASE_URL);

// ----- URL per-modul (susunan folder terbaru) -----
// Halaman fitur berada di /pages, auth di /auth, endpoint di /api.
define('PahamFin_URL_PAGES', getenv('PahamFin_URL_PAGES') ?: PahamFin_BASE_URL . '/pages');
define('PahamFin_URL_AUTH',  getenv('PahamFin_URL_AUTH')  ?: PahamFin_BASE_URL . '/auth');
define('PahamFin_URL_API',   getenv('PahamFin_URL_API')   ?: PahamFin_BASE_URL . '/api');

// Tautan logo/favicon (absolut agar berfungsi dari folder mana pun).
define('PahamFin_URL_LOGO', getenv('PahamFin_URL_LOGO') ?: PahamFin_BASE_URL . '/image/icon.png');

/* ------------------------------------------------------------------
 * BOT WHATSAPP
 * ------------------------------------------------------------------ */
// Nomor "bot" WhatsApp yang menyala / menjadi tempat chat masuk.
// Format: 62xxxxxxxxxx (tanpa +, tanpa spasi).
//  - Dipakai pada tombol "Chat Bot WhatsApp" (wa.me/<nomor-bot>).
//  - Jika diisi '', tautan WA akan memakai nomor pada profil pengguna.
define('PahamFin_WA_BOT_NUMBER', getenv('PahamFin_WA_BOT_NUMBER') ?: '6287725776961');

// Nomor default yang dipakai bila nomor profil pengguna kosong.
define('PahamFin_WA_FALLBACK_NUMBER', getenv('PahamFin_WA_FALLBACK_NUMBER') ?: '6281234567890');

// Token Fonnte (Opsional - Jika menggunakan API WA Fonnte)
define('PahamFin_FONNTE_TOKEN', getenv('PahamFin_FONNTE_TOKEN') ?: 'Su8QwPKUsh88899CoCj2');

/* ------------------------------------------------------------------
 * BOT TELEGRAM
 * ------------------------------------------------------------------ */
// Token bot Telegram dari @BotFather (rahasia). Kosongkan jika memakai .env.
define('PahamFin_TELEGRAM_BOT_TOKEN', getenv('PahamFin_TELEGRAM_BOT_TOKEN') ?: '');

// Username bot Telegram (tanpa tanda @), contoh: 'PahamFinkuBot'.
// Dipakai pada tombol "Chat Bot Telegram" (t.me/<username>).
define('PahamFin_TELEGRAM_BOT_USERNAME', getenv('PahamFin_TELEGRAM_BOT_USERNAME') ?: 'Fin890Bot');

// ID/admin Telegram yang berhak menerima notifikasi (opsional).
define('PahamFin_TELEGRAM_ADMIN_ID', getenv('PahamFin_TELEGRAM_ADMIN_ID') ?: '');

/* ------------------------------------------------------------------
 * KEAMANAN WEBHOOK
 * ------------------------------------------------------------------ */
// Kunci rahasia bersama untuk otentikasi panggilan dari bot ke webhook.
// Bot mengirim header `X-PahamFin-Key`; webhook menolak bila tidak cocok.
define('PahamFin_WEBHOOK_SECRET', getenv('PahamFin_WEBHOOK_SECRET') ?: 'UbahIniDenganKunciRahasiaPanjang');

/* ------------------------------------------------------------------
 * DIGITALOCEAN — BOT SERVER
 * ------------------------------------------------------------------ */
// IP / domain server DigitalOcean tempat bot Node.js berjalan.
// Dipakai jika kamu menjalankan bot di DO Droplet terpisah dari Hostinger.
// Kosongkan jika bot dan web masih di server yang sama.
define('PahamFin_DO_BOT_IP',     getenv('PahamFin_DO_BOT_IP')     ?: '');

// Token API DigitalOcean (dari cloud.digitalocean.com/account/api/tokens).
// Dipakai untuk keperluan manajemen server lewat DO API (opsional).
define('PahamFin_DO_API_TOKEN',  getenv('PahamFin_DO_API_TOKEN')  ?: '');

// URL webhook PHP yang dikirim ke bot di DO.
// Otomatis menggunakan PahamFin_BASE_URL jika tidak diset sendiri.
// Contoh: 'https://pahamfin.softwaremahasiswa.com/api/webhook.php'
define('PahamFin_DO_WEBHOOK_URL', getenv('PahamFin_DO_WEBHOOK_URL') ?: PahamFin_BASE_URL . '/api/webhook.php');

/* ------------------------------------------------------------------
 * LOGIN DENGAN GOOGLE (OAuth 2.0)
 * ------------------------------------------------------------------ */
// Isi Client ID & Client Secret dari Google Cloud Console
// (https://console.cloud.google.com/apis/credentials).
// Kosongkan bila belum pakai login Google.
define('PahamFin_GOOGLE_CLIENT_ID', getenv('PahamFin_GOOGLE_CLIENT_ID') ?: '');
define('PahamFin_GOOGLE_CLIENT_SECRET', getenv('PahamFin_GOOGLE_CLIENT_SECRET') ?: '');

// Redirect URI yang didaftarkan di Google (harus persis sama).
define('PahamFin_GOOGLE_REDIRECT_URI', getenv('PahamFin_GOOGLE_REDIRECT_URI') ?: PahamFin_BASE_URL . '/auth/google-auth.php');

/* ------------------------------------------------------------------
 * ADMIN
 * ------------------------------------------------------------------ */
// Daftar email yang otomatis berstatus ADMIN saat login / daftar.
// Dipisah dengan tanda koma, contoh: 'admin@PahamFin.dev,bos@PahamFin.dev'.
// Pengguna yang sudah berstatus admin (role = 'admin') tetap admin meskipun
// emailnya tidak ada di daftar ini. Untuk admin baru cukup tambahkan
// emailnya di sini lalu minta dia login.
define('PahamFin_ADMIN_EMAILS', getenv('PahamFin_ADMIN_EMAILS') ?: 'arabintangpamungkas123@gmail.com');

/* ------------------------------------------------------------------
 * PARSING NOMINAL
 * ------------------------------------------------------------------ */
// Satuan / pengali yang dikenali saat mem-parsing nominal dari chat
// (contoh: "100rb" -> 100.000, "5jt" -> 5.000.000).
$GLOBALS['PahamFin_AMOUNT_UNITS'] = [
    'm'  => 1000000,
    'jt' => 1000000,
    'juta' => 1000000,
    'rb' => 1000,
    'ribu' => 1000,
    'k'  => 1000,
];

/* ------------------------------------------------------------------
 * PENGATURAN EMAIL SMTP (Untuk OTP)
 * ------------------------------------------------------------------ */
// Gunakan akun Gmail Anda. Untuk password, Anda HARUS membuat 
// "Sandi Aplikasi" (App Password) di pengaturan Keamanan Akun Google Anda,
// BUKAN menggunakan password login Gmail biasa.
define('PahamFin_SMTP_HOST', getenv('PahamFin_SMTP_HOST') ?: 'smtp.gmail.com');
define('PahamFin_SMTP_PORT', getenv('PahamFin_SMTP_PORT') ?: 465); // 465 untuk SSL, 587 untuk TLS
define('PahamFin_SMTP_USER', getenv('PahamFin_SMTP_USER') ?: 'bintangara157@gmail.com');
define('PahamFin_SMTP_PASS', getenv('PahamFin_SMTP_PASS') ?: 'eles mcsj bffj cbvj'); // Isi dengan 16 digit Sandi Aplikasi

/* ------------------------------------------------------------------
 * UTILITAS
 * ------------------------------------------------------------------ */

// Membangun tautan WhatsApp ke nomor tertentu.
function PahamFin_wa_link(?string $phoneNumber): string
{
    $number = preg_replace('/[^0-9]/', '', (string) $phoneNumber);
    if ($number === '') {
        $number = preg_replace('/[^0-9]/', '', PahamFin_WA_FALLBACK_NUMBER);
    }
    if ($number === '') {
        return 'settings.php';
    }
    return 'https://wa.me/' . $number;
}

// Membangun tautan Telegram ke username bot.
function PahamFin_tg_link(): string
{
    $username = trim(PahamFin_TELEGRAM_BOT_USERNAME);
    if ($username === '') {
        return 'settings.php';
    }
    return 'https://t.me/' . $username;
}
