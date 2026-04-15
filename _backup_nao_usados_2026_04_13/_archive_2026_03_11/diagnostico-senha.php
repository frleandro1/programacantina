<?php
require 'db/config.php';

echo "<h1>🔍 DIAGNÓSTICO DETALHADO DE LOGIN</h1>\n\n";

// 1. Listar usuários e suas senhas (hash)
echo "<h2>1️⃣ Usuários e Senhas Armazenadas:</h2>\n";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Login</th><th>Nome</th><th>Papel</th><th>Senha (Hash)</th><th>Tipo de Hash</th></tr>\n";

$result = $conn->query("SELECT id_usuario, login, nome, papel, senha FROM usuarios");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $hashType = '';
        if (preg_match('/^\$2[aby]\$/', $row['senha'])) {
            $hashType = '✅ bcrypt';
        } elseif (strlen($row['senha']) === 64) {
            $hashType = '✅ SHA256 (64 chars)';
        } elseif (strlen($row['senha']) === 32) {
            $hashType = '✅ MD5 (32 chars)';
        } else {
            $hashType = '⚠️ Texto plano ou desconhecido';
        }
        
        echo "<tr>";
        echo "<td>" . $row['id_usuario'] . "</td>";
        echo "<td><strong>" . $row['login'] . "</strong></td>";
        echo "<td>" . $row['nome'] . "</td>";
        echo "<td>" . $row['papel'] . "</td>";
        echo "<td><code style='font-size: 11px;'>" . substr($row['senha'], 0, 20) . "...</code></td>";
        echo "<td>" . $hashType . "</td>";
        echo "</tr>\n";
    }
} else {
    echo "<tr><td colspan='6'>❌ Nenhum usuário encontrado</td></tr>\n";
}
echo "</table>\n\n";

// 2. Testar senhas comuns
echo "<h2>2️⃣ Testando Senhas Comuns:</h2>\n";
echo "<p style='background: #fff3cd; padding: 10px; border-radius: 5px;'>\n";
echo "<strong>⚠️ IMPORTANTE:</strong> Se nenhuma senha funcionar, you pode redefinir a senha do admin.\n";
echo "</p>\n\n";

$senhas_testes = ['admin123', 'admin', '123456', '12345678', 'cantina', 'password', ''];

$result = $conn->query("SELECT id_usuario, login, nome, papel, senha FROM usuarios WHERE login = 'admin' LIMIT 1");
if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    echo "<h3>Testando senhas para usuário: <strong>" . $admin['login'] . "</strong></h3>\n";
    echo "<ul>\n";
    
    foreach ($senhas_testes as $test_senha) {
        $resultado = 'FALHA';
        $classe = 'style="color: red;"';
        
        // Testar com bcrypt
        if (preg_match('/^\$2[aby]\$/', $admin['senha'])) {
            if (password_verify($test_senha, $admin['senha'])) {
                $resultado = '✅ SUCESSO (bcrypt)';
                $classe = 'style="color: green; font-weight: bold;"';
            }
        }
        // Testar com SHA256
        elseif (strlen($admin['senha']) === 64 && ctype_xdigit($admin['senha'])) {
            if (hash('sha256', $test_senha) === strtolower($admin['senha'])) {
                $resultado = '✅ SUCESSO (SHA256)';
                $classe = 'style="color: green; font-weight: bold;"';
            }
        }
        // Testar com MD5
        elseif (strlen($admin['senha']) === 32 && ctype_xdigit($admin['senha'])) {
            if (md5($test_senha) === strtolower($admin['senha'])) {
                $resultado = '✅ SUCESSO (MD5)';
                $classe = 'style="color: green; font-weight: bold;"';
            }
        }
        // Testar texto plano
        else {
            if ($admin['senha'] === $test_senha) {
                $resultado = '✅ SUCESSO (Texto plano)';
                $classe = 'style="color: green; font-weight: bold;"';
            }
        }
        
        echo "<li $classe>Senha: <code>" . ($test_senha === '' ? '(vazia)' : $test_senha) . "</code> → $resultado</li>\n";
    }
    
    echo "</ul>\n";
} else {
    echo "<p>❌ Usuário 'admin' não encontrado</p>\n";
}

// 3. Script para redefinir senha
echo "<h2>3️⃣ Redefinir Senha do Admin:</h2>\n";
echo "<form method='POST' style='background: #f9f9f9; padding: 15px; border-radius: 5px;'>\n";
echo "<p>\n";
echo "  <label for='nova_senha'>Nova Senha:</label><br>\n";
echo "  <input type='password' id='nova_senha' name='nova_senha' placeholder='Digite uma nova senha' required style='padding: 8px; width: 250px;'>\n";
echo "</p>\n";
echo "<button type='submit' name='redefinir' style='padding: 10px 20px; background: #ff7a45; color: white; border: none; border-radius: 5px; cursor: pointer;'>Redefinir Senha</button>\n";
echo "</form>\n";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redefinir'])) {
    $nova_senha = $_POST['nova_senha'] ?? '';
    
    if (strlen($nova_senha) < 4) {
        echo "<p style='color: red;'>❌ Senha muito curta (mínimo 4 caracteres)</p>\n";
    } else {
        // Usar SHA256 (compatível com o sistema)
        $senha_hash = hash('sha256', $nova_senha);
        
        $stmt = $conn->prepare('UPDATE usuarios SET senha = ? WHERE login = ?');
        $stmt->bind_param('ss', $senha_hash, 'admin');
        
        if ($stmt->execute()) {
            echo "<p style='color: green; background: #d4edda; padding: 10px; border-radius: 5px;'>\n";
            echo "✅ <strong>Senha do admin redefinida com sucesso!</strong><br><br>\n";
            echo "Login: <code>admin</code><br>\n";
            echo "Senha: <code>" . htmlspecialchars($nova_senha) . "</code>\n";
            echo "</p>\n";
        } else {
            echo "<p style='color: red;'>❌ Erro ao redefinir senha: " . $stmt->error . "</p>\n";
        }
    }
}

echo "\n<hr>\n";
echo "<h2>💡 Próximos Passos:</h2>\n";
echo "<ol>\n";
echo "<li>Se encontrou uma senha que funciona acima, use-a para fazer login em <a href='index.html'>index.html</a></li>\n";
echo "<li>Se nenhuma senha funciona, use o formulário acima para redefinir a senha do admin</li>\n";
echo "<li>Após redefinir, tente fazer login com a nova senha</li>\n";
echo "</ol>\n";
?>
