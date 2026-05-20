@echo off
REM Script para iniciar MySQL no XAMPP
REM Execute como Administrador se necessário

echo ========================================
echo INICIANDO MYSQL - CANTINA SYSTEM
echo ========================================
echo.

REM Detectar instalação do XAMPP
if exist "C:\xampp\mysql\bin\mysqld.exe" (
    echo [OK] XAMPP encontrado em C:\xampp
    set MYSQL_PATH=C:\xampp\mysql
) else if exist "D:\xampp\mysql\bin\mysqld.exe" (
    echo [OK] XAMPP encontrado em D:\xampp
    set MYSQL_PATH=D:\xampp\mysql
) else (
    echo [ERRO] XAMPP nao encontrado!
    echo Localize a pasta mysql e execute este script novamente.
    pause
    exit /b 1
)

echo.
echo Iniciando MySQL...
cd /d "%MYSQL_PATH%\bin"

REM Tentar iniciar o serviço MySQL
sc query MySQL80 >nul 2>&1
if %errorlevel%==0 (
    echo [INFO] Tentando iniciar servico MySQL80...
    net start MySQL80 >nul 2>&1
    if %errorlevel%==0 (
        echo [OK] MySQL80 iniciado como servico
    ) else (
        echo [AVISO] Nao conseguiu iniciar servico, tentando mysqld diretamente...
        start mysqld.exe
        timeout /t 3 /nobreak
    )
) else (
    echo [INFO] Servico nao registrado, iniciando mysqld diretamente...
    start mysqld.exe
    timeout /t 3 /nobreak
)

echo.
echo [OK] MySQL deveria estar rodando agora!
echo.
echo Testando conexao...
timeout /t 2 /nobreak
mysql -u root -e "SELECT 1" >nul 2>&1
if %errorlevel%==0 (
    echo [OK] CONEXAO BEM-SUCEDIDA!
    echo.
    echo Acesse: http://localhost/Cantina/login-menu.html
) else (
    echo [AVISO] Nao conseguiu verificar. Verifique o XAMPP Control Panel.
)

echo.
pause
