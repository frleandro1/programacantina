param(
  [string]$ProjectDir = "C:\xampp\htdocs\Cantina"
)

$ErrorActionPreference = 'SilentlyContinue'
$queueDir     = Join-Path $ProjectDir 'logs\print-queue'
$processedDir = Join-Path $ProjectDir 'logs\print-processed'
$workerLog    = Join-Path $ProjectDir 'logs\print\worker.log'

New-Item -ItemType Directory -Force -Path $queueDir     | Out-Null
New-Item -ItemType Directory -Force -Path $processedDir | Out-Null

function Write-WorkerLog([string]$msg) {
    try {
        $line = ('[{0}] {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $msg)
        Add-Content -Path $workerLog -Value $line -Encoding UTF8
    } catch {}
}

Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;

public class RawPrint {
    [DllImport("winspool.Drv", EntryPoint="OpenPrinterA", SetLastError=true, CharSet=CharSet.Ansi)]
    public static extern bool OpenPrinter(string szPrinter, out IntPtr hPrinter, IntPtr pd);

    [DllImport("winspool.Drv", EntryPoint="ClosePrinter", SetLastError=true)]
    public static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint="StartDocPrinterA", SetLastError=true, CharSet=CharSet.Ansi)]
    public static extern bool StartDocPrinter(IntPtr hPrinter, int level, ref DOCINFOA di);

    [DllImport("winspool.Drv", EntryPoint="EndDocPrinter", SetLastError=true)]
    public static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint="StartPagePrinter", SetLastError=true)]
    public static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint="EndPagePrinter", SetLastError=true)]
    public static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint="WritePrinter", SetLastError=true)]
    public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);

    [StructLayout(LayoutKind.Sequential, CharSet=CharSet.Ansi)]
    public struct DOCINFOA {
        public string pDocName;
        public string pOutputFile;
        public string pDataType;
    }

    public static bool SendRaw(string printerName, byte[] bytes) {
        IntPtr hPrinter;
        if (!OpenPrinter(printerName, out hPrinter, IntPtr.Zero)) return false;
        var di = new DOCINFOA { pDocName = "Cupom", pDataType = "RAW", pOutputFile = null };
        if (!StartDocPrinter(hPrinter, 1, ref di)) { ClosePrinter(hPrinter); return false; }
        if (!StartPagePrinter(hPrinter)) { EndDocPrinter(hPrinter); ClosePrinter(hPrinter); return false; }
        IntPtr ptr = Marshal.AllocCoTaskMem(bytes.Length);
        Marshal.Copy(bytes, 0, ptr, bytes.Length);
        int written = 0;
        bool ok = WritePrinter(hPrinter, ptr, bytes.Length, out written);
        Marshal.FreeCoTaskMem(ptr);
        EndPagePrinter(hPrinter);
        EndDocPrinter(hPrinter);
        ClosePrinter(hPrinter);
        return ok && written == bytes.Length;
    }
}
"@

function Get-DefaultPrinterName {
    $p = Get-CimInstance Win32_Printer | Where-Object { $_.Default -eq $true } | Select-Object -First 1 -ExpandProperty Name
    if (-not $p) {
        $p = Get-CimInstance Win32_Printer | Select-Object -First 1 -ExpandProperty Name
    }
    return $p
}

function Get-ConfiguredPrinterName {
    try {
        $uri = 'http://localhost/Cantina/api/configuracoes.php?action=obter-uma&chave=impressora_nome'
        $resp = Invoke-WebRequest -UseBasicParsing -Uri $uri -TimeoutSec 2
        if (-not $resp -or -not $resp.Content) { return $null }
        $json = $resp.Content | ConvertFrom-Json
        if ($json.sucesso -and $json.dados -and $json.dados.valor) {
            $name = [string]$json.dados.valor
            if (-not [string]::IsNullOrWhiteSpace($name)) {
                return $name.Trim()
            }
        }
    } catch {}
    return $null
}

function Resolve-PrinterName {
    $configured = Get-ConfiguredPrinterName
    if ($configured) {
        $exists = Get-CimInstance Win32_Printer | Where-Object { $_.Name -eq $configured } | Select-Object -First 1
        if ($exists) {
            return $configured
        }
    }
    return Get-DefaultPrinterName
}

function Build-EscPosBytes([string]$texto) {
    # Inicializa impressora: ESC @
    $init   = [byte[]](0x1B, 0x40)
    # Selecionar codepage 858 (CP858, bom para latim/acentos) : ESC t 19
    $cpCmd  = [byte[]](0x1B, 0x74, 19)
    # Encoding 850 encodes acentos para o codepage da térmica
    $enc    = [System.Text.Encoding]::GetEncoding(850)
    $corpo  = $enc.GetBytes($texto)
    # Alimenta 2 linhas e corta: LF LF GS V 1 (corte parcial)
    $cut    = [byte[]](0x0A, 0x0A, 0x1D, 0x56, 0x01)
    $all    = New-Object 'System.Collections.Generic.List[byte]'
    $all.AddRange($init)
    $all.AddRange($cpCmd)
    $all.AddRange($corpo)
    $all.AddRange($cut)
    return $all.ToArray()
}

while ($true) {
    $printerName = Resolve-PrinterName
    if ($printerName) {
        $files = Get-ChildItem -Path $queueDir -Filter '*.txt' -File -ErrorAction SilentlyContinue | Sort-Object CreationTime
        foreach ($file in $files) {
            try {
                $content = [System.IO.File]::ReadAllText($file.FullName, [System.Text.Encoding]::UTF8)
                if (-not [string]::IsNullOrWhiteSpace($content)) {
                    # Normaliza quebras de linha
                    $content = $content.Replace("`r`n", "`n").Replace("`r", "`n")
                    $bytes = Build-EscPosBytes $content
                    Write-WorkerLog ("Processando " + $file.Name + " em " + $printerName + " (bytes=" + $bytes.Length + ")")
                    $ok = [RawPrint]::SendRaw($printerName, $bytes)
                    if (-not $ok) {
                        Write-WorkerLog ("Falha RAW em " + $printerName + " para " + $file.Name)
                        throw "Falha no envio RAW para impressora: $printerName"
                    }
                    Write-WorkerLog ("Impresso com sucesso: " + $file.Name + " em " + $printerName)
                }

                $target = Join-Path $processedDir $file.Name
                Move-Item -Path $file.FullName -Destination $target -Force -ErrorAction SilentlyContinue
            } catch {
                Write-WorkerLog ("Erro no arquivo " + $file.Name + ": " + $_.Exception.Message)
                $errPath = $file.FullName + '.err'
                Move-Item -Path $file.FullName -Destination $errPath -Force -ErrorAction SilentlyContinue
            }
        }
    }

    Start-Sleep -Milliseconds 400
}
