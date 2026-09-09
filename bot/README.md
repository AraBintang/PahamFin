# PahamFin Bot

Bot untuk pencatatan keuangan otomatis via WhatsApp & Telegram.

> **Konfigurasi sentral:** File `includes/config.php` di root project adalah tempat
> utama mengatur API & tautan bot (username bot, nomor WA, base URL webhook,
> satuan nominal). Nilai yang bersifat rahasia (token Telegram) disimpan di
> `bot/.env` dan dibaca pada runtime.

## Cara Setup

### 1. Install Dependencies
```bash
cd bot
npm install
```

### 2. Buat File .env
Duplikat `.env.example` menjadi `.env`, lalu isi token & pastikan URL webhook benar:
```env
TELEGRAM_TOKEN=MASUKKAN_TOKEN_BOT_TELEGRAM_DISINI
WEBHOOK_URL=http://localhost/PahamFin/webhook.php
```

### 3. Setup Bot Telegram
1. Buka Telegram, cari **@BotFather**
2. Kirim `/newbot` → ikuti instruksi → salin **TOKEN** ke `.env`
3. Pastikan `PAHAMFIN_TELEGRAM_BOT_USERNAME` di `includes/config.php` = username bot kamu.

### 4. Menjalankan Bot Telegram
Bot Telegram memakai **long polling** ke API Telegram, jadi prosesnya harus **berjalan terus-menerus** (bukan sekali jalan). Jalankan dari folder `bot`:

```bash
cd bot
node telegram_bot.js
```

Pada startup, bot akan mencetak pesan seperti
`Connecting to Telegram... Telegram bot connected! (username: @Fin890Bot)`.
Jika berhasil, bot siap menerima perintah.

> **Catatan:** terminal harus tetap terbuka. Untuk berjalan terus tanpa harus menjaga
> terminal, gunakan salah satu opsi di bawah (Linux/Mac atau Windows):
>
> - **systemd (Linux server):** daftarkan unit service yang menjalankan `node telegram_bot.js`
> - **pm2:** `pm2 start telegram_bot.js --name pahamfin-tg`
> - **Windows:** jalankan di latar belakang / dengan `pm2` atau buat Task Scheduler
> - **Screen/Tmux:** `screen -S pahamfin` lalu `node telegram_bot.js`

#### Menghubungkan akun ke bot Telegram
1. Buka Telegram → chat dengan **PAHAMFIN_TELEGRAM_BOT_USERNAME** (mis. @Fin890Bot)
2. Kirim `/start` → bot membalas dengan **ID Telegram** kamu
3. Masuk ke Dashboard PahamFin → menu **Settings** → isi **ID Telegram** dengan angka itu
4. Setelah itu perintah seperti `/saldo`, `/laporan`, `/kategori`, `/riwayat` akan memakai data akunmu.

Perintah yang tersedia: `/start`, `/help`, `/saldo`, `/laporan`, `/kategori`, `/riwayat`.
Selain perintah, kamu juga bisa langsung mencatat transaksi, mis. `makan 50000`.

### 5. Menjalankan Bot WhatsApp
- Pastikan `WEBHOOK_URL` di `.env` benar (menunjuk ke `webhook.php` di server PahamFin)
- Jalankan bot, scan QR Code di terminal padatan muncul:

```bash
node app.js
```

### 5. Hubungkan ke Akun
- Login ke Dashboard → menu **Profil User**
- Isi **Nomor WhatsApp** dan **ID Telegram** Anda
- ID Telegram bisa didapat dengan kirim `/start` ke bot

---

## Format Pesan yang Didukung

| Format | Contoh |
|--------|--------|
| Kata + Angka | `makan 50000` |
| Kata + rb/ribu | `bensin 100rb` |
| Kata + k | `kopi 25k` |
| Kata + jt/juta/m | `gaji 5jt`, `bonus 2juta`, `gaji 3m` |
| Angka depan | `50000 makan siang` |
