<?php
require_once __DIR__ . '/../db/config.php';
header('Content-Type: application/json; charset=utf-8');

function verify_password_diagnostico($plain, $hash) {
    if (!$hash) {
        return false;
    }
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

function tabela_existe($conn, $table) {
    $table = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

$diagnostico = [
    'sucesso' => true,
    'database' => [
        'status' => 'ok',
        'tables' => []
    ],
    'usuarios' => [],
    'login' => [],
    'produtos' => 0,
    'vendas' => 0,
    'relatorios' => 0,
    'resumo' => 'Sistema operacional! Todos os testes passaram.'
];

$tables = ['usuarios', 'produtos', 'vendas', 'relatorios'];
foreach ($tables as $table) {
    $existe = tabela_existe($conn, $table);
    $diagnostico['database']['tables'][$table] = $existe ? 'existe' : 'não existe';
}

if (!isset($conn) || $conn->connect_error) {
    $diagnostico['sucesso'] = false;
    $diagnostico['database']['status'] = 'erro';
    $diagnostico['resumo'] = 'Falha na conexão com o banco de dados.';
    echo json_encode($diagnostico, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($diagnostico['database']['tables']['usuarios'] === 'existe') {
    $result = $conn->query("SELECT id_usuario, login, papel, nome FROM usuarios");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $diagnostico['usuarios'][] = $row;
        }
    }

    $username = 'admin';
    $password = 'admin123';
    $stmt = $conn->prepare('SELECT id_usuario, login, senha, papel, nome FROM usuarios WHERE login = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (verify_password_diagnostico($password, $row['senha'])) {
                $diagnostico['login']['status'] = 'funcionando';
                $diagnostico['login']['usuario'] = [
                    'id_usuario' => $row['id_usuario'],
                    'login' => $row['login'],
                    'papel' => $row['papel'],
                    'nome' => $row['nome']
                ];
            } else {
                $diagnostico['sucesso'] = false;
                $diagnostico['login']['status'] = 'erro_senha';
                $diagnostico['login']['error'] = 'Senha de teste não corresponde ao usuário admin';
            }
        } else {
            $diagnostico['sucesso'] = false;
            $diagnostico['login']['status'] = 'erro_usuario';
            $diagnostico['login']['error'] = 'Usuário admin não encontrado';
        }
        $stmt->close();
    }
}

if ($diagnostico['database']['tables']['produtos'] === 'existe') {
    $result = $conn->query("SELECT COUNT(*) as cnt FROM produtos");
    if ($result) {
        $row = $result->fetch_assoc();
        $diagnostico['produtos'] = (int)($row['cnt'] ?? 0);
    }
}

if ($diagnostico['database']['tables']['vendas'] === 'existe') {
    $result = $conn->query("SELECT COUNT(*) as cnt FROM vendas");
    if ($result) {
        $row = $result->fetch_assoc();
        $diagnostico['vendas'] = (int)($row['cnt'] ?? 0);
    }
}

if ($diagnostico['database']['tables']['relatorios'] === 'existe') {
    $result = $conn->query("SELECT COUNT(*) as cnt FROM relatorios");
    if ($result) {
        $row = $result->fetch_assoc();
        $diagnostico['relatorios'] = (int)($row['cnt'] ?? 0);
    }
}

if (!$diagnostico['sucesso']) {
    $diagnostico['resumo'] = 'Sistema com pendências. Revise os campos de diagnóstico.';
}

echo json_encode($diagnostico, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$conn->close();
?>
