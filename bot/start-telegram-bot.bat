@echo off
REM =====================================================
REM  PahamFin - Telegram Bot Launcher (auto-start)
REM  Menjalankan telegram_bot.js sebagai proses latar
REM  dan memastikan hanya ada SATU instance yang berjalan.
REM  Window bisa langsung ditutup; bot tetap berjalan.
REM =====================================================
setlocal

set "NODE=C:\Program Files\nodejs\node.exe"
set "BOTDIR=C:\laragon\www\PahamFin\bot"
set "LOG=%BOTDIR%\telegram-bot.log"

REM Matikan instance PahamFin Telegram lama (hindari duplikat polling)
for /f "tokens=1" %%p in ('wmic process where "name='node.exe'" get processid ^| findstr /r "[0-9]"') do (
  wmic process where "processid=%%p" get commandline | findstr /i "telegram_bot.js" >nul 2>&1
  if not errorlevel 1 (
    taskkill /pid %%p /f >nul 2>&1
  )
)
timeout /t 1 /nobreak >nul

REM Jalankan bot sebagai proses latar agar tetap hidup setelah window ditutup
start "PahamFin Telegram Bot" /min "%NODE%" "%BOTDIR%\telegram_bot.js" >> "%LOG%" 2>&1

echo PahamFin Telegram bot sudah diluncurkan di latar belakang.
echo Log: %LOG%
timeout /t 2 /nobreak >nul

endlocal