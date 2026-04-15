<?php
require_once dirname(__DIR__, 2) . '/db/config.php';

$login = 'admin';
$senha = '123456';
$nome = 'Administrador';
$papel = 'admin';

// Hash SHA256 da senha
$hash = hash('sha256', $senha);

// Verificar se já existe
$stmt = $conn->prepare('SELECT id_usuario FROM usuarios WHERE login = ? LIMIT 1');
$stmt->bind_param('s', $login);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo "Usuário admin já existe!";
} else {
    // Inserir novo usuário
    $stmt->close();
    $stmt = $conn->prepare('INSERT INTO usuarios (login, senha, papel, nome) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $login, $hash, $papel, $nome);
    
    if ($stmt->execute()) {
        echo "✅ Usuário admin criado com sucesso!";
    } else {
        echo "❌ Erro: " . $stmt->error;
    }
}

$stmt->close();
$conn->close();
?>
