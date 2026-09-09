@echo off
setlocal EnableExtensions
title BidBoard Setup

echo.
echo BidBoard Setup
echo ==============
echo.
echo Choose how you want to run BidBoard:
echo   1. Docker (Apache, MariaDB, and Adminer)
echo   2. XAMPP (Apache and MySQL started manually)
echo.
choice /C 12 /N /M "Select 1 or 2"

if errorlevel 2 goto :xampp
if errorlevel 1 goto :docker

:docker
where docker >nul 2>&1
if errorlevel 1 (
    echo.
    echo Docker Desktop was not found. Install and start Docker Desktop, then run this file again.
    goto :end
)

docker compose version >nul 2>&1
if errorlevel 1 (
    echo.
    echo Docker Compose is unavailable. Start Docker Desktop and run this file again.
    goto :end
)

echo.
echo Building and starting the Docker services...
docker compose up --build -d
if errorlevel 1 (
    echo.
    echo Docker services could not be started. Review the message above and try again.
    goto :end
)

echo Waiting for MariaDB to become available...
set /a DB_ATTEMPTS=0
:wait_for_db
docker compose exec -T db mariadb -uroot -prootpass -e "SELECT 1;" >nul 2>&1
if not errorlevel 1 goto :check_schema
set /a DB_ATTEMPTS+=1
if %DB_ATTEMPTS% GEQ 30 (
    echo.
    echo MariaDB did not become ready within 60 seconds.
    echo Run "docker compose logs db" to inspect the database service.
    goto :end
)
timeout /t 2 /nobreak >nul
goto :wait_for_db

:check_schema
set "TABLE_EXISTS="
for /f "delims=" %%A in ('docker compose exec -T db mariadb -uroot -prootpass -N -e "USE bidboard; SHOW TABLES LIKE 'admins';" 2^>nul') do set "TABLE_EXISTS=%%A"
if /I "%TABLE_EXISTS%"=="admins" goto :docker_ready

echo Loading the BidBoard database schema...
docker compose exec -T db mariadb -uroot -prootpass bidboard < sql\bidboard.sql
if errorlevel 1 (
    echo.
    echo The database schema could not be imported. Review the message above and try again.
    goto :end
)

:docker_ready
echo Database schema is ready.
echo.
echo BidBoard: http://localhost:8080/
echo Adminer:  http://localhost:8081/  ^(server: db, user: root, password: rootpass^)
goto :end

:xampp
echo.
echo XAMPP setup selected.
echo 1. Start Apache and MySQL from the XAMPP Control Panel.
echo 2. Open http://localhost/phpmyadmin and create a database named "bidboard".
echo 3. Import sql\bidboard.sql into that database.
echo 4. Visit http://localhost/bidboard/

:end
echo.
pause
endlocal
