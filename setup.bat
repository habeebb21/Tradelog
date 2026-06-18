@echo off
title Tradelog Setup
cd /d "%~dp0"

echo ============================================
echo   Tradelog Setup
echo ============================================
echo.

REM ── Step 1: Generate icon ──────────────────────
echo [1/3] Generating icon...
if not exist tradelog.ico (
    powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0make-icon.ps1" >nul 2>&1
    if exist tradelog.ico (
        echo       Icon created.
    ) else (
        echo       WARNING: Could not create icon.
    )
) else (
    echo       Icon already exists.
)

REM ── Step 2: Create desktop shortcut ───────────────
echo [2/3] Creating shortcut...
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0create-shortcut.ps1" >nul 2>&1
echo       Shortcut created in project folder.

REM ── Step 3: Check Docker ───────────────────────────
echo [3/3] Checking Docker...
docker info >nul 2>&1
if errorlevel 1 (
    echo       WARNING: Docker Desktop is not running.
    echo       Start Docker Desktop, then run start.bat to launch Tradelog.
) else (
    echo       Docker is ready.
)

echo.
echo ============================================
echo   Setup complete!
echo   - Double-click Tradelog.lnk to launch.
echo   - Or run start.bat directly.
echo ============================================
echo.
pause
