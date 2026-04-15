<?php
require_once __DIR__ . '/db/config.php';

// Ver o hash armazenado
$stmt = $conn->prepare('SELECT login, senha FROM usuarios WHERE login = ? LIMIT 1');
$stmt->bind_param('s', $login);
$login = 'admin';
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    echo "Login: " . $user['login'] . "\n";
    echo "Hash armazenado: " . $user['senha'] . "\n";
    
    // Testar alguns senhas comuns
    $senhas_teste = ['123456', 'admin', 'cantina', ''];
    echo "\nTentando senhas:\n";
    foreach ($senhas_teste as $senha) {
        $hash_teste = hash('sha256', $senha);
        if ($hash_teste === $user['senha']) {
            echo "✅ Encontrado! Senha: '$senha'\n";
        }
    }
} else {
    echo "Usuário não encontrado!";
}

$stmt->close();
$conn->close();
?>
