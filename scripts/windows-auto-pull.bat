@echo off
REM Wrapper for Task Scheduler (double-click or schedule this file).
set REPO=C:\xampp\htdocs\CMMP
set LOG=%REPO%\logs\auto-pull.log
if not exist "%REPO%\logs" mkdir "%REPO%\logs"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%REPO%\scripts\windows-auto-pull.ps1" -RepoPath "%REPO%" -LogFile "%LOG%"
exit /b %ERRORLEVEL%
