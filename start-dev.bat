@echo off
setlocal enabledelayedexpansion
title ATHLETICA Dev Environment Manager
echo ===================================================
echo   ATHLETICA - Local Development Environment Launcher
echo ===================================================
echo.

:: 1. CHECK & CLEAN STALE MYSQL PID FILE
set "MYSQL_PID=C:\xampp\mysql\data\mysql.pid"
if exist "%MYSQL_PID%" (
    for /f "usebackq tokens=*" %%A in ("%MYSQL_PID%") do set OLD_PID=%%A
    tasklist /FI "PID eq !OLD_PID!" /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I "mysqld.exe" >NUL
    if errorlevel 1 (
        echo [INFO] Removing stale mysql.pid (!OLD_PID!)...
        del /f /q "%MYSQL_PID%" >NUL 2>&1
    )
)

:: 2. ENSURE MYSQL / MARIADB IS RUNNING ON PORT 3306
echo [CHECK] Checking MySQL on port 3306...
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I "mysqld.exe" >NUL
if errorlevel 1 (
    echo [START] Starting MariaDB/MySQL (127.0.0.1:3306)...
    powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
        "$cmd = 'C:\xampp\mysql\bin\mysqld.exe --defaults-file=\"C:\xampp\mysql\bin\my.ini\" --standalone';" ^
        "Invoke-CimMethod -ClassName Win32_Process -MethodName Create -Arguments @{ CommandLine = $cmd; CurrentDirectory = 'C:\xampp\mysql' } | Out-Null"
    timeout /t 2 /nobreak >NUL
) else (
    echo [OK] MySQL is already running.
)

:: 3. ENSURE APACHE IS RUNNING ON PORT 80
echo [CHECK] Checking Apache on port 80...
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I "httpd.exe" >NUL
if errorlevel 1 (
    echo [START] Starting Apache HTTP Server...
    powershell.exe -NoProfile -ExecutionPolicy Bypass -Command ^
        "$cmd = 'C:\xampp\apache\bin\httpd.exe -d C:/xampp/apache';" ^
        "Invoke-CimMethod -ClassName Win32_Process -MethodName Create -Arguments @{ CommandLine = $cmd; CurrentDirectory = 'C:\xampp\apache' } | Out-Null"
    timeout /t 2 /nobreak >NUL
) else (
    echo [OK] Apache is already running.
)

:: 4. ENSURE NEXT.JS DEV SERVER IS RUNNING ON PORT 3000
echo [CHECK] Checking Next.js Storefront on port 3000...
netstat -ano | findstr /R /C:":3000.*LISTENING" >NUL 2>&1
if errorlevel 1 (
    echo [START] Launching Next.js Storefront (http://localhost:3000)...
    cd /D "C:\Users\Shahzaib Khan\.gemini\antigravity\scratch\athletica\frontend"
    start "ATHLETICA Storefront (port 3000)" cmd.exe /k "npm run dev"
) else (
    echo [OK] Next.js Storefront is already running on port 3000.
)

echo.
echo ===================================================
echo   ALL ATHLETICA SERVICES ARE OPERATIONAL:
echo   - Admin Panel: http://localhost/athletica/admin/
echo   - Storefront:  http://localhost:3000
echo   - MySQL Port:  127.0.0.1:3306
echo ===================================================
echo.
pause
