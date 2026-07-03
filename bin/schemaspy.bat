@echo off
setlocal enabledelayedexpansion

REM SchemaSpy CLI - Script d'exécution Windows
REM @author  Laurent HADJADJ - maMoulinette
REM @version 3.0.0

REM Récupérer le dossier du script
set SCRIPT_DIR=%~dp0
set PROJECT_DIR=%SCRIPT_DIR%..

REM Vérifier PHP
where php >nul 2>nul
if errorlevel 1 (
    echo ❌ PHP n'est pas installé ou n'est pas dans le PATH
    pause
    exit /b 1
)

REM Vérifier l'existence de l'autoloader
if not exist "%PROJECT_DIR%\vendor\autoload.php" (
    echo ⚠️  Composer n'a pas été exécuté. Lancement de composer install...
    composer install --no-dev --optimize-autoloader
    if errorlevel 1 (
        echo ❌ Erreur lors de composer install
        pause
        exit /b 1
    )
)

REM Exécuter l'application
php "%PROJECT_DIR%\src\bootstrap.php" %*
if errorlevel 1 (
    echo.
    echo ❌ Erreur lors de l'exécution
    pause
    exit /b 1
)
