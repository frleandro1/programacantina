<?php
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../db/config.php';

$action = $_GET['action'] ?? '';

if ($action === 'registrar_perda') {
    $input = read_json_input();

    $produto_id  = isset($input['produto_id'])  ? (int)$input['produto_id']  : 0;
    $quantidade  = isset($input['quantidade'])   ? (int)$input['quantidade']  : 0;
    $motivo      = isset($input['motivo'])        ? trim($input['motivo'])     : '';
    $usuario_id  = isset($input['usuario_id'])   ? (int)$input['usuario_id']  : null;

    if ($produto_id <= 0 || $quantidade <= 0) {
        json_error('Produto e quantidade são obrigatórios.');
    }

    // Verificar estoque disponível
    $stmt = $conn->prepare('SELECT estoque_atual FROM produtos WHERE id_produto = ? LIMIT 1');
    if (!$stmt) json_error('Erro ao consultar produto.');
    $stmt->bind_param('i', $produto_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) json_error('Produto não encontrado.');

    $estoque_atual = (int)$row['estoque_atual'];
    if ($quantidade > $estoque_atual) {
        json_error('Quantidade excede o estoque atual (' . $estoque_atual . ').');
    }

    // Iniciar transação
    $conn->begin_transaction();
    try {
        // Reduzir estoque
        $upd = $conn->prepare('UPDATE produtos SET estoque_atual = estoque_atual - ? WHERE id_produto = ?');
        if (!$upd) throw new Exception('Erro ao preparar update.');
        $upd->bind_param('ii', $quantidade, $produto_id);
        $upd->execute();
        $upd->close();

        // Registrar movimentação
        $desc = $motivo !== '' ? $motivo : 'Perda registrada manualmente';
        $tipo = 'perda';
        $ins  = $conn->prepare(
            'INSERT INTO movimentacoes (produto_id, id_produto, tipo, quantidade, descricao, usuario_id, id_usuario)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$ins) throw new Exception('Erro ao preparar insert.');
        $ins->bind_param('iisisii', $produto_id, $produto_id, $tipo, $quantidade, $desc, $usuario_id, $usuario_id);
        $ins->execute();
        $ins->close();

        $conn->commit();
        json_response(true, 'Perda registrada com sucesso.');
    } catch (Exception $e) {
        $conn->rollback();
        json_error('Erro ao registrar perda: ' . $e->getMessage());
    }
}

json_error('Ação não reconhecida.');
