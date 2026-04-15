Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;
public class RawDiag {
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
    [DllImport("kernel32.dll")]
    public static extern uint GetLastError();
    [StructLayout(LayoutKind.Sequential, CharSet=CharSet.Ansi)]
    public struct DOCINFOA { public string pDocName; public string pOutputFile; public string pDataType; }
}
"@

$printer = 'POS-58-IBFC'
$init = [byte[]](0x1B,0x40)
$cp   = [byte[]](0x1B,0x74,19)
$txt  = [System.Text.Encoding]::GetEncoding(850).GetBytes("TESTE RAW DIRETO`nCANTINA IBFC`n")
$cut  = [byte[]](0x0A,0x0A,0x1D,0x56,0x01)
$all = New-Object 'System.Collections.Generic.List[byte]'
$all.AddRange($init); $all.AddRange($cp); $all.AddRange($txt); $all.AddRange($cut)
$bytes = $all.ToArray()

$h=[IntPtr]::Zero
$okOpen = [RawDiag]::OpenPrinter($printer, [ref]$h, [IntPtr]::Zero)
if(-not $okOpen){ Write-Output "OPEN_FAIL:$([RawDiag]::GetLastError())"; exit 2 }
$di = New-Object RawDiag+DOCINFOA
$di.pDocName='RawDiag'
$di.pDataType='RAW'
$okDoc = [RawDiag]::StartDocPrinter($h, 1, [ref]$di)
if(-not $okDoc){ $e=[RawDiag]::GetLastError(); [RawDiag]::ClosePrinter($h) | Out-Null; Write-Output "STARTDOC_FAIL:$e"; exit 3 }
$okPage = [RawDiag]::StartPagePrinter($h)
if(-not $okPage){ $e=[RawDiag]::GetLastError(); [RawDiag]::EndDocPrinter($h) | Out-Null; [RawDiag]::ClosePrinter($h) | Out-Null; Write-Output "STARTPAGE_FAIL:$e"; exit 4 }
$ptr=[System.Runtime.InteropServices.Marshal]::AllocCoTaskMem($bytes.Length)
[System.Runtime.InteropServices.Marshal]::Copy($bytes,0,$ptr,$bytes.Length)
$written=0
$okWrite=[RawDiag]::WritePrinter($h,$ptr,$bytes.Length,[ref]$written)
$err=[RawDiag]::GetLastError()
[System.Runtime.InteropServices.Marshal]::FreeCoTaskMem($ptr)
[RawDiag]::EndPagePrinter($h) | Out-Null
[RawDiag]::EndDocPrinter($h) | Out-Null
[RawDiag]::ClosePrinter($h) | Out-Null
Write-Output "WRITE_OK=$okWrite WRITTEN=$written LEN=$($bytes.Length) ERR=$err"
