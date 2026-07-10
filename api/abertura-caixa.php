<?php
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/helpers.php';

session_start();
require_once __DIR__ . '/../db/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Verificar se já tem abertura de caixa HOJE
if ($method === 'GET' && $action === 'verificar_hoje') {
    $usuario_id = (int)($_GET['usuario_id'] ?? 0);

    if ($usuario_id <= 0) {
        json_error('Usuário não informado');
        exit;
    }

    $hoje = date('Y-m-d');

    // Buscar sessão de hoje (qualquer status)
    $stmtVerificar = $conn->prepare(
        'SELECT id_sessao, status, valor_abertura, fechamento_caixa FROM caixa_sessoes 
         WHERE id_usuario = ? AND DATE(abertura_caixa) = ? 
         ORDER BY abertura_caixa DESC LIMIT 1'
    );

    if (!$stmtVerificar) {
        json_error('Erro ao preparar verificação: ' . $conn->error);
        exit;
    }

    $stmtVerificar->bind_param('is', $usuario_id, $hoje);
    $stmtVerificar->execute();
    $resultVerificar = $stmtVerificar->get_result();

    if ($resultVerificar->num_rows > 0) {
        $sessao = $resultVerificar->fetch_assoc();
        json_response(true, 'Sessão encontrada', [
            'id_sessao' => (int)$sessao['id_sessao'],
            'status' => $sessao['status'],
            'valor_abertura' => (float)$sessao['valor_abertura'],
            'fechamento_caixa' => $sessao['fechamento_caixa']
        ]);
    } else {
        json_response(true, 'Sem sessão aberta', null);
    }

    $stmtVerificar->close();
    exit;
}

if ($method === 'POST' && $action === 'registrar') {
    $data = read_json_input();

    $sessao_id = isset($data['sessao_id']) ? (int)$data['sessao_id'] : 0;
    $usuario_id = isset($data['usuario_id']) ? (int)$data['usuario_id'] : 0;
    $valor_abertura = isset($data['valor_abertura']) ? (float)$data['valor_abertura'] : 0;
    $observacoes = isset($data['observacoes']) ? trim((string)$data['observacoes']) : '';

    if (!$sessao_id) {
        json_error('Sessão de caixa não informada');
        exit;
    }

    if ($valor_abertura < 0) {
        json_error('Valor de abertura inválido');
        exit;
    }

    // Verificar se JÁ TEM abertura de caixa ABERTA hoje (diferente da atual)
    if ($usuario_id > 0) {
        $hoje = date('Y-m-d');
        $stmtJaAberto = $conn->prepare(
            'SELECT id_sessao FROM caixa_sessoes 
             WHERE id_usuario = ? AND DATE(abertura_caixa) = ? AND status = "aberto" AND id_sessao != ? LIMIT 1'
        );

        if (!$stmtJaAberto) {
            json_error('Erro ao verificar abertura existente: ' . $conn->error);
            exit;
        }

        $stmtJaAberto->bind_param('isi', $usuario_id, $hoje, $sessao_id);
        $stmtJaAberto->execute();
        $resultJaAberto = $stmtJaAberto->get_result();

        if ($resultJaAberto->num_rows > 0) {
            json_error('Você já tem outro caixa aberto hoje. Feche-o antes de abrir um novo.');
            exit;
        }

        $stmtJaAberto->close();
    }

    // Validar que a sessão existe e está aberta
    $stmtValidar = $conn->prepare(
        'SELECT id_sessao, status FROM caixa_sessoes WHERE id_sessao = ? AND status = "aberto" LIMIT 1'
    );

    if (!$stmtValidar) {
        json_error('Erro ao preparar validação: ' . $conn->error);
        exit;
    }

    $stmtValidar->bind_param('i', $sessao_id);
    $stmtValidar->execute();
    $resultValidar = $stmtValidar->get_result();

    if ($resultValidar->num_rows === 0) {
        json_error('Sessão de caixa não encontrada ou já foi fechada');
        exit;
    }

    $stmtValidar->close();

    // Atualizar caixa_sessoes com valor de abertura
    $stmtAtualizar = $conn->prepare(
        'UPDATE caixa_sessoes SET valor_abertura = ?, observacoes = ? WHERE id_sessao = ?'
    );

    if (!$stmtAtualizar) {
        json_error('Erro ao preparar atualização: ' . $conn->error);
        exit;
    }

    $stmtAtualizar->bind_param('dsi', $valor_abertura, $observacoes, $sessao_id);

    if (!$stmtAtualizar->execute()) {
        json_error('Erro ao registrar abertura de caixa: ' . $stmtAtualizar->error);
        exit;
    }

    $stmtAtualizar->close();

    // Recuperar dados da sessão atualizada
    $stmtRetorna = $conn->prepare(
        'SELECT id_sessao, id_usuario, login_usuario, abertura_caixa, valor_abertura, status FROM caixa_sessoes WHERE id_sessao = ?'
    );

    if (!$stmtRetorna) {
        json_error('Erro ao recuperar dados: ' . $conn->error);
        exit;
    }

    $stmtRetorna->bind_param('i', $sessao_id);
    $stmtRetorna->execute();
    $resultRetorna = $stmtRetorna->get_result();
    $sessaoDados = $resultRetorna->fetch_assoc();
    $stmtRetorna->close();

    json_response(true, 'Abertura de caixa registrada com sucesso', [
        'sessao_id' => (int)$sessaoDados['id_sessao'],
        'usuario' => $sessaoDados['login_usuario'],
        'abertura_caixa' => $sessaoDados['abertura_caixa'],
        'valor_abertura' => (float)$sessaoDados['valor_abertura'],
        'status' => $sessaoDados['status']
    ]);
    exit;
}

json_error('Ação não permitida');
