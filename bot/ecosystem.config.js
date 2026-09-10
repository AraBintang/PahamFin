module.exports = {
  apps: [{
    name: 'pahamfin-bot',
    script: './bot/telegram_bot.js',
    autorestart: true,
    watch: false,
    max_memory_restart: '200M',
    env: {
      NODE_ENV: 'production',
    }
  }]
};
