@echo off
REM ==========================================
REM SCRIPT PARA INICIAR CANTINA - COMPLETO
REM Inicia XAMPP + Abre navegador automaticamente
REM ==========================================

setlocal enabledelayedexpansion
color 0A
cls

echo.
echo ========================================
echo    CANTINA - SISTEMA DE VENDAS
echo ========================================
echo.

REM Detectar instalação XAMPP
if exist "C:\xampp\apache\bin\httpd.exe" (
    set XAMPP_PATH=C:\xampp
    echo [OK] XAMPP encontrado: C:\xampp
) else if exist "D:\xampp\apache\bin\httpd.exe" (
    set XAMPP_PATH=D:\xampp
    echo [OK] XAMPP encontrado: D:\xampp
) else (
    echo [ERRO] XAMPP nao encontrado!
    echo Localize a pasta XAMPP e execute este script novamente.
    pause
    exit /b 1
)

echo.
echo ========================================
echo INICIANDO SERVICOS...
echo ========================================
echo.

REM Verificar se Apache já está rodando
tasklist | find /I "httpd.exe" >nul
if %errorlevel%==0 (
    echo [OK] Apache ja esta rodando
) else (
    echo [INFO] Iniciando Apache...
    cd /d "%XAMPP_PATH%\apache\bin"
    start "" httpd.exe
    timeout /t 2 /nobreak
    echo [OK] Apache iniciado
)

echo.

REM Verificar se MySQL já está rodando
tasklist | find /I "mysqld.exe" >nul
if %errorlevel%==0 (
    echo [OK] MySQL ja esta rodando
) else (
    echo [INFO] Iniciando MySQL...
    cd /d "%XAMPP_PATH%\mysql\bin"
    start "" mysqld.exe
    timeout /t 3 /nobreak
    echo [OK] MySQL iniciado
)

echo.
echo ========================================
echo ABRINDO APLICACAO...
echo ========================================
echo.

REM Aguardar Apache ficar pronto
timeout /t 2 /nobreak

REM Abrir navegador
if exist "C:\Program Files\Google\Chrome\Application\chrome.exe" (
    start "" "C:\Program Files\Google\Chrome\Application\chrome.exe" "http://localhost/Cantina/login-menu.html"
    echo [OK] Abrindo Chrome...
) else if exist "C:\Program Files (x86)\Google\Chrome\Application\chrome.exe" (
    start "" "C:\Program Files (x86)\Google\Chrome\Application\chrome.exe" "http://localhost/Cantina/login-menu.html"
    echo [OK] Abrindo Chrome...
) else if exist "C:\Program Files\Mozilla Firefox\firefox.exe" (
    start "" "C:\Program Files\Mozilla Firefox\firefox.exe" "http://localhost/Cantina/login-menu.html"
    echo [OK] Abrindo Firefox...
) else (
    start "" "http://localhost/Cantina/login-menu.html"
    echo [OK] Abrindo navegador padrao...
)

echo.
echo ========================================
echo SISTEMA INICIADO COM SUCESSO!
echo ========================================
echo.
echo Se o navegador nao abrir automaticamente:
echo Acesse manualmente: http://localhost/Cantina/login-menu.html
echo.
echo Para fechar todos os servicos:
echo Execute: PARAR-PROGRAMA.bat
echo.
pause
