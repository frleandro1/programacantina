<?php
require dirname(__DIR__, 2) . '/db/config.php';

echo "=== CRIAR/ATUALIZAR USUÁRIOS ===\n\n";

// 1. Criar usuário vendedor
$usuarios = [
    ['login' => 'joao', 'senha' => 'vendedor123', 'nome' => 'João Rodrigues', 'papel' => 'atendente'],
    ['login' => 'maria', 'senha' => 'vendedor123', 'nome' => 'Maria Silva', 'papel' => 'atendente'],
    ['login' => 'pedro', 'senha' => 'vendedor123', 'nome' => 'Pedro Santos', 'papel' => 'atendente'],
];

foreach ($usuarios as $user) {
    $login = $user['login'];
    $hash = md5($user['senha']);
    $nome = $user['nome'];
    $papel = $user['papel'];
    
    // Verificar se existe
    $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE login = ?");
    $stmt->bind_param('s', $login);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Atualizar
        $stmt->close();
        $stmt = $conn->prepare("UPDATE usuarios SET senha = ?, papel = ?, nome = ? WHERE login = ?");
        $stmt->bind_param('ssss', $hash, $papel, $nome, $login);
        if ($stmt->execute()) {
            echo "✅ Usuário '$login' ATUALIZADO\n";
        } else {
            echo "❌ Erro ao atualizar '$login': " . $stmt->error . "\n";
        }
    } else {
        // Inserir novo
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO usuarios (login, senha, nome, papel, status) VALUES (?, ?, ?, ?, 'ativo')");
        $stmt->bind_param('ssss', $login, $hash, $nome, $papel);
        if ($stmt->execute()) {
            echo "✅ Usuário '$login' CRIADO\n";
        } else {
            echo "❌ Erro ao criar '$login': " . $stmt->error . "\n";
        }
    }
    
    $stmt->close();
}

echo "\n=== CREDENCIAIS FINAIS ===\n\n";
echo "👨‍💼 ADMINISTRADOR (Gestor):\n";
echo "   Login: admin\n";
echo "   Senha: admin123\n";
echo "   Papel: Gestor\n\n";

echo "🛒 VENDEDORES (Atendentes):\n";
echo "   Login: joao\n";
echo "   Senha: vendedor123\n";
echo "   Papel: Atendente (Vendedor)\n\n";

echo "   Login: maria\n";
echo "   Senha: vendedor123\n";
echo "   Papel: Atendente (Vendedor)\n\n";

echo "   Login: pedro\n";
echo "   Senha: vendedor123\n";
echo "   Papel: Atendente (Vendedor)\n\n";

$conn->close();
?>
