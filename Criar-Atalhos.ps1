# PowerShell Script para criar atalhos na área de trabalho
# Execute como Administrador

param(
    [string]$Action = "criar"
)

$ErrorActionPreference = "SilentlyContinue"

# Encontrar pasta XAMPP
function Find-XamppPath {
    $paths = @('C:\xampp', 'D:\xampp', 'E:\xampp')
    foreach ($path in $paths) {
        if (Test-Path "$path\apache\bin\httpd.exe") {
            return $path
        }
    }
    return $null
}

$xamppPath = Find-XamppPath

if (-not $xamppPath) {
    Write-Host "[ERRO] XAMPP nao encontrado" -ForegroundColor Red
    exit 1
}

$cantina = "$xamppPath\htdocs\Cantina"
$desktop = [Environment]::GetFolderPath([Environment+SpecialFolder]::Desktop)

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "CRIANDO ATALHOS - CANTINA" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

if ($Action -eq "criar" -or $Action -eq "all") {
    Write-Host "Criando atalhos..." -ForegroundColor Yellow
    
    # Atalho para Iniciar Programa
    $WshShell = New-Object -ComObject WScript.Shell
    
    $lnkStart = "$desktop\🚀 Iniciar Cantina.lnk"
    $shortcut = $WshShell.CreateShortcut($lnkStart)
    $shortcut.TargetPath = "$cantina\INICIAR-PROGRAMA.bat"
    $shortcut.WorkingDirectory = $cantina
    $shortcut.Description = "Inicia XAMPP e abre a Cantina no navegador"
    $shortcut.IconLocation = "C:\Windows\System32\cmd.exe"
    $shortcut.Save()
    Write-Host "[OK] Atalho criado: $lnkStart" -ForegroundColor Green
    
    # Atalho para Parar Programa
    $lnkStop = "$desktop\⛔ Parar Cantina.lnk"
    $shortcut = $WshShell.CreateShortcut($lnkStop)
    $shortcut.TargetPath = "$cantina\PARAR-PROGRAMA.bat"
    $shortcut.WorkingDirectory = $cantina
    $shortcut.Description = "Encerra XAMPP e fecha a Cantina"
    $shortcut.IconLocation = "C:\Windows\System32\cmd.exe"
    $shortcut.Save()
    Write-Host "[OK] Atalho criado: $lnkStop" -ForegroundColor Green
    
    # Atalho para Login Menu
    $lnkLogin = "$desktop\🔐 Cantina - Login.lnk"
    $shortcut = $WshShell.CreateShortcut($lnkLogin)
    $shortcut.TargetPath = "http://localhost/Cantina/login-menu.html"
    $shortcut.Description = "Abre a pagina de login da Cantina"
    $shortcut.Save()
    Write-Host "[OK] Atalho criado: $lnkLogin" -ForegroundColor Green
    
    # Atalho para Vendas
    $lnkVendas = "$desktop\🛒 Cantina - Vendas.lnk"
    $shortcut = $WshShell.CreateShortcut($lnkVendas)
    $shortcut.TargetPath = "http://localhost/Cantina/vendas.html"
    $shortcut.Description = "Abre o modulo de vendas da Cantina"
    $shortcut.Save()
    Write-Host "[OK] Atalho criado: $lnkVendas" -ForegroundColor Green
    
    # Atalho para Admin
    $lnkAdmin = "$desktop\👨‍💼 Cantina - Admin.lnk"
    $shortcut = $WshShell.CreateShortcut($lnkAdmin)
    $shortcut.TargetPath = "http://localhost/Cantina/admin.html"
    $shortcut.Description = "Abre o painel administrativo da Cantina"
    $shortcut.Save()
    Write-Host "[OK] Atalho criado: $lnkAdmin" -ForegroundColor Green
    
    # Atalho para Relatorios
    $lnkRelatorios = "$desktop\📊 Cantina - Relatorios.lnk"
    $shortcut = $WshShell.CreateShortcut($lnkRelatorios)
    $shortcut.TargetPath = "http://localhost/Cantina/relatorios.html"
    $shortcut.Description = "Abre os relatorios da Cantina"
    $shortcut.Save()
    Write-Host "[OK] Atalho criado: $lnkRelatorios" -ForegroundColor Green
    
    Write-Host ""
    Write-Host "[OK] Todos os atalhos foram criados na area de trabalho!" -ForegroundColor Green
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "PROXIMAS ETAPAS" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Verifique a area de trabalho" -ForegroundColor White
Write-Host "2. Duplo clique em '🚀 Iniciar Cantina'" -ForegroundColor White
Write-Host "3. Aguarde Apache + MySQL iniciarem" -ForegroundColor White
Write-Host "4. O navegador abrira automaticamente" -ForegroundColor White
Write-Host ""
