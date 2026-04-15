<?php
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔍 Diagnóstico da API\n\n";

// Teste 1: Conexão com banco
echo "1️⃣ Testando conexão com banco...\n";
require_once __DIR__ . '/db/config.php';

if ($conn->connect_error) {
    die(json_encode(['erro' => 'Conexão com banco falhou: ' . $conn->connect_error]));
}
echo "✅ Banco conectado\n\n";

// Teste 2: Tabela de produtos existe?
echo "2️⃣ Verificando tabela 'produtos'...\n";
$result = $conn->query("SHOW TABLES LIKE 'produtos'");
if ($result->num_rows > 0) {
    echo "✅ Tabela 'produtos' existe\n\n";
} else {
    die(json_encode(['erro' => 'Tabela produtos não existe']));
}

// Teste 3: Estrutura da tabela
echo "3️⃣ Estrutura da tabela 'produtos':\n";
$result = $conn->query("DESCRIBE produtos");
$colunas = [];
while ($row = $result->fetch_assoc()) {
    $colunas[] = $row['Field'];
    echo "  - {$row['Field']} ({$row['Type']})\n";
}
echo "\n";

// Teste 4: Quantidade de produtos
echo "4️⃣ Produtos cadastrados:\n";
$result = $conn->query("SELECT COUNT(*) as total FROM produtos");
$row = $result->fetch_assoc();
$total = $row['total'];
echo "  Total: $total produtos\n\n";

// Teste 5: Listar produtos
echo "5️⃣ Listando primeiro produtos:\n";
$result = $conn->query("SELECT * FROM produtos LIMIT 3");
while ($row = $result->fetch_assoc()) {
    echo "  - ID: {$row['id_produto']}, Nome: {$row['nome']}\n";
}
echo "\n";

// Teste 6: Query de listar (como na API)
echo "6️⃣ Simulando query da API:\n";
$sql = "SELECT id_produto, nome, tipo_categoria, preco_venda, preco_custo, estoque_atual, status, imagem, data_criacao, data_atualizacao FROM produtos ORDER BY data_criacao DESC";
$result = $conn->query($sql);

if (!$result) {
    echo "❌ Erro na query: " . $conn->error . "\n";
} else {
    $dados = [];
    while ($row = $result->fetch_assoc()) {
        $dados[] = [
            'id' => (int)$row['id_produto'],
            'nome' => $row['nome']
        ];
    }
    echo "✅ Query executada. " . count($dados) . " produtos\n";
    echo json_encode(['sucesso' => true, 'dados' => $dados], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
