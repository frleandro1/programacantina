<?php
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/helpers.php';

function responder($sucesso, $mensagem, $dados = null, $httpCode = 200) {
    http_response_code($httpCode);
    $json = json_encode([
        'sucesso' => $sucesso,
        'mensagem' => $mensagem,
        'dados' => $dados
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    if ($json === false) {
        $json = '{"sucesso":false,"mensagem":"Falha ao serializar resposta JSON","dados":null}';
    }

    echo $json;
    exit;
}

function ler_json_input() {
    $raw = file_get_contents('php://input');
    if (!is_string($raw)) {
        return [null, ''];
    }

    // Remove BOM UTF-8 quando presente.
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
        $raw = substr($raw, 3);
    }

    $rawTrim = trim($raw);
    if ($rawTrim === '') {
        return [null, ''];
    }

    $data = json_decode($rawTrim, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        return [null, $rawTrim];
    }

    return [$data, $rawTrim];
}

function executar_powershell($script) {
    $disabled = ini_get('disable_functions') ?: '';
    if (!function_exists('shell_exec') || stripos($disabled, 'shell_exec') !== false) {
        return 'ERRO:SHELL_EXEC_DESABILITADO';
    }

    $prefix = '$ProgressPreference="SilentlyContinue"; [Console]::OutputEncoding = [System.Text.Encoding]::UTF8; ';
    $script = $prefix . $script;

    // Scripts muito longos podem falhar silenciosamente via -EncodedCommand por limite de linha de comando.
    // Neste caso, executamos por arquivo temporario .ps1.
    if (strlen($script) > 1500) {
        $tmpDir = sys_get_temp_dir();
        $tmpFile = rtrim($tmpDir, "\\/") . DIRECTORY_SEPARATOR . 'cantina_print_' . uniqid('', true) . '.ps1';
        $payload = "\xEF\xBB\xBF" . $script;

        if (@file_put_contents($tmpFile, $payload) === false) {
            return 'ERRO:NAO_FOI_POSSIVEL_CRIAR_PS1_TEMP';
        }

        $cmd = 'powershell -NoLogo -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($tmpFile) . ' 2>&1';
        $output = shell_exec($cmd);
        @unlink($tmpFile);

        if (!is_string($output)) {
            return '';
        }

        $clean = preg_replace('/#<\s*CLIXML.*$/s', '', $output);
        return trim((string)$clean);
    }

    if (function_exists('mb_convert_encoding')) {
        $utf16 = mb_convert_encoding($script, 'UTF-16LE', 'UTF-8');
    } elseif (function_exists('iconv')) {
        $utf16 = iconv('UTF-8', 'UTF-16LE', $script);
    } else {
        return 'ERRO:SEM_CONVERSOR_UTF16';
    }

    if ($utf16 === false || $utf16 === null) {
        return 'ERRO:FALHA_CONVERSAO_UTF16';
    }

    $encoded = base64_encode($utf16);
    $cmd = 'powershell -NoLogo -NoProfile -ExecutionPolicy Bypass -EncodedCommand ' . $encoded . ' 2>&1';
    $output = shell_exec($cmd);
    if (!is_string($output)) {
        return '';
    }

    // Remove ruído comum de CLIXML/progress do PowerShell.
    $clean = preg_replace('/#<\s*CLIXML.*$/s', '', $output);
    return trim((string)$clean);
}

function executar_cmd($cmd) {
    $disabled = ini_get('disable_functions') ?: '';
    if (!function_exists('shell_exec') || stripos($disabled, 'shell_exec') !== false) {
        return 'ERRO:SHELL_EXEC_DESABILITADO';
    }
    $output = shell_exec($cmd . ' 2>&1');
    return is_string($output) ? trim($output) : '';
}

function imprimir_via_lpt1($arquivo) {
    $arquivoEsc = '"' . str_replace('"', '\\"', $arquivo) . '"';
    $copyOut = executar_cmd('cmd /c copy /b ' . $arquivoEsc . ' LPT1');
    if (
        preg_match('/\b1\s+arquivo\(s\)\s+copiado\(s\)\b/i', $copyOut) ||
        preg_match('/\b1\s+file\(s\)\s+copied\b/i', $copyOut)
    ) {
        return [true, 'LPT1 copy ok', $copyOut];
    }

    $printOut = executar_cmd('cmd /c print /D:LPT1 ' . $arquivoEsc);
    if (
        stripos($printOut, 'Printing') !== false ||
        stripos($printOut, 'imprimindo') !== false ||
        stripos($printOut, 'submitted') !== false
    ) {
        return [true, 'LPT1 print ok', $printOut];
    }

    return [false, 'LPT1 indisponível', trim($copyOut . ' | ' . $printOut)];
}

$action = $_GET['action'] ?? 'status';

if ($action === 'listar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $script = 'Get-CimInstance Win32_Printer | Select-Object -ExpandProperty Name | Sort-Object | ForEach-Object { $_ }';
    $raw = executar_powershell($script);

    if (stripos($raw, 'ERRO:') !== false || $raw === '') {
        responder(false, 'Não foi possível listar impressoras', ['detalhe' => $raw]);
    }

    $lista = array_values(array_filter(array_map('trim', explode("\n", $raw))));
    responder(true, 'Impressoras listadas', ['impressoras' => $lista]);
}

if ($action === 'status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $script = <<<'PS'
$printers = @(Get-CimInstance Win32_Printer)
$printer = $printers | Where-Object { $_.Default -eq $true } | Select-Object -First 1
if (-not $printer) {
    $printer = $printers | Where-Object { $_.Name -match 'POS|THERM|58|EPSON|XP-' } | Select-Object -First 1
}
if (-not $printer) {
    $printer = $printers | Select-Object -First 1
}
if (-not $printer) {
    Write-Output "SEM_IMPRESSORA|TENTAR_LPT1"
  exit 1
}
$spooler = Get-Service -Name Spooler -ErrorAction SilentlyContinue
if ($null -eq $spooler) {
    Write-Output ("OK|" + $printer.Name + "|Default:" + $printer.Default + "|Spooler:desconhecido")
  exit 0
}
Write-Output ("OK|" + $printer.Name + "|Default:" + $printer.Default + "|Spooler:" + $spooler.Status)
PS;

    $raw = executar_powershell($script);
    if (stripos($raw, 'ERRO:SHELL_EXEC_DESABILITADO') !== false) {
        responder(false, 'PHP bloqueou shell_exec. Habilite shell_exec no php.ini para impressão nativa.');
    }

    if (stripos($raw, 'ERRO:SEM_CONVERSOR_UTF16') !== false || stripos($raw, 'ERRO:FALHA_CONVERSAO_UTF16') !== false) {
        responder(false, 'PHP sem suporte de conversão UTF-16 (mbstring/iconv).');
    }

    if ($raw === '') {
        responder(true, 'PowerShell sem resposta; modo fallback LPT1 disponível', ['saida' => 'fallback:lpt1']);
    }

    if (stripos($raw, 'SEM_IMPRESSORA') !== false) {
        responder(false, 'Nenhuma impressora padrão encontrada no Windows');
    }

    responder(true, 'Status da impressora carregado', ['saida' => $raw]);
}

if ($action === 'imprimir-lpt1' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    [$body, $rawBody] = ler_json_input();

    $texto = '';
    $copias = 1;

    if (is_array($body)) {
        $texto = isset($body['texto']) ? (string)$body['texto'] : '';
        $copias = isset($body['copias']) ? max(1, min(5, (int)$body['copias'])) : 1;
    } else {
        $texto = isset($_POST['texto']) ? (string)$_POST['texto'] : '';
        $copias = isset($_POST['copias']) ? max(1, min(5, (int)$_POST['copias'])) : 1;
    }

    if (trim($texto) === '') {
        responder(false, 'Texto do cupom vazio', [
            'raw_len' => strlen((string)$rawBody),
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? ''
        ], 400);
    }

    $logDir = __DIR__ . '/../logs/print';
    if (!is_dir($logDir) && !mkdir($logDir, 0777, true) && !is_dir($logDir)) {
        responder(false, 'Não foi possível criar pasta de impressão', null, 500);
    }

    $arquivo = $logDir . '/cupom_lpt1_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.txt';
    $textoWindows = str_replace(["\r\n", "\n"], "\r\n", $texto) . "\r\n\r\n";
    if (file_put_contents($arquivo, $textoWindows) === false) {
        responder(false, 'Falha ao gerar arquivo temporário para LPT1', null, 500);
    }

    $detalhes = [];
    for ($i = 0; $i < $copias; $i++) {
        [$okLpt1, $msgLpt1, $detLpt1] = imprimir_via_lpt1($arquivo);
        $detalhes[] = $msgLpt1 . ' | ' . $detLpt1;
        if (!$okLpt1) {
            responder(false, 'Falha ao imprimir via LPT1', [
                'arquivo' => basename($arquivo),
                'detalhe' => implode(' || ', $detalhes)
            ]);
        }
    }

    responder(true, 'Cupom enviado via LPT1', [
        'arquivo' => basename($arquivo),
        'detalhe' => implode(' || ', $detalhes)
    ]);
}

if ($action === 'enfileirar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    [$body, $rawBody] = ler_json_input();

    $texto = '';
    if (is_array($body)) {
        $texto = isset($body['texto']) ? (string)$body['texto'] : '';
    } else {
        $texto = isset($_POST['texto']) ? (string)$_POST['texto'] : '';
    }

    if (trim($texto) === '') {
        responder(false, 'Texto do cupom vazio', [
            'raw_len' => strlen((string)$rawBody),
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? ''
        ], 400);
    }

    $queueDir = __DIR__ . '/../logs/print-queue';
    if (!is_dir($queueDir) && !mkdir($queueDir, 0777, true) && !is_dir($queueDir)) {
        responder(false, 'Não foi possível criar a fila de impressão', null, 500);
    }

    $arquivo = $queueDir . '/queue_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.txt';
    $textoWindows = str_replace(["\r\n", "\n"], "\r\n", $texto) . "\r\n\r\n";

    if (file_put_contents($arquivo, $textoWindows) === false) {
        responder(false, 'Falha ao enfileirar cupom para impressão', null, 500);
    }

    responder(true, 'Cupom enfileirado para impressão local', [
        'arquivo' => basename($arquivo)
    ]);
}

if ($action === 'imprimir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    [$body, $rawBody] = ler_json_input();

    $texto = '';
    $copias = 1;

    if (is_array($body)) {
        $texto = isset($body['texto']) ? (string)$body['texto'] : '';
        $copias = isset($body['copias']) ? max(1, min(5, (int)$body['copias'])) : 1;
    } else {
        // Fallback para envio via form-urlencoded/multipart
        $texto = isset($_POST['texto']) ? (string)$_POST['texto'] : '';
        $copias = isset($_POST['copias']) ? max(1, min(5, (int)$_POST['copias'])) : 1;
    }

    if (trim($texto) === '') {
        responder(false, 'Texto do cupom vazio', [
            'raw_len' => strlen((string)$rawBody),
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? ''
        ], 400);
    }

    $logDir = __DIR__ . '/../logs/print';
    if (!is_dir($logDir) && !mkdir($logDir, 0777, true) && !is_dir($logDir)) {
        responder(false, 'Não foi possível criar pasta de impressão', null, 500);
    }

    $arquivo = $logDir . '/cupom_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.txt';
    $textoWindows = str_replace(["\r\n", "\n"], "\r\n", $texto);
    if (file_put_contents($arquivo, $textoWindows) === false) {
        responder(false, 'Falha ao gerar arquivo temporário de impressão', null, 500);
    }

    $arquivoPs = str_replace("'", "''", $arquivo);
        $scriptTemplate = <<<'PS'
$printers = @(Get-CimInstance Win32_Printer)
$printerObj = $printers | Where-Object { $_.Default -eq $true } | Select-Object -First 1
if (-not $printerObj) {
    $printerObj = $printers | Where-Object { $_.Name -match 'POS|THERM|58|EPSON|XP-' } | Select-Object -First 1
}
if (-not $printerObj) {
    $printerObj = $printers | Select-Object -First 1
}
if (-not $printerObj) { Write-Output 'ERRO:SEM_IMPRESSORA'; exit 2 }
$printer = $printerObj.Name

Add-Type -TypeDefinition @'
using System;
using System.Runtime.InteropServices;
public class RawPrintApi {
    [DllImport("winspool.Drv",EntryPoint="OpenPrinterA",SetLastError=true,CharSet=CharSet.Ansi)]
    public static extern bool OpenPrinter(string sz,out IntPtr h,IntPtr pd);
    [DllImport("winspool.Drv",EntryPoint="ClosePrinter",SetLastError=true)]
    public static extern bool ClosePrinter(IntPtr h);
    [DllImport("winspool.Drv",EntryPoint="StartDocPrinterA",SetLastError=true,CharSet=CharSet.Ansi)]
    public static extern bool StartDocPrinter(IntPtr h,int lv,ref DOC di);
    [DllImport("winspool.Drv",EntryPoint="EndDocPrinter",SetLastError=true)]
    public static extern bool EndDocPrinter(IntPtr h);
    [DllImport("winspool.Drv",EntryPoint="StartPagePrinter",SetLastError=true)]
    public static extern bool StartPagePrinter(IntPtr h);
    [DllImport("winspool.Drv",EntryPoint="EndPagePrinter",SetLastError=true)]
    public static extern bool EndPagePrinter(IntPtr h);
    [DllImport("winspool.Drv",EntryPoint="WritePrinter",SetLastError=true)]
    public static extern bool WritePrinter(IntPtr h,IntPtr p,int n,out int w);
    [StructLayout(LayoutKind.Sequential,CharSet=CharSet.Ansi)]
    public struct DOC { public string pDocName; public string pOutputFile; public string pDataType; }
    public static bool Send(string name,byte[] data){
        IntPtr h; if(!OpenPrinter(name,out h,IntPtr.Zero))return false;
        var d=new DOC{pDocName="Cupom",pDataType="RAW",pOutputFile=null};
        if(!StartDocPrinter(h,1,ref d)){ClosePrinter(h);return false;}
        if(!StartPagePrinter(h)){EndDocPrinter(h);ClosePrinter(h);return false;}
        var ptr=System.Runtime.InteropServices.Marshal.AllocCoTaskMem(data.Length);
        System.Runtime.InteropServices.Marshal.Copy(data,0,ptr,data.Length);
        int w=0; bool ok=WritePrinter(h,ptr,data.Length,out w);
        System.Runtime.InteropServices.Marshal.FreeCoTaskMem(ptr);
        EndPagePrinter(h);EndDocPrinter(h);ClosePrinter(h);
        return ok&&w==data.Length;
    }
}
'@ -ErrorAction SilentlyContinue

$text  = [System.IO.File]::ReadAllText('__ARQUIVO__', [System.Text.Encoding]::UTF8)
$text  = $text.Replace("`r`n", "`n").Replace("`r", "`n")
$text  = $text.TrimEnd() + "`n"
$init  = [byte[]](0x1B, 0x40)
$cpCmd = [byte[]](0x1B, 0x74, 19)
$enc   = [System.Text.Encoding]::GetEncoding(850)
$corpo = $enc.GetBytes($text)
$cut   = [byte[]](0x1D, 0x56, 0x41, 0x00)
$all   = New-Object 'System.Collections.Generic.List[byte]'
$all.AddRange($init); $all.AddRange($cpCmd); $all.AddRange($corpo); $all.AddRange($cut)
$bytes = $all.ToArray()

$ok = $false
for ($i = 0; $i -lt __COPIAS__; $i++) {
    $ok = [RawPrintApi]::Send($printer, $bytes)
    if ($i -lt (__COPIAS__ - 1)) { Start-Sleep -Milliseconds 200 }
}
if ($ok) {
    Write-Output ('OK:' + $printer)
} else {
    Write-Output ('ERRO:RAW_FALHOU:' + $printer)
    exit 3
}
PS;
    $script = str_replace(['__ARQUIVO__', '__COPIAS__'], [$arquivoPs, (string)$copias], $scriptTemplate);

    $raw = executar_powershell($script);

    if (stripos($raw, 'ERRO:SHELL_EXEC_DESABILITADO') !== false) {
        responder(false, 'PHP bloqueou shell_exec. Habilite shell_exec no php.ini para impressão nativa.', ['detalhe' => $raw]);
    }

    if (stripos($raw, 'ERRO:SEM_CONVERSOR_UTF16') !== false || stripos($raw, 'ERRO:FALHA_CONVERSAO_UTF16') !== false) {
        responder(false, 'PHP sem suporte de conversão UTF-16 (mbstring/iconv).', ['detalhe' => $raw]);
    }

    $powershellFalhou = ($raw === '' || stripos($raw, 'ERRO:') !== false || stripos($raw, 'acesso negado') !== false);
    if ($powershellFalhou) {
        [$okLpt1, $msgLpt1, $detLpt1] = imprimir_via_lpt1($arquivo);
        if ($okLpt1) {
            responder(true, 'Cupom enviado via fallback LPT1', [
                'arquivo' => basename($arquivo),
                'detalhe' => $msgLpt1 . ' | ' . $detLpt1
            ]);
        }
        responder(false, 'Falha na impressão nativa do Windows', ['detalhe' => trim(($raw ?: 'sem saída powershell') . ' | ' . $detLpt1)]);
    }

    responder(true, 'Cupom enviado para impressora padrão', [
        'arquivo' => basename($arquivo),
        'detalhe' => $raw
    ]);
}

if ($action === 'fila-status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $queueDir = __DIR__ . '/../logs/print-queue';
    $pendentes = 0;
    if (is_dir($queueDir)) {
        $files = glob($queueDir . '/*.txt');
        $pendentes = is_array($files) ? count($files) : 0;
    }
    responder(true, 'Status da fila', ['pendentes' => $pendentes]);
}

if ($action === 'reiniciar-worker' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Encerra worker atual (se houver)
    $killPs = <<<'PS'
$procs = Get-CimInstance Win32_Process | Where-Object {
    $_.Name -eq 'powershell.exe' -and $_.CommandLine -like '*print-worker.ps1*'
}
foreach ($p in $procs) {
    try { Stop-Process -Id $p.ProcessId -Force -ErrorAction Stop } catch {}
}
Write-Output 'OK:KILLED'
PS;
    executar_powershell($killPs);

    $projectDir = realpath(__DIR__ . '/..');
    $workerPath = $projectDir . '\\scripts\\setup\\print-worker.ps1';
    if (!file_exists($workerPath)) {
        responder(false, 'Script do worker não encontrado', ['caminho' => $workerPath]);
    }

    $wp = str_replace('"', '\\"', $workerPath);
    $pd = str_replace('"', '\\"', $projectDir);
    $createTask = 'schtasks /create /f /tn "CantinaPrintWorker" /tr "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File \\"' . $wp . '\\" -ProjectDir \\"' . $pd . '\\"" /sc ONCE /st 00:00 /it 2>&1';
    $runTask    = 'schtasks /run /tn "CantinaPrintWorker" 2>&1';
    $outCreate  = executar_cmd($createTask);
    $outRun     = executar_cmd($runTask);

    $iniciou = stripos($outRun, 'SUCCESS') !== false || stripos($outRun, 'ÊXITO') !== false || stripos($outRun, 'com êxito') !== false || stripos($outCreate, 'SUCCESS') !== false || stripos($outCreate, 'criada') !== false || stripos($outCreate, 'updated') !== false || stripos($outCreate, 'atualizada') !== false;
    responder($iniciou, $iniciou ? 'Worker reiniciado com sucesso' : 'Falha ao reiniciar worker', [
        'reiniciado' => $iniciou,
        'status'   => $iniciou ? 'reiniciado' : 'falhou',
        'detalhe'  => trim($outCreate . ' | ' . $outRun)
    ]);
}

if ($action === 'iniciar-worker' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $projectDir = realpath(__DIR__ . '/..');
    $workerPath = $projectDir . '\\scripts\\setup\\print-worker.ps1';

    if (!file_exists($workerPath)) {
        responder(false, 'Script do worker não encontrado', ['caminho' => $workerPath]);
    }

    // Verifica se já está rodando (funciona cross-session)
    $checkPs = '$p = Get-CimInstance Win32_Process | Where-Object { $_.Name -eq \'powershell.exe\' -and $_.CommandLine -like \'*print-worker.ps1*\' } | Select-Object -First 1; if ($p) { "RUNNING" } else { "STOPPED" }';
    $check = executar_powershell($checkPs);

    if (stripos($check, 'RUNNING') !== false) {
        responder(true, 'Worker já está em execução', ['iniciado' => false, 'status' => 'ja_rodando']);
    }

    // Cria/atualiza tarefa agendada e dispara (roda na sessão interativa do usuário)
    $wp = str_replace('"', '\\"', $workerPath);
    $pd = str_replace('"', '\\"', $projectDir);
    $createTask = 'schtasks /create /f /tn "CantinaPrintWorker" /tr "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File \\"' . $wp . '\\" -ProjectDir \\"' . $pd . '\\"" /sc ONCE /st 00:00 /it 2>&1';
    $runTask    = 'schtasks /run /tn "CantinaPrintWorker" 2>&1';
    $outCreate  = executar_cmd($createTask);
    $outRun     = executar_cmd($runTask);

    $iniciou = stripos($outRun, 'SUCCESS') !== false || stripos($outRun, 'ÊXITO') !== false || stripos($outRun, 'com êxito') !== false || stripos($outCreate, 'SUCCESS') !== false || stripos($outCreate, 'criada') !== false || stripos($outCreate, 'updated') !== false || stripos($outCreate, 'atualizada') !== false;
    responder($iniciou, $iniciou ? 'Worker iniciado com sucesso' : 'Falha ao iniciar worker', [
        'iniciado' => $iniciou,
        'status'   => $iniciou ? 'iniciado' : 'falhou',
        'detalhe'  => trim($outCreate . ' | ' . $outRun)
    ]);
}

responder(false, 'Ação inválida', null, 404);

