@echo off
color 0b
title PahamFin Server Starter

echo =========================================
echo       Menyalakan PahamFin Server...
echo =========================================
echo.

:: 1. Jalankan Bot Telegram di background
echo [1/2] Menjalankan Bot Telegram (Node.js)...
cd /d "C:\laragon\www\PahamFin\bot"
start "PahamFin Telegram" cmd /c "node telegram_bot.js"

:: 2. Jalankan Ngrok
echo [2/2] Menjalankan Ngrok (Forwarding Webhook)...
cd /d "C:\laragon\www\PahamFin"
start "Ngrok PahamFin" cmd /c "ngrok http 80 --domain=eligibly-chute-crawling.ngrok-free.dev"

echo.
echo Selesai! Dua jendela baru telah terbuka:
echo 1. Jendela Bot Telegram (jangan ditutup)
echo 2. Jendela Ngrok (jangan ditutup)
echo.
pause
