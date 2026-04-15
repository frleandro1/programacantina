@echo off
setlocal enabledelayedexpansion

set "XAMPP_DIR=C:\xampp"
set "PROJECT_DIR=C:\xampp\htdocs\Cantina"
set "URL=http://localhost/Cantina/login-menu.html"
set "HEALTH=http://localhost/Cantina/api/system-status.php"

echo ========================================
echo   Iniciando Cantina
echo ========================================
echo.

REM --- Apache ---
tasklist /FI "IMAGENAME eq httpd.exe" 2^>nul ^| find /I "httpd.exe" ^>nul
if %errorlevel% neq 0 (
    echo Iniciando Apache...
    start "Apache" /min "%XAMPP_DIR%\apache\bin\httpd.exe"
) else (
    echo Apache ja rodando.
)

REM --- MySQL ---
tasklist /FI "IMAGENAME eq mysqld.exe" 2^>nul ^| find /I "mysqld.exe" ^>nul
if %errorlevel% neq 0 (
    echo Iniciando MySQL...
    start "MySQL" /min "%XAMPP_DIR%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP_DIR%\mysql\bin\my.ini"
) else (
    echo MySQL ja rodando.
)

echo.
echo Aguardando sistema... (pode levar ate 60 segundos)
set "TRY=0"
:WAIT
set /a TRY+=1
powershell -NoProfile -ExecutionPolicy Bypass -Command "try{$r=Invoke-WebRequest -Uri '%HEALTH%' -UseBasicParsing -TimeoutSec 3;$j=$r.Content|ConvertFrom-Json;if($j.sucesso){exit 0}else{exit 1}}catch{exit 1}" ^>nul 2^>^&1
if !errorlevel! equ 0 goto ONLINE
if !TRY! geq 30 goto ABRE
echo   Tentativa !TRY!/30...
timeout /t 2 /nobreak ^>nul
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
pause ^>nul
endlocal