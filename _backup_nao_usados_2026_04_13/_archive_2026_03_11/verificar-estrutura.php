<?php
require 'db/config.php';

// Verificar estrutura real das tabelas
echo "=== ESTRUTURA REAL DO BANCO ===\n\n";

echo "TABELA USUARIOS:\n";
$result = $conn->query("DESCRIBE usuarios");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " (" . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . ")\n";
}

echo "\n\nTABELA PRODUTOS:\n";
$result = $conn->query("DESCRIBE produtos");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " (" . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . ")\n";
}

echo "\n\nTABELA VENDAS:\n";
$result = $conn->query("DESCRIBE vendas");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " (" . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . ")\n";
}

// Mostrar dados
echo "\n\n=== USUÁRIOS NO BANCO ===\n";
$result = $conn->query("SELECT * FROM usuarios");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

echo "\n\n=== PRIMEIROS 3 PRODUTOS ===\n";
$result = $conn->query("SELECT * FROM produtos LIMIT 3");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

$conn->close();
?>
