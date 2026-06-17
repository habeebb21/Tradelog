@echo off
title Tradelog - Starting...
color 0A

echo.
echo  ==========================================
echo    Tradelog - Trading Journal
echo  ==========================================
echo.

:: Check if Docker is installed
where docker >nul 2>&1
if %errorlevel% neq 0 (
    color 0C
    echo  [ERROR] Docker is not installed!
    echo.
    echo  Please install Docker Desktop first:
    echo  https://www.docker.com/products/docker-desktop/
    echo.
    pause
    exit /b 1
)

:: Check if Docker is running
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo  Docker is not running. Starting Docker Desktop...
    start "" "C:\Program Files\Docker\Docker\Docker Desktop.exe" 2>nul
    if %errorlevel% neq 0 (
        start "" "%LOCALAPPDATA%\Docker\Docker Desktop.exe" 2>nul
    )
    echo.
    echo  Waiting for Docker to start (this may take up to 60 seconds)...
    :wait_docker
    timeout /t 5 /nobreak >nul
    docker info >nul 2>&1
    if %errorlevel% neq 0 goto wait_docker
    echo  Docker is ready!
)

echo  Starting Tradelog...
echo.

:: Navigate to app folder and start containers
cd /d "%~dp0"
docker compose up -d --build 2>&1

if %errorlevel% neq 0 (
    color 0C
    echo.
    echo  [ERROR] Failed to start Tradelog.
    echo  Please make sure Docker Desktop is running and try again.
    echo.
    pause
    exit /b 1
)

:: Wait for the app to be ready
echo.
echo  Waiting for app to be ready...
:wait_app
timeout /t 3 /nobreak >nul
curl -s -o nul -w "%%{http_code}" http://localhost:8080 2>nul | findstr /r "200 302" >nul
if %errorlevel% neq 0 goto wait_app

echo.
echo  ==========================================
echo    Tradelog is ready!
echo    Opening http://localhost:8080 ...
echo  ==========================================
echo.

:: Open the app in the default browser
start "" "http://localhost:8080"

echo  App is running in the background.
echo  Close this window or run stop.bat to shut it down.
echo.
pause
