<?php
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../db/config.php';

$action = $_GET['action'] ?? '';

if ($action === 'fechar') {
    $input = read_json_input();

    $sessao_id       = isset($input['sessao_id'])       ? (int)$input['sessao_id']       : 0;
    $valor_fechamento = isset($input['valor_fechamento']) ? (float)$input['valor_fechamento'] : 0;
    $observacoes     = isset($input['observacoes'])      ? trim($input['observacoes'])    : '';
    $valor_abertura  = isset($input['valor_abertura'])   ? (float)$input['valor_abertura'] : 0;
    $total_vendas    = isset($input['total_vendas'])     ? (float)$input['total_vendas'] : 0;
    $valor_esperado  = isset($input['valor_esperado'])   ? (float)$input['valor_esperado'] : 0;

    if ($sessao_id <= 0) {
        json_error('ID da sessão inválido.');
    }

    // Verificar se a sessão existe e está aberta
    $stmt = $conn->prepare('SELECT id_sessao, status FROM caixa_sessoes WHERE id_sessao = ? LIMIT 1');
    if (!$stmt) json_error('Erro ao consultar sessão.');
    $stmt->bind_param('i', $sessao_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) json_error('Sessão de caixa não encontrada.');
    if ($row['status'] === 'fechado') json_error('Sessão de caixa já foi fechada.');

    // Se total_vendas não foi fornecido, calcular
    if ($total_vendas == 0) {
        $stmtTotal = $conn->prepare('SELECT COALESCE(SUM(COALESCE(valor_total, total, 0)), 0) as total_vendas FROM vendas WHERE sessao_caixa_id = ?');
        if (!$stmtTotal) json_error('Erro ao calcular total de vendas.');
        $stmtTotal->bind_param('i', $sessao_id);
        $stmtTotal->execute();
        $resTotal = $stmtTotal->get_result();
        $rowTotal = $resTotal ? $resTotal->fetch_assoc() : null;
        $stmtTotal->close();
        $total_vendas = (float)($rowTotal['total_vendas'] ?? 0);
    }

    // Se valor_esperado não foi fornecido, calcular
    if ($valor_esperado == 0) {
        $valor_esperado = $valor_abertura + $total_vendas;
    }

    // Calcular diferença
    $diferenca = $valor_fechamento - $valor_esperado;

    // Atualizar sessão com fechamento
    $upd = $conn->prepare(
        'UPDATE caixa_sessoes SET 
            status = ?, 
            fechamento_caixa = NOW(), 
            valor_fechamento = ?, 
            valor_abertura = ?,
            total_vendas = ?, 
            observacoes = ? 
         WHERE id_sessao = ?'
    );
    if (!$upd) json_error('Erro ao atualizar sessão.');
    $status = 'fechado';
    $upd->bind_param('sdddsi', $status, $valor_fechamento, $valor_abertura, $total_vendas, $observacoes, $sessao_id);
    if (!$upd->execute()) {
        $upd->close();
        json_error('Erro ao fechar caixa: ' . $conn->error);
    }
    $upd->close();

    json_response(true, 'Caixa fechado com sucesso.', [
        'sessao_id' => $sessao_id,
        'status' => 'fechado',
        'valor_abertura' => $valor_abertura,
        'total_vendas' => $total_vendas,
        'valor_esperado' => $valor_esperado,
        'valor_fechamento' => $valor_fechamento,
        'diferenca' => $diferenca
    ]);
}

json_error('Ação não reconhecida.');
