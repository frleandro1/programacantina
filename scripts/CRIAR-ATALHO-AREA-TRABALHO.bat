@echo off
setlocal

set "ROOT=%~dp0..\"

echo ========================================
echo   Criando atalho da Cantina
echo ========================================
echo.

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
 "$root=(Resolve-Path '%ROOT%').Path.TrimEnd('\\');" ^
 "$desktop=[Environment]::GetFolderPath('Desktop');" ^
 "$shortcutPath=Join-Path $desktop 'Cantina.lnk';" ^
 "$targetPath=Join-Path $root 'INICIAR-CANTINA.bat';" ^
 "$iconPath=Join-Path $root 'cantina-icon.ico';" ^
 "$shell=New-Object -ComObject WScript.Shell;" ^
 "$shortcut=$shell.CreateShortcut($shortcutPath);" ^
 "$shortcut.TargetPath=$targetPath;" ^
 "$shortcut.WorkingDirectory=$root;" ^
 "if (Test-Path $iconPath) { $shortcut.IconLocation=($iconPath + ',0') };" ^
 "$shortcut.Description='Iniciar sistema Cantina';" ^
 "$shortcut.Save();" ^
 "Write-Host '[OK] Atalho criado em:' $shortcutPath"

if %errorlevel% neq 0 (
    echo.
    echo [ERRO] Nao foi possivel criar o atalho.
    pause
    exit /b 1
)

echo.
echo [OK] Atalho atualizado com sucesso.
echo Use o arquivo Cantina.lnk na Area de Trabalho.
echo.
pause
exit /b 0
