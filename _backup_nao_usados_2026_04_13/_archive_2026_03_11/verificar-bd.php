<?php
require_once __DIR__ . '/db/config.php';

echo "<h2>Teste de Banco de Dados - Programa Cantina</h2>";

// Testar conexão
if ($conn->connect_error) {
    echo "<p style='color: red;'><strong>❌ Erro na conexão:</strong> " . $conn->connect_error . "</p>";
    exit;
}

echo "<p style='color: green;'><strong>✅ Conexão estabelecida com sucesso!</strong></p>";
echo "<p>Banco de dados: <strong>" . DB_NAME . "</strong></p>";

// Verificar estrutura da tabela usuarios
echo "<h3>📊 Estrutura da Tabela 'usuarios':</h3>";
$estructura = $conn->query('DESCRIBE usuarios');
if ($estructura) {
    echo "<table border='1' cellpadding='10' style='margin-bottom: 20px;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($col = $estructura->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . ($col['Default'] ?? '-') . "</td>";
        echo "<td>" . ($col['Extra'] ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Contar usuários
$resultUsuarios = $conn->query('SELECT COUNT(*) as total FROM usuarios');
$usuariosRow = $resultUsuarios->fetch_assoc();
echo "<h3>👤 Usuários: " . $usuariosRow['total'] . "</h3>";

// Listar usuários (usando SELECT * para ver todas as colunas)
$users = $conn->query('SELECT * FROM usuarios');
if ($users && $users->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='margin-bottom: 20px;'>";
    $firstRow = true;
    while ($user = $users->fetch_assoc()) {
        if ($firstRow) {
            echo "<tr>";
            foreach (array_keys($user) as $key) {
                echo "<th>" . $key . "</th>";
            }
            echo "</tr>";
            $firstRow = false;
        }
        echo "<tr>";
        foreach ($user as $value) {
            echo "<td>" . ($value ?? '-') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Nenhum usuário encontrado.</p>";
}

// Verificar estrutura da tabela produtos
echo "<h3>📊 Estrutura da Tabela 'produtos':</h3>";
$estructuraProdutos = $conn->query('DESCRIBE produtos');
if ($estructuraProdutos) {
    echo "<table border='1' cellpadding='10' style='margin-bottom: 20px;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($col = $estructuraProdutos->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . ($col['Default'] ?? '-') . "</td>";
        echo "<td>" . ($col['Extra'] ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Contar produtos
$resultProdutos = $conn->query('SELECT COUNT(*) as total FROM produtos');
$produtosRow = $resultProdutos->fetch_assoc();
echo "<h3>📦 Produtos: " . $produtosRow['total'] . "</h3>";

// Listar produtos (usando SELECT * para ver todas as colunas)
$prods = $conn->query('SELECT * FROM produtos');
if ($prods && $prods->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    $firstRow = true;
    while ($prod = $prods->fetch_assoc()) {
        if ($firstRow) {
            echo "<tr>";
            foreach (array_keys($prod) as $key) {
                echo "<th>" . $key . "</th>";
            }
            echo "</tr>";
            $firstRow = false;
        }
        echo "<tr>";
        foreach ($prod as $value) {
            echo "<td>" . ($value ?? '-') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Nenhum produto encontrado.</p>";
}

$conn->close();
?>
