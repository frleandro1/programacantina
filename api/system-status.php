<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../db/config.php';

$ok = true;
$mensagem = 'Sistema online';

if (!isset($conn) || $conn->connect_error) {
    $ok = false;
    $mensagem = 'Falha na conexao com banco';
}

echo json_encode([
    'sucesso' => $ok,
    'mensagem' => $mensagem,
    'timestamp' => date('c'),
    'app' => 'Programa Cantina',
    'url_login' => '/Cantina/login-menu.html'
], JSON_UNESCAPED_UNICODE);
