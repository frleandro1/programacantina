<?php
session_start();
require_once 'config.php';

// Função para fazer login
function fazerLogin($email, $senha) {
    global $conn;
    
    // Validar entrada
    if (empty($email) || empty($senha)) {
        return ['success' => false, 'error' => 'Email e senha são obrigatórios'];
    }
    
    // Buscar usuário
    $stmt = $conn->prepare("SELECT id, nome, email, senha, tipo, ativo FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'error' => 'Email ou senha incorretos'];
    }
    
    $usuario = $result->fetch_assoc();
    $stmt->close();
    
    // Verificar se usuário está ativo
    if (!$usuario['ativo']) {
        return ['success' => false, 'error' => 'Usuário desativado'];
    }
    
    // Verificar senha
    if (!password_verify($senha, $usuario['senha'])) {
        return ['success' => false, 'error' => 'Email ou senha incorretos'];
    }
    
    // Atualizar último acesso
    $agora = date('Y-m-d H:i:s');
    $update_stmt = $conn->prepare("UPDATE usuarios SET ultimo_acesso = ? WHERE id = ?");
    $update_stmt->bind_param("si", $agora, $usuario['id']);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Criar sessão
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome'];
    $_SESSION['usuario_email'] = $usuario['email'];
    $_SESSION['usuario_tipo'] = $usuario['tipo'];
    $_SESSION['login_time'] = time();
    
    return ['success' => true, 'message' => 'Login realizado com sucesso'];
}

// Função para registrar novo usuário
function registrarUsuario($nome, $email, $senha, $confirmar_senha) {
    global $conn;
    
    // Validar entrada
    if (empty($nome) || empty($email) || empty($senha) || empty($confirmar_senha)) {
        return ['success' => false, 'error' => 'Todos os campos são obrigatórios'];
    }
    
    // Validar email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Email inválido'];
    }
    
    // Validar comprimento da senha
    if (strlen($senha) < 6) {
        return ['success' => false, 'error' => 'Senha deve ter no mínimo 6 caracteres'];
    }
    
    // Validar confirmação de senha
    if ($senha !== $confirmar_senha) {
        return ['success' => false, 'error' => 'Senhas não conferem'];
    }
    
    // Verificar se email já existe
    $check_stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $check_stmt->close();
        return ['success' => false, 'error' => 'Email já cadastrado'];
    }
    $check_stmt->close();
    
    // Hash da senha
    $senha_hash = password_hash($senha, PASSWORD_BCRYPT);
    $tipo = 'atendente'; // Tipo padrão para novos usuários
    
    // Inserir usuário
    $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nome, $email, $senha_hash, $tipo);
    
    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Usuário registrado com sucesso! Faça login para continuar'];
    } else {
        $stmt->close();
        return ['success' => false, 'error' => 'Erro ao registrar usuário: ' . $conn->error];
    }
}

// Função para fazer logout
function fazerLogout() {
    session_destroy();
    return ['success' => true, 'message' => 'Logout realizado com sucesso'];
}

// Função para verificar se usuário está logado
function usuarioLogado() {
    return isset($_SESSION['usuario_id']) && isset($_SESSION['usuario_nome']);
}

// Função para obter dados do usuário logado
function obterUsuarioLogado() {
    if (!usuarioLogado()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['usuario_id'],
        'nome' => $_SESSION['usuario_nome'],
        'email' => $_SESSION['usuario_email'],
        'tipo' => $_SESSION['usuario_tipo']
    ];
}

// Função para verificar permissão
function temPermissao($tipo_requerido) {
    if (!usuarioLogado()) {
        return false;
    }
    
    $tipo_usuario = $_SESSION['usuario_tipo'];
    
    // Admin tem acesso a tudo
    if ($tipo_usuario === 'admin') {
        return true;
    }
    
    // Verificar permissões específicas
    $permissoes = [
        'admin' => ['admin', 'gerente', 'atendente', 'produtor'],
        'gerente' => ['gerente', 'atendente', 'produtor'],
        'atendente' => ['atendente'],
        'produtor' => ['produtor']
    ];
    
    if (!isset($permissoes[$tipo_usuario])) {
        return false;
    }
    
    return in_array($tipo_requerido, $permissoes[$tipo_usuario]);
}

// Processar requisições de autenticação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'login') {
        $email = $_POST['email'] ?? '';
        $senha = $_POST['senha'] ?? '';
        $resultado = fazerLogin($email, $senha);
        
        if ($resultado['success']) {
            header('Location: index.html');
            exit;
        } else {
            $_SESSION['erro'] = $resultado['error'];
            header('Location: login.html');
            exit;
        }
    } elseif ($action === 'registrar') {
        $nome = $_POST['nome'] ?? '';
        $email = $_POST['email'] ?? '';
        $senha = $_POST['senha'] ?? '';
        $confirmar_senha = $_POST['confirmar_senha'] ?? '';
        $resultado = registrarUsuario($nome, $email, $senha, $confirmar_senha);
        
        $_SESSION['mensagem'] = $resultado['message'] ?? '';
        $_SESSION['erro'] = $resultado['error'] ?? '';
        
        if ($resultado['success']) {
            header('Location: login.html');
        } else {
            header('Location: registrar.html');
        }
        exit;
    } elseif ($action === 'logout') {
        fazerLogout();
        header('Location: login.html');
        exit;
    }
}

// API para requisições AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['api'] ?? '';
    
    if ($action === 'login') {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';
        $senha = $data['senha'] ?? '';
        $resultado = fazerLogin($email, $senha);
        echo json_encode($resultado);
    } elseif ($action === 'registrar') {
        $data = json_decode(file_get_contents('php://input'), true);
        $nome = $data['nome'] ?? '';
        $email = $data['email'] ?? '';
        $senha = $data['senha'] ?? '';
        $confirmar_senha = $data['confirmar_senha'] ?? '';
        $resultado = registrarUsuario($nome, $email, $senha, $confirmar_senha);
        echo json_encode($resultado);
    } elseif ($action === 'logout') {
        fazerLogout();
        echo json_encode(['success' => true]);
    } elseif ($action === 'usuario') {
        if (usuarioLogado()) {
            require_once 'permissions.php';
            $usuario = obterUsuarioLogado();
            $permissoes = obterPermissoesUsuario();
            echo json_encode([
                'success' => true, 
                'usuario' => $usuario,
                'permissoes' => $permissoes
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Não autenticado']);
        }
    }
    exit;
}
?>
