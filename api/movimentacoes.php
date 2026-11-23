<?php
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/estoque-contabil.php';

garantir_schema_estoque_contabil($conn);

$action = $_GET['action'] ?? '';

if ($action === 'registrar_perda') {
    $input = read_json_input();

    $produto_id      = isset($input['produto_id'])      ? (int)$input['produto_id']      : 0;
    $quantidade      = isset($input['quantidade'])      ? (float)$input['quantidade']    : 0;
    $motivo          = isset($input['motivo'])          ? trim($input['motivo'])         : '';
    $usuario_id      = ec_obter_usuario_id($input['usuario_id'] ?? null);
    $sessao_caixa_id = isset($input['sessao_caixa_id']) && (int)$input['sessao_caixa_id'] > 0
        ? (int)$input['sessao_caixa_id']
        : (!empty($_SESSION['caixa_sessao_id']) ? (int)$_SESSION['caixa_sessao_id'] : null);

    if ($produto_id <= 0 || $quantidade <= 0) {
        json_error('Produto e quantidade são obrigatórios.');
    }

    // Iniciar transação com bloqueio para evitar estoque negativo
    $conn->begin_transaction();
    try {
        $colsP = ec_mapa_colunas_produtos($conn);
        $pkCol = $colsP['pk'];
        $estCol = $colsP['estoque'];
        $updColSql = $colsP['atualizado'] ? ", {$colsP['atualizado']} = CURRENT_TIMESTAMP" : "";
        $unidExpr = $colsP['unidade'] ? "COALESCE({$colsP['unidade']}, 'un')" : "'un'";
        $custoExpr = $colsP['custo'] ? "COALESCE({$colsP['custo']}, 0)" : "0";

        $stmt = $conn->prepare("SELECT nome, COALESCE({$estCol}, 0) AS estoque_atual, {$custoExpr} AS preco_custo, {$unidExpr} AS unidade FROM produtos WHERE {$pkCol} = ? LIMIT 1 FOR UPDATE");
        if (!$stmt) throw new Exception('Erro ao consultar produto.');
        $stmt->bind_param('i', $produto_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            throw new Exception('Produto não encontrado.');
        }

        $estoque_atual = (float)$row['estoque_atual'];
        if ($quantidade > $estoque_atual) {
            throw new Exception('Quantidade excede o estoque atual (' . $estoque_atual . ' ' . $row['unidade'] . ').');
        }

        $custo_unitario = max(0.0, (float)$row['preco_custo']);
        $prejuizo = round(-($quantidade * $custo_unitario), 2);

        // Reduzir estoque com trava atômica
        $upd = $conn->prepare("UPDATE produtos SET {$estCol} = GREATEST({$estCol} - ?, 0){$updColSql} WHERE {$pkCol} = ? AND {$estCol} >= ?");
        if (!$upd) throw new Exception('Erro ao preparar update.');
        $upd->bind_param('did', $quantidade, $produto_id, $quantidade);
        $upd->execute();
        $afetadas = $upd->affected_rows;
        $upd->close();

        if ($afetadas === 0) {
            throw new Exception('Estoque insuficiente no momento da confirmação.');
        }

        // Registrar movimentação contábil automática de PERDA
        $desc = $motivo !== '' ? $motivo : 'Perda registrada no PDV';
        registrar_movimentacao_contabil($conn, [
            'produto_id' => $produto_id,
            'tipo' => 'PERDA',
            'quantidade' => $quantidade,
            'valor_unitario_praticado' => 0.00,
            'custo_unitario_praticado' => $custo_unitario,
            'resultado_financeiro' => $prejuizo,
            'descricao' => $desc,
            'usuario_id' => $usuario_id,
            'sessao_caixa_id' => $sessao_caixa_id,
            'origem' => 'PERDA_PDV'
        ]);

        $conn->commit();
        json_response(true, 'Perda registrada e prejuízo contabilizado com sucesso.', [
            'produto' => $row['nome'],
            'quantidade' => $quantidade,
            'custo_unitario' => $custo_unitario,
            'prejuizo' => abs($prejuizo),
            'resultado_financeiro' => $prejuizo,
            'novo_estoque' => round($estoque_atual - $quantidade, 3)
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        json_error('Erro ao registrar perda: ' . $e->getMessage());
    }
}

json_error('Ação não reconhecida.');
