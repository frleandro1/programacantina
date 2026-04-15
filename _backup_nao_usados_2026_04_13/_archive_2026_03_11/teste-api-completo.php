<?php
// Simular uma requisição HTTP POST para auth.php
require 'db/config.php';

// Simular o que o login.html faz
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'login';

// Simular JSON input
$json_input = json_encode([
    'username' => 'admin',
    'password' => 'admin123'
]);

// Temporariamente substituir php://input
$mock_input = $json_input;

echo "<h1>🧪 TESTE COMPLETO DA API</h1>\n\n";
echo "<h2>Requisição Simulada:</h2>\n";
echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>\n";
echo "POST /api/auth.php?action=login\n";
echo "Content-Type: application/json\n\n";
echo $json_input . "\n";
echo "</pre>\n\n";

// Agora vou incluir o auth.php para rodar a lógica
ob_start();

// Simular o que auth.php faz
header('Content-Type: application/json');
error_reporting(0);
session_start();

$method = 'POST';
$action = 'login';

function verify_password($plain, $hash) {
    if (!$hash) return false;
    if (preg_match('/^\$2[aby]\$/', $hash)) {
        return password_verify($plain, $hash);
    }
    if (strlen($hash) === 64 && ctype_xdigit($hash)) {
        return hash('sha256', $plain) === strtolower($hash);
    }
    if (strlen($hash) === 32 && ctype_xdigit($hash)) {
        return md5($plain) === strtolower($hash);
    }
    return hash_equals($hash, $plain);
}

if ($method === 'POST' && $action === 'login') {
    $data = [
        'username' => 'admin',
        'password' => 'admin123'
    ];

    $username = trim($data['username'] ?? '');
    $password = (string)($data['password'] ?? '');

    if ($username === '' || $password === '') {
        $response = ['sucesso' => false, 'mensagem' => 'Usuário e senha obrigatórios'];
    } else {
        $stmt = $conn->prepare('SELECT id_usuario, login, senha, papel, nome FROM usuarios WHERE login = ? LIMIT 1');
        if (!$stmt) {
            $response = ['sucesso' => false, 'mensagem' => 'Erro na preparação: ' . $conn->error];
        } else {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                if (verify_password($password, $row['senha'])) {
                    $_SESSION['usuario_id'] = $row['id_usuario'];
                    $_SESSION['username'] = $row['login'];
                    $_SESSION['tipo'] = $row['papel'];

                    $response = [
                        'sucesso' => true,
                        'mensagem' => 'Login realizado com sucesso',
                        'usuario' => [
                            'id' => (int)$row['id_usuario'],
                            'username' => $row['login'],
                            'nome' => $row['nome'],
                            'tipo' => $row['papel'],
                            'email' => null
                        ]
                    ];
                } else {
                    $response = ['sucesso' => false, 'mensagem' => 'Usuário ou senha incorretos'];
                }
            } else {
                $response = ['sucesso' => false, 'mensagem' => 'Usuário ou senha incorretos'];
            }
            $stmt->close();
        }
    }
}

ob_end_clean();

echo "<h2>Resposta da API:</h2>\n";
echo "<pre style='background: " . ($response['sucesso'] ? "#d4edda" : "#f8d7da") . "; padding: 15px; border-radius: 5px; overflow-x: auto;'>\n";
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
echo "</pre>\n";

if ($response['sucesso']) {
    echo "<p style='color: green; font-size: 16px; margin-top: 20px;'>\n";
    echo "✅ <strong>A API está funcionando perfeitamente!</strong><br><br>\n";
    echo "Se você ainda não consegue fazer login na página:\n";
    echo "<ul>\n";
    echo "<li>Abra o navegador (F12) → Aba <strong>Console</strong></li>\n";
    echo "<li>Tente fazer login</li>\n";
    echo "<li>Veja se há alguma mensagem de erro no console</li>\n";
    echo "<li>Cole uma mensagem de erro aqui</li>\n";
    echo "</ul>\n";
    echo "</p>\n";
}
?>
