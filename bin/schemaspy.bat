@echo off
rem SchemaSpy CLI - Windows launcher (calls bin/schemaspy). ASCII only, CRLF line endings.
setlocal
where php >nul 2>nul
if errorlevel 1 (
    echo [ERROR] PHP not found in PATH. Install PHP 8.1+ or add it to PATH.
    exit /b 9009
)
chcp 65001 >nul
php "%~dp0schemaspy" %*
set "RC=%errorlevel%"
endlocal & exit /b %RC%
