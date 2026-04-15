<?php
require __DIR__ . '/db/config.php';

echo "=== USUÁRIOS CADASTRADOS NO SISTEMA ===\n\n";

$result = $conn->query("SELECT id_usuario, login, papel, nome, senha FROM usuarios ORDER BY id_usuario");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id_usuario'] . "\n";
        echo "Login: " . $row['login'] . "\n";
        echo "Nome: " . $row['nome'] . "\n";
        echo "Papel: " . $row['papel'] . "\n";
        echo "---\n\n";
    }
} else {
    echo "Nenhum usuário encontrado\n";
}

$conn->close();
?>
