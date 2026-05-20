<?php
// Arquivo de diagnóstico rápido
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

$diagnostico = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'database' => [],
    'files' => [],
    'api_test' => []
];

// Teste de conexão com banco
try {
    require_once __DIR__ . '/db/config.php';
    
    if (isset($conn) && !$conn->connect_error) {
        $diagnostico['database']['status'] = 'ok';
        $diagnostico['database']['host'] = DB_HOST;
        $diagnostico['database']['database'] = DB_NAME;
        
        // Teste de tabelas
        $result = $conn->query("SHOW TABLES");
        $tables = [];
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        $diagnostico['database']['tables'] = $tables;
    } else {
        $diagnostico['database']['status'] = 'erro';
        $diagnostico['database']['mensagem'] = $conn->connect_error ?? 'Conexão indisponível';
    }
} catch (Exception $e) {
    $diagnostico['database']['status'] = 'erro';
    $diagnostico['database']['mensagem'] = $e->getMessage();
}

// Teste de arquivos
$arquivos = [
    'api/auth.php' => file_exists(__DIR__ . '/api/auth.php'),
    'api/system-status.php' => file_exists(__DIR__ . '/api/system-status.php'),
    'db/config.php' => file_exists(__DIR__ . '/db/config.php'),
];

foreach ($arquivos as $arquivo => $existe) {
    $diagnostico['files'][$arquivo] = $existe ? 'existe' : 'falta';
}

// Teste de API
$diagnostico['api_test']['auth_endpoint'] = 'POST /api/auth.php?action=login';
$diagnostico['api_test']['instrucoes'] = [
    'Teste com curl: curl -X POST http://localhost/Cantina/api/auth.php?action=login -H "Content-Type: application/json" -d \'{"username":"admin","password":"admin123"}\'',
    'Ou via navegador: POST para http://localhost/Cantina/api/auth.php?action=login'
];

echo json_encode($diagnostico, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
