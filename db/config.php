<?php
// Configuração do banco de dados
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'programa_cantina');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        header('Content-Type: application/json');
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao conectar ao banco: ' . $conn->connect_error
        ]);
    } else {
        echo 'Erro na conexão: ' . $conn->connect_error;
    }
    exit;
}

$conn->set_charset('utf8mb4');
?>