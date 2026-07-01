param(
    [string]$ProjectDir = ""
)

$ErrorActionPreference = 'Stop'

function Write-WorkerLog {
    param([string]$Message)

    try {
        $ts = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
        $line = "[$ts] $Message"
        Add-Content -Path $script:WorkerLogPath -Value $line -Encoding UTF8
    } catch {
        # Nao derruba o worker por falha de log.
    }
}

if ([string]::IsNullOrWhiteSpace($ProjectDir)) {
    $ProjectDir = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
}

$queueDir = Join-Path $ProjectDir 'logs\print-queue'
$processedDir = Join-Path $ProjectDir 'logs\print-processed'
$failedDir = Join-Path $ProjectDir 'logs\print-failed'
$script:WorkerLogPath = Join-Path $ProjectDir 'logs\print\worker.log'

New-Item -ItemType Directory -Path $queueDir -Force | Out-Null
New-Item -ItemType Directory -Path $processedDir -Force | Out-Null
New-Item -ItemType Directory -Path $failedDir -Force | Out-Null
New-Item -ItemType Directory -Path (Split-Path $script:WorkerLogPath -Parent) -Force | Out-Null

Write-WorkerLog "Worker iniciado. ProjectDir=$ProjectDir"

$printApiUrl = 'http://localhost/Cantina/api/impressora.php?action=imprimir'

while ($true) {
    try {
        $files = Get-ChildItem -Path $queueDir -Filter '*.txt' -File | Sort-Object LastWriteTime

        foreach ($file in $files) {
            try {
                $texto = Get-Content -Path $file.FullName -Raw -Encoding UTF8
                if ([string]::IsNullOrWhiteSpace($texto)) {
                    $destEmpty = Join-Path $failedDir $file.Name
                    Move-Item -Path $file.FullName -Destination $destEmpty -Force
                    Write-WorkerLog "Arquivo vazio movido para failed: $($file.Name)"
                    continue
                }

                $payload = @{ texto = $texto; copias = 1 } | ConvertTo-Json -Compress
                $resp = Invoke-RestMethod -Uri $printApiUrl -Method Post -ContentType 'application/json' -Body $payload -TimeoutSec 25

                if ($null -ne $resp -and $resp.sucesso -eq $true) {
                    $destOk = Join-Path $processedDir $file.Name
                    Move-Item -Path $file.FullName -Destination $destOk -Force
                    Write-WorkerLog "Impresso e movido para processed: $($file.Name)"
                } else {
                    $msg = if ($null -ne $resp) { [string]$resp.mensagem } else { 'sem resposta da API' }
                    $destFail = Join-Path $failedDir $file.Name
                    Move-Item -Path $file.FullName -Destination $destFail -Force
                    Write-WorkerLog "Falha ao imprimir $($file.Name): $msg. Movido para failed."
                }
            } catch {
                try {
                    $destFail = Join-Path $failedDir $file.Name
                    if (Test-Path $file.FullName) {
                        Move-Item -Path $file.FullName -Destination $destFail -Force
                    }
                    Write-WorkerLog "Erro processando $($file.Name): $($_.Exception.Message). Movido para failed."
                } catch {
                    Write-WorkerLog "Erro processando $($file.Name): $($_.Exception.Message). Falha ao mover para failed: $($_.Exception.Message)"
                }
            }
        }
    } catch {
        Write-WorkerLog "Erro no loop principal: $($_.Exception.Message)"
    }

    Start-Sleep -Seconds 3
}
