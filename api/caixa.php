<?php
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/helpers.php';
session_start();
require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/estoque-contabil.php';

garantir_schema_estoque_contabil($conn);

$action = $_GET['action'] ?? '';

if ($action === 'fechar') {
    $input = read_json_input();

    $sessao_id        = isset($input['sessao_id'])        ? (int)$input['sessao_id']          : 0;
    $valor_fechamento = isset($input['valor_fechamento']) ? (float)$input['valor_fechamento'] : 0;
    $observacoes      = isset($input['observacoes'])      ? trim($input['observacoes'])       : '';
    $valor_abertura   = isset($input['valor_abertura'])   ? (float)$input['valor_abertura']   : 0;
    $total_vendas     = isset($input['total_vendas'])     ? (float)$input['total_vendas']     : 0;
    $valor_esperado   = isset($input['valor_esperado'])   ? (float)$input['valor_esperado']   : 0;
    $perdas           = isset($input['perdas']) && is_array($input['perdas'])   ? $input['perdas']  : [];
    $queimas          = isset($input['queimas']) && is_array($input['queimas']) ? $input['queimas'] : [];

    if ($sessao_id <= 0) {
        json_error('ID da sessão inválido.');
    }

    // Verificar se a sessão existe e está aberta
    $stmt = $conn->prepare('SELECT id_sessao, id_usuario, status, valor_abertura FROM caixa_sessoes WHERE id_sessao = ? LIMIT 1');
    if (!$stmt) json_error('Erro ao consultar sessão.');
    $stmt->bind_param('i', $sessao_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$row) json_error('Sessão de caixa não encontrada.');
    if ($row['status'] === 'fechado') json_error('Sessão de caixa já foi fechada.');

    $usuario_id = ec_obter_usuario_id($row['id_usuario'] ?? null);

    // Preservar valor_abertura do banco caso não tenha sido informado na requisição
    if (!isset($input['valor_abertura']) || $valor_abertura == 0) {
        $valor_abertura = (float)($row['valor_abertura'] ?? 0);
    }

    // Calcular total_vendas do PDV no banco caso não informado
    $stmtTotal = $conn->prepare('SELECT COALESCE(SUM(COALESCE(valor_total, 0)), 0) as total_vendas FROM vendas WHERE sessao_caixa_id = ?');
    if (!$stmtTotal) json_error('Erro ao calcular total de vendas.');
    $stmtTotal->bind_param('i', $sessao_id);
    $stmtTotal->execute();
    $resTotal = $stmtTotal->get_result();
    $rowTotal = $resTotal ? $resTotal->fetch_assoc() : null;
    $stmtTotal->close();
    $total_vendas_pdv = (float)($rowTotal['total_vendas'] ?? 0);
    if ($total_vendas <= 0) {
        $total_vendas = $total_vendas_pdv;
    }

    $conn->begin_transaction();
    try {
        $colsP = ec_mapa_colunas_produtos($conn);
        $pkCol = $colsP['pk'];
        $estCol = $colsP['estoque'];
        $updColSql = $colsP['atualizado'] ? ", {$colsP['atualizado']} = CURRENT_TIMESTAMP" : "";
        $unidExpr = $colsP['unidade'] ? "COALESCE({$colsP['unidade']}, 'un')" : "'un'";
        $custoExpr = $colsP['custo'] ? "COALESCE({$colsP['custo']}, 0)" : "0";

        $total_perdas_rs = 0.0;
        $total_queimas_receita = 0.0;
        $total_queimas_resultado = 0.0;
        $resumo_lancamentos = [];

        // 1. Processar Perdas informadas no fechamento de caixa
        foreach ($perdas as $itemPerda) {
            $pid = (int)($itemPerda['produto_id'] ?? 0);
            $qtd = (float)($itemPerda['quantidade'] ?? 0);
            $motivo = trim((string)($itemPerda['motivo'] ?? ''));

            if ($pid <= 0 || $qtd <= 0) {
                continue;
            }

            $stmtP = $conn->prepare("SELECT nome, COALESCE({$estCol}, 0) AS estoque_atual, {$custoExpr} AS preco_custo, {$unidExpr} AS unidade FROM produtos WHERE {$pkCol} = ? LIMIT 1 FOR UPDATE");
            if (!$stmtP) throw new Exception('Erro ao consultar produto para perda: ' . $conn->error);
            $stmtP->bind_param('i', $pid);
            $stmtP->execute();
            $prod = $stmtP->get_result()->fetch_assoc();
            $stmtP->close();

            if (!$prod) {
                throw new Exception("Produto #{$pid} não encontrado para registro de perda.");
            }
            $estoqueDisp = (float)$prod['estoque_atual'];
            if ($qtd > $estoqueDisp) {
                throw new Exception("Estoque insuficiente para perda de '{$prod['nome']}' (disponível: {$estoqueDisp} {$prod['unidade']}).");
            }

            $custoUnit = max(0.0, (float)$prod['preco_custo']);
            $prejuizo = round(-($qtd * $custoUnit), 2);
            $total_perdas_rs += abs($prejuizo);

            $updP = $conn->prepare("UPDATE produtos SET {$estCol} = GREATEST({$estCol} - ?, 0){$updColSql} WHERE {$pkCol} = ? AND {$estCol} >= ?");
            if (!$updP) throw new Exception('Erro ao preparar baixa de estoque na perda: ' . $conn->error);
            $updP->bind_param('did', $qtd, $pid, $qtd);
            if (!$updP->execute() || $updP->affected_rows === 0) {
                $updP->close();
                throw new Exception("Não foi possível baixar estoque de '{$prod['nome']}' na perda.");
            }
            $updP->close();

            $descPerda = 'Fechamento de Caixa' . ($motivo !== '' ? " | {$motivo}" : '');
            registrar_movimentacao_contabil($conn, [
                'produto_id' => $pid,
                'tipo' => 'PERDA',
                'quantidade' => $qtd,
                'valor_unitario_praticado' => 0.00,
                'custo_unitario_praticado' => $custoUnit,
                'resultado_financeiro' => $prejuizo,
                'descricao' => $descPerda,
                'usuario_id' => $usuario_id,
                'sessao_caixa_id' => $sessao_id,
                'origem' => 'FECHAMENTO_CAIXA'
            ]);

            $resumo_lancamentos[] = "Perda: {$qtd} {$prod['unidade']} {$prod['nome']} (-R$ " . number_format(abs($prejuizo), 2, ',', '.') . ")";
        }

        // 2. Processar Queimas de Estoque informadas no fechamento de caixa
        foreach ($queimas as $itemQueima) {
            $pid = (int)($itemQueima['produto_id'] ?? 0);
            $qtd = (float)($itemQueima['quantidade'] ?? 0);
            $precoQueima = (float)($itemQueima['preco_queima'] ?? 0);
            $obsQueima = trim((string)($itemQueima['observacao'] ?? ''));

            if ($pid <= 0 || $qtd <= 0) {
                continue;
            }
            if ($precoQueima <= 0) {
                throw new Exception('Informe um preço promocional válido maior que zero para a queima de estoque.');
            }

            $stmtQ = $conn->prepare("SELECT nome, COALESCE({$estCol}, 0) AS estoque_atual, {$custoExpr} AS preco_custo, {$unidExpr} AS unidade FROM produtos WHERE {$pkCol} = ? LIMIT 1 FOR UPDATE");
            if (!$stmtQ) throw new Exception('Erro ao consultar produto para queima: ' . $conn->error);
            $stmtQ->bind_param('i', $pid);
            $stmtQ->execute();
            $prod = $stmtQ->get_result()->fetch_assoc();
            $stmtQ->close();

            if (!$prod) {
                throw new Exception("Produto #{$pid} não encontrado para queima de estoque.");
            }
            $estoqueDisp = (float)$prod['estoque_atual'];
            if ($qtd > $estoqueDisp) {
                throw new Exception("Estoque insuficiente para queima de '{$prod['nome']}' (disponível: {$estoqueDisp} {$prod['unidade']}).");
            }

            $custoUnit = max(0.0, (float)$prod['preco_custo']);
            $receitaItem = round($qtd * $precoQueima, 2);
            $resultadoItem = round($receitaItem - ($qtd * $custoUnit), 2);
            $total_queimas_receita += $receitaItem;
            $total_queimas_resultado += $resultadoItem;

            $updQ = $conn->prepare("UPDATE produtos SET {$estCol} = GREATEST({$estCol} - ?, 0){$updColSql} WHERE {$pkCol} = ? AND {$estCol} >= ?");
            if (!$updQ) throw new Exception('Erro ao preparar baixa de estoque na queima: ' . $conn->error);
            $updQ->bind_param('did', $qtd, $pid, $qtd);
            if (!$updQ->execute() || $updQ->affected_rows === 0) {
                $updQ->close();
                throw new Exception("Não foi possível baixar estoque de '{$prod['nome']}' na queima.");
            }
            $updQ->close();

            $descQueima = 'Queima no Fechamento a R$ ' . number_format($precoQueima, 2, ',', '.') . ($obsQueima !== '' ? " | {$obsQueima}" : '');
            registrar_movimentacao_contabil($conn, [
                'produto_id' => $pid,
                'tipo' => 'QUEIMA',
                'quantidade' => $qtd,
                'valor_unitario_praticado' => $precoQueima,
                'custo_unitario_praticado' => $custoUnit,
                'resultado_financeiro' => $resultadoItem,
                'descricao' => $descQueima,
                'usuario_id' => $usuario_id,
                'sessao_caixa_id' => $sessao_id,
                'origem' => 'FECHAMENTO_CAIXA'
            ]);

            $resumo_lancamentos[] = "Queima: {$qtd} {$prod['unidade']} {$prod['nome']} (+R$ " . number_format($receitaItem, 2, ',', '.') . ")";
        }

        // Incorporar receita de queima ao total de vendas da sessão e ao valor esperado
        $total_vendas_final = round($total_vendas_pdv + $total_queimas_receita, 2);
        $valor_esperado = round($valor_abertura + $total_vendas_final, 2);
        $diferenca = round($valor_fechamento - $valor_esperado, 2);

        if (count($resumo_lancamentos) > 0) {
            $anexoObs = '[' . implode(' ; ', $resumo_lancamentos) . ']';
            $observacoes = $observacoes !== '' ? ($observacoes . ' ' . $anexoObs) : $anexoObs;
        }

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
        if (!$upd) throw new Exception('Erro ao atualizar sessão.');
        $status = 'fechado';
        $upd->bind_param('sdddsi', $status, $valor_fechamento, $valor_abertura, $total_vendas_final, $observacoes, $sessao_id);
        if (!$upd->execute()) {
            $err = $upd->error;
            $upd->close();
            throw new Exception('Erro ao fechar caixa: ' . $err);
        }
        $upd->close();

        $conn->commit();

        json_response(true, 'Caixa fechado com sucesso.', [
            'sessao_id' => $sessao_id,
            'status' => 'fechado',
            'valor_abertura' => $valor_abertura,
            'total_vendas' => $total_vendas_final,
            'total_vendas_pdv' => $total_vendas_pdv,
            'total_queimas_receita' => round($total_queimas_receita, 2),
            'total_queimas_resultado' => round($total_queimas_resultado, 2),
            'total_perdas_rs' => round($total_perdas_rs, 2),
            'valor_esperado' => $valor_esperado,
            'valor_fechamento' => $valor_fechamento,
            'diferenca' => $diferenca
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        json_error($e->getMessage());
    }
}

json_error('Ação não reconhecida.');
