@echo off
title Tradelog - Stopping...
color 0E

echo.
echo  ==========================================
echo    Tradelog - Shutting Down
echo  ==========================================
echo.

cd /d "%~dp0"
docker compose down

echo.
echo  Tradelog has been stopped.
echo.
pause
