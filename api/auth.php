<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

error_reporting(0);
session_start();

require_once __DIR__ . '/../db/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

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

    // Schema real: usuarios(login, senha, id_usuario, papel, nome)
    $stmt = $conn->prepare('SELECT id_usuario, login, senha, papel, nome FROM usuarios WHERE login = ? LIMIT 1');
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
    $sql = "SELECT id_usuario as id, login as username, email, papel as tipo FROM usuarios ORDER BY login";
    $result = $conn->query($sql);
    $usuarios = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = [
                'id' => (int)$row['id'],
                'username' => $row['username'],
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
    
    $stmt = $conn->prepare('DELETE FROM usuarios WHERE id_usuario = ?');
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

echo json_encode(['sucesso' => false, 'mensagem' => 'Ação inválida']);
?>