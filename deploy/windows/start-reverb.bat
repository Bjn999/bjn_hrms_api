@echo off
title BJN HRMS - Laravel Reverb WebSocket Server
cd /d "%~dp0..\.."

echo [BJN HRMS] Starting Laravel Reverb Server on 0.0.0.0:8080...
:loop
php artisan reverb:start --host=0.0.0.0 --port=8080 --no-interaction
echo [BJN HRMS] Reverb stopped unexpectedly. Restarting in 5 seconds...
timeout /t 5 /nobreak >nul
goto loop
