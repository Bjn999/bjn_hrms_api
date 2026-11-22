@echo off
title BJN HRMS - Laravel Task Scheduler
cd /d "%~dp0..\.."

echo [BJN HRMS] Starting Laravel Task Scheduler (schedule:work)...
:loop
php artisan schedule:work --no-interaction
echo [BJN HRMS] Scheduler stopped unexpectedly. Restarting in 5 seconds...
timeout /t 5 /nobreak >nul
goto loop
