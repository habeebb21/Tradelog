@echo off
title Tradalyze - Stop

echo Stopping Tradalyze...
cd /d "%~dp0"
docker compose down

echo.
echo Tradalyze has been stopped.
pause
