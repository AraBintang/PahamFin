@echo off
title PahamFin Telegram Bot
echo =========================================
echo   PahamFin - Memulai Telegram Bot
echo =========================================

:: Pastikan tidak ada instance lain yang berjalan
echo [1/3] Menghentikan instance bot lama...
taskkill /F /IM node.exe /T >nul 2>&1
timeout /t 2 /nobreak >nul

echo [2/3] Instance lama dihentikan.
echo [3/3] Memulai bot Telegram...
echo -----------------------------------------

:: Pindah ke folder bot lalu jalankan
cd /d "%~dp0"
node telegram_bot.js

:: Jika bot berhenti (crash/exit), tampilkan pesan
echo.
echo =========================================
echo Bot berhenti. Tekan sembarang tombol untuk keluar...
echo =========================================
pause >nul
