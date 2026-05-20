@echo off
REM ==========================================
REM SCRIPT PARA PARAR CANTINA
REM Fecha Apache e MySQL
REM ==========================================

setlocal enabledelayedexpansion
color 0C
cls

echo.
echo ========================================
echo    ENCERRANDO CANTINA
echo ========================================
echo.

REM Parar Apache
echo [INFO] Encerrando Apache...
taskkill /IM httpd.exe /F >nul 2>&1
if %errorlevel%==0 (
    echo [OK] Apache encerrado
) else (
    echo [INFO] Apache nao estava rodando
)

timeout /t 1 /nobreak
echo.

REM Parar MySQL
echo [INFO] Encerrando MySQL...
taskkill /IM mysqld.exe /F >nul 2>&1
if %errorlevel%==0 (
    echo [OK] MySQL encerrado
) else (
    echo [INFO] MySQL nao estava rodando
)

echo.
echo ========================================
echo TODOS OS SERVICOS FORAM ENCERRADOS
echo ========================================
echo.
pause
