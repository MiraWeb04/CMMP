@echo off
cd /d "%~dp0\.."
if exist "C:\xampp\mysql\bin\mysqldump.exe" set MYSQLDUMP_PATH=C:\xampp\mysql\bin\mysqldump.exe
if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" scripts\backup-database.php
) else (
    php scripts\backup-database.php
)
if errorlevel 1 exit /b 1
echo Import the newest file in backups\ after a failure: php scripts\restore-database.php backups\cmmp_db-YYYYMMDD-HHMMSS.sql
