# Script PowerShell para iniciar MySQL
# Execute como Administrador

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "INICIANDO MYSQL - CANTINA SYSTEM" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Função para encontrar XAMPP
function Find-XamppPath {
    $paths = @('C:\xampp', 'D:\xampp', 'E:\xampp', $env:XAMPP_HOME)
    
    foreach ($path in $paths) {
        if ($path -and (Test-Path "$path\mysql\bin\mysqld.exe")) {
            return $path
        }
    }
    return $null
}

$xamppPath = Find-XamppPath

if (-not $xamppPath) {
    Write-Host "[ERRO] XAMPP nao encontrado!" -ForegroundColor Red
    Write-Host "Instale XAMPP ou defina a variavel de ambiente XAMPP_HOME" -ForegroundColor Yellow
    Read-Host "Pressione ENTER para sair"
    exit 1
}

Write-Host "[OK] XAMPP encontrado em: $xamppPath" -ForegroundColor Green
Write-Host ""

# Função para iniciar MySQL
function Start-MySQLService {
    param([string]$XamppPath)
    
    $mysqlExe = "$XamppPath\mysql\bin\mysqld.exe"
    $mysqlPath = "$XamppPath\mysql\bin"
    
    # Tentar iniciar como serviço
    Write-Host "Tentando iniciar MySQL80 como servico..." -ForegroundColor Yellow
    
    $service = Get-Service -Name "MySQL80" -ErrorAction SilentlyContinue
    
    if ($service) {
        if ($service.Status -eq "Running") {
            Write-Host "[OK] MySQL80 ja estava rodando" -ForegroundColor Green
            return $true
        }
        
        try {
            Start-Service -Name "MySQL80"
            Start-Sleep -Seconds 2
            Write-Host "[OK] MySQL80 iniciado como servico" -ForegroundColor Green
            return $true
        } catch {
            Write-Host "[AVISO] Erro ao iniciar servico: $_" -ForegroundColor Yellow
        }
    }
    
    # Fallback: iniciar mysqld.exe diretamente
    Write-Host "Iniciando mysqld.exe diretamente..." -ForegroundColor Yellow
    
    try {
        Start-Process -FilePath $mysqlExe -NoNewWindow -PassThru
        Start-Sleep -Seconds 3
        Write-Host "[OK] mysqld.exe iniciado" -ForegroundColor Green
        return $true
    } catch {
        Write-Host "[ERRO] Nao conseguiu iniciar mysqld: $_" -ForegroundColor Red
        return $false
    }
}

# Executar
$success = Start-MySQLService -XamppPath $xamppPath

if ($success) {
    Write-Host ""
    Write-Host "Testando conexao com MySQL..." -ForegroundColor Yellow
    Start-Sleep -Seconds 2
    
    try {
        # Testar conexão
        $mysqlPath = "$xamppPath\mysql\bin"
        $mysqlCmd = "$mysqlPath\mysql.exe -u root -e 'SELECT 1;' 2>$null"
        
        $result = Invoke-Expression $mysqlCmd -ErrorAction SilentlyContinue
        
        if ($result) {
            Write-Host ""
            Write-Host "[OK] CONEXAO BEM-SUCEDIDA!" -ForegroundColor Green
            Write-Host ""
            Write-Host "Acesse: http://localhost/Cantina/login-menu.html" -ForegroundColor Cyan
        } else {
            Write-Host "[INFO] MySQL iniciado, mas nao conseguiu verificar conexao" -ForegroundColor Yellow
            Write-Host "Aguarde alguns segundos e tente acessar a aplicacao" -ForegroundColor Yellow
        }
    } catch {
        Write-Host "[INFO] MySQL iniciado, mas nao conseguiu verificar conexao" -ForegroundColor Yellow
        Write-Host "Aguarde alguns segundos e tente acessar a aplicacao" -ForegroundColor Yellow
    }
} else {
    Write-Host ""
    Write-Host "[ERRO] Nao conseguiu iniciar MySQL" -ForegroundColor Red
    Write-Host "Tente iniciar pelo XAMPP Control Panel manualmente" -ForegroundColor Yellow
}

Write-Host ""
Read-Host "Pressione ENTER para sair"
