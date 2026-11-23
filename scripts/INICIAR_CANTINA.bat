@echo off
setlocal enabledelayedexpansion

set "XAMPP_DIR=C:\xampp"
set "PROJECT_DIR=C:\xampp\htdocs\Cantina1"
set "URL=http://localhost/Cantina1/login-menu.html"
set "HEALTH=http://localhost/Cantina1/api/system-status.php"

echo ========================================
echo   Iniciando Cantina
echo ========================================
echo.

REM --- Apache ---
tasklist /FI "IMAGENAME eq httpd.exe" | findstr /I /C:"httpd.exe" >nul 2>&1
if %errorlevel% neq 0 (
    echo Iniciando Apache...
    start "Apache" /min "%XAMPP_DIR%\apache\bin\httpd.exe"
) else (
    echo Apache ja rodando.
)

REM --- MySQL ---
tasklist /FI "IMAGENAME eq mysqld.exe" | findstr /I /C:"mysqld.exe" >nul 2>&1
if %errorlevel% neq 0 (
    echo Iniciando MySQL...
    if exist "%XAMPP_DIR%\mysql_start.bat" (
        start "MySQL" /min cmd /c ""%XAMPP_DIR%\mysql_start.bat""
    ) else (
        start "MySQL" /min "%XAMPP_DIR%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP_DIR%\mysql\bin\my.ini"
    )
) else (
    echo MySQL ja rodando.
)

echo Verificando MySQL na porta 3306...
set "MYSQL_READY=0"
set "MYSQL_TRY=0"
:WAIT_MYSQL
set /a MYSQL_TRY+=1
powershell -NoProfile -ExecutionPolicy Bypass -Command "try{$c=New-Object Net.Sockets.TcpClient('127.0.0.1',3306);$c.Close();exit 0}catch{exit 1}" >nul 2>&1
if !errorlevel! equ 0 (
    set "MYSQL_READY=1"
    goto MYSQL_OK
)
if !MYSQL_TRY! geq 15 goto MYSQL_FAIL
echo   Aguardando MySQL !MYSQL_TRY!/15...
ping -n 3 127.0.0.1 >nul
goto WAIT_MYSQL

:MYSQL_OK
echo MySQL online.
goto CONTINUE_START

:MYSQL_FAIL
echo [AVISO] MySQL nao respondeu na porta 3306.
echo [AVISO] O sistema pode abrir, mas sem banco de dados.

:CONTINUE_START

echo.
echo Aguardando sistema... (pode levar ate 60 segundos)
set "TRY=0"
:WAIT
set /a TRY+=1
powershell -NoProfile -ExecutionPolicy Bypass -Command "try{$r=Invoke-WebRequest -Uri '%HEALTH%' -UseBasicParsing -TimeoutSec 3;$j=$r.Content|ConvertFrom-Json;if($j.sucesso){exit 0}else{exit 1}}catch{exit 1}" >nul 2>&1
if !errorlevel! equ 0 goto ONLINE
if !TRY! geq 30 goto ABRE
echo   Tentativa !TRY!/30...
ping -n 3 127.0.0.1 >nul
goto WAIT

:ONLINE
echo Sistema online!

:ABRE
set "BROWSER="
if exist "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" set "BROWSER=%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe"
if exist "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe" set "BROWSER=%ProgramFiles%\Microsoft\Edge\Application\msedge.exe"
if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" set "BROWSER=%ProgramFiles%\Google\Chrome\Application\chrome.exe"

echo.
if defined BROWSER (
    echo Abrindo sistema no navegador...
    start "" "%BROWSER%" --app="%URL%" --no-first-run
) else (
    echo Abrindo no navegador padrao...
    start "" "%URL%"
)

echo.
echo ========================================
echo   Sistema iniciado! Nao feche esta janela.
echo ========================================
if "%MYSQL_READY%"=="0" (
    echo.
    echo [DICA] Abra o XAMPP Control Panel e inicie o MySQL manualmente.
)
pause >nul
endlocal