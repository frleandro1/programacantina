<?php
require 'db/config.php';

echo "<h1>🧪 TESTE DIRETO DA API DE LOGIN</h1>\n\n";

// Simular POST request
$username = 'admin';
$password = 'admin123';

echo "<h2>Tentando login com:</h2>\n";
echo "<p>Username: <strong>$username</strong></p>\n";
echo "<p>Password: <strong>$password</strong></p>\n\n";

// Copiar a lógica de auth.php
function verify_password($plain, $hash) {
    if (!$hash) return false;
    if (preg_match('/^\$2[aby]\$/', $hash)) { // bcrypt
        return password_verify($plain, $hash);
    }
    if (strlen($hash) === 64 && ctype_xdigit($hash)) { // sha256 hex
        return hash('sha256', $plain) === strtolower($hash);
    }
    if (strlen($hash) === 32 && ctype_xdigit($hash)) { // md5 hex
        echo "<p>🔍 Comparando MD5:</p>\n";
        echo "<p>  Hash armazenado: <code>" . strtolower($hash) . "</code></p>\n";
        echo "<p>  Hash da senha: <code>" . md5($plain) . "</code></p>\n";
        $result = md5($plain) === strtolower($hash);
        echo "<p>  Resultado: " . ($result ? "✅ MATCH" : "❌ NÃO COMBINA") . "</p>\n";
        return $result;
    }
    return hash_equals($hash, $plain); // fallback texto puro
}

// Query
$stmt = $conn->prepare('SELECT id_usuario, login, senha, papel, nome FROM usuarios WHERE login = ? LIMIT 1');
if (!$stmt) {
    echo "<p style='color: red;'>❌ Erro na preparação: " . $conn->error . "</p>\n";
    exit;
}

$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo "<h2>Usuário encontrado no banco:</h2>\n";
    echo "<p>ID: " . $row['id_usuario'] . "</p>\n";
    echo "<p>Login: " . $row['login'] . "</p>\n";
    echo "<p>Nome: " . $row['nome'] . "</p>\n";
    echo "<p>Papel: " . $row['papel'] . "</p>\n";
    echo "<p>Senha (hash): <code>" . substr($row['senha'], 0, 20) . "...</code></p>\n\n";
    
    echo "<h2>Verificação de Senha:</h2>\n";
    if (verify_password($password, $row['senha'])) {
        echo "<p style='color: green; background: #d4edda; padding: 15px; border-radius: 5px;'>\n";
        echo "✅ <strong>SENHA CORRETA!</strong><br><br>\n";
        echo "Você deveria conseguir fazer login em: <a href='index.html'>http://localhost/Cantina/</a>\n";
        echo "</p>\n";
    } else {
        echo "<p style='color: red; background: #f8d7da; padding: 15px; border-radius: 5px;'>\n";
        echo "❌ <strong>SENHA INCORRETA!</strong><br><br>\n";
        echo "A senha não corresponde ao hash armazenado.\n";
        echo "</p>\n";
    }
} else {
    echo "<p style='color: red;'>❌ Usuário '$username' não encontrado no banco</p>\n";
}

$stmt->close();
?>
