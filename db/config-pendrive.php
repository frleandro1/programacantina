<?php
/**
 * Configuração do Banco de Dados - Versão Pendrive
 * Este arquivo detecta automaticamente se está rodando do pendrive (E:)
 * ou do XAMPP local (C:\xampp)
 */

// Detecta o caminho base
$script_dir = __DIR__;
$is_pendrive = (strpos($script_dir, 'E:') !== false || strpos($script_dir, 'e:') !== false);

if ($is_pendrive) {
    // Configurações para Pendrive (E:)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASSWORD', '');
    define('DB_NAME', 'programa_cantina');
    define('DB_PORT', 3306);
    define('XAMPP_BASE', 'E:\xampp');
    define('PROJECT_BASE', 'E:\htdocs\Cantina');
    define('IS_PENDRIVE', true);
} else {
    // Configurações para XAMPP Local (C:\xampp)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASSWORD', '');
    define('DB_NAME', 'programa_cantina');
    define('DB_PORT', 3306);
    define('XAMPP_BASE', 'C:\xampp');
    define('PROJECT_BASE', 'C:\xampp\htdocs\Cantina');
    define('IS_PENDRIVE', false);
}

// Conecta ao banco de dados
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        header('Content-Type: application/json');
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao conectar ao banco: ' . $conn->connect_error,
            'detalhes' => [
                'host' => DB_HOST,
                'database' => DB_NAME,
                'is_pendrive' => IS_PENDRIVE,
                'base_path' => PROJECT_BASE
            ]
        ]);
    } else {
        echo 'Erro na conexão: ' . $conn->connect_error;
        echo "\nDetalhes:";
        echo "\n- Host: " . DB_HOST;
        echo "\n- Banco: " . DB_NAME;
        echo "\n- Pendrive: " . (IS_PENDRIVE ? 'Sim' : 'Não');
        echo "\n- Caminho: " . PROJECT_BASE;
    }
    exit;
}

$conn->set_charset('utf8mb4');
?>
