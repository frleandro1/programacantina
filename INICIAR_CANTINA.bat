@echo off
setlocal enabledelayedexpansion

set "XAMPP_DIR=C:\xampp"
set "PROJECT_DIR=%XAMPP_DIR%\htdocs\Cantina"
set "PROJECT_URL=http://localhost/Cantina/login-menu.html"
set "HEALTH_URL=http://localhost/Cantina/api/system-status.php"
set "BROWSER_EXE="
set "BROWSER_PROFILE=%PROJECT_DIR%\logs\browser-kiosk-profile"
set "DEFAULT_PRINTER="
set "BROWSER_ARGS=--kiosk-printing --disable-print-preview --disable-extensions --disable-component-extensions-with-background-pages --no-first-run --no-default-browser-check --new-window"

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

if not exist "%PROJECT_DIR%\logs" mkdir "%PROJECT_DIR%\logs"

for /f "usebackq delims=" %%P in (`powershell -NoProfile -Command "try { $p = Get-CimInstance Win32_Printer ^| Where-Object { $_.Default -eq $true } ^| Select-Object -First 1 -ExpandProperty Name; if($p){$p} } catch {}"`) do set "DEFAULT_PRINTER=%%P"

if not defined DEFAULT_PRINTER (
    echo [AVISO] Nenhuma impressora padrao configurada no Windows.
    echo Defina a impressora termica como padrao para imprimir automaticamente.
) else (
    echo Impressora padrao detectada: %DEFAULT_PRINTER%
)

call :SETUP_BROWSER

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
call :OPEN_SYSTEM
goto END

:API_TIMEOUT
echo [AVISO] Nao foi possivel confirmar a API a tempo.
echo Abrindo o sistema mesmo assim...
call :OPEN_SYSTEM

:END
echo.
echo Pronto.
endlocal
exit /b 0

:SETUP_BROWSER
where msedge.exe >nul 2>&1
if %errorlevel% equ 0 (
    set "BROWSER_EXE=msedge.exe"
    goto :eof
)

where chrome.exe >nul 2>&1
if %errorlevel% equ 0 (
    set "BROWSER_EXE=chrome.exe"
    goto :eof
)

if exist "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" (
    set "BROWSER_EXE=%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe"
    goto :eof
)

if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" (
    set "BROWSER_EXE=%ProgramFiles%\Google\Chrome\Application\chrome.exe"
    goto :eof
)

goto :eof

:OPEN_SYSTEM
if defined BROWSER_EXE (
    echo Impressao direta habilitada sem preview do navegador.
    start "" "%BROWSER_EXE%" --user-data-dir="%BROWSER_PROFILE%" --app="%PROJECT_URL%" %BROWSER_ARGS%
) else (
    echo [AVISO] Edge/Chrome nao encontrados. Abrindo navegador padrao sem impressao silenciosa.
    start "" "%PROJECT_URL%"
)
goto :eof
