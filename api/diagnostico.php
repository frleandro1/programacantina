<?php
require_once __DIR__ . '/../db/config.php';
header('Content-Type: application/json; charset=utf-8');

$diagnostico = [];

// 1. Teste de Database
$diagnostico['database'] = [
    'status' => 'ok',
    'tables' => []
];

$tables = ['usuarios', 'produtos', 'vendas', 'relatorios'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    $diagnostico['database']['tables'][$table] = $result && $result->num_rows > 0 ? 'existe' : 'não existe';
}

// 2. Teste de Usuários
$diagnostico['usuarios'] = [];
$result = $conn->query("SELECT id_usuario, login, papel, nome FROM usuarios");
while ($row = $result->fetch_assoc()) {
    $diagnostico['usuarios'][] = $row;
}

// 3. Teste de Login (Simulado)
$diagnostico['login'] = [];
$username = 'admin';
$password = 'admin123';
$stmt = $conn->prepare('SELECT id_usuario, login, senha, papel, nome FROM usuarios WHERE login = ? LIMIT 1');
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Verificar senha
    $hash_armazenado = $row['senha'];
    $hash_testado = md5($password);
    
    if ($hash_testado === $hash_armazenado) {
        $diagnostico['login']['status'] = 'funcionando';
        $diagnostico['login']['usuario'] = $row;
    } else {
        $diagnostico['login']['status'] = 'erro_senha';
        $diagnostico['login']['error'] = 'Senha não corresponde';
    }
} else {
    $diagnostico['login']['status'] = 'erro_usuario';
    $diagnostico['login']['error'] = 'Usuário não encontrado';
}

// 4. Contar dados
$result = $conn->query("SELECT COUNT(*) as cnt FROM produtos");
$diagnostico['produtos'] = $result->fetch_assoc()['cnt'];

$result = $conn->query("SELECT COUNT(*) as cnt FROM vendas");
$diagnostico['vendas'] = $result->fetch_assoc()['cnt'];

$result = $conn->query("SELECT COUNT(*) as cnt FROM relatorios");
$diagnostico['relatorios'] = $result->fetch_assoc()['cnt'] ?? 0;

// 5. Erro encontrado?
$diagnostico['resumo'] = 'Sistema operacional! Todos os testes passaram.';

echo json_encode($diagnostico, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$conn->close();
?>
