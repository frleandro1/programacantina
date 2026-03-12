<?php
// Mostrar o arquivo de log de erros se existir
$logFile = __DIR__ . '/logs/api-imagens.log';

echo "Verificando logs de erro...\n";
echo "Arquivo de log: $logFile\n";
echo "Existe? " . (file_exists($logFile) ? "SIM" : "NÃO") . "\n\n";

if (file_exists($logFile)) {
    echo "=== CONTEÚDO DO LOG ===\n";
    echo file_get_contents($logFile);
    echo "\n=== FIM DO LOG ===\n";
}

// Tentar acessar a API diretamente
echo "\n\nTestando api/imagens.php com action=list:\n";

require_once dirname(__DIR__, 2) . '/db/config.php';

// Testar a query diretamente
echo "Testando conexão com banco de dados...\n";
echo "Host: " . DB_HOST . "\n";
echo "User: " . DB_USER . "\n";
echo "Database: " . DB_NAME . "\n";

if ($conn->connect_error) {
    echo "Erro ao conectar: " . $conn->connect_error . "\n";
    exit;
}

echo "Conexão OK!\n\n";

// Testar a query
echo "Testando query:\n";
$sql = "SELECT id_produto, nome, imagem FROM produtos WHERE imagem IS NOT NULL AND imagem != '' ORDER BY id_produto";
echo "SQL: " . $sql . "\n\n";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "Erro ao preparar statement: " . $conn->error . "\n";
    exit;
}

echo "Statement preparado OK!\n";

if (!$stmt->execute()) {
    echo "Erro ao executar: " . $stmt->error . "\n";
    exit;
}

echo "Execute OK!\n";

$resultado = $stmt->get_result();
if (!$resultado) {
    echo "Erro ao obter resultado: " . $stmt->error . "\n";
    exit;
}

echo "Get Result OK!\n\n";

$imagens = [];
while ($row = $resultado->fetch_assoc()) {
    $imagens[] = [
        'produto_id' => $row['id_produto'],
        'nome' => $row['nome'],
        'imagem' => $row['imagem'],
        'url' => '/Cantina/uploads/produtos/' . $row['imagem']
    ];
}

$stmt->close();

echo "Total de registros: " . count($imagens) . "\n";
echo "Dados:\n";
print_r($imagens);

echo "\n\nJSON válido:\n";
echo json_encode([
    'sucesso' => true,
    'total' => count($imagens),
    'imagens' => $imagens
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
