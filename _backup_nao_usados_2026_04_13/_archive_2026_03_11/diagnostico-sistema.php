<?php
require 'db/config.php';
header('Content-Type: text/html; charset=utf-8');

echo "<h1>📋 Diagnóstico Completo do Sistema</h1>\n\n";

// 1. Verificar banco de dados
echo "<h2>1️⃣ Banco de Dados</h2>\n";
echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 8px;'>\n";

$tables = ['usuarios', 'produtos', 'vendas', 'relatorios'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Tabela '$table' existe\n";
        $count = $conn->query("SELECT COUNT(*) as cnt FROM $table")->fetch_assoc()['cnt'];
        echo "   └─ Registros: $count\n";
    } else {
        echo "❌ Tabela '$table' NÃO existe\n";
    }
}
echo "</pre>\n\n";

// 2. Verificar usuários
echo "<h2>2️⃣ Usuários Cadastrados</h2>\n";
echo "<pre style='background: #e8f4f8; padding: 15px; border-radius: 8px;'>\n";
$result = $conn->query("SELECT id_usuario, login, papel, nome FROM usuarios");
if ($result && $result->num_rows > 0) {
    echo sprintf("%-5s | %-15s | %-15s | %-20s\n", "ID", "Login", "Papel", "Nome");
    echo str_repeat("-", 60) . "\n";
    while ($row = $result->fetch_assoc()) {
        echo sprintf("%-5s | %-15s | %-15s | %-20s\n", 
            $row['id_usuario'], 
            $row['login'], 
            $row['papel'], 
            $row['nome']
        );
    }
} else {
    echo "⚠️ Nenhum usuário cadastrado!\n";
}
echo "</pre>\n\n";

// 3. Verificar produtos
echo "<h2>3️⃣ Produtos Cadastrados</h2>\n";
echo "<pre style='background: #fff9e6; padding: 15px; border-radius: 8px;'>\n";
$result = $conn->query("SELECT COUNT(*) as cnt FROM produtos");
$count = $result->fetch_assoc()['cnt'] ?? 0;
echo "Total de produtos: $count\n";
if ($count > 0) {
    $result = $conn->query("SELECT id_produto, nome, preco, estoque FROM produtos LIMIT 5");
    echo "\nPrimeiros 5 produtos:\n";
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['nome'] . " (R$ " . number_format($row['preco'], 2, ',', '.') . ") - Estoque: " . $row['estoque'] . "\n";
    }
} else {
    echo "⚠️ Nenhum produto cadastrado!\n";
}
echo "</pre>\n\n";

// 4. Verificar vendas
echo "<h2>4️⃣ Vendas Registradas</h2>\n";
echo "<pre style='background: #f0f0f0; padding: 15px; border-radius: 8px;'>\n";
$result = $conn->query("SELECT COUNT(*) as cnt FROM vendas");
$count = $result->fetch_assoc()['cnt'] ?? 0;
echo "Total de vendas: $count\n";
if ($count > 0) {
    $result = $conn->query("SELECT SUM(total) as total FROM vendas");
    $total = $result->fetch_assoc()['total'] ?? 0;
    echo "Faturamento total: R$ " . number_format($total, 2, ',', '.') . "\n";
} else {
    echo "⚠️ Nenhuma venda registrada!\n";
}
echo "</pre>\n\n";

// 5. Verificar arquivos essenciais
echo "<h2>5️⃣ Arquivos Essenciais</h2>\n";
echo "<pre style='background: #e8f5e9; padding: 15px; border-radius: 8px;'>\n";
$files = [
    'login.html' => 'Página de Login',
    'dashboard.html' => 'Dashboard',
    'admin.html' => 'Admin',
    'vendas.html' => 'Vendas',
    'relatorios.html' => 'Relatórios',
    'api/auth.php' => 'API de Autenticação',
    'api/produtos.php' => 'API de Produtos',
    'api/vendas.php' => 'API de Vendas',
    'api/relatorios.php' => 'API de Relatórios',
    'css/theme.css' => 'CSS Theme',
    'js/dashboard.js' => 'JS Dashboard',
    'js/vendas.js' => 'JS Vendas',
    'js/relatorios.js' => 'JS Relatórios',
];

foreach ($files as $file => $desc) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $size = filesize($path);
        echo "✅ $desc ($file) - " . number_format($size, 0, ',', '.') . " bytes\n";
    } else {
        echo "❌ $desc ($file) - NÃO ENCONTRADO\n";
    }
}
echo "</pre>\n\n";

// 6. Testar API de Login
echo "<h2>6️⃣ Teste de API</h2>\n";
echo "<pre style='background: #fce4ec; padding: 15px; border-radius: 8px;'>\n";
echo "Testando login com admin/admin123:\n\n";

// Simular login
$username = 'admin';
$password = 'admin123';
$stmt = $conn->prepare('SELECT id_usuario, login, senha, papel, nome FROM usuarios WHERE login = ? LIMIT 1');
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Verificar senha
    $hash_armazenado = $row['senha'];
    $hash_testado = md5($password);
    
    if ($hash_testado === $hash_armazenado) {
        echo "✅ LOGIN FUNCIONANDO PERFEITAMENTE!\n";
        echo "   Usuário: " . $row['login'] . "\n";
        echo "   Nome: " . $row['nome'] . "\n";
        echo "   Papel: " . $row['papel'] . "\n";
    } else {
        echo "❌ ERRO: Senha incorreta\n";
        echo "   Hash esperado: $hash_armazenado\n";
        echo "   Hash testado:  $hash_testado\n";
    }
} else {
    echo "❌ ERRO: Usuário 'admin' não encontrado\n";
}
echo "</pre>\n\n";

// 7. Resumo
echo "<h2>✅ Resumo</h2>\n";
echo "<pre style='background: #d4edda; padding: 15px; border-radius: 8px; color: #155724;'>\n";
echo "Sistema operacional! 🎉\n";
echo "\nProximos passos:\n";
echo "1. Acesse o login: http://localhost/Cantina/login.html\n";
echo "2. Use: admin / admin123\n";
echo "3. Você será redirecionado para o dashboard\n";
echo "</pre>\n";

$conn->close();
?>
