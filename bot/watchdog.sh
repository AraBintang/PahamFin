#!/bin/bash
# Script Auto-Restart Watchdog PahamFin Bot untuk Hostinger Shared Hosting

export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
cd "$DIR"

# Cek apakah PM2 daemon & bot running
if ! npx pm2 pid pahamfin-bot > /dev/null 2>&1; then
    echo "[$(date)] PahamFin Bot mati! Menjalankan ulang..." >> ~/bot_watchdog.log
    npx pm2 start bot/ecosystem.config.js
    npx pm2 save
fi
