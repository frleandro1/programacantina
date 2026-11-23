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
    $papelLower = function_exists('mb_strtolower') ? mb_strtolower($papel, 'UTF-8') : strtolower($papel);

    // Compatibilidade com valores legados: tudo que for perfil administrativo vira "admin".
    if (in_array($papelLower, ['admin', 'administrador', 'gestor'], true)) {
        return 'admin';
    }

    // Qualquer outro valor é tratado como perfil operacional padrão.
    return 'vendedor';
}

function ensure_caixa_schema($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS caixa_sessoes (
        id_sessao INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        login_usuario VARCHAR(100) NOT NULL,
        abertura_caixa DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fechamento_caixa DATETIME NULL,
        valor_abertura DECIMAL(10, 2) DEFAULT 0,
        valor_fechamento DECIMAL(10, 2) DEFAULT 0,
        total_vendas DECIMAL(10, 2) DEFAULT 0,
        observacoes TEXT NULL,
        status ENUM('aberto','fechado') NOT NULL DEFAULT 'aberto',
        ip_origem VARCHAR(45) NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_usuario_status (id_usuario, status),
        INDEX idx_abertura (abertura_caixa)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $extraCols = [
        'valor_abertura' => "ALTER TABLE caixa_sessoes ADD COLUMN valor_abertura DECIMAL(10, 2) DEFAULT 0 AFTER fechamento_caixa",
        'valor_fechamento' => "ALTER TABLE caixa_sessoes ADD COLUMN valor_fechamento DECIMAL(10, 2) DEFAULT 0 AFTER valor_abertura",
        'total_vendas' => "ALTER TABLE caixa_sessoes ADD COLUMN total_vendas DECIMAL(10, 2) DEFAULT 0 AFTER valor_fechamento",
        'observacoes' => "ALTER TABLE caixa_sessoes ADD COLUMN observacoes TEXT NULL AFTER total_vendas"
    ];
    foreach ($extraCols as $col => $alterSql) {
        $check = $conn->query("SHOW COLUMNS FROM caixa_sessoes LIKE '{$col}'");
        if (!$check || $check->num_rows === 0) {
            $conn->query($alterSql);
        }
    }
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
            $papelNormalizado = normalize_usuario_role($row['papel'] ?? 'vendedor');
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;

            // Encerrar qualquer sessão anterior ainda aberta deste usuário, preservando o total de vendas já feito
            $stmtAbertas = $conn->prepare(
                "SELECT id_sessao FROM caixa_sessoes WHERE id_usuario = ? AND status = 'aberto'"
            );
            if ($stmtAbertas) {
                $stmtAbertas->bind_param('i', $row['id_usuario']);
                $stmtAbertas->execute();
                $resAbertas = $stmtAbertas->get_result();
                $stmtFecharAnt = $conn->prepare(
                    "UPDATE caixa_sessoes
                     SET status = 'fechado',
                         fechamento_caixa = NOW(),
                         total_vendas = COALESCE((SELECT SUM(COALESCE(valor_total, 0)) FROM vendas WHERE sessao_caixa_id = ?), total_vendas, 0)
                     WHERE id_sessao = ?"
                );
                while ($ab = $resAbertas->fetch_assoc()) {
                    $idAnt = (int)$ab['id_sessao'];
                    if ($stmtFecharAnt) {
                        $stmtFecharAnt->bind_param('ii', $idAnt, $idAnt);
                        $stmtFecharAnt->execute();
                    }
                }
                if ($stmtFecharAnt) {
                    $stmtFecharAnt->close();
                }
                $stmtAbertas->close();
            }

            // Sempre iniciar uma nova sessão de caixa ZERADA para este login (sem herdar quebra/financeiro anterior)
            $stmtSessao = $conn->prepare(
                "INSERT INTO caixa_sessoes (id_usuario, login_usuario, abertura_caixa, valor_abertura, valor_fechamento, total_vendas, status, ip_origem)
                 VALUES (?, ?, NOW(), 0.00, 0.00, 0.00, 'aberto', ?)"
            );
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
            $_SESSION['tipo'] = $papelNormalizado;
            $_SESSION['caixa_sessao_id'] = $sessaoCaixaId;
            $_SESSION['login_iniciado_em'] = date('Y-m-d H:i:s');

            echo json_encode([
                'sucesso' => true,
                'mensagem' => 'Login realizado com sucesso',
                'sessao_caixa_id' => $sessaoCaixaId,
                'usuario' => [
                    'id' => (int)$row['id_usuario'],
                    'username' => $row['login'],
                    'nome' => $row['nome'],
                    'tipo' => $papelNormalizado,
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
    $papel = trim($data['papel'] ?? 'vendedor');
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
    $papel = normalize_usuario_role($data['papel'] ?? 'vendedor');
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
    $filters = [];
    if (has_usuario_column($conn, 'status')) {
        $filters[] = "LOWER(COALESCE(status, 'ativo')) <> 'inativo'";
    }
    $filters[] = sprintf("LOWER(%s) NOT LIKE '__inativo__%%'", $selectMap['login']);
    $whereClause = ' WHERE ' . implode(' AND ', $filters);
    $sql = sprintf(
        'SELECT %s AS id, %s AS username, %s AS nome, %s AS email, %s AS tipo FROM usuarios%s ORDER BY %s',
        $selectMap['id'],
        $selectMap['login'],
        $selectMap['nome'] ?: 'NULL',
        $selectMap['email'] ?: 'NULL',
        $selectMap['papel'],
        $whereClause,
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
                'tipo' => normalize_usuario_role($row['tipo'] ?? 'vendedor')
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

    // Impedir deletar o próprio usuário logado
    if (isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] === $id) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Você não pode deletar sua própria conta']);
        exit;
    }

    $selectMap = get_usuario_select_map($conn);
    $idColumn = $selectMap['id'];
    $loginColumn = $selectMap['login'];
    $papelColumn = $selectMap['papel'];
    $hasStatusColumn = has_usuario_column($conn, 'status');

    $deactivateUser = static function($conn, $idColumn, $loginColumn, $hasStatusColumn, $id) {
        $stmtInativar = null;
        if ($hasStatusColumn) {
            $stmtInativar = $conn->prepare("UPDATE usuarios SET status = 'inativo' WHERE {$idColumn} = ?");
        } else {
            // Fallback para banco legado sem coluna status: renomeia login para ocultar na listagem e bloquear login.
            $inactiveLogin = '__inativo__' . $id . '__' . time();
            $stmtInativar = $conn->prepare("UPDATE usuarios SET {$loginColumn} = ? WHERE {$idColumn} = ?");
            if ($stmtInativar) {
                $stmtInativar->bind_param('si', $inactiveLogin, $id);
            }
        }

        if (!$stmtInativar) {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao desativar usuário: ' . $conn->error
            ];
        }

        if ($hasStatusColumn) {
            $stmtInativar->bind_param('i', $id);
        }
        $ok = $stmtInativar->execute();
        $affected = $stmtInativar->affected_rows;
        $stmtErrno = (int)$stmtInativar->errno;
        $stmtError = $stmtInativar->error;
        $stmtInativar->close();

        if (!$ok && $stmtErrno !== 0) {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao desativar usuário: ' . $stmtError
            ];
        }

        if ($affected <= 0) {
            return [
                'sucesso' => false,
                'mensagem' => 'Usuário não encontrado'
            ];
        }

        return [
            'sucesso' => true,
            'mensagem' => 'Usuário possui registros vinculados e foi desativado com sucesso'
        ];
    };

    // Impedir deletar o último admin
    $stmtAdminCount = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE LOWER({$papelColumn}) IN ('admin','administrador','gestor')");
    if ($stmtAdminCount) {
        $stmtAdminCount->execute();
        $stmtAdminCount->bind_result($totalAdmins);
        $stmtAdminCount->fetch();
        $stmtAdminCount->close();

        if ($totalAdmins <= 1) {
            // Verificar se o usuário a deletar é admin
            $stmtIsAdmin = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE {$idColumn} = ? AND LOWER({$papelColumn}) IN ('admin','administrador','gestor')");
            if ($stmtIsAdmin) {
                $stmtIsAdmin->bind_param('i', $id);
                $stmtIsAdmin->execute();
                $stmtIsAdmin->bind_result($isAdmin);
                $stmtIsAdmin->fetch();
                $stmtIsAdmin->close();
                if ($isAdmin > 0) {
                    echo json_encode(['sucesso' => false, 'mensagem' => 'Não é possível deletar o único administrador do sistema']);
                    exit;
                }
            }
        }
    }

    $stmt = null;
    try {
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE {$idColumn} = ?");
        if (!$stmt) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro na preparação: ' . $conn->error]);
            exit;
        }

        $stmt->bind_param('i', $id);
        $executed = $stmt->execute();

        if (!$executed) {
            $stmtErrno = (int)$stmt->errno;
            if ($stmtErrno === 1451) {
                $fallback = $deactivateUser($conn, $idColumn, $loginColumn, $hasStatusColumn, $id);
                echo json_encode($fallback);
                exit;
            }

            echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao deletar: ' . $stmt->error]);
            exit;
        }

        if ($stmt->affected_rows === 0) {
            echo json_encode(['sucesso' => false, 'mensagem' => 'Usuário não encontrado']);
        } else {
            echo json_encode(['sucesso' => true, 'mensagem' => 'Usuário deletado com sucesso']);
        }
    } catch (Throwable $e) {
        $mysqlErrno = (int)$e->getCode();
        if ($mysqlErrno === 1451) {
            $fallback = $deactivateUser($conn, $idColumn, $loginColumn, $hasStatusColumn, $id);
            echo json_encode($fallback);
            exit;
        }
        $stmtError = ($stmt instanceof mysqli_stmt) ? $stmt->error : $conn->error;
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao deletar: ' . $stmtError]);
    } finally {
        if ($stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
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
    
    // Buscar usuários admin ativos
    $selectMap = get_usuario_select_map($conn);
    $loginCol = $selectMap['login'];
    $senhaCol = $selectMap['senha'];
    $papelCol = $selectMap['papel'];
    $statusFilter = has_usuario_column($conn, 'status')
        ? " AND LOWER(COALESCE(status, 'ativo')) <> 'inativo'"
        : '';
    
    // Buscar todos os usuários com perfil administrativo (inclui valores legados e login 'admin')
    $sql = sprintf(
        "SELECT %s FROM usuarios WHERE (LOWER(%s) IN ('admin', 'administrador', 'gestor') OR LOWER(%s) = 'admin') AND LOWER(%s) NOT LIKE '__inativo__%%'%s",
        $senhaCol,
        $papelCol,
        $loginCol,
        $loginCol,
        $statusFilter
    );
    
    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) {
        echo json_encode(['sucesso' => false, 'mensagem' => 'Admin não encontrado']);
        exit;
    }
    
    while ($user = $result->fetch_assoc()) {
        $senhaHash = $user[$senhaCol] ?? '';
        if (verify_password($senha, $senhaHash)) {
            echo json_encode(['sucesso' => true, 'mensagem' => 'Autenticado']);
            exit;
        }
    }
    
    echo json_encode(['sucesso' => false, 'mensagem' => 'Senha incorreta']);
    exit;
}

echo json_encode(['sucesso' => false, 'mensagem' => 'Ação inválida']);
?>