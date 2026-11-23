<?php
/**
 * Motor de Contabilidade Automática de Estoque, Vendas, Queimas, Reaproveitamento e Perdas.
 * Garante que vendas do PDV, perdas, queimas e reaproveitamentos contabilizem
 * lucros e prejuízos automaticamente na tabela `movimentacoes`.
 */

function ec_colunas_tabela($conn, $tabela) {
    $colunas = [];
    $res = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($tabela) . "`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $colunas[$row['Field']] = $row;
        }
    }
    return $colunas;
}

function ec_primeira_coluna($conn, $tabela, $candidatas) {
    $colunas = ec_colunas_tabela($conn, $tabela);
    foreach ($candidatas as $candidata) {
        if (isset($colunas[$candidata])) {
            return $candidata;
        }
    }
    return null;
}

function ec_mapa_colunas_produtos($conn) {
    garantir_schema_estoque_contabil($conn);
    return [
        'pk'         => ec_primeira_coluna($conn, 'produtos', ['id_produto', 'id']) ?: 'id_produto',
        'estoque'    => ec_primeira_coluna($conn, 'produtos', ['estoque_atual', 'estoque']) ?: 'estoque',
        'preco'      => ec_primeira_coluna($conn, 'produtos', ['preco_venda', 'preco']) ?: 'preco',
        'categoria'  => ec_primeira_coluna($conn, 'produtos', ['tipo_categoria', 'categoria']) ?: 'categoria',
        'atualizado' => ec_primeira_coluna($conn, 'produtos', ['data_atualizacao', 'atualizado_em']),
        'unidade'    => ec_primeira_coluna($conn, 'produtos', ['unidade']),
        'custo'      => ec_primeira_coluna($conn, 'produtos', ['preco_custo']),
        'minimo'     => ec_primeira_coluna($conn, 'produtos', ['estoque_minimo'])
    ];
}

function garantir_schema_estoque_contabil($conn) {
    static $executado = false;
    if ($executado) {
        return;
    }
    $executado = true;

    // 1. Garantir colunas na tabela produtos
    $colsProd = ec_colunas_tabela($conn, 'produtos');
    if (!isset($colsProd['preco_custo'])) {
        $ancoraPreco = isset($colsProd['preco_venda']) ? 'preco_venda' : 'preco';
        @$conn->query("ALTER TABLE produtos ADD COLUMN preco_custo DECIMAL(10, 2) DEFAULT 0.00 AFTER {$ancoraPreco}");
    }
    if (!isset($colsProd['unidade'])) {
        @$conn->query("ALTER TABLE produtos ADD COLUMN unidade ENUM('un', 'kg') NOT NULL DEFAULT 'un' AFTER nome");
    }
    if (!isset($colsProd['estoque_minimo'])) {
        $ancoraEstoque = isset($colsProd['estoque_atual']) ? 'estoque_atual' : 'estoque';
        @$conn->query("ALTER TABLE produtos ADD COLUMN estoque_minimo DECIMAL(10, 3) NOT NULL DEFAULT 5.000 AFTER {$ancoraEstoque}");
    }
    $colEst = isset($colsProd['estoque_atual']) ? 'estoque_atual' : (isset($colsProd['estoque']) ? 'estoque' : null);
    if ($colEst && stripos($colsProd[$colEst]['Type'] ?? '', 'int') !== false) {
        @$conn->query("ALTER TABLE produtos MODIFY COLUMN {$colEst} DECIMAL(10, 3) NOT NULL DEFAULT 0.000");
    }
    if (isset($colsProd['preco']) && stripos($colsProd['preco']['Null'] ?? '', 'NO') !== false && ($colsProd['preco']['Default'] ?? null) === null) {
        @$conn->query("ALTER TABLE produtos MODIFY COLUMN preco DECIMAL(10, 2) NOT NULL DEFAULT 0.00");
    }

    // 2. Garantir tabela movimentacoes e suas colunas contábeis
    $conn->query("CREATE TABLE IF NOT EXISTS movimentacoes (
        id INT PRIMARY KEY AUTO_INCREMENT,
        produto_id INT NOT NULL,
        id_produto INT NULL,
        tipo VARCHAR(30) NOT NULL,
        quantidade DECIMAL(10, 3) NOT NULL DEFAULT 0.000,
        valor_unitario_praticado DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        custo_unitario_praticado DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        resultado_financeiro DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        descricao TEXT NULL,
        usuario_id INT NULL,
        id_usuario INT NULL,
        venda_id INT NULL,
        item_venda_id INT NULL,
        sessao_caixa_id INT NULL,
        produto_destino_id INT NULL,
        quantidade_gerada DECIMAL(10, 3) NULL,
        origem VARCHAR(30) NOT NULL DEFAULT 'MANUAL',
        data_movimentacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_mov_produto (produto_id),
        INDEX idx_mov_data (data_movimentacao),
        INDEX idx_mov_venda (venda_id),
        INDEX idx_mov_sessao (sessao_caixa_id),
        INDEX idx_mov_tipo_data (tipo, data_movimentacao)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $colsMov = ec_colunas_tabela($conn, 'movimentacoes');
    if (isset($colsMov['quantidade']) && stripos($colsMov['quantidade']['Type'] ?? '', 'int') !== false) {
        @$conn->query("ALTER TABLE movimentacoes MODIFY COLUMN quantidade DECIMAL(10, 3) NOT NULL DEFAULT 0.000");
    }
    if (isset($colsMov['tipo']) && stripos($colsMov['tipo']['Type'] ?? '', 'varchar(20)') !== false) {
        @$conn->query("ALTER TABLE movimentacoes MODIFY COLUMN tipo VARCHAR(30) NOT NULL");
    }
    if (!isset($colsMov['id_produto'])) {
        @$conn->query("ALTER TABLE movimentacoes ADD COLUMN id_produto INT NULL AFTER produto_id");
    }
    if (!isset($colsMov['id_usuario'])) {
        @$conn->query("ALTER TABLE movimentacoes ADD COLUMN id_usuario INT NULL AFTER usuario_id");
    }
    if (!isset($colsMov['valor_unitario_praticado'])) {
        @$conn->query("ALTER TABLE movimentacoes ADD COLUMN valor_unitario_praticado DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER quantidade");
    }
    if (!isset($colsMov['custo_unitario_praticado'])) {
        @$conn->query("ALTER TABLE movimentacoes ADD COLUMN custo_unitario_praticado DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER valor_unitario_praticado");
    }
    if (!isset($colsMov['resultado_financeiro'])) {
        @$conn->query("ALTER TABLE movimentacoes ADD COLUMN resultado_financeiro DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER custo_unitario_praticado");
    }
    if (!isset($colsMov['venda_id'])) {
        @$conn->query("ALTER TABLE movimentacoes ADD COLUMN venda_id INT NULL AFTER id_usuario");
        @$conn->query("ALTER TABLE movimentacoes ADD INDEX idx_mov_venda (venda_id)");
    }
    if (!isset($colsMov['item_venda_id'])) {
        @$conn->query("ALTER TABLE movimentacoes ADD COLUMN item_venda_id INT NULL AFTER venda_id");
        @$conn->query("ALTER TABLE movimentacoes ADD INDEX idx_mov_item_venda (item_venda_id)");
    }
    if (!isset($colsMov['sessao_caixa_id'])) {
        @$conn->query("ALTER TABLE movimentacoes ADD COLUMN sessao_caixa_id INT NULL AFTER item_venda_id");
        @$conn->query("ALTER TABLE movimentacoes ADD INDEX idx_mov_sessao (sessao_caixa_id)");
    }
    if (!isset($colsMov['produto_destino_id'])) {
        @$conn->query("ALTER TABLE movimentacoes ADD COLUMN produto_destino_id INT NULL AFTER sessao_caixa_id");
    }
    if (!isset($colsMov['quantidade_gerada'])) {
        @$conn->query("ALTER TABLE movimentacoes ADD COLUMN quantidade_gerada DECIMAL(10, 3) NULL AFTER produto_destino_id");
    }
    if (!isset($colsMov['origem'])) {
        @$conn->query("ALTER TABLE movimentacoes ADD COLUMN origem VARCHAR(30) NOT NULL DEFAULT 'MANUAL' AFTER quantidade_gerada");
    }
}

/**
 * Resolve o ID do usuário autenticado na sessão ou o informado.
 */
function ec_obter_usuario_id($fallback = null) {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    if (!empty($_SESSION['usuario_id'])) {
        return (int)$_SESSION['usuario_id'];
    }
    if (!empty($_SESSION['id_usuario'])) {
        return (int)$_SESSION['id_usuario'];
    }
    $fb = (int)($fallback ?? 0);
    return $fb > 0 ? $fb : null;
}

/**
 * Insere um lançamento contábil em `movimentacoes`.
 */
function registrar_movimentacao_contabil($conn, array $dados) {
    garantir_schema_estoque_contabil($conn);

    $produtoId = (int)($dados['produto_id'] ?? 0);
    if ($produtoId <= 0) {
        throw new Exception('Produto inválido para movimentação contábil.');
    }

    $tipo = strtoupper(trim((string)($dados['tipo'] ?? '')));
    $quantidade = round((float)($dados['quantidade'] ?? 0), 3);
    $valorPraticado = round((float)($dados['valor_unitario_praticado'] ?? 0), 2);
    $custoPraticado = round((float)($dados['custo_unitario_praticado'] ?? 0), 2);
    $resultadoFinanceiro = round((float)($dados['resultado_financeiro'] ?? 0), 2);
    $descricao = trim((string)($dados['descricao'] ?? ''));
    $usuarioId = isset($dados['usuario_id']) && (int)$dados['usuario_id'] > 0 ? (int)$dados['usuario_id'] : null;
    $vendaId = isset($dados['venda_id']) && (int)$dados['venda_id'] > 0 ? (int)$dados['venda_id'] : null;
    $itemVendaId = isset($dados['item_venda_id']) && (int)$dados['item_venda_id'] > 0 ? (int)$dados['item_venda_id'] : null;
    $sessaoCaixaId = isset($dados['sessao_caixa_id']) && (int)$dados['sessao_caixa_id'] > 0 ? (int)$dados['sessao_caixa_id'] : null;
    $produtoDestinoId = isset($dados['produto_destino_id']) && (int)$dados['produto_destino_id'] > 0 ? (int)$dados['produto_destino_id'] : null;
    $quantidadeGerada = isset($dados['quantidade_gerada']) && $dados['quantidade_gerada'] !== null ? round((float)$dados['quantidade_gerada'], 3) : null;
    $origem = strtoupper(trim((string)($dados['origem'] ?? 'MANUAL')));
    $dataHora = !empty($dados['data_movimentacao']) ? (string)$dados['data_movimentacao'] : null;

    if ($dataHora !== null) {
        $sql = "INSERT INTO movimentacoes
            (produto_id, id_produto, tipo, quantidade, valor_unitario_praticado, custo_unitario_praticado,
             resultado_financeiro, descricao, usuario_id, id_usuario, venda_id, item_venda_id, sessao_caixa_id,
             produto_destino_id, quantidade_gerada, origem, data_movimentacao)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Erro ao preparar movimentação contábil: ' . $conn->error);
        }
        $stmt->bind_param(
            'iisddddsiiiiiidss',
            $produtoId, $produtoId, $tipo, $quantidade,
            $valorPraticado, $custoPraticado, $resultadoFinanceiro,
            $descricao, $usuarioId, $usuarioId, $vendaId, $itemVendaId,
            $sessaoCaixaId, $produtoDestinoId, $quantidadeGerada, $origem, $dataHora
        );
    } else {
        $sql = "INSERT INTO movimentacoes
            (produto_id, id_produto, tipo, quantidade, valor_unitario_praticado, custo_unitario_praticado,
             resultado_financeiro, descricao, usuario_id, id_usuario, venda_id, item_venda_id, sessao_caixa_id,
             produto_destino_id, quantidade_gerada, origem)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Erro ao preparar movimentação contábil: ' . $conn->error);
        }
        $stmt->bind_param(
            'iisddddsiiiiiids',
            $produtoId, $produtoId, $tipo, $quantidade,
            $valorPraticado, $custoPraticado, $resultadoFinanceiro,
            $descricao, $usuarioId, $usuarioId, $vendaId, $itemVendaId,
            $sessaoCaixaId, $produtoDestinoId, $quantidadeGerada, $origem
        );
    }

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new Exception('Erro ao gravar movimentação contábil: ' . $err);
    }
    $insertId = (int)$stmt->insert_id;
    $stmt->close();
    return $insertId;
}

/**
 * Registra automaticamente o lucro/prejuízo de um item vendido no PDV.
 */
function registrar_venda_contabil($conn, $vendaId, $itemVendaId, $produtoId, $quantidade, $precoUnitario, $custoUnitario, $usuarioId, $sessaoCaixaId = null, $numeroVendaDia = null, $dataHora = null) {
    $qtd = (float)$quantidade;
    $preco = (float)$precoUnitario;
    $custo = max(0.0, (float)$custoUnitario);
    $lucro = round(($qtd * $preco) - ($qtd * $custo), 2);

    $rotuloVenda = $numeroVendaDia ? "Venda PDV #{$numeroVendaDia} (Pedido #{$vendaId})" : "Venda PDV #{$vendaId}";
    registrar_movimentacao_contabil($conn, [
        'produto_id' => $produtoId,
        'tipo' => 'VENDA',
        'quantidade' => $qtd,
        'valor_unitario_praticado' => $preco,
        'custo_unitario_praticado' => $custo,
        'resultado_financeiro' => $lucro,
        'descricao' => $rotuloVenda,
        'usuario_id' => $usuarioId,
        'venda_id' => $vendaId,
        'item_venda_id' => $itemVendaId,
        'sessao_caixa_id' => $sessaoCaixaId,
        'origem' => 'PDV',
        'data_movimentacao' => $dataHora
    ]);

    return $lucro;
}

/**
 * Remove lançamentos contábeis ligados a uma venda (usado em edição/exclusão).
 */
function remover_venda_contabil($conn, $vendaId) {
    garantir_schema_estoque_contabil($conn);
    $stmt = $conn->prepare("DELETE FROM movimentacoes WHERE venda_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $vendaId);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Recalcula e resincroniza os lançamentos contábeis de uma venda específica após edição.
 */
function sincronizar_venda_contabil($conn, $vendaId) {
    garantir_schema_estoque_contabil($conn);
    remover_venda_contabil($conn, $vendaId);

    $vendaPk = ec_primeira_coluna($conn, 'vendas', ['id_venda', 'id']);
    $usuarioId = ec_primeira_coluna($conn, 'vendas', ['id_usuario', 'usuario_id']);
    $dataVenda = ec_primeira_coluna($conn, 'vendas', ['data_hora', 'data_venda']);
    $itemPk = ec_primeira_coluna($conn, 'itens_venda', ['id_item', 'id']);
    $produtoPk = ec_primeira_coluna($conn, 'produtos', ['id_produto', 'id']);
    $sql = "SELECT v.{$vendaPk} AS id_venda, v.{$usuarioId} AS id_usuario,
                   v.sessao_caixa_id, v.numero_venda_dia, v.{$dataVenda} AS data_hora,
                   iv.{$itemPk} AS id_item, iv.id_produto, iv.quantidade, iv.preco_unitario,
                   COALESCE(p.preco_custo, 0) AS preco_custo
            FROM vendas v
            INNER JOIN itens_venda iv ON iv.id_venda = v.{$vendaPk}
            LEFT JOIN produtos p ON p.{$produtoPk} = iv.id_produto
            WHERE v.{$vendaPk} = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $vendaId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        registrar_venda_contabil(
            $conn,
            (int)$row['id_venda'],
            (int)$row['id_item'],
            (int)$row['id_produto'],
            (float)$row['quantidade'],
            (float)$row['preco_unitario'],
            (float)$row['preco_custo'],
            (int)$row['id_usuario'],
            !empty($row['sessao_caixa_id']) ? (int)$row['sessao_caixa_id'] : null,
            !empty($row['numero_venda_dia']) ? (int)$row['numero_venda_dia'] : null,
            $row['data_hora']
        );
    }
    $stmt->close();
}

/**
 * Sincronização idempotente de histórico legado:
 * - Importa itens de vendas antigas ainda sem registro em `movimentacoes`
 * - Recalcula prejuízo de perdas antigas cujo `resultado_financeiro` ainda estava zerado
 */
function sincronizar_historico_contabil($conn) {
    garantir_schema_estoque_contabil($conn);
    $produtoPk = ec_primeira_coluna($conn, 'produtos', ['id_produto', 'id']);
    $vendaPk = ec_primeira_coluna($conn, 'vendas', ['id_venda', 'id']);
    $usuarioId = ec_primeira_coluna($conn, 'vendas', ['id_usuario', 'usuario_id']);
    $dataVenda = ec_primeira_coluna($conn, 'vendas', ['data_hora', 'data_venda']);
    $itemPk = ec_primeira_coluna($conn, 'itens_venda', ['id_item', 'id']);
    $statusVenda = ec_primeira_coluna($conn, 'vendas', ['status']);

    // 1. Normalizar tipo para MAIÚSCULO nas movimentações existentes
    @$conn->query("UPDATE movimentacoes SET tipo = UPPER(TRIM(tipo)) WHERE tipo <> UPPER(TRIM(tipo))");

    // 2. Atualizar perdas antigas sem prejuízo contabilizado
    @$conn->query("
        UPDATE movimentacoes m
        INNER JOIN produtos p ON p.{$produtoPk} = COALESCE(m.id_produto, m.produto_id)
        SET
            m.custo_unitario_praticado = COALESCE(p.preco_custo, 0),
            m.valor_unitario_praticado = 0.00,
            m.resultado_financeiro = ROUND(-(m.quantidade * COALESCE(p.preco_custo, 0)), 2)
        WHERE UPPER(m.tipo) = 'PERDA'
          AND m.resultado_financeiro = 0
          AND COALESCE(p.preco_custo, 0) > 0
    ");

    // 3. Importar vendas antigas de itens_venda que ainda não estão em movimentacoes
    $filtroStatus = $statusVenda
        ? "AND COALESCE(v.{$statusVenda}, 'ativo') NOT IN ('cancelado', 'cancelada')"
        : '';
    $sqlPendentes = "
        SELECT v.{$vendaPk} AS id_venda, v.{$usuarioId} AS id_usuario,
               v.sessao_caixa_id, v.numero_venda_dia, v.{$dataVenda} AS data_hora,
               iv.{$itemPk} AS id_item, iv.id_produto, iv.quantidade, iv.preco_unitario,
               COALESCE(p.preco_custo, 0) AS preco_custo
        FROM itens_venda iv
        INNER JOIN vendas v ON v.{$vendaPk} = iv.id_venda
        LEFT JOIN produtos p ON p.{$produtoPk} = iv.id_produto
        LEFT JOIN movimentacoes m
               ON m.item_venda_id = iv.{$itemPk}
               OR (m.item_venda_id IS NULL AND m.venda_id = v.{$vendaPk} AND COALESCE(m.id_produto, m.produto_id) = iv.id_produto)
        WHERE m.id IS NULL
          {$filtroStatus}
        ORDER BY v.{$vendaPk} ASC, iv.{$itemPk} ASC
        LIMIT 1000
    ";

    $res = $conn->query($sqlPendentes);
    $importados = 0;
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $qtd = (float)$row['quantidade'];
            $preco = (float)$row['preco_unitario'];
            $custo = max(0.0, (float)$row['preco_custo']);
            $lucro = round(($qtd * $preco) - ($qtd * $custo), 2);
            $numDia = !empty($row['numero_venda_dia']) ? (int)$row['numero_venda_dia'] : null;
            $rotulo = $numDia ? "Venda PDV #{$numDia} (Pedido #{$row['id_venda']})" : "Venda PDV #{$row['id_venda']}";

            try {
                registrar_movimentacao_contabil($conn, [
                    'produto_id' => (int)$row['id_produto'],
                    'tipo' => 'VENDA',
                    'quantidade' => $qtd,
                    'valor_unitario_praticado' => $preco,
                    'custo_unitario_praticado' => $custo,
                    'resultado_financeiro' => $lucro,
                    'descricao' => $rotulo,
                    'usuario_id' => !empty($row['id_usuario']) ? (int)$row['id_usuario'] : null,
                    'venda_id' => (int)$row['id_venda'],
                    'item_venda_id' => (int)$row['id_item'],
                    'sessao_caixa_id' => !empty($row['sessao_caixa_id']) ? (int)$row['sessao_caixa_id'] : null,
                    'origem' => 'PDV',
                    'data_movimentacao' => $row['data_hora']
                ]);
                $importados++;
            } catch (Exception $e) {
                // Ignora item inconsistente (ex: produto excluído) sem travar a sincronização
            }
        }
    }

    return $importados;
}
