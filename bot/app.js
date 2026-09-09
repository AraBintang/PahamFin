const fs = require('fs');
const path = require('path');
const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const axios = require('axios');

function loadEnv() {
    const envFile = path.join(__dirname, '.env');
    const config = { WEBHOOK_URL: 'http://pahamfin.softwaremahasiswa.com/webhook.php', PAHAMFIN_WEBHOOK_KEY: '' };

    if (fs.existsSync(envFile)) {
        const lines = fs.readFileSync(envFile, 'utf8').split(/\r?\n/);
        for (const line of lines) {
            const match = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
            if (!match) continue;
            config[match[1]] = match[2].replace(/^['"]|['"]$/g, '');
        }
    }

    return config;
}

const { WEBHOOK_URL, PAHAMFIN_WEBHOOK_KEY } = loadEnv();

const client = new Client({
    authStrategy: new LocalAuth(),
});

// Generate QR Code di terminal
client.on('qr', (qr) => {
    console.log('Silakan scan QR Code ini menggunakan WhatsApp Anda:');
    qrcode.generate(qr, { small: true });
});

client.on('ready', () => {
    console.log('Bot WhatsApp sudah siap dan terhubung!');
});

function formatRp(amount) {
    return new Intl.NumberFormat('id-ID').format(amount);
}

const HELP_TEXT =
    `*Perintah PahamFin WhatsApp*\n\n` +
    `📝  *Catat transaksi:*\n` +
    `   ketik "makan 50000" atau "gaji 5jt"\n\n` +
    `💳  *Saldo bulan ini:*   /saldo\n` +
    `📊  *Laporan bulan ini:* /laporan\n` +
    `🗂  *Daftar kategori:*    /kategori\n` +
    `📜  *Riwayat terbaru:*    /riwayat\n` +
    `❓  *Bantuan:*            /help`;

// Kirim data ke webhook dengan header otentikasi
async function callWebhook(payload) {
    return axios.post(WEBHOOK_URL, payload, {
        headers: PAHAMFIN_WEBHOOK_KEY ? { 'X-PahamFin-Key': PAHAMFIN_WEBHOOK_KEY } : {},
        timeout: 30000,
    });
}

client.on('message', async (msg) => {
    if (msg.from.includes('@g.us')) return;

    const phone = msg.from.replace('@c.us', '');
    const text = (msg.body || '').trim();

    try {
        // Handle perintah
        if (text.startsWith('/')) {
            const cmd = text.toLowerCase();
            let reply;
            if (cmd === '/help' || cmd === '/bantuan') {
                reply = { message: HELP_TEXT, success: true };
            } else if (cmd.startsWith('/saldo') || cmd.startsWith('/laporan') || cmd.startsWith('/kategori') || cmd.startsWith('/riwayat')) {
                const action = cmd.split(' ')[0].replace('/', '');
                reply = (await callWebhook({ phone, message: text, action })).data;
            } else {
                reply = { message: 'Perintah tidak dikenal. Ketik /help untuk bantuan.', success: false };
            }
            msg.reply(reply.message || 'Terjadi kesalahan.');
            return;
        }

        // Catat transaksi
        const response = await callWebhook({ phone, message: text });
        if (response.data && response.data.message) {
            msg.reply(response.data.message);
        } else {
            msg.reply('Maaf, terjadi kesalahan pada sistem saat mencatat data.');
        }
    } catch (error) {
        console.error('Error API:', error.message);
        const errMsg = error.response?.data?.message;
        if (errMsg) {
            msg.reply('⚠️ ' + errMsg);
        } else {
            msg.reply('Server sedang offline. Pastikan server PHP (XAMPP) berjalan.');
        }
    }
});

client.initialize();