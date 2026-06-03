<?php
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/helpers.php';

session_start();
require_once __DIR__ . '/../db/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function get_usuarios_columns($conn) {
    static $columns = null;

    if ($columns !== null) {
        return $columns;
    }

    $columns = [];
    $result = $conn->query('SHOW COLUMNS FROM usuarios');

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }

    return $columns;
}

function has_usuario_column($conn, $column) {
    return in_array($column, get_usuarios_columns($conn), true);
}

function get_usuario_select_map($conn) {
    return [
        'id' => has_usuario_column($conn, 'id_usuario') ? 'id_usuario' : 'id',
        'login' => has_usuario_column($conn, 'login') ? 'login' : 'username',
        'senha' => has_usuario_column($conn, 'senha') ? 'senha' : 'password',
        'papel' => has_usuario_column($conn, 'papel') ? 'papel' : 'tipo',
        'nome' => has_usuario_column($conn, 'nome') ? 'nome' : null,
        'email' => has_usuario_column($conn, 'email') ? 'email' : null,
    ];
}

function normalize_usuario_role($papel) {
    $papel = trim((string)$papel);
    $allowedRoles = ['atendente', 'producao', 'gestor', 'admin', 'administrador'];

    if (!in_array($papel, $allowedRoles, true)) {
        return 'atendente';
    }

    return $papel;
}

function ensure_caixa_schema($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS caixa_sessoes (
        id_sessao INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        login_usuario VARCHAR(100) NOT NULL,
        abertura_caixa DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fechamento_caixa DATETIME NULL,
        status ENUM('aberto','fechado') NOT NULL DEFAULT 'aberto',
        ip_origem VARCHAR(45) NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_usuario_status (id_usuario, status),
        INDEX idx_abertura (abertura_caixa)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensure_caixa_schema($conn);

function verify_password($plain, $hash) {
    if (!$hash) return false;
    if (preg_match('/^\$2[aby]\$/', $hash)) { // bcrypt
        return password_verify($plain, $hash);
    }
    if (strlen($hash) === 64 && ctype_xdigit($hash)) { // sha256 hex
        return hash('sha256', $plain) === strtolower($hash);
    }
    if (strlen($hash) === 32 && ctype_xdigit($hash)) { // md5 hex
        return md5($plain) === strtolower($hash);
    }
    return hash_equals($hash, $plain); // fallback texto puro
}

if ($method === 'POST' && $action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $username = trim($data['username'] ?? '');
    $password = (string)($data['password'] ?? '');

    if ($username === '' || $password === '') {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Usuário e senha obrigatórios']);
        exit;
    }

    $selectMap = get_usuario_select_map($conn);
    $sql = sprintf(
        'SELECT %s AS id_usuario, %s AS login, %s AS senha, %s AS papel, %s AS nome FROM usuarios WHERE %s = ? LIMIT 1',
        $selectMap['id'],
        $selectMap['login'],
        $selectMap['senha'],
        $selectMap['papel'],
        $selectMap['nome'] ?: 'NULL',
        $selectMap['login']
    );

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro na preparação: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (verify_password($password, $row['senha'])) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $stmtSessao = $conn->prepare('INSERT INTO caixa_sessoes (id_usuario, login_usuario, abertura_caixa, status, ip_origem) VALUES (?, ?, NOW(), \'aberto\', ?)');
            if (!$stmtSessao) {
                echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao abrir caixa: ' . $conn->error]);
                exit;
            }
            $stmtSessao->bind_param('iss', $row['id_usuario'], $row['login'], $ip);
            if (!$stmtSessao->execute()) {
                echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao abrir caixa: ' . $stmtSessao->error]);
                exit;
            }
            $sessaoCaixaId = (int)$stmtSessao->insert_id;
            $stmtSessao->close();

            $_SESSION['usuario_id'] = $row['id_usuario'];
            $_SESSION['username'] = $row['login'];
            $_SESSION['tipo'] = $row['papel'];
            $_SESSION['caixa_sessao_id'] = $sessaoCaixaId;

            echo json_encode([
                'sucesso' => true,
                'mensagem' => 'Login realizado com sucesso',
                'sessao_caixa_id' => $sessaoCaixaId,
                'usuario' => [
                    'id' => (int)$row['id_usuario'],
                    'username' => $row['login'],
                    'nome' => $row['nome'],
                    'tipo' => $row['papel'],
                    'email' => null
                ]
            ]);
        } else {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Usuário ou senha incorretos']);
        }
    } else {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Usuário ou senha incorretos']);
    }
    $stmt->close();
    exit;
}

if ($method === 'POST' && $action === 'logout') {
    if (isset($_SESSION['caixa_sessao_id'])) {
        $sessaoId = (int)$_SESSION['caixa_sessao_id'];
        $stmtFechar = $conn->prepare("UPDATE caixa_sessoes SET status = 'fechado', fechamento_caixa = NOW() WHERE id_sessao = ? AND status = 'aberto'");
        if ($stmtFechar) {
            $stmtFechar->bind_param('i', $sessaoId);
            $stmtFechar->execute();
            $stmtFechar->close();
        }
    }
    session_destroy();
    echo json_encode(['sucesso' => true, 'mensagem' => 'Logout realizado com sucesso']);
    exit;
}

if ($method === 'POST' && $action === 'criar_usuario') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $login = trim($data['login'] ?? '');
    $senha = (string)($data['senha'] ?? '');
    $nome = trim($data['nome'] ?? '');
    $papel = trim($data['papel'] ?? 'atendente');
    $email = trim((string)($data['email'] ?? ''));

    if ($login === '' || $senha === '' || $nome === '') {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Login, senha e nome são obrigatórios']);
        exit;
    }

    if (strlen($senha) < 6) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'A senha deve ter no mínimo 6 caracteres']);
        exit;
    }

    $papel = normalize_usuario_role($papel);

    $columns = get_usuarios_columns($conn);
    $loginColumn = has_usuario_column($conn, 'login') ? 'login' : 'username';

    $stmtCheck = $conn->prepare("SELECT 1 FROM usuarios WHERE {$loginColumn} = ? LIMIT 1");
    if (!$stmtCheck) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro na verificação: ' . $conn->error]);
        exit;
    }

    $stmtCheck->bind_param('s', $login);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    if ($resultCheck && $resultCheck->fetch_row()) {
        $stmtCheck->close();
        echo json_encode(['sucesso' => false, 'mensagem' => 'Já existe um usuário com esse login']);
        exit;
    }
    $stmtCheck->close();

    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $insertColumns = [];
    $insertValues = [];
    $insertTypes = '';
    $params = [];

    $fieldMap = [
        'login' => $login,
        'username' => $login,
        'senha' => $hash,
        'password' => $hash,
        'nome' => $nome,
        'papel' => $papel,
        'tipo' => $papel,
        'email' => $email !== '' ? $email : null,
        'status' => 'ativo',
    ];

    foreach ($fieldMap as $column => $value) {
        if (!in_array($column, $columns, true)) {
            continue;
        }

        $insertColumns[] = $column;
        $insertValues[] = '?';
        $insertTypes .= 's';
        $params[] = $value;
    }

    if (empty($insertColumns)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Estrutura da tabela de usuários não suportada']);
        exit;
    }

    $sql = sprintf(
        'INSERT INTO usuarios (%s) VALUES (%s)',
        implode(', ', $insertColumns),
        implode(', ', $insertValues)
    );

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro na preparação: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param($insertTypes, ...$params);

    if ($stmt->execute()) {
        echo json_encode(['sucesso' => true, 'mensagem' => 'Usuário criado com sucesso']);
    } else {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao criar usuário: ' . $stmt->error]);
    }

    $stmt->close();
    exit;
}

if ($method === 'POST' && $action === 'editar_usuario') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $id = (int)($data['id'] ?? 0);
    $login = trim($data['login'] ?? '');
    $senha = (string)($data['senha'] ?? '');
    $nome = trim($data['nome'] ?? '');
    $papel = normalize_usuario_role($data['papel'] ?? 'atendente');
    $email = trim((string)($data['email'] ?? ''));

    if ($id <= 0) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']);
        exit;
    }

    if ($login === '' || $nome === '') {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Login e nome são obrigatórios']);
        exit;
    }

    if ($senha !== '' && strlen($senha) < 6) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'A senha deve ter no mínimo 6 caracteres']);
        exit;
    }

    $selectMap = get_usuario_select_map($conn);
    $columns = get_usuarios_columns($conn);
    $idColumn = $selectMap['id'];
    $loginColumn = $selectMap['login'];

    $stmtCheck = $conn->prepare("SELECT 1 FROM usuarios WHERE {$loginColumn} = ? AND {$idColumn} <> ? LIMIT 1");
    if (!$stmtCheck) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro na verificação: ' . $conn->error]);
        exit;
    }

    $stmtCheck->bind_param('si', $login, $id);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    if ($resultCheck && $resultCheck->fetch_row()) {
        $stmtCheck->close();
        echo json_encode(['sucesso' => false, 'mensagem' => 'Já existe um usuário com esse login']);
        exit;
    }
    $stmtCheck->close();

    $updates = [];
    $types = '';
    $params = [];

    $fieldMap = [
        'login' => $login,
        'username' => $login,
        'nome' => $nome,
        'papel' => $papel,
        'tipo' => $papel,
        'email' => $email,
    ];

    foreach ($fieldMap as $column => $value) {
        if (!in_array($column, $columns, true)) {
            continue;
        }

        $updates[] = "{$column} = ?";
        $types .= 's';
        $params[] = $value;
    }

    if ($senha !== '') {
        $hash = password_hash($senha, PASSWORD_DEFAULT);

        if (in_array('senha', $columns, true)) {
            $updates[] = 'senha = ?';
            $types .= 's';
            $params[] = $hash;
        }

        if (in_array('password', $columns, true)) {
            $updates[] = 'password = ?';
            $types .= 's';
            $params[] = $hash;
        }
    }

    if (empty($updates)) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum campo disponível para atualização']);
        exit;
    }

    $sql = sprintf('UPDATE usuarios SET %s WHERE %s = ?', implode(', ', $updates), $idColumn);
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro na preparação: ' . $conn->error]);
        exit;
    }

    $types .= 'i';
    $params[] = $id;
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        echo json_encode(['sucesso' => true, 'mensagem' => 'Usuário atualizado com sucesso']);
    } else {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao atualizar usuário: ' . $stmt->error]);
    }

    $stmt->close();
    exit;
}

if ($method === 'GET' && $action === 'verificar') {
    if (isset($_SESSION['usuario_id'])) {
        echo json_encode([
            'autenticado' => true,
            'usuario_id' => $_SESSION['usuario_id'],
            'username' => $_SESSION['username'],
            'tipo' => $_SESSION['tipo'],
            'sessao_caixa_id' => $_SESSION['caixa_sessao_id'] ?? null
        ]);
    } else {
        echo json_encode(['autenticado' => false]);
    }
    exit;
}

if ($method === 'GET' && $action === 'list_usuarios') {
    $selectMap = get_usuario_select_map($conn);
    $sql = sprintf(
        'SELECT %s AS id, %s AS username, %s AS nome, %s AS email, %s AS tipo FROM usuarios ORDER BY %s',
        $selectMap['id'],
        $selectMap['login'],
        $selectMap['nome'] ?: 'NULL',
        $selectMap['email'] ?: 'NULL',
        $selectMap['papel'],
        $selectMap['login']
    );
    $result = $conn->query($sql);
    $usuarios = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = [
                'id' => (int)$row['id'],
                'username' => $row['username'],
                'nome' => $row['nome'],
                'email' => $row['email'],
                'tipo' => $row['tipo']
            ];
        }
    }
    
    echo json_encode([
        'sucesso' => true,
        'usuarios' => $usuarios
    ]);
    exit;
}

if ($method === 'POST' && $action === 'deletar_usuario') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($data['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido']);
        exit;
    }
    
    $idColumn = get_usuario_select_map($conn)['id'];
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE {$idColumn} = ?");
    if (!$stmt) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro na preparação: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['sucesso' => true, 'mensagem' => 'Usuário deletado com sucesso']);
    } else {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao deletar: ' . $stmt->error]);
    }
    exit;
}

if ($method === 'POST' && $action === 'verificar_senha_admin') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $senha = (string)($data['senha'] ?? '');
    
    if ($senha === '') {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Senha inválida']);
        exit;
    }
    
    // Buscar usuário admin
    $selectMap = get_usuario_select_map($conn);
    $loginCol = $selectMap['login'];
    $senhaCol = $selectMap['senha'];
    $papelCol = $selectMap['papel'];
    
    // Buscar usuário admin ou administrador
    $sql = sprintf(
        'SELECT %s FROM usuarios WHERE %s IN ("admin", "administrador") LIMIT 1',
        $senhaCol,
        $papelCol
    );
    
    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) {
        // Tentar buscar por username admin
        $sql2 = sprintf(
            'SELECT %s FROM usuarios WHERE %s = "admin" LIMIT 1',
            $senhaCol,
            $loginCol
        );
        $result = $conn->query($sql2);
    }
    
    if (!$result || $result->num_rows === 0) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Admin não encontrado']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $senhaHash = $user[$senhaCol] ?? '';
    
    // Verificar com password_verify (bcrypt)
    if (password_verify($senha, $senhaHash)) {
        echo json_encode(['sucesso' => true, 'mensagem' => 'Autenticado']);
        exit;
    }
    
    // Verificar SHA256
    if (hash('sha256', $senha) === $senhaHash) {
        echo json_encode(['sucesso' => true, 'mensagem' => 'Autenticado']);
        exit;
    }
    
    // Verificar MD5
    if (md5($senha) === $senhaHash) {
        echo json_encode(['sucesso' => true, 'mensagem' => 'Autenticado']);
        exit;
    }
    
    // Verificar plaintext
    if ($senha === $senhaHash) {
        echo json_encode(['sucesso' => true, 'mensagem' => 'Autenticado']);
        exit;
    }
    
    echo json_encode(['sucesso' => false, 'mensagem' => 'Senha incorreta']);
    exit;
}

echo json_encode(['sucesso' => false, 'mensagem' => 'Ação inválida']);
?>