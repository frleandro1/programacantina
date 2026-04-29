<?php
session_start();
require_once 'config.php';

// Verificar se usuário está autenticado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_nome'])) {
    // Se for uma requisição AJAX, retornar JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado']);
        exit;
    }
    
    // Caso contrário, redirecionar para login
    header('Location: login.html');
    exit;
}

// Função para obter usuário atual
function obterUsuarioAtual() {
    return [
        'id' => $_SESSION['usuario_id'],
        'nome' => $_SESSION['usuario_nome'],
        'email' => $_SESSION['usuario_email'],
        'tipo' => $_SESSION['usuario_tipo']
    ];
}

// Função para verificar permissão
function verificarPermissao($tipo_requerido) {
    if (!isset($_SESSION['usuario_tipo'])) {
        return false;
    }
    
    $tipo_usuario = $_SESSION['usuario_tipo'];
    
    // Admin tem acesso a tudo
    if ($tipo_usuario === 'admin') {
        return true;
    }
    
    // Verificar se é um array de tipos
    if (is_array($tipo_requerido)) {
        return in_array($tipo_usuario, $tipo_requerido);
    }
    
    return $tipo_usuario === $tipo_requerido;
}

// Função para verificar timeout de sessão
function verificarTimeoutSessao($timeout = 3600) { // 1 hora padrão
    if (isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > $timeout) {
            session_destroy();
            header('Location: login.html?msg=Sua+sessão+expirou');
            exit;
        }
    }
}

// Verificar timeout
verificarTimeoutSessao();
?>
