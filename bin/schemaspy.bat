@echo off
rem SchemaSpy CLI - lanceur Windows (délègue à bin/schemaspy)
chcp 65001 >nul
php "%~dp0schemaspy" %*
exit /b %errorlevel%
