@echo off
title Tradelog - Starting...

:: Go to project folder
cd /d "%~dp0"

:: Check if already running — if so, just open browser immediately
docker ps --filter "name=tradelog" --filter "status=running" --format "{{.Names}}" 2>nul | findstr /i "tradelog" >nul
if not errorlevel 1 (
    start "" http://localhost:8080
    exit /b 0
)

:: Not running — check if Docker Desktop is up
echo Starting Tradelog...
echo.
docker info >nul 2>&1
if errorlevel 1 (
    echo Docker Desktop is not running. Starting it...
    start "" "C:\Program Files\Docker\Docker\Docker Desktop.exe"
    echo Waiting for Docker to start ^(this may take 30-60 seconds^)...
    :waitdocker
    timeout /t 5 /nobreak >nul
    docker info >nul 2>&1
    if errorlevel 1 goto waitdocker
    echo Docker is ready.
    echo.
)

:: Rebuild and start
echo Building and starting app...
docker compose up --build -d

if errorlevel 1 (
    echo.
    echo ERROR: Failed to start. Check that Docker Desktop is running.
    pause
    exit /b 1
)

:: Wait for the app to be ready
echo Waiting for app to be ready...
:waitapp
timeout /t 3 /nobreak >nul
curl -s -o nul -w "%%{http_code}" http://localhost:8080 2>nul | findstr /r "^[23]" >nul
if errorlevel 1 goto waitapp

echo.
echo App is ready! Opening browser...
start "" http://localhost:8080
exit /b 0
