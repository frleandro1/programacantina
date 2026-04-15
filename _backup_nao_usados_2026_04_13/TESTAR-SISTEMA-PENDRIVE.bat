@echo off
REM =========================================
REM Script de Teste - Pendrive
REM Verifica se o sistema está pronto para rodar
REM =========================================

echo.
echo ========================================
echo  TESTE DE SISTEMA - PENDRIVE
echo ========================================
echo.

REM Detecta a unidade
echo [1/4] Verificando unidade...
if exist "E:\xampp\xampp_start.exe" (
    echo OK: XAMPP encontrado em E:\xampp
) else (
    echo ERRO: XAMPP nao encontrado em E:\xampp
    echo Copie XAMPP Portable para E:\xampp e tente novamente
    pause
    exit /b 1
)

REM Verifica o projeto
echo.
echo [2/4] Verificando projeto...
if exist "E:\htdocs\Cantina\api\auth.php" (
    echo OK: Projeto encontrado em E:\htdocs\Cantina
) else (
    echo ERRO: Projeto nao encontrado em E:\htdocs\Cantina
    pause
    exit /b 1
)

REM Inicia XAMPP
echo.
echo [3/4] Iniciando XAMPP...
start "" /min "E:\xampp\xampp_start.exe"
echo XAMPP iniciado em background...
echo Aguardando 5 segundos...
timeout /t 5 /nobreak

REM Abre diagnóstico
echo.
echo [4/4] Abrindo diagnóstico...
start "" "http://localhost/Cantina/api/diagnostico-pendrive.php"

echo.
echo ========================================
echo Teste concluído! Verifique o navegador.
echo ========================================
echo.
pause
