@echo off
REM Iniciar servidor PHP (Se PHP estiver instalado globalmente)
REM Caso contrário, use XAMPP ou outro servidor

setlocal enabledelayedexpansion

REM Verificar se está em um servidor
echo.
echo ========================================
echo Programa Cantina - Instruções de Acesso
echo ========================================
echo.
echo Voce precisa de um servidor web para rodar este projeto.
echo.
echo OPCAO 1 - XAMPP (Recomendado)
echo ==========================
echo 1. Baixe XAMPP em: https://www.apachefriends.org/pt_BR/
echo 2. Instale
echo 3. Copie esta pasta para: C:\xampp\htdocs\
echo 4. Inicie Apache e MySQL no XAMPP Control Panel
echo 5. Acesse: http://localhost/ProgramaCantina%%20Dezembro/teste-completo.php
echo.
echo OPCAO 2 - PHP Built-in (Se tiver PHP instalado)
echo ===============================================
echo 1. Abra o terminal aqui
echo 2. Execute: php -S localhost:8000
echo 3. Acesse: http://localhost:8000/teste-completo.php
echo.
echo OPCAO 3 - IIS (Se tiver IIS instalado)
echo ========================================
echo 1. Copie a pasta para C:\inetpub\wwwroot\
echo 2. Configure um site no IIS
echo 3. Acesse via browser
echo.
pause
