@echo off
title PahamFin - Stop All Bot Instances
echo =========================================
echo   PahamFin - Menghentikan semua bot...
echo =========================================

:: Matikan semua proses node.js
taskkill /F /IM node.exe /T >nul 2>&1

if %ERRORLEVEL% == 0 (
    echo [OK] Semua proses Node.js berhasil dihentikan.
) else (
    echo [INFO] Tidak ada proses Node.js yang berjalan.
)

timeout /t 2 /nobreak >nul
echo Selesai. Aman untuk memulai bot baru.
exit
