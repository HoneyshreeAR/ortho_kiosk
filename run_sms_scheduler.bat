@echo off
title OrthoKiosk Automated Medicine SMS & Reminder Runner
color 0A
echo ======================================================================
echo    ORTHOKIOSK AUTOMATED MEDICINE SMS & REMINDER SCHEDULER
echo ======================================================================
echo.
echo Running automated check every 60 seconds...
echo Press Ctrl+C anytime to stop.
echo.

:loop
"c:\xampp\php\php.exe" "c:\xampp\htdocs\ortho_kiosk\cron_reminders.php"
echo.
echo [Next check in 60 seconds at %TIME%...]
timeout /t 60 /nobreak >nul
goto loop
