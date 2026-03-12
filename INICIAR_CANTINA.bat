@echo off
setlocal enabledelayedexpansion

set "XAMPP_DIR=C:\xampp"
set "PROJECT_DIR=%XAMPP_DIR%\htdocs\Cantina"
set "PROJECT_URL=http://localhost/Cantina/login-menu.html"
set "HEALTH_URL=http://localhost/Cantina/api/system-status.php"

echo ========================================
echo Iniciando Programa Cantina
echo ========================================
echo.

if not exist "%XAMPP_DIR%\xampp_start.exe" (
    echo [ERRO] XAMPP nao encontrado em "%XAMPP_DIR%".
    echo Ajuste o caminho no arquivo INICIAR_CANTINA.bat.
    pause
    exit /b 1
)

if not exist "%PROJECT_DIR%" (
    echo [ERRO] Projeto nao encontrado em "%PROJECT_DIR%".
    echo Copie a pasta Cantina para C:\xampp\htdocs\ e tente novamente.
    pause
    exit /b 1
)

echo [1/3] Iniciando servicos do XAMPP...
start "" /min "%XAMPP_DIR%\xampp_start.exe"

echo [2/3] Aguardando API ficar online...
set "MAX_TRIES=20"
set "TRY=0"
:WAIT_LOOP
set /a TRY+=1
powershell -NoProfile -ExecutionPolicy Bypass -Command "try { $r = Invoke-WebRequest -Uri '%HEALTH_URL%' -UseBasicParsing -TimeoutSec 2; if ($r.StatusCode -ge 200) { exit 0 } else { exit 1 } } catch { exit 1 }"
if !errorlevel! equ 0 goto API_ONLINE
if !TRY! geq !MAX_TRIES! goto API_TIMEOUT
timeout /t 1 /nobreak >nul
goto WAIT_LOOP

:API_ONLINE
echo [3/3] API online. Abrindo sistema...
start "" "%PROJECT_URL%"
goto END

:API_TIMEOUT
echo [AVISO] Nao foi possivel confirmar a API a tempo.
echo Abrindo o sistema mesmo assim...
start "" "%PROJECT_URL%"

:END
echo.
echo Pronto.
endlocal
exit /b 0
