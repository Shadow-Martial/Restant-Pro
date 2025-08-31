@echo off
setlocal enabledelayedexpansion

REM Deployment Health Check Script for Windows
REM This script verifies that the application is healthy after deployment

REM Configuration
if "%HEALTH_CHECK_URL%"=="" set HEALTH_CHECK_URL=http://localhost/health
if "%MAX_RETRIES%"=="" set MAX_RETRIES=10
if "%RETRY_DELAY%"=="" set RETRY_DELAY=30
if "%TIMEOUT%"=="" set TIMEOUT=30

echo [INFO] Starting deployment health verification
echo [INFO] Health check URL: %HEALTH_CHECK_URL%
echo [INFO] Max retries: %MAX_RETRIES%
echo [INFO] Retry delay: %RETRY_DELAY%s
echo [INFO] Timeout: %TIMEOUT%s
echo.

REM Check if curl is available
curl --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] curl is required but not installed
    exit /b 1
)

REM Wait for application to be ready
echo [INFO] Waiting for application to be ready...
set /a attempt=1

:retry_loop
echo [INFO] Attempt %attempt%/%MAX_RETRIES%...

curl -f -s --max-time %TIMEOUT% "%HEALTH_CHECK_URL%" >nul 2>&1
if not errorlevel 1 (
    echo [SUCCESS] Application is responding!
    goto health_check
)

if %attempt% lss %MAX_RETRIES% (
    echo [WARNING] Application not ready, waiting %RETRY_DELAY%s before retry...
    timeout /t %RETRY_DELAY% /nobreak >nul
    set /a attempt+=1
    goto retry_loop
) else (
    echo [ERROR] Application failed to become ready after %MAX_RETRIES% attempts
    exit /b 1
)

:health_check
echo.
echo [INFO] Performing comprehensive health check...

REM Get overall health status
curl -f -s --max-time %TIMEOUT% "%HEALTH_CHECK_URL%" > temp_health.json 2>nul
if errorlevel 1 (
    echo [ERROR] Failed to get health status
    exit /b 1
)

REM Check if we can parse the response (basic check)
findstr /c:"status" temp_health.json >nul
if errorlevel 1 (
    echo [ERROR] Invalid health check response
    del temp_health.json 2>nul
    exit /b 1
)

REM Check for healthy status
findstr /c:"healthy" temp_health.json >nul
if not errorlevel 1 (
    echo [SUCCESS] Application health check passed!
    del temp_health.json 2>nul
    
    REM Run Laravel health check if available
    if exist artisan (
        echo [INFO] Running Laravel health check command...
        php artisan deployment:health-check --format=json >nul 2>&1
        if not errorlevel 1 (
            echo [SUCCESS] Laravel health check passed
        ) else (
            echo [WARNING] Laravel health check failed or not available
        )
    )
    
    echo.
    echo [SUCCESS] Deployment health verification completed successfully!
    exit /b 0
) else (
    echo [ERROR] Application health check failed
    del temp_health.json 2>nul
    exit /b 1
)

:cleanup
del temp_health.json 2>nul
exit /b 1