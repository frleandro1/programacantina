<?php
// Script de teste de conexão com banco de dados

require 'db/config.php';

echo "=== TESTE DE CONEXÃO COM BANCO DE DADOS ===\n\n";

// 1. Testar conexão
echo "1. Status da conexão:\n";
if ($conn->connect_error) {
    echo "❌ ERRO: " . $conn->connect_error . "\n";
    exit;
} else {
    echo "✅ Conexão OK\n";
}

// 2. Verificar banco de dados
echo "\n2. Banco de dados ativo: " . DB_NAME . "\n";

// 3. Verificar se as tabelas existem
echo "\n3. Verificando tabelas:\n";
$tables = ['usuarios', 'produtos', 'vendas', 'movimentacoes'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "   ✅ Tabela '$table' existe\n";
    } else {
        echo "   ❌ Tabela '$table' NÃO existe\n";
    }
}

// 4. Contar registros
echo "\n4. Contagem de registros:\n";
$result = $conn->query("SELECT COUNT(*) as total FROM usuarios");
if ($result) {
    $row = $result->fetch_assoc();
    echo "   - Usuários: " . $row['total'] . "\n";
} else {
    echo "   ❌ Erro ao contar usuários: " . $conn->error . "\n";
}

$result = $conn->query("SELECT COUNT(*) as total FROM produtos");
if ($result) {
    $row = $result->fetch_assoc();
    echo "   - Produtos: " . $row['total'] . "\n";
} else {
    echo "   ❌ Erro ao contar produtos\n";
}

// 5. Verificar charset
echo "\n5. Charset: " . $conn->get_charset()->charset . "\n";

echo "\n✅ Todas as verificações concluídas!\n";
echo "\nSe você está vendo 'Erro de conexão' nas páginas, tente:\n";
echo "1. Certifique-se de que o MySQL está rodando (ver XAMPP Control Panel)\n";
echo "2. Recarregue a página (Ctrl+F5)\n";
echo "3. Limpe o localStorage (F12 > Application > Local Storage > Delete)\n";
?>
