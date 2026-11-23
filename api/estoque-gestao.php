<?php
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/helpers.php';

session_start();
require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/estoque-contabil.php';

garantir_schema_estoque_contabil($conn);

$action = $_GET['action'] ?? ($_GET['acao'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];

function eg_responder($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function eg_exigir_login() {
    if (empty($_SESSION['usuario_id'])) {
        eg_responder([
            'sucesso' => false,
            'autenticado' => false,
            'mensagem' => 'Sessão expirada. Faça login novamente.'
        ], 401);
    }
}

function eg_exigir_admin() {
    eg_exigir_login();
    if (($_SESSION['tipo'] ?? '') !== 'admin') {
        eg_responder([
            'sucesso' => false,
            'acesso_negado' => true,
            'mensagem' => 'Acesso restrito a administradores.'
        ], 403);
    }
}

function eg_colunas($conn, $tabela) {
    return ec_colunas_tabela($conn, $tabela);
}

function eg_coluna($conn, $tabela, $candidatas) {
    $colunas = eg_colunas($conn, $tabela);
    foreach ($candidatas as $candidata) {
        if (isset($colunas[$candidata])) {
            return $candidata;
        }
    }
    return null;
}

function eg_dados_sessao($conn) {
    $uid = (int)($_SESSION['usuario_id'] ?? 0);
    $nome = (string)($_SESSION['username'] ?? 'Operador');
    $tipo = (string)($_SESSION['tipo'] ?? 'vendedor');

    if ($uid > 0) {
        $usuarioPk = eg_coluna($conn, 'usuarios', ['id_usuario', 'id']);
        $usuarioLogin = eg_coluna($conn, 'usuarios', ['login', 'username']);
        $stmt = $conn->prepare("SELECT COALESCE(NULLIF(nome, ''), NULLIF({$usuarioLogin}, '')) AS nome_exibicao FROM usuarios WHERE {$usuarioPk} = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row && !empty($row['nome_exibicao'])) {
                $nome = $row['nome_exibicao'];
            }
            $stmt->close();
        }
    }

    $cantinaNome = 'Cantina IBFC';
    $resCfg = @$conn->query("SELECT valor FROM configuracoes WHERE chave = 'nome_cantina' LIMIT 1");
    if ($resCfg && ($rCfg = $resCfg->fetch_assoc()) && trim((string)$rCfg['valor']) !== '') {
        $cantinaNome = trim((string)$rCfg['valor']);
    }

    return [
        'usuario_id' => $uid,
        'usuario_nome' => $nome,
        'usuario_tipo' => $tipo,
        'usuario_cargo' => $tipo === 'admin' ? 'Administrador' : 'Ministério',
        'is_admin' => ($tipo === 'admin'),
        'cantina_nome' => $cantinaNome,
        'sessao_caixa_id' => !empty($_SESSION['caixa_sessao_id']) ? (int)$_SESSION['caixa_sessao_id'] : null
    ];
}

// =========================================================
// 1. DASHBOARD OPERACIONAL (AUTO-CONTABILIZADO)
// =========================================================
if ($method === 'GET' && $action === 'dashboard') {
    eg_exigir_login();

    $sincronizados = sincronizar_historico_contabil($conn);
    $produtoPk = eg_coluna($conn, 'produtos', ['id_produto', 'id']);
    $produtoCategoria = eg_coluna($conn, 'produtos', ['tipo_categoria', 'categoria']);
    $produtoUnidade = eg_coluna($conn, 'produtos', ['unidade']);
    $produtoPreco = eg_coluna($conn, 'produtos', ['preco_venda', 'preco']);
    $produtoEstoque = eg_coluna($conn, 'produtos', ['estoque_atual', 'estoque']);
    $produtoAtualizado = eg_coluna($conn, 'produtos', ['data_atualizacao', 'atualizado_em']);
    $usuarioPk = eg_coluna($conn, 'usuarios', ['id_usuario', 'id']);
    $usuarioLogin = eg_coluna($conn, 'usuarios', ['login', 'username']);

    $periodo = $_GET['periodo'] ?? 'sessao';
    $sessaoAtualId = !empty($_SESSION['caixa_sessao_id']) ? (int)$_SESSION['caixa_sessao_id'] : 0;
    $whereData = '';
    if ($periodo === 'sessao') {
        if ($sessaoAtualId > 0) {
            $whereData = "WHERE m.sessao_caixa_id = {$sessaoAtualId}";
        } else {
            $whereData = 'WHERE 1 = 0';
        }
    } elseif ($periodo === 'hoje') {
        $whereData = 'WHERE DATE(m.data_movimentacao) = CURDATE()';
    } elseif ($periodo === '7dias') {
        $whereData = 'WHERE m.data_movimentacao >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
    } elseif ($periodo === '30dias') {
        $whereData = 'WHERE m.data_movimentacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
    }

    $produtos = [];
    $resProd = $conn->query("
        SELECT {$produtoPk} AS id, nome,
               COALESCE(NULLIF({$produtoCategoria}, ''), 'Outro') AS categoria,
               " . ($produtoUnidade ? "COALESCE({$produtoUnidade}, 'un')" : "'un'") . " AS unidade,
               COALESCE(preco_custo, 0) AS custo_unitario,
               COALESCE({$produtoPreco}, 0) AS preco_venda,
               COALESCE({$produtoEstoque}, 0) AS estoque_atual,
               COALESCE(estoque_minimo, 5) AS estoque_minimo,
               COALESCE(status, 'ativo') AS status
        FROM produtos
        WHERE LOWER(COALESCE(status, 'ativo')) NOT IN ('inativo', 'inactive')
        ORDER BY nome ASC
    ");
    if ($resProd) {
        while ($p = $resProd->fetch_assoc()) {
            $custo = (float)$p['custo_unitario'];
            $venda = (float)$p['preco_venda'];
            $produtos[] = [
                'id' => (int)$p['id'],
                'nome' => $p['nome'],
                'categoria' => $p['categoria'],
                'unidade' => $p['unidade'] === 'kg' ? 'kg' : 'un',
                'custo_unitario' => $custo,
                'preco_venda' => $venda,
                'margem_unitaria' => round($venda - $custo, 2),
                'estoque_atual' => (float)$p['estoque_atual'],
                'estoque_minimo' => (float)$p['estoque_minimo'],
                'status' => $p['status']
            ];
        }
    }

    $historico = [];
    $sqlHist = "
        SELECT m.id,
               UPPER(m.tipo) AS tipo,
               m.quantidade,
               m.valor_unitario_praticado,
               m.custo_unitario_praticado,
               m.resultado_financeiro,
               COALESCE(m.descricao, '') AS observacao,
               COALESCE(m.origem, 'MANUAL') AS origem,
               m.venda_id,
               m.data_movimentacao AS data_hora,
               COALESCE(p.nome, CONCAT('Produto #', COALESCE(m.id_produto, m.produto_id))) AS produto_nome,
               " . ($produtoUnidade ? "COALESCE(p.{$produtoUnidade}, 'un')" : "'un'") . " AS unidade,
               COALESCE(NULLIF(u.nome, ''), NULLIF(u.{$usuarioLogin}, ''), 'Sistema') AS operador_nome
        FROM movimentacoes m
        LEFT JOIN produtos p ON p.{$produtoPk} = COALESCE(m.id_produto, m.produto_id)
        LEFT JOIN usuarios u ON u.{$usuarioPk} = COALESCE(m.id_usuario, m.usuario_id)
        {$whereData}
        ORDER BY m.data_movimentacao DESC, m.id DESC
        LIMIT 60
    ";
    $resHist = $conn->query($sqlHist);
    if ($resHist) {
        while ($h = $resHist->fetch_assoc()) {
            $historico[] = [
                'id' => (int)$h['id'],
                'tipo' => $h['tipo'],
                'quantidade' => (float)$h['quantidade'],
                'valor_unitario_praticado' => (float)$h['valor_unitario_praticado'],
                'custo_unitario_praticado' => (float)$h['custo_unitario_praticado'],
                'resultado_financeiro' => (float)$h['resultado_financeiro'],
                'observacao' => $h['observacao'],
                'origem' => $h['origem'],
                'venda_id' => $h['venda_id'] ? (int)$h['venda_id'] : null,
                'data_hora' => $h['data_hora'],
                'produto_nome' => $h['produto_nome'],
                'unidade' => $h['unidade'] === 'kg' ? 'kg' : 'un',
                'operador_nome' => $h['operador_nome']
            ];
        }
    }

    $sqlKpi = "
        SELECT
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'VENDA' THEN (m.quantidade * m.valor_unitario_praticado) ELSE 0 END), 0) AS faturamento_normal,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'QUEIMA' THEN (m.quantidade * m.valor_unitario_praticado) ELSE 0 END), 0) AS faturamento_queima,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) IN ('VENDA', 'QUEIMA') THEN m.resultado_financeiro ELSE 0 END), 0) AS lucro_vendas,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'PERDA' THEN ABS(m.resultado_financeiro) ELSE 0 END), 0) AS total_perda_pura,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'REAPROVEITAMENTO' AND m.resultado_financeiro < 0 THEN ABS(m.resultado_financeiro) ELSE 0 END), 0) AS quebra_transformacao,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) IN ('VENDA', 'QUEIMA', 'REAPROVEITAMENTO', 'PERDA') THEN m.resultado_financeiro ELSE 0 END), 0) AS resultado_liquido_real
        FROM movimentacoes m
        {$whereData}
    ";
    $kpisRow = $conn->query($sqlKpi)->fetch_assoc();

    $capRow = $conn->query("
        SELECT COALESCE(SUM({$produtoEstoque} * COALESCE(preco_custo, 0)), 0) AS capital_estoque,
               SUM(CASE WHEN COALESCE(preco_custo, 0) <= 0 THEN 1 ELSE 0 END) AS sem_custo
        FROM produtos
        WHERE LOWER(COALESCE(status, 'ativo')) NOT IN ('inativo', 'inactive')
    ")->fetch_assoc();

    $fatTotal = (float)$kpisRow['faturamento_normal'] + (float)$kpisRow['faturamento_queima'];
    $perdaPura = (float)$kpisRow['total_perda_pura'];
    $baseIndice = $fatTotal + $perdaPura;
    $indicePerda = $baseIndice > 0 ? round(($perdaPura / $baseIndice) * 100, 2) : 0.0;

    eg_responder([
        'sucesso' => true,
        'sessao' => eg_dados_sessao($conn),
        'periodo' => $periodo,
        'sincronizados_agora' => $sincronizados,
        'kpis' => [
            'capital_estoque' => round((float)($capRow['capital_estoque'] ?? 0), 2),
            'produtos_sem_custo' => (int)($capRow['sem_custo'] ?? 0),
            'faturamento_normal' => round((float)$kpisRow['faturamento_normal'], 2),
            'faturamento_queima' => round((float)$kpisRow['faturamento_queima'], 2),
            'receita_bruta' => round($fatTotal, 2),
            'lucro_vendas' => round((float)$kpisRow['lucro_vendas'], 2),
            'quebra_transformacao' => round((float)$kpisRow['quebra_transformacao'], 2),
            'total_perda_pura' => round($perdaPura, 2),
            'resultado_liquido_real' => round((float)$kpisRow['resultado_liquido_real'], 2),
            'indice_perda' => $indicePerda
        ],
        'produtos' => $produtos,
        'historico' => $historico
    ]);
}

// =========================================================
// 2. CADASTRAR PRODUTO RÁPIDO
// =========================================================
if ($method === 'POST' && $action === 'novo_produto') {
    eg_exigir_login();
    $input = read_json_input();

    $nome      = trim((string)($input['nome'] ?? ''));
    $categoria = trim((string)($input['categoria'] ?? 'Outro'));
    if ($categoria === '') $categoria = 'Outro';
    $unidade   = ($input['unidade'] ?? 'un') === 'kg' ? 'kg' : 'un';
    $custo     = max(0.0, (float)($input['custo_unitario'] ?? 0));
    $venda     = max(0.0, (float)($input['preco_venda'] ?? 0));
    $estoque   = max(0.0, (float)($input['estoque_inicial'] ?? 0));
    $minimo    = max(0.0, (float)($input['estoque_minimo'] ?? 5));

    if ($nome === '' || $venda <= 0) {
        eg_responder(['sucesso' => false, 'mensagem' => 'Informe nome e preço de venda válidos.'], 400);
    }

    $conn->begin_transaction();
    try {
        $produtoPk = eg_coluna($conn, 'produtos', ['id_produto', 'id']);
        $produtoCategoria = eg_coluna($conn, 'produtos', ['tipo_categoria', 'categoria']);
        $produtoPreco = eg_coluna($conn, 'produtos', ['preco_venda', 'preco']);
        $produtoEstoque = eg_coluna($conn, 'produtos', ['estoque_atual', 'estoque']);
        $colsProd = ec_colunas_tabela($conn, 'produtos');
        $status = 'ativo';
        $campos = "nome, {$produtoCategoria}, unidade, {$produtoPreco}, preco_custo, {$produtoEstoque}, estoque_minimo, status";
        $stmt = $conn->prepare("INSERT INTO produtos ({$campos}) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssdddds', $nome, $categoria, $unidade, $venda, $custo, $estoque, $minimo, $status);

        if (!$stmt || !$stmt->execute()) {
            throw new Exception('Erro ao cadastrar produto: ' . ($stmt ? $stmt->error : $conn->error));
        }
        $novoId = (int)$stmt->insert_id;
        $stmt->close();

        if ($estoque > 0) {
            registrar_movimentacao_contabil($conn, [
                'produto_id' => $novoId,
                'tipo' => 'ENTRADA',
                'quantidade' => $estoque,
                'valor_unitario_praticado' => $custo,
                'custo_unitario_praticado' => $custo,
                'resultado_financeiro' => 0.00,
                'descricao' => 'Estoque inicial cadastrado',
                'usuario_id' => (int)$_SESSION['usuario_id'],
                'sessao_caixa_id' => !empty($_SESSION['caixa_sessao_id']) ? (int)$_SESSION['caixa_sessao_id'] : null,
                'origem' => 'CADASTRO'
            ]);
        }

        $conn->commit();
        eg_responder(['sucesso' => true, 'mensagem' => "Produto '{$nome}' cadastrado com sucesso!", 'id' => $novoId]);
    } catch (Exception $e) {
        $conn->rollback();
        eg_responder(['sucesso' => false, 'mensagem' => $e->getMessage()], 400);
    }
}

// =========================================================
// 3. ATUALIZAÇÃO RÁPIDA DE CUSTO / VENDA / MÍNIMO
// =========================================================
if ($method === 'POST' && $action === 'atualizar_produto_rapido') {
    eg_exigir_login();
    $input = read_json_input();

    $id      = (int)($input['id'] ?? 0);
    $custo   = max(0.0, (float)($input['custo_unitario'] ?? 0));
    $venda   = max(0.0, (float)($input['preco_venda'] ?? 0));
    $minimo  = max(0.0, (float)($input['estoque_minimo'] ?? 5));
    $unidade = ($input['unidade'] ?? 'un') === 'kg' ? 'kg' : 'un';

    if ($id <= 0 || $venda <= 0) {
        eg_responder(['sucesso' => false, 'mensagem' => 'Dados inválidos para atualizar produto.'], 400);
    }

    $colsProd = ec_colunas_tabela($conn, 'produtos');
    $produtoPk = eg_coluna($conn, 'produtos', ['id_produto', 'id']);
    $produtoPreco = eg_coluna($conn, 'produtos', ['preco_venda', 'preco']);
    $produtoAtualizado = eg_coluna($conn, 'produtos', ['data_atualizacao', 'atualizado_em']);
    $stmt = $conn->prepare("UPDATE produtos SET {$produtoPreco} = ?, preco_custo = ?, estoque_minimo = ?, unidade = ?, {$produtoAtualizado} = CURRENT_TIMESTAMP WHERE {$produtoPk} = ?");
    $stmt->bind_param('dddsi', $venda, $custo, $minimo, $unidade, $id);

    if ($stmt && $stmt->execute()) {
        $stmt->close();
        eg_responder(['sucesso' => true, 'mensagem' => 'Custo, preço e estoque mínimo atualizados!']);
    }
    eg_responder(['sucesso' => false, 'mensagem' => 'Erro ao atualizar produto: ' . $conn->error], 400);
}

// =========================================================
// 4. REGISTRAR MOVIMENTAÇÃO (ENTRADA / VENDA / QUEIMA / REAPROVEITAMENTO / PERDA)
// =========================================================
if ($method === 'POST' && $action === 'movimentar') {
    eg_exigir_login();
    $input = read_json_input();

    $produtoId = (int)($input['produto_id'] ?? 0);
    $tipo      = strtoupper(trim((string)($input['tipo'] ?? '')));
    $qtd       = (float)($input['quantidade'] ?? 0);
    $obs       = trim((string)($input['observacao'] ?? ''));
    $usuarioId = (int)$_SESSION['usuario_id'];
    $sessaoCaixaId = !empty($_SESSION['caixa_sessao_id']) ? (int)$_SESSION['caixa_sessao_id'] : null;

    if ($produtoId <= 0 || $qtd <= 0) {
        eg_responder(['sucesso' => false, 'mensagem' => 'Selecione um produto e informe uma quantidade maior que zero.'], 400);
    }

    $conn->begin_transaction();
    try {
        $produtoPk = eg_coluna($conn, 'produtos', ['id_produto', 'id']);
        $produtoPreco = eg_coluna($conn, 'produtos', ['preco_venda', 'preco']);
        $produtoEstoque = eg_coluna($conn, 'produtos', ['estoque_atual', 'estoque']);
        $produtoAtualizado = eg_coluna($conn, 'produtos', ['data_atualizacao', 'atualizado_em']);
        $produtoUnidade = eg_coluna($conn, 'produtos', ['unidade']);
        $stmt = $conn->prepare("
            SELECT {$produtoPk} AS id_produto, nome, " . ($produtoUnidade ? "COALESCE({$produtoUnidade}, 'un')" : "'un'") . " AS unidade,
                   COALESCE(preco_custo, 0) AS custo_unitario,
                   COALESCE({$produtoPreco}, 0) AS preco_venda,
                   COALESCE({$produtoEstoque}, 0) AS estoque_atual
            FROM produtos
            WHERE {$produtoPk} = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->bind_param('i', $produtoId);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$prod) {
            throw new Exception('Produto não encontrado.');
        }

        $estoqueAtual = (float)$prod['estoque_atual'];
        $custoUnit = max(0.0, (float)$prod['custo_unitario']);
        $precoVenda = (float)$prod['preco_venda'];
        $unidade = $prod['unidade'] === 'kg' ? 'kg' : 'un';

        if ($tipo !== 'ENTRADA' && $qtd > $estoqueAtual) {
            throw new Exception("Saldo insuficiente ({$estoqueAtual} {$unidade} disponíveis).");
        }

        $custoTotalLote = $qtd * $custoUnit;
        $valorPraticado = 0.0;
        $resultadoFinanceiro = 0.0;
        $novoEstoque = $estoqueAtual;
        $destinoId = null;
        $qtdGerada = null;

        switch ($tipo) {
            case 'ENTRADA':
                $valorPraticado = $custoUnit;
                $resultadoFinanceiro = 0.0;
                $novoEstoque = $estoqueAtual + $qtd;
                break;

            case 'VENDA':
                $valorPraticado = $precoVenda;
                $resultadoFinanceiro = ($qtd * $valorPraticado) - $custoTotalLote;
                $novoEstoque = $estoqueAtual - $qtd;
                break;

            case 'QUEIMA':
                $valorPraticado = (float)($input['preco_queima'] ?? 0);
                if ($valorPraticado <= 0) {
                    throw new Exception('Informe um preço promocional válido para a queima.');
                }
                $resultadoFinanceiro = ($qtd * $valorPraticado) - $custoTotalLote;
                $novoEstoque = $estoqueAtual - $qtd;
                $obs = 'Queima a R$ ' . number_format($valorPraticado, 2, ',', '.') . ($obs !== '' ? " | {$obs}" : '');
                break;

            case 'REAPROVEITAMENTO':
                $destinoId = (int)($input['produto_destino_id'] ?? 0);
                $qtdGerada = (float)($input['qtd_gerada'] ?? 0);

                if ($destinoId <= 0 || $destinoId === $produtoId) {
                    throw new Exception('Escolha um subproduto de destino diferente do produto de origem.');
                }
                if ($qtdGerada <= 0) {
                    throw new Exception('Informe a quantidade gerada do subproduto.');
                }

                $stmtDest = $conn->prepare("
                    SELECT {$produtoPk} AS id_produto, nome, " . ($produtoUnidade ? "COALESCE({$produtoUnidade}, 'un')" : "'un'") . " AS unidade,
                           COALESCE(preco_custo, 0) AS custo_unitario
                    FROM produtos
                    WHERE {$produtoPk} = ?
                    LIMIT 1
                    FOR UPDATE
                ");
                $stmtDest->bind_param('i', $destinoId);
                $stmtDest->execute();
                $prodDest = $stmtDest->get_result()->fetch_assoc();
                $stmtDest->close();

                if (!$prodDest) {
                    throw new Exception('Subproduto de destino não encontrado.');
                }

                $custoDest = max(0.0, (float)$prodDest['custo_unitario']);
                $valorRecuperado = $qtdGerada * $custoDest;
                $valorPraticado = $custoDest;
                $resultadoFinanceiro = $valorRecuperado - $custoTotalLote;
                $novoEstoque = $estoqueAtual - $qtd;

                $updDest = $conn->prepare("UPDATE produtos SET {$produtoEstoque} = {$produtoEstoque} + ?, {$produtoAtualizado} = CURRENT_TIMESTAMP WHERE {$produtoPk} = ?");
                $updDest->bind_param('di', $qtdGerada, $destinoId);
                if (!$updDest->execute()) {
                    throw new Exception('Erro ao creditar estoque do subproduto.');
                }
                $updDest->close();

                $unDest = $prodDest['unidade'] === 'kg' ? 'kg' : 'un';
                $obs = "Convertido em {$qtdGerada} {$unDest} de {$prodDest['nome']}" . ($obs !== '' ? " | {$obs}" : '');
                break;

            case 'PERDA':
                $valorPraticado = 0.0;
                $resultadoFinanceiro = -$custoTotalLote;
                $novoEstoque = $estoqueAtual - $qtd;
                break;

            default:
                throw new Exception('Tipo de operação inválido.');
        }

        $updOrig = $conn->prepare("UPDATE produtos SET {$produtoEstoque} = ?, {$produtoAtualizado} = CURRENT_TIMESTAMP WHERE {$produtoPk} = ?");
        $updOrig->bind_param('di', $novoEstoque, $produtoId);
        if (!$updOrig->execute()) {
            throw new Exception('Erro ao atualizar estoque do produto.');
        }
        $updOrig->close();

        registrar_movimentacao_contabil($conn, [
            'produto_id' => $produtoId,
            'tipo' => $tipo,
            'quantidade' => $qtd,
            'valor_unitario_praticado' => $valorPraticado,
            'custo_unitario_praticado' => $custoUnit,
            'resultado_financeiro' => $resultadoFinanceiro,
            'descricao' => $obs,
            'usuario_id' => $usuarioId,
            'sessao_caixa_id' => $sessaoCaixaId,
            'produto_destino_id' => $destinoId,
            'quantidade_gerada' => $qtdGerada,
            'origem' => 'MANUAL'
        ]);

        $conn->commit();
        $sessao = eg_dados_sessao($conn);
        eg_responder([
            'sucesso' => true,
            'mensagem' => "Lançamento ({$tipo}) registrado por {$sessao['usuario_nome']}!",
            'resultado_financeiro' => round($resultadoFinanceiro, 2),
            'novo_estoque' => round($novoEstoque, 3)
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        eg_responder(['sucesso' => false, 'mensagem' => $e->getMessage()], 400);
    }
}

// =========================================================
// 5. RELATÓRIOS GERENCIAIS DE PERDAS, QUEIMAS & LUCRO/PREJUÍZO + EXPORT CSV
// =========================================================
if ($method === 'GET' && ($action === 'relatorios' || $action === 'exportar_csv')) {
    eg_exigir_admin();
    sincronizar_historico_contabil($conn);
    $produtoPk = eg_coluna($conn, 'produtos', ['id_produto', 'id']);
    $produtoCategoria = eg_coluna($conn, 'produtos', ['tipo_categoria', 'categoria']);
    $produtoUnidade = eg_coluna($conn, 'produtos', ['unidade']);
    $usuarioPk = eg_coluna($conn, 'usuarios', ['id_usuario', 'id']);
    $usuarioLogin = eg_coluna($conn, 'usuarios', ['login', 'username']);

    $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
    $dataFim    = $_GET['data_fim']    ?? date('Y-m-d');
    $usuarioFiltro   = $_GET['usuario_id'] ?? 'TODOS';
    $categoriaFiltro = trim((string)($_GET['categoria'] ?? 'TODAS'));
    $sessaoFiltro    = trim((string)($_GET['sessao_id'] ?? 'ATUAL'));
    $sessaoAtualId   = !empty($_SESSION['caixa_sessao_id']) ? (int)$_SESSION['caixa_sessao_id'] : 0;

    $dtIni = $dataInicio . ' 00:00:00';
    $dtFim = $dataFim . ' 23:59:59';

    // Sessões recentes para filtro
    $sessoesLista = [];
    $resSess = @$conn->query("
        SELECT id_sessao, login_usuario, abertura_caixa, status
        FROM caixa_sessoes
        ORDER BY id_sessao DESC
        LIMIT 40
    ");
    if ($resSess) {
        while ($rs = $resSess->fetch_assoc()) {
            $sessoesLista[] = [
                'id' => (int)$rs['id_sessao'],
                'login' => $rs['login_usuario'],
                'abertura' => $rs['abertura_caixa'],
                'status' => $rs['status']
            ];
        }
    }

    // Listas para filtros
    $operadores = [];
    $resOps = $conn->query("
        SELECT {$usuarioPk} AS id,
               COALESCE(NULLIF(nome, ''), NULLIF({$usuarioLogin}, '')) AS nome,
               COALESCE(papel, tipo, 'vendedor') AS cargo
        FROM usuarios
        ORDER BY nome ASC
    ");
    if ($resOps) {
        while ($r = $resOps->fetch_assoc()) {
            $operadores[] = [
                'id' => (int)$r['id'],
                'nome' => $r['nome'],
                'cargo' => $r['cargo']
            ];
        }
    }

    $categorias = [];
    $resCats = $conn->query("SELECT DISTINCT COALESCE(NULLIF({$produtoCategoria}, ''), 'Outro') AS categoria FROM produtos ORDER BY categoria ASC");
    if ($resCats) {
        while ($r = $resCats->fetch_assoc()) {
            $categorias[] = $r['categoria'];
        }
    }

    // Construir filtros dinâmicos
    $whereParts = ['m.data_movimentacao BETWEEN ? AND ?'];
    $types = 'ss';
    $params = [$dtIni, $dtFim];

    if ($sessaoFiltro === 'ATUAL') {
        if ($sessaoAtualId > 0) {
            $whereParts[] = 'm.sessao_caixa_id = ?';
            $types .= 'i';
            $params[] = $sessaoAtualId;
        } else {
            $whereParts[] = '1 = 0';
        }
    } elseif ($sessaoFiltro !== 'TODAS' && is_numeric($sessaoFiltro) && (int)$sessaoFiltro > 0) {
        $whereParts[] = 'm.sessao_caixa_id = ?';
        $types .= 'i';
        $params[] = (int)$sessaoFiltro;
    }

    if ($usuarioFiltro !== 'TODOS' && is_numeric($usuarioFiltro) && (int)$usuarioFiltro > 0) {
        $whereParts[] = 'COALESCE(m.id_usuario, m.usuario_id) = ?';
        $types .= 'i';
        $params[] = (int)$usuarioFiltro;
    }

    if ($categoriaFiltro !== '' && $categoriaFiltro !== 'TODAS') {
        $whereParts[] = "COALESCE(NULLIF(p.{$produtoCategoria}, ''), 'Outro') = ?";
        $types .= 's';
        $params[] = $categoriaFiltro;
    }

    $whereSql = implode(' AND ', $whereParts);

    // 5.1 Ranking por Categoria
    $sqlCat = "
        SELECT
            COALESCE(NULLIF(p.{$produtoCategoria}, ''), 'Outro') AS categoria,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'VENDA' THEN (m.quantidade * m.valor_unitario_praticado) ELSE 0 END), 0) AS faturamento_normal,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'QUEIMA' THEN (m.quantidade * m.valor_unitario_praticado) ELSE 0 END), 0) AS faturamento_queima,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) IN ('VENDA', 'QUEIMA') THEN m.resultado_financeiro ELSE 0 END), 0) AS lucro_vendas,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'REAPROVEITAMENTO' AND m.resultado_financeiro < 0 THEN ABS(m.resultado_financeiro) ELSE 0 END), 0) AS quebra_transformacao,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'PERDA' THEN ABS(m.resultado_financeiro) ELSE 0 END), 0) AS perda_pura,
            COUNT(CASE WHEN UPPER(m.tipo) = 'PERDA' THEN 1 END) AS qtd_ocorrencias_perda,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) IN ('VENDA', 'QUEIMA', 'REAPROVEITAMENTO', 'PERDA') THEN m.resultado_financeiro ELSE 0 END), 0) AS resultado_liquido
        FROM movimentacoes m
        LEFT JOIN produtos p ON p.{$produtoPk} = COALESCE(m.id_produto, m.produto_id)
        WHERE {$whereSql}
        GROUP BY 1
        ORDER BY perda_pura DESC, resultado_liquido DESC
    ";
    $stmtCat = $conn->prepare($sqlCat);
    $stmtCat->bind_param($types, ...$params);
    $stmtCat->execute();
    $rankingCategorias = $stmtCat->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtCat->close();

    // 5.2 Auditoria por Operador
    $sqlOp = "
        SELECT
            COALESCE(u.{$usuarioPk}, 0) AS usuario_id,
            COALESCE(NULLIF(u.nome, ''), NULLIF(u.{$usuarioLogin}, ''), 'Sistema') AS operador_nome,
            COALESCE(u.papel, u.tipo, 'vendedor') AS cargo,
            COUNT(m.id) AS total_lancamentos,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'VENDA' THEN (m.quantidade * m.valor_unitario_praticado) ELSE 0 END), 0) AS total_vendas_rs,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'PERDA' THEN ABS(m.resultado_financeiro) ELSE 0 END), 0) AS total_perda_rs,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'QUEIMA' THEN (m.quantidade * m.valor_unitario_praticado) ELSE 0 END), 0) AS total_queima_recuperada_rs,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'REAPROVEITAMENTO' THEN m.quantidade ELSE 0 END), 0) AS qtd_reaproveitada,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) IN ('VENDA', 'QUEIMA', 'REAPROVEITAMENTO', 'PERDA') THEN m.resultado_financeiro ELSE 0 END), 0) AS resultado_liquido_rs
        FROM movimentacoes m
        LEFT JOIN produtos p ON p.{$produtoPk} = COALESCE(m.id_produto, m.produto_id)
        LEFT JOIN usuarios u ON u.{$usuarioPk} = COALESCE(m.id_usuario, m.usuario_id)
        WHERE {$whereSql}
        GROUP BY 1, 2, 3
        ORDER BY total_perda_rs DESC, total_vendas_rs DESC
    ";
    $stmtOp = $conn->prepare($sqlOp);
    $stmtOp->bind_param($types, ...$params);
    $stmtOp->execute();
    $rankingOperadores = $stmtOp->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtOp->close();

    // 5.3 Produtos Críticos (Maiores Perdas / Queimas / Reaproveitamento)
    $sqlViloes = "
        SELECT
            COALESCE(p.nome, CONCAT('Produto #', COALESCE(m.id_produto, m.produto_id))) AS produto_nome,
            COALESCE(NULLIF(p.{$produtoCategoria}, ''), 'Outro') AS categoria,
            " . ($produtoUnidade ? "COALESCE(p.{$produtoUnidade}, 'un')" : "'un'") . " AS unidade,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'VENDA' THEN m.quantidade ELSE 0 END), 0) AS qtd_vendida,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'VENDA' THEN m.resultado_financeiro ELSE 0 END), 0) AS lucro_vendas,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'PERDA' THEN m.quantidade ELSE 0 END), 0) AS qtd_perda,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'PERDA' THEN ABS(m.resultado_financeiro) ELSE 0 END), 0) AS valor_perda,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'QUEIMA' THEN m.quantidade ELSE 0 END), 0) AS qtd_queima,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'REAPROVEITAMENTO' THEN m.quantidade ELSE 0 END), 0) AS qtd_reaproveitada
        FROM movimentacoes m
        LEFT JOIN produtos p ON p.{$produtoPk} = COALESCE(m.id_produto, m.produto_id)
        WHERE {$whereSql}
          AND UPPER(m.tipo) IN ('PERDA', 'QUEIMA', 'REAPROVEITAMENTO', 'VENDA')
        GROUP BY COALESCE(m.id_produto, m.produto_id), 1, 2, 3
        HAVING valor_perda > 0 OR qtd_queima > 0 OR qtd_reaproveitada > 0
        ORDER BY valor_perda DESC, qtd_queima DESC
        LIMIT 15
    ";
    $stmtVil = $conn->prepare($sqlViloes);
    $stmtVil->bind_param($types, ...$params);
    $stmtVil->execute();
    $produtosViloes = $stmtVil->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtVil->close();

    // 5.4 Evolução Diária (Lucros vs Prejuízos por dia)
    $sqlDia = "
        SELECT
            DATE(m.data_movimentacao) AS dia,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) IN ('VENDA', 'QUEIMA') THEN (m.quantidade * m.valor_unitario_praticado) ELSE 0 END), 0) AS receita_bruta,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) IN ('VENDA', 'QUEIMA') THEN m.resultado_financeiro ELSE 0 END), 0) AS lucro_vendas,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'PERDA' THEN ABS(m.resultado_financeiro) ELSE 0 END), 0) AS perda_pura,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) = 'REAPROVEITAMENTO' AND m.resultado_financeiro < 0 THEN ABS(m.resultado_financeiro) ELSE 0 END), 0) AS quebra_transf,
            COALESCE(SUM(CASE WHEN UPPER(m.tipo) IN ('VENDA', 'QUEIMA', 'REAPROVEITAMENTO', 'PERDA') THEN m.resultado_financeiro ELSE 0 END), 0) AS resultado_liquido
        FROM movimentacoes m
        LEFT JOIN produtos p ON p.{$produtoPk} = COALESCE(m.id_produto, m.produto_id)
        WHERE {$whereSql}
        GROUP BY dia
        ORDER BY dia DESC
        LIMIT 60
    ";
    $stmtDia = $conn->prepare($sqlDia);
    $stmtDia->bind_param($types, ...$params);
    $stmtDia->execute();
    $evolucaoDiaria = $stmtDia->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtDia->close();

    // Consolidar resumo geral
    $totFatNormal = 0.0;
    $totFatQueima = 0.0;
    $totLucroVendas = 0.0;
    $totQuebra = 0.0;
    $totPerda = 0.0;
    $totResultadoLiquido = 0.0;

    foreach ($rankingCategorias as $rc) {
        $totFatNormal += (float)$rc['faturamento_normal'];
        $totFatQueima += (float)$rc['faturamento_queima'];
        $totLucroVendas += (float)$rc['lucro_vendas'];
        $totQuebra += (float)$rc['quebra_transformacao'];
        $totPerda += (float)$rc['perda_pura'];
        $totResultadoLiquido += (float)$rc['resultado_liquido'];
    }
    $receitaBruta = $totFatNormal + $totFatQueima;
    $baseIndiceGeral = $receitaBruta + $totPerda;
    $indicePerdaGeral = $baseIndiceGeral > 0 ? round(($totPerda / $baseIndiceGeral) * 100, 2) : 0.0;

    $sessao = eg_dados_sessao($conn);

    // Exportar CSV para Excel
    if ($action === 'exportar_csv') {
        $nomeArquivo = "relatorio_lucros_perdas_{$dataInicio}_a_{$dataFim}.csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($out, ['RELATORIO GERENCIAL DE VENDAS, PERDAS, QUEIMAS E LUCRO LIQUIDO - ' . strtoupper($sessao['cantina_nome'])], ';');
        fputcsv($out, ['Periodo:', date('d/m/Y', strtotime($dataInicio)) . ' a ' . date('d/m/Y', strtotime($dataFim))], ';');
        fputcsv($out, ['Gerado por:', $sessao['usuario_nome']], ';');
        fputcsv($out, [], ';');

        fputcsv($out, ['RESUMO GERAL'], ';');
        fputcsv($out, ['Receita Bruta (R$)', 'Queima Recuperada (R$)', 'Lucro Vendas+Queima (R$)', 'Quebra Transf. (R$)', 'Perda Pura Lixo (R$)', 'Resultado Liquido Real (R$)', 'Indice de Perda (%)'], ';');
        fputcsv($out, [
            number_format($receitaBruta, 2, ',', ''),
            number_format($totFatQueima, 2, ',', ''),
            number_format($totLucroVendas, 2, ',', ''),
            number_format($totQuebra, 2, ',', ''),
            number_format($totPerda, 2, ',', ''),
            number_format($totResultadoLiquido, 2, ',', ''),
            number_format($indicePerdaGeral, 2, ',', '') . '%'
        ], ';');
        fputcsv($out, [], ';');

        fputcsv($out, ['1. DESEMPENHO POR CATEGORIA'], ';');
        fputcsv($out, ['Posicao', 'Categoria', 'Venda Normal (R$)', 'Queima Saldao (R$)', 'Quebra Transf. (R$)', 'Perda Pura (R$)', 'Indice Perda (%)', 'Lucro Vendas (R$)', 'Resultado Liquido (R$)'], ';');
        foreach ($rankingCategorias as $idx => $cat) {
            $fat = (float)$cat['faturamento_normal'] + (float)$cat['faturamento_queima'];
            $perdaCat = (float)$cat['perda_pura'];
            $baseCat = $fat + $perdaCat;
            $ind = $baseCat > 0 ? ($perdaCat / $baseCat) * 100 : 0;
            fputcsv($out, [
                '#' . ($idx + 1),
                $cat['categoria'],
                number_format((float)$cat['faturamento_normal'], 2, ',', ''),
                number_format((float)$cat['faturamento_queima'], 2, ',', ''),
                number_format((float)$cat['quebra_transformacao'], 2, ',', ''),
                number_format((float)$cat['perda_pura'], 2, ',', ''),
                number_format($ind, 2, ',', '') . '%',
                number_format((float)$cat['lucro_vendas'], 2, ',', ''),
                number_format((float)$cat['resultado_liquido'], 2, ',', '')
            ], ';');
        }
        fputcsv($out, [], ';');

        fputcsv($out, ['2. AUDITORIA POR OPERADOR'], ';');
        fputcsv($out, ['Operador', 'Perfil', 'Lancamentos', 'Vendas (R$)', 'Perda Pura (R$)', 'Queima Recuperada (R$)', 'Qtd Reaproveitada', 'Resultado Liquido (R$)'], ';');
        foreach ($rankingOperadores as $op) {
            fputcsv($out, [
                $op['operador_nome'],
                $op['cargo'],
                $op['total_lancamentos'],
                number_format((float)$op['total_vendas_rs'], 2, ',', ''),
                number_format((float)$op['total_perda_rs'], 2, ',', ''),
                number_format((float)$op['total_queima_recuperada_rs'], 2, ',', ''),
                number_format((float)$op['qtd_reaproveitada'], 2, ',', ''),
                number_format((float)$op['resultado_liquido_rs'], 2, ',', '')
            ], ';');
        }
        fputcsv($out, [], ';');

        fputcsv($out, ['3. PRODUTOS CRITICOS'], ';');
        fputcsv($out, ['Produto', 'Categoria', 'Perda Pura (R$)', 'Qtd Perdida', 'Qtd Queima', 'Qtd Reaproveitada', 'Lucro Vendas (R$)'], ';');
        foreach ($produtosViloes as $pv) {
            fputcsv($out, [
                $pv['produto_nome'],
                $pv['categoria'],
                number_format((float)$pv['valor_perda'], 2, ',', ''),
                number_format((float)$pv['qtd_perda'], 2, ',', '') . ' ' . $pv['unidade'],
                number_format((float)$pv['qtd_queima'], 2, ',', '') . ' ' . $pv['unidade'],
                number_format((float)$pv['qtd_reaproveitada'], 2, ',', '') . ' ' . $pv['unidade'],
                number_format((float)$pv['lucro_vendas'], 2, ',', '')
            ], ';');
        }

        fclose($out);
        exit;
    }

    eg_responder([
        'sucesso' => true,
        'sessao' => $sessao,
        'sessoes' => $sessoesLista,
        'operadores' => $operadores,
        'categorias' => $categorias,
        'resumo_geral' => [
            'faturamento_normal' => round($totFatNormal, 2),
            'faturamento_queima' => round($totFatQueima, 2),
            'receita_bruta' => round($receitaBruta, 2),
            'lucro_vendas' => round($totLucroVendas, 2),
            'quebra_transformacao' => round($totQuebra, 2),
            'perda_pura' => round($totPerda, 2),
            'resultado_liquido_real' => round($totResultadoLiquido, 2),
            'indice_perda' => $indicePerdaGeral
        ],
        'ranking_categorias' => $rankingCategorias,
        'ranking_operadores' => $rankingOperadores,
        'produtos_viloes' => $produtosViloes,
        'evolucao_diaria' => $evolucaoDiaria
    ]);
}

// =========================================================
// 6. FORÇAR SINCRONIZAÇÃO DE HISTÓRICO
// =========================================================
if ($method === 'POST' && $action === 'sincronizar') {
    eg_exigir_login();
    $qtd = sincronizar_historico_contabil($conn);
    eg_responder([
        'sucesso' => true,
        'mensagem' => $qtd > 0
            ? "{$qtd} venda(s) do histórico foram sincronizadas e contabilizadas automaticamente."
            : 'Todo o histórico de vendas e perdas já está sincronizado.',
        'importados' => $qtd
    ]);
}

eg_responder(['sucesso' => false, 'mensagem' => 'Ação inválida.'], 400);
