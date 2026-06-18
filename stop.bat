@echo off
title Tradelog - Stop

echo Stopping Tradelog...
cd /d "%~dp0"
docker compose down

echo.
echo Tradelog has been stopped.
pause
