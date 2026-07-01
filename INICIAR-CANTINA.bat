@echo off
setlocal

set "ROOT=%~dp0"
cd /d "%ROOT%"

if not exist "%ROOT%scripts\INICIAR_CANTINA.bat" (
    echo [ERRO] Nao foi encontrado: %ROOT%scripts\INICIAR_CANTINA.bat
    pause
    exit /b 1
)

call "%ROOT%scripts\INICIAR_CANTINA.bat"
exit /b %errorlevel%
