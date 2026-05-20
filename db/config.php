<?php
// Configuração do banco de dados
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'programa_cantina');

// Garantir que os headers JSON sejam sempre enviados para requisições de API
$isApiRequest = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
if ($isApiRequest) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    $errorMsg = 'Erro ao conectar ao banco de dados: ' . $conn->connect_error;
    
    if ($isApiRequest) {
        // Garantir saída limpa antes de JSON
        if (ob_get_length() > 0) {
            ob_end_clean();
        }
        http_response_code(503);
        echo json_encode([
            'sucesso' => false,
            'erro' => 'database_connection_error',
            'mensagem' => $errorMsg . '. Certifique-se de que MySQL está rodando.'
        ]);
    } else {
        echo '<h1>Erro de Conexão</h1>';
        echo '<p>' . htmlspecialchars($errorMsg) . '</p>';
        echo '<p><strong>Solução:</strong> Inicie o MySQL no XAMPP Control Panel</p>';
    }
    exit;
}

$conn->set_charset('utf8mb4');
?>