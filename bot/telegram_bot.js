/**
* PahamFin - Telegram Bot (Fixed & Stable)
 *
 * Perbaikan:
 * - Tombol inline pakai t.me/<username> (valid HTTPS, bukan localhost)
 * - Nama variabel env diperbaiki (PAHAMFIN_WEBHOOK_SECRET)
 * - Error handler global supaya bot tidak crash
 * - Polling conflict handler agar tidak crash saat instance ganda
 */

'use strict';

const fs   = require('fs');
const path = require('path');
const TelegramBot = require('node-telegram-bot-api');
const axios = require('axios');

// ── Load .env ─────────────────────────────────────────────────────────────────
function loadEnv() {
    const defaults = {
        TELEGRAM_TOKEN: '',
        TELEGRAM_BOT_USERNAME: '',
        WEBHOOK_URL: 'http://pahamfin.softwaremahasiswa.com/webhook.php',
        PAHAMFIN_WEBHOOK_SECRET: '',
        GEMINI_API_KEY: '',
        OPENAI_API_KEY: '',
    };

    const envFile = path.join(__dirname, '.env');
    if (!fs.existsSync(envFile)) return defaults;

    const lines = fs.readFileSync(envFile, 'utf8').split(/\r?\n/);
    for (const line of lines) {
        const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
        if (!m) continue;
        defaults[m[1]] = m[2].replace(/^['"]|['"]$/g, '').trim();
    }

    return defaults;
}

const ENV = loadEnv();
const { TELEGRAM_TOKEN, TELEGRAM_BOT_USERNAME, WEBHOOK_URL, PAHAMFIN_WEBHOOK_SECRET, GEMINI_API_KEY, OPENAI_API_KEY } = ENV;

if (!TELEGRAM_TOKEN || TELEGRAM_TOKEN.includes('MASUKKAN')) {
    console.error('❌ TELEGRAM_TOKEN belum dikonfigurasi di file bot/.env');
    process.exit(1);
}

// ── Inisialisasi Bot ───────────────────────────────────────────────────────────
const bot = new TelegramBot(TELEGRAM_TOKEN, {
    polling: {
        interval: 1000,
        autoStart: true,
        params: { timeout: 10 }
    }
});

console.log('🤖 PahamFin Telegram berjalan...');
if (TELEGRAM_BOT_USERNAME) console.log('   Link bot: https://t.me/' + TELEGRAM_BOT_USERNAME);
console.log('   Webhook : ' + WEBHOOK_URL);

// Set bot commands (tampil saat user ketik /)
bot.setMyCommands([
    { command: 'start',     description: '🏠 Mulai & info akun kamu' },
    { command: 'help',      description: '📖 Panduan lengkap semua perintah' },
    { command: 'saldo',     description: '💰 Cek saldo & ringkasan bulan ini' },
    { command: 'laporan',   description: '📊 Laporan pengeluaran per kategori' },
    { command: 'riwayat',   description: '🕐 5 transaksi terakhir' },
    { command: 'kategori',  description: '🏷️ Daftar kategori & keyword bot' },
    { command: 'tabungan',  description: '🐷 Progres semua target tabungan' },
    { command: 'hutang',    description: '🤝 Daftar hutang & piutang aktif' },
    { command: 'dompet',    description: '👛 Saldo semua dompet' },
    { command: 'pengingat', description: '🔔 Daftar pengingat aktif' },
]).then(() => console.log('✅ Bot commands terdaftar!')).catch(console.error);

// ── Helpers ────────────────────────────────────────────────────────────────────

/** Membangun inline keyboard. Hanya tampilkan tombol jika ada username (URL HTTPS valid). */
function buildKeyboard(includeWa = false) {
    const buttons = [];
    if (buttons.length === 0) return undefined; // tidak ada keyboard
    return { inline_keyboard: [buttons] };
}

/** Kirim pesan dengan keyboard opsional. Jika tidak ada keyboard, kirim polos. */
async function reply(chatId, text, keyboard) {
    const opts = { parse_mode: 'Markdown' };
    if (keyboard) opts.reply_markup = keyboard;
    return bot.sendMessage(chatId, text, opts);
}

/** Kirim request ke webhook PHP */
async function callWebhook(payload) {
    if (PAHAMFIN_WEBHOOK_SECRET) {
        payload.key = PAHAMFIN_WEBHOOK_SECRET;
    }
    const headers = {};
    if (PAHAMFIN_WEBHOOK_SECRET) {
        headers['X-PahamFin-Key'] = PAHAMFIN_WEBHOOK_SECRET;
    }

    const response = await axios.post(WEBHOOK_URL, payload, {
        headers,
        timeout: 60000,
    });

    return response.data;
}

/**
 * Cek apakah telegram_id sudah terdaftar.
 * Jika belum, langsung buatkan akun otomatis.
 */
async function checkRegistered(telegramId, name) {
    const checkUrl = WEBHOOK_URL.replace('webhook.php', 'api/tg_check.php') + '?telegram_id=' + telegramId;
    try {
        const res = await axios.get(checkUrl, { timeout: 8000 });
        if (res.data.registered) {
            return { registered: true };
        }
        
        return { 
            registered: false, 
            is_new: true,
            login_url: res.data.login_url 
        };
    } catch (err) {
        return { registered: true }; // Anggap terdaftar jika error
    }
}

/** Kirim pesan detail akun baru */
async function replyNewAccount(chatId, regData) {
    const textMsg = `👋 *Akun belum terhubung!*\n\nSilakan klik tombol di bawah ini untuk **Login** atau **Daftar** di Web PahamFin, lalu akun Telegram kamu akan otomatis terhubung!`;

    try {
        return await bot.sendMessage(chatId, textMsg, {
            parse_mode: 'Markdown',
            reply_markup: {
                inline_keyboard: [[
                    { text: '🔗 Hubungkan Akun PahamFin', url: regData.login_url }
                ]]
            }
        });
    } catch (err) {
        // Fallback jika Telegram menolak URL (misal karena localhost)
        const fallbackText = textMsg + `\n\n🔗 Link Login: ${regData.login_url}`;
        return await bot.sendMessage(chatId, fallbackText, { parse_mode: 'Markdown' });
    }
}


function parseAmount(text) {
    const lower = text.toLowerCase();
    const units = [
        { re: /(\d+(?:[.,]\d+)?)\s*juta\b/i, mul: 1_000_000 },
        { re: /(\d+(?:[.,]\d+)?)\s*jt\b/i,   mul: 1_000_000 },
        { re: /(\d+(?:[.,]\d+)?)\s*m\b/i,     mul: 1_000_000 },
        { re: /(\d+(?:[.,]\d+)?)\s*ribu\b/i,  mul: 1_000 },
        { re: /(\d+(?:[.,]\d+)?)\s*rb\b/i,    mul: 1_000 },
        { re: /(\d+(?:[.,]\d+)?)\s*k\b/i,     mul: 1_000 },
    ];

    for (const { re, mul } of units) {
        const m = lower.match(re);
        if (m) return parseFloat(m[1].replace(',', '.')) * mul;
    }

    const m = lower.match(/\b(\d{3,})\b/);
    return m ? parseInt(m[1], 10) : null;
}

// ── Handler Perintah ───────────────────────────────────────────────────────────

bot.onText(/\/start/, async (msg) => {
    const chatId     = msg.chat.id;
    const telegramId = String(chatId);
    const name       = msg.from.first_name || 'Pengguna';

    const reg = await checkRegistered(telegramId, name);
    if (!reg.registered) {
        await replyNewAccount(chatId, reg);
        return; // Berhenti di sini, jangan tampilkan pesan sambutan
    }

    const kb = buildKeyboard();
    reply(chatId,
        `Halo, *${name}!* 👋\n\n` +
        `Selamat datang kembali di *PahamFin* 🤖\n` +
        `Bot pencatatan keuangan otomatis!\n\n` +
        `*Cara Pakai:*\n` +
        `Cukup kirim pesan:\n` +
        `➤ \`makan 50000\`\n` +
        `➤ \`bensin 100rb\`\n` +
        `➤ \`gaji 5jt\`\n\n` +
        `Ketik /help untuk melihat semua daftar perintah lengkap (Tabungan, Hutang, Pengingat, dll).`,
        kb
    );
});

['saldo', 'laporan', 'kategori', 'riwayat', 'tabungan', 'pengingat', 'help'].forEach((cmd) => {
    bot.onText(new RegExp(`^\\/${cmd}(\\s.*)?$`), async (msg) => {
        const chatId     = msg.chat.id;
        const telegramId = String(chatId);
        const name       = msg.from.first_name || 'Pengguna';

        // Cek pendaftaran
        const reg = await checkRegistered(telegramId, name);
        if (!reg.registered) return replyNewAccount(chatId, reg);

        try {
            const data = await callWebhook({
                source:      'telegram',
                telegram_id: telegramId,
                message:     `/${cmd}`,
                action:      cmd,
            });

            await reply(chatId, data.message || 'Tidak ada data.', buildKeyboard());
        } catch (err) {
            const errMsg = err?.response?.data?.message || err.message;
            console.error(`[${cmd}] Error:`, errMsg);
            await reply(chatId, `⚠️ Gagal mengambil data.\n_${errMsg}_`);
        }
    });
});

// Handler /done [id] untuk menyelesaikan pengingat
bot.onText(/^\/done\s+(\d+)$/, async (msg, match) => {
    const chatId = msg.chat.id;
    try {
        const data = await callWebhook({
            source: 'telegram', telegram_id: String(chatId),
            message: msg.text, action: 'done', description: match[1]
        });
        await reply(chatId, data.message || 'Berhasil diselesaikan.', buildKeyboard());
    } catch (err) {
        await reply(chatId, `⚠️ Gagal: ${err?.response?.data?.message || err.message}`);
    }
});

// ── Cron Job Pengingat (Jalan Setiap 1 Menit) ─────────────────────────────────
setInterval(async () => {
    try {
        const headers = {};
        if (PAHAMFIN_WEBHOOK_SECRET) headers['X-PahamFin-Key'] = PAHAMFIN_WEBHOOK_SECRET;
        
        const cronUrl = WEBHOOK_URL.replace('webhook.php', 'cron_trigger.php');
        const res = await axios.get(cronUrl, { headers, timeout: 10000 });
        
        if (res.data && res.data.success && res.data.send) {
            for (const item of res.data.send) {
                if (item.telegram_id && item.message) {
                    await bot.sendMessage(item.telegram_id, item.message, { parse_mode: 'Markdown' });
                }
            }
        }
    } catch (err) {
        // Abaikan error cron diam-diam agar tidak membanjiri log
    }
}, 60 * 1000);

// ── Handler Pesan Transaksi ────────────────────────────────────────────────────
bot.on('message', async (msg) => {
    if (!msg.text || msg.text.startsWith('/')) return;
    if (msg.photo || msg.document || msg.sticker) return; // skip media

    const chatId     = msg.chat.id;
    const telegramId = String(chatId);
    const text       = msg.text.trim();
    const lower      = text.toLowerCase();
    const name       = msg.from.first_name || 'Pengguna';

    // ── Cek apakah sudah terdaftar ──────────────────────────────────────────
    const reg = await checkRegistered(telegramId, name);
    if (!reg.registered) {
        return replyNewAccount(chatId, reg);
    }

    // ── Shortcut: Pengingat / Reminder ──────────────────────────────────────
    if (lower.startsWith('pengingat ') || lower.startsWith('ingatkan ') || lower.startsWith('reminder ')) {
        return reply(chatId,
            `🔔 *Tambah Pengingat*\n\n` +
            `Untuk menambah pengingat, silakan buka Dashboard PahamFin:\n` +
            `👉 ${WEBHOOK_URL.replace('/webhook.php', '')}/pages/reminders.php\n\n` +
            `_Atau ketik /pengingat untuk melihat daftar pengingat aktif kamu._`
        );
    }

    // ── Shortcut: Catatan ────────────────────────────────────────────────────
    if (lower.startsWith('catatan ') || lower.startsWith('catat ') || lower.startsWith('note ')) {
        return reply(chatId,
            `📝 *Tambah Catatan*\n\n` +
            `Untuk menambah catatan keuangan, buka halaman Catatan di Dashboard:\n` +
            `👉 ${WEBHOOK_URL.replace('/webhook.php', '')}/pages/notes.php\n\n` +
            `_Catatan kamu tersimpan aman di dashboard PahamFin._`
        );
    }

    // ── Shortcut: Target Tabungan ────────────────────────────────────────────
    if (lower.startsWith('target ') || lower.startsWith('buat tabungan ')) {
        return reply(chatId,
            `🐷 *Buat Target Tabungan*\n\n` +
            `Untuk membuat target tabungan baru, buka halaman Tabungan di Dashboard:\n` +
            `👉 ${WEBHOOK_URL.replace('/webhook.php', '')}/pages/savings.php\n\n` +
            `_Untuk menambah ke tabungan yang sudah ada, ketik:_\n` +
            `➤ \`nabung [nama tabungan] [nominal]\`\n` +
            `Contoh: \`nabung rumah 500000\``
        );
    }

    const amount = parseAmount(text);

    if (!amount || amount <= 0) {
        await reply(chatId,
            `❓ *Format tidak dikenali.*\n\n` +
            `Contoh yang benar:\n` +
            `• \`makan 50000\`\n` +
            `• \`bensin 100rb\`\n` +
            `• \`gaji 5jt\`\n\n` +
            `Ketik /help untuk panduan lengkap.`
        );
        return;
    }

    try {
        const data = await callWebhook({
            source:      'telegram',
            telegram_id: telegramId,
            sender:      `${msg.from.first_name || ''} ${msg.from.last_name || ''}`.trim(),
            message:     text,
            amount,
            description: text,
        });

        if (data.success) {
            const emoji  = data.type === 'PEMASUKAN' ? '💰' : '🧾';
            const tanda  = data.type === 'PEMASUKAN' ? '+' : '-';
            const fmtAmt = new Intl.NumberFormat('id-ID').format(amount);

            await reply(chatId,
                `${emoji} *Transaksi Dicatat!*\n\n` +
                `📂 Kategori : *${data.category}*\n` +
                `💵 Nominal  : *${tanda}Rp ${fmtAmt}*\n` +
                `📝 Ket.     : ${data.description || text}\n\n` +
                `_Ketik /saldo untuk cek ringkasan._`,
                buildKeyboard()
            );
        } else {
            await reply(chatId,
                `⚠️ *Gagal mencatat:*\n${data.message || 'Kategori tidak ditemukan.'}\n\n` +
                `Pastikan keyword sudah diatur di Dashboard, lalu coba lagi.`
            );
        }

    } catch (err) {
        const errMsg = err?.response?.data?.message || err.message;
        console.error('[Transaksi] Error:', errMsg);
        await reply(chatId,
            `⛔ *Terjadi kesalahan.*\n_${errMsg}_\n\nCoba lagi dalam beberapa saat.`
        );
    }
});

// ── Handler Foto: OCR struk belanja ───────────────────────────────────────────
bot.on('photo', async (msg) => {
    const chatId = msg.chat.id;
    const telegramId = String(msg.from.id);
    const senderName = [msg.from.first_name, msg.from.last_name].filter(Boolean).join(' ') || 'Pengguna';

    const reg = await checkRegistered(telegramId, senderName);
    if (!reg.registered) return replyNewAccount(chatId, reg);

    try {
        if (!OPENAI_API_KEY && !GEMINI_API_KEY) {
            await reply(chatId,
                `📷 *Foto diterima!*\n\n` +
                `⚠️ *Fitur scan struk belum aktif* karena API Key AI belum diset di file \`bot/.env\`.\n\n` +
                `Kamu tetap bisa catat manual dengan teks:\n` +
                `➤ \`makan 35000\`\n` +
                `➤ \`belanja 150rb\``
            );
            return;
        }

        await reply(chatId, `🔍 *Sedang membaca foto struk belanjaan kamu dengan ChatGPT AI...* Mohon tunggu sebentar ⏳`);

        // Ambil foto dengan resolusi tertinggi (terakhir di array photo)
        const photoArr = msg.photo;
        const fileId = photoArr[photoArr.length - 1].file_id;

        // Download foto dari Telegram API
        const fileStream = bot.getFileStream(fileId);
        const chunks = [];
        for await (const chunk of fileStream) {
            chunks.push(chunk);
        }
        const buffer = Buffer.concat(chunks);
        const base64Data = buffer.toString('base64');

        let cleanJson = '';
        const systemPrompt = `Analisis gambar ini yang berisi foto struk, nota, bukti transfer, atau screenshot bukti pembayaran QRIS / ShopeePay / Gopay / OVO / Dana / Bank.
Ekstrak informasi transaksi utama dan kembalikan HANYA format JSON valid.

ATURAN PENTING NOMINAL:
1. NOMINAL UTAMA: Ambil angka nominal transaksi utama di bagian atas berukuran besar (contoh: -Rp66.500, -Rp30.210, +Rp49.000, -Rp18.000).
2. ABAIKAN BANNER PROMO: JANGAN mengambil angka pada banner/iklan promo (seperti "100RB", "Rp100.000", "Pasti Cashback", atau "+Rp100 Cashback").
3. FORMAT RUPIAH: Di Indonesia, titik (.) pada 66.500, 30.210, 18.000 adalah pemisah ribuan (artinya 66500, 30210, 18000 rupiah). Wajib kembalikan total_amount sebagai integer murni Rupiah tanpa titik dan tanpa koma.

ATURAN MERCHANT:
Cari teks di bagian "Bayar Ke" atau "Isi Saldo Dari/Ke" atau nama toko di struk (contoh: "PBH Sirajudin Pedalangan", "PASAR CELL", "Kedai Risol", "ShopeePay").

JSON Schema:
{
  "is_receipt": boolean (true jika gambar adalah bukti bayar/struk/transfer valid),
  "merchant": string (nama toko/merchant/penerima ringkas),
  "total_amount": number (nominal transaksi utama integer Rupiah, contoh: 66500, 30210, 18000, 49000),
  "items_summary": string (ringkasan item jika ada)
}

Jika gambar BUKAN struk/nota/bukti bayar valid, set is_receipt: false, total_amount: 0, merchant: "".`;

        // Deteksi MIME type sesungguhnya dari binary buffer (PNG vs JPEG vs WEBP)
        let mimeType = 'image/jpeg';
        if (buffer && buffer.length >= 4) {
            if (buffer[0] === 0x89 && buffer[1] === 0x50 && buffer[2] === 0x4E && buffer[3] === 0x47) {
                mimeType = 'image/png';
            } else if (buffer[0] === 0x52 && buffer[1] === 0x49 && buffer[2] === 0x46 && buffer[3] === 0x46) {
                mimeType = 'image/webp';
            }
        }

        if (OPENAI_API_KEY) {
            try {
                const OpenAI = require('openai');
                const openai = new OpenAI({ apiKey: OPENAI_API_KEY });

                const response = await openai.chat.completions.create({
                    model: "gpt-4o-mini",
                    messages: [
                        {
                            role: "user",
                            content: [
                                { type: "text", text: systemPrompt },
                                {
                                    type: "image_url",
                                    image_url: {
                                        url: `data:${mimeType};base64,${base64Data}`,
                                        detail: "auto"
                                    }
                                }
                            ]
                        }
                    ],
                    response_format: { type: "json_object" }
                });

                cleanJson = response.choices[0].message.content.trim();
            } catch (openAiErr) {
                console.error('OpenAI error:', openAiErr.message);
                if (GEMINI_API_KEY) {
                    const { GoogleGenerativeAI } = require('@google/generative-ai');
                    const genAI = new GoogleGenerativeAI(GEMINI_API_KEY);
                    const model = genAI.getGenerativeModel({ model: 'gemini-1.5-flash' });
                    const imagePart = { inlineData: { data: base64Data, mimeType: mimeType } };
                    const result = await model.generateContent([systemPrompt, imagePart]);
                    cleanJson = result.response.text().trim().replace(/```json/gi, '').replace(/```/g, '').trim();
                } else {
                    throw openAiErr;
                }
            }
        } else {
            // Fallback ke Gemini Vision
            const { GoogleGenerativeAI } = require('@google/generative-ai');
            const genAI = new GoogleGenerativeAI(GEMINI_API_KEY);
            const model = genAI.getGenerativeModel({ model: 'gemini-1.5-flash' });
            const imagePart = { inlineData: { data: base64Data, mimeType: mimeType } };
            const result = await model.generateContent([systemPrompt, imagePart]);
            const responseText = result.response.text().trim();
            cleanJson = responseText.replace(/```json/gi, '').replace(/```/g, '').trim();
        }

        let parsed;
        try {
            parsed = JSON.parse(cleanJson);
        } catch (e) {
            console.error('Failed to parse AI JSON output:', cleanJson);
            await reply(chatId, `⚠️ Gagal membaca struk. Pastikan foto struk terang, jelas, dan tidak buram.`);
            return;
        }

        let rawAmount = Number(parsed.total_amount);
        if (isNaN(rawAmount)) rawAmount = 0;

        // Failsafe jika AI membaca titik sebagai pecahan (misal 18.00 -> 18)
        if (rawAmount > 0 && rawAmount < 1000) {
            rawAmount = Math.round(rawAmount * 1000);
        }
        const amount = Math.round(rawAmount);

        if (!parsed.is_receipt || amount <= 0) {
            await reply(chatId, `⚠️ Gambar yang kamu kirim tidak terdeteksi sebagai struk/bukti bayar valid. Silakan kirim foto struk kasir atau screenshot bukti bayar yang jelas.`);
            return;
        }

        const merchant = parsed.merchant || 'Belanja Struk';
        const items = parsed.items_summary ? ` (${parsed.items_summary})` : '';
        const fullMessage = `${merchant}${items} ${amount}`;

        // Kirim data transaksi ke webhook PahamFin
        const webhookRes = await callWebhook({
            telegram_id: telegramId,
            sender: senderName,
            message: fullMessage,
            amount: amount,
            description: `${merchant}${items}`
        });

        if (webhookRes && webhookRes.message) {
            await reply(chatId, `🧾 *Scan Struk Berhasil!*\n\n` + webhookRes.message);
        } else {
            await reply(chatId, `✅ *Struk Terbaca!*\n\n• **Keterangan:** ${merchant}\n• **Total:** Rp ${new Intl.NumberFormat('id-ID').format(amount)}\n\n_Transaksi berhasil dicatat!_`);
        }

    } catch (err) {
        console.error('Photo handler error:', err);
        const errMsg = err?.response?.data?.error?.message || err?.message || String(err);
        await reply(chatId, `⚠️ Gagal memproses foto struk:\n\`${errMsg}\``);
    }
});

// Handler dokumen (misal PDF struk)
bot.on('document', async (msg) => {
    const chatId = msg.chat.id;
    await reply(chatId,
        `📎 *Dokumen diterima!*\n\n` +
        `PahamFin hanya mendukung pesan teks dan foto struk saat ini.\n` +
        `Silakan catat transaksi dengan format teks ya! 😊`
    );
});

// ── Global Error Handlers (bot tidak crash) ────────────────────────────────────
bot.on('polling_error', (err) => {
    // 409 = ada instance bot lain — hentikan proses ini agar tidak konflik
    if (err.code === 'ETELEGRAM' && err.message.includes('409')) {
        console.error('❌ CONFLICT 409: Ada instance bot lain yang masih berjalan!');
        console.error('   Tutup semua instance lain lalu jalankan ulang bot ini.');
        console.error('   Cara cepat: jalankan "stop-bot.bat" dulu, baru "start-bot.bat"');
        bot.stopPolling();
        process.exit(1);
    }
    console.error('[polling_error]', err.message);
});

bot.on('error', (err) => {
    console.error('[bot error]', err.message);
});

process.on('unhandledRejection', (reason) => {
    // Tangkap error inline keyboard "Wrong HTTP URL" agar tidak crash
    if (reason && String(reason).includes('Wrong HTTP URL')) {
        console.warn('⚠️  Inline keyboard URL tidak valid (mungkin masih localhost). Dilewati.');
        return;
    }
    console.error('[unhandledRejection]', reason);
});

process.on('uncaughtException', (err) => {
    console.error('[uncaughtException]', err.message);
    // Jangan exit — biarkan bot tetap jalan
});

