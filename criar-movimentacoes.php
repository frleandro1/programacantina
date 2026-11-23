<?php
/**
 * Criar a tabela movimentacoes que está faltando
 */

$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'programa_cantina';

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

if ($conn->connect_error) {
    die('❌ Erro: ' . $conn->connect_error);
}

echo "🔧 Criando tabela movimentacoes...<br><br>";

$sql = "CREATE TABLE IF NOT EXISTS movimentacoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    produto_id INT NOT NULL,
    tipo VARCHAR(20),
    quantidade INT,
    descricao TEXT,
    usuario_id INT,
    data_movimentacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "✅ Tabela movimentacoes criada com sucesso!<br>";
} else {
    echo "❌ Erro ao criar tabela: " . $conn->error . "<br>";
}

// Verificar todas as tabelas
echo "<br>📊 Tabelas atuais:<br>";
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    echo "  ✅ " . $row[0] . "<br>";
}

$conn->close();
?>
