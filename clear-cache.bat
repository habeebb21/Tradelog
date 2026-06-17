@echo off
setlocal

echo ============================================
echo  Tradalyze - Rebuild and Restart
echo ============================================
echo.

REM Check if Docker is available
where docker >nul 2>&1
if errorlevel 1 (
    echo ERROR: Docker was not found in PATH.
    echo Make sure Docker Desktop is running.
    pause
    exit /b 1
)

cd /d "%~dp0"

echo [1/3] Stopping existing container...
docker compose down
if errorlevel 1 (
    echo WARNING: Could not stop container (may not be running, continuing...)
)

echo.
echo [2/3] Rebuilding Docker image (this includes fresh assets + cache clear)...
docker compose build --no-cache
if errorlevel 1 goto :failed

echo.
echo [3/3] Starting container...
docker compose up -d
if errorlevel 1 goto :failed

echo.
echo ============================================
echo  Done! Site is live at http://localhost:8080
echo ============================================
echo.
pause
exit /b 0

:failed
echo.
echo ERROR: Build or start failed. Check the output above.
pause
exit /b 1
