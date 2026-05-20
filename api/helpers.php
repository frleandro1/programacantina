<?php
/**
 * Helper Functions para APIs
 * Funções compartilhadas entre todos os endpoints
 */

/**
 * Responder com JSON erro (simples)
 */
function json_error($msg) {
    echo json_encode(['sucesso' => false, 'mensagem' => $msg]);
    exit;
}

/**
 * Responder com JSON estruturado
 */
function json_response($sucesso, $mensagem, $dados = null) {
    echo json_encode([
        'sucesso' => $sucesso,
        'mensagem' => $mensagem,
        'dados' => $dados
    ]);
    exit;
}

/**
 * Ler JSON da requisição (POST/PUT/PATCH)
 */
function read_json_input() {
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        return [];
    }

    return $data;
}

/**
 * Validar token/autenticação
 * @param array $headers Headers da requisição
 * @return bool
 */
function validate_token($headers = []) {
    if (empty($headers)) {
        $headers = getallheaders();
    }
    
    $token = $headers['Authorization'] ?? $_GET['token'] ?? $_SESSION['token'] ?? null;
    
    // Se não tem token, continua (verificação pode ser feita por endpoint)
    return !empty($token);
}

/**
 * Get valor seguro de GET/POST
 */
function get_param($name, $default = null, $sanitize = true) {
    $value = $_GET[$name] ?? $_POST[$name] ?? $default;
    
    if ($sanitize && $value !== null) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    return $value;
}

/**
 * Validar método HTTP
 */
function require_method($allowed) {
    $allowed = is_array($allowed) ? $allowed : [$allowed];
    $allowed = array_map('strtoupper', $allowed);
    
    if (!in_array($_SERVER['REQUEST_METHOD'], $allowed, true)) {
        json_response(false, 'Método HTTP não permitido: ' . $_SERVER['REQUEST_METHOD']);
    }
}

/**
 * Log de erro (opcional)
 */
function log_error($message, $file = null) {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/api-errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[{$timestamp}] {$message}";
    
    if ($file) {
        $entry .= " (File: {$file})";
    }
    
    error_log($entry . PHP_EOL, 3, $log_file);
}

/**
 * Responder com sucesso e dados
 */
function json_success($mensagem = 'Operação realizada com sucesso', $dados = null) {
    json_response(true, $mensagem, $dados);
}

/**
 * Responder com erro
 */
function json_fail($mensagem = 'Erro ao processar requisição', $codigo = 400) {
    http_response_code($codigo);
    json_response(false, $mensagem);
}
?>
