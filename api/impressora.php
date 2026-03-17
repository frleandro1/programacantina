<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
ini_set('display_errors', '0');
error_reporting(E_ALL);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
$text = Get-Content -Raw -Encoding UTF8 -Path '__ARQUIVO__'
$sent = $false
try {
    for ($i = 0; $i -lt __COPIAS__; $i++) { $text | Out-Printer -Name $printer; Start-Sleep -Milliseconds 200 }
    $sent = $true
    Write-Output ('OK:' + $printer)
} catch {
    # Fallback: envio direto para LPT1 (comum em impressora térmica POS)
    try {
        $bytes = [System.IO.File]::ReadAllBytes('__ARQUIVO__')
        $stream = [System.IO.File]::Open('LPT1', [System.IO.FileMode]::OpenOrCreate, [System.IO.FileAccess]::Write)
        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Close()
        $sent = $true
        Write-Output 'OK:LPT1'
    } catch {
        Write-Output ('ERRO:' + $_.Exception.Message)
        exit 3
    }
}

if (-not $sent) {
    Write-Output 'ERRO:FALHA_DESCONHECIDA'
    exit 4
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

    $powershellFalhou = ($raw === '' || stripos($raw, 'ERRO:SEM_IMPRESSORA') !== false || stripos($raw, 'ERRO:') !== false || stripos($raw, 'out-printer') !== false || stripos($raw, 'acesso negado') !== false);
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

responder(false, 'Ação inválida', null, 404);
