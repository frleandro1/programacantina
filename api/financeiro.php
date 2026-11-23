<?php
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/financeiro-calculos.php';
session_start();
if (empty($_SESSION['usuario_id']) || ($_SESSION['tipo'] ?? '') !== 'admin') {
    json_fail('Entre com um usuario administrador para acessar a apuracao.', 403);
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../db/config.php';

function fin_query($sql, $tipos = '', $valores = []) {
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($tipos !== '') {
        $stmt->bind_param($tipos, ...$valores);
    }
    $stmt->execute();
    return $stmt;
}

function fin_documento($json) {
    return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
}

function fin_json($valor) {
    return json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function fin_coluna($tabela, $candidatas) {
    static $cache = [];
    $chave = $tabela . ':' . implode(',', $candidatas);
    if (!array_key_exists($chave, $cache)) {
        global $conn;
        $resultado = $conn->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $conn->real_escape_string($tabela) . "'"
        );
        $existentes = [];
        while ($row = $resultado->fetch_assoc()) {
            $existentes[$row['COLUMN_NAME']] = true;
        }
        $cache[$chave] = null;
        foreach ($candidatas as $candidata) {
            if (isset($existentes[$candidata])) {
                $cache[$chave] = $candidata;
                break;
            }
        }
    }
    return $cache[$chave];
}

function fin_evento($id, $bloquear = false) {
    $row = fin_query('SELECT * FROM financeiro_eventos WHERE id = ?' . ($bloquear ? ' FOR UPDATE' : ''), 'i', [$id])
        ->get_result()->fetch_assoc();
    if (!$row) {
        throw new InvalidArgumentException('Evento nao encontrado.');
    }
    $row['id'] = (int)$row['id'];
    $row['versao'] = (int)$row['versao'];
    $row['documento'] = fin_documento($row['documento']);
    return $row;
}

function fin_editavel($evento, $versao) {
    if ($evento['status'] !== 'rascunho') {
        throw new InvalidArgumentException('Evento finalizado nao pode ser alterado.');
    }
    if ($evento['versao'] !== financeiro_inteiro($versao, 'versao', 1)) {
        throw new InvalidArgumentException('Este evento foi alterado em outra tela. Recarregue antes de editar.');
    }
}

function fin_vendas($id) {
    $produtoId = fin_coluna('produtos', ['id_produto', 'id']);
    $vendaId = fin_coluna('vendas', ['id_venda', 'id']);
    $itemProdutoId = fin_coluna('itens_venda', ['id_produto', 'produto_id']);
    $sessaoId = fin_coluna('vendas', ['sessao_caixa_id']);
    $status = fin_coluna('vendas', ['status']);
    if (!$produtoId || !$vendaId || !$itemProdutoId || !$sessaoId) {
        return [];
    }
    $filtroStatus = $status ? "AND COALESCE(v.{$status}, 'ativo') NOT IN ('cancelado', 'cancelada')" : '';
    $result = fin_query(
        "SELECT i.{$itemProdutoId} AS id_produto, MAX(p.nome) AS nome, SUM(i.quantidade) AS quantidade,
                SUM(i.subtotal) AS receita
         FROM financeiro_evento_caixas ec
         JOIN vendas v ON v.{$sessaoId} = ec.sessao_id
         JOIN itens_venda i ON i.id_venda = v.{$vendaId}
         JOIN produtos p ON p.{$produtoId} = i.{$itemProdutoId}
         WHERE ec.evento_id = ? {$filtroStatus}
         GROUP BY i.{$itemProdutoId}", 'i', [$id]
    )->get_result();
    $vendas = [];
    while ($row = $result->fetch_assoc()) {
        $vendas[(int)$row['id_produto']] = [
            'nome' => $row['nome'], 'quantidade' => (int)$row['quantidade'],
            'receita_centavos' => (int)round((float)$row['receita'] * 100)
        ];
    }
    return $vendas;
}

function fin_detalhes($id) {
    $evento = fin_evento($id);
    $doc = $evento['documento'];
    $caixas = fin_query(
        'SELECT c.id_sessao, c.login_usuario, c.status, c.abertura_caixa
         FROM caixa_sessoes c JOIN financeiro_evento_caixas ec ON ec.sessao_id = c.id_sessao
         WHERE ec.evento_id = ? ORDER BY c.id_sessao', 'i', [$id]
    )->get_result()->fetch_all(MYSQLI_ASSOC);
    if ($evento['status'] === 'rascunho') {
        $vendas = fin_vendas($id);
        $presentes = array_column($doc['produtos'], 'produto_id');
        foreach ($vendas as $produtoId => $venda) {
            if (!in_array($produtoId, $presentes, true)) {
                $doc['produtos'][] = [
                    'produto_id' => $produtoId, 'nome' => $venda['nome'], 'lotes' => [],
                    'perdida' => 0, 'doada' => 0, 'equipe' => 0, 'observacoes' => ''
                ];
            }
        }
        $doc['apuracao'] = financeiro_apurar($doc['produtos'], $doc['despesas'], $vendas);
        $doc['produtos'] = $doc['apuracao']['produtos'];
    }
    $evento['documento'] = $doc;
    $evento['caixas'] = $caixas;
    $evento['transferencias'] = fin_query(
        'SELECT produto_id, lote_indice, destino_evento FROM financeiro_transferencias WHERE origem_evento = ?',
        'i', [$id]
    )->get_result()->fetch_all(MYSQLI_ASSOC);
    return $evento;
}

function fin_importados($produtos) {
    $origens = [];
    foreach ($produtos as $produto) {
        foreach ($produto['lotes'] as $lote) {
            if (!empty($lote['origem'])) {
                $chave = $lote['origem'];
                if (!is_string($chave) || isset($origens[$chave])) {
                    throw new InvalidArgumentException('Origem de lote invalida ou repetida.');
                }
                $origens[$chave] = [$produto['produto_id'], $lote['quantidade'], $lote['custo_centavos']];
            }
        }
    }
    ksort($origens);
    return $origens;
}

$transacao = false;
try {
    $schema = file_get_contents(__DIR__ . '/../db/financeiro.sql');
    if ($schema === false) {
        throw new RuntimeException('Arquivo de estrutura financeira indisponivel.');
    }
    foreach (explode(';', $schema) as $sql) {
        if (trim($sql) !== '') {
            $conn->query($sql);
        }
    }
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET' && $action === 'inicial') {
        $eventos = fin_query('SELECT id, nome, data_evento, status, versao FROM financeiro_eventos ORDER BY data_evento DESC, id DESC')
            ->get_result()->fetch_all(MYSQLI_ASSOC);
        $caixas = fin_query(
            'SELECT c.id_sessao, c.login_usuario, c.abertura_caixa, c.status, ec.evento_id
             FROM caixa_sessoes c LEFT JOIN financeiro_evento_caixas ec ON ec.sessao_id = c.id_sessao
             ORDER BY c.id_sessao DESC'
        )->get_result()->fetch_all(MYSQLI_ASSOC);
        $produtoId = fin_coluna('produtos', ['id_produto', 'id']);
        $precoVenda = fin_coluna('produtos', ['preco_venda', 'preco']);
        if (!$produtoId || !$precoVenda) {
            throw new RuntimeException('Estrutura de produtos do banco nao reconhecida.');
        }
        $colunaCusto = fin_coluna('produtos', ['preco_custo']);
        $custoSql = $colunaCusto ? $colunaCusto : 'NULL';
        $produtos = fin_query(
            "SELECT {$produtoId} AS id_produto, nome, {$precoVenda} AS preco_venda, {$custoSql} AS preco_custo FROM produtos ORDER BY nome"
        )
            ->get_result()->fetch_all(MYSQLI_ASSOC);
        $receitas = fin_query('SELECT id, nome, versao, documento FROM financeiro_receitas ORDER BY nome')
            ->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($receitas as &$receita) {
            $receita['documento'] = fin_documento($receita['documento']);
        }
        unset($receita);
        json_success('Dados carregados.', compact('eventos', 'caixas', 'produtos', 'receitas'));
    }
    if ($method === 'GET' && $action === 'dashboard') {
        $produtoId = fin_coluna('produtos', ['id_produto', 'id']);
        $custosResult = $conn->query(
            "SELECT {$produtoId} AS produto_id, COALESCE(preco_custo, 0) AS preco_custo FROM produtos"
        );
        $custos = [];
        while ($custo = $custosResult->fetch_assoc()) {
            $custos[(int)$custo['produto_id']] = (int)round((float)$custo['preco_custo'] * 100);
        }

        $eventos = [];
        $rows = fin_query('SELECT id FROM financeiro_eventos ORDER BY data_evento DESC, id DESC')->get_result();
        while ($row = $rows->fetch_assoc()) {
            $detalhe = fin_detalhes((int)$row['id']);
            $apuracao = $detalhe['documento']['apuracao'] ?? [
                'produtos' => [], 'receita_centavos' => 0, 'despesas_centavos' => 0,
                'lucro_centavos' => 0, 'margem_percentual' => null, 'pendencias' => []
            ];
            $totalVendida = 0;
            $totalPerdida = 0;
            $totalDoada = 0;
            $totalEquipe = 0;
            $custoConsumido = 0;
            $produtos = [];
            foreach ($apuracao['produtos'] as $produto) {
                $produtoIdAtual = (int)$produto['produto_id'];
                $vendida = (int)($produto['vendida'] ?? 0);
                $perdida = (int)($produto['perdida'] ?? 0);
                $doada = (int)($produto['doada'] ?? 0);
                $equipe = (int)($produto['equipe'] ?? 0);
                $disponivel = (int)($produto['disponivel'] ?? array_sum(array_column($produto['lotes'] ?? [], 'quantidade')));
                $custoProduto = (int)($produto['custo_total_centavos'] ?? 0) - (int)($produto['retorno_centavos'] ?? 0);
                if ($custoProduto === 0 && ($vendida + $perdida + $doada + $equipe) > 0) {
                    $custoProduto = ($vendida + $perdida + $doada + $equipe) * ($custos[$produtoIdAtual] ?? 0);
                }
                $totalVendida += $vendida;
                $totalPerdida += $perdida;
                $totalDoada += $doada;
                $totalEquipe += $equipe;
                $custoConsumido += $custoProduto;
                $produtos[] = [
                    'id' => $produtoIdAtual,
                    'nome' => $produto['nome'] ?? "Produto {$produtoIdAtual}",
                    'vendida' => $vendida,
                    'perdida' => $perdida,
                    'doada' => $doada,
                    'equipe' => $equipe,
                    'disponivel' => $disponivel,
                    'total_saida' => $vendida + $perdida + $doada + $equipe,
                    'receita_centavos' => (int)($produto['receita_centavos'] ?? 0),
                    'custo_centavos' => $custoProduto
                ];
            }
            $receita = (int)($apuracao['receita_centavos'] ?? 0);
            $despesas = (int)($apuracao['despesas_centavos'] ?? 0);
            $lucro = $receita - $custoConsumido - $despesas;
            $totalSaida = $totalVendida + $totalPerdida + $totalDoada + $totalEquipe;
            $eventos[] = [
                'id' => $detalhe['id'],
                'nome' => $detalhe['nome'],
                'data_evento' => $detalhe['data_evento'],
                'status' => $detalhe['status'],
                'caixas' => count($detalhe['caixas']),
                'caixas_abertos' => count(array_filter($detalhe['caixas'], fn($caixa) => $caixa['status'] !== 'fechado')),
                'vendida' => $totalVendida,
                'perdida' => $totalPerdida,
                'doada' => $totalDoada,
                'equipe' => $totalEquipe,
                'total_saida' => $totalSaida,
                'receita_centavos' => $receita,
                'custo_centavos' => $custoConsumido,
                'despesas_centavos' => $despesas,
                'lucro_centavos' => $lucro,
                'margem_percentual' => $receita > 0 ? round($lucro / $receita * 100, 2) : null,
                'pendencias' => count($apuracao['pendencias'] ?? []),
                'produtos' => $produtos
            ];
        }
        $total = [
            'eventos' => count($eventos),
            'vendida' => array_sum(array_column($eventos, 'vendida')),
            'perdida' => array_sum(array_column($eventos, 'perdida')),
            'doada' => array_sum(array_column($eventos, 'doada')),
            'equipe' => array_sum(array_column($eventos, 'equipe')),
            'total_saida' => array_sum(array_column($eventos, 'total_saida')),
            'receita_centavos' => array_sum(array_column($eventos, 'receita_centavos')),
            'custo_centavos' => array_sum(array_column($eventos, 'custo_centavos')),
            'despesas_centavos' => array_sum(array_column($eventos, 'despesas_centavos')),
            'lucro_centavos' => array_sum(array_column($eventos, 'lucro_centavos'))
        ];
        json_success('Dashboard financeiro carregado.', ['total' => $total, 'eventos' => $eventos]);
    }
    if ($method === 'GET' && $action === 'obter') {
        json_success('Evento carregado.', fin_detalhes(financeiro_inteiro($_GET['id'] ?? null, 'evento', 1)));
    }
    require_method('POST');
    $dados = read_json_input();
    $conn->begin_transaction();
    $transacao = true;

    if ($action === 'salvar_receita') {
        $receita = financeiro_receita($dados);
        $id = financeiro_inteiro($dados['id'] ?? 0, 'receita');
        $json = fin_json($receita);
        if ($id > 0) {
            $versao = financeiro_inteiro($dados['versao'] ?? null, 'versao', 1);
            $stmt = fin_query(
                'UPDATE financeiro_receitas SET nome = ?, documento = ?, versao = versao + 1 WHERE id = ? AND versao = ?',
                'ssii', [$receita['nome'], $json, $id, $versao]
            );
            if ($stmt->affected_rows !== 1) {
                throw new InvalidArgumentException('Receita alterada em outra tela. Recarregue.');
            }
        } else {
            fin_query('INSERT INTO financeiro_receitas (nome, documento) VALUES (?, ?)', 'ss', [$receita['nome'], $json]);
            $id = $conn->insert_id;
        }
    } elseif ($action === 'salvar' || $action === 'finalizar') {
        $id = financeiro_inteiro($dados['id'] ?? 0, 'evento');
        $anterior = null;
        if ($id > 0) {
            $anterior = fin_evento($id, true);
            fin_editavel($anterior, $dados['versao'] ?? null);
        }
        $nome = financeiro_texto($dados['nome'] ?? null, 'nome do evento', 150, true);
        $data = financeiro_texto($dados['data_evento'] ?? null, 'data', 10, true);
        $dt = DateTime::createFromFormat('!Y-m-d', $data);
        if (!$dt || $dt->format('Y-m-d') !== $data) {
            throw new InvalidArgumentException('Data do evento invalida.');
        }
        $produtos = financeiro_lista($dados['produtos'] ?? null, 'produtos');
        $despesas = financeiro_lista($dados['despesas'] ?? null, 'despesas');
        foreach ($produtos as &$produto) {
            $produto['produto_id'] = financeiro_inteiro($produto['produto_id'] ?? null, 'produto', 1);
            $produtoId = fin_coluna('produtos', ['id_produto', 'id']);
            if (!$produtoId) {
                throw new RuntimeException('Estrutura de produtos do banco nao reconhecida.');
            }
            $cadastro = fin_query("SELECT nome FROM produtos WHERE {$produtoId} = ?", 'i', [$produto['produto_id']])
                ->get_result()->fetch_assoc();
            if (!$cadastro) {
                throw new InvalidArgumentException('Produto nao encontrado.');
            }
            $produto['nome'] = $cadastro['nome'];
            $produto['observacoes'] = financeiro_texto($produto['observacoes'] ?? '', 'observacoes do produto', 5000);
            $produto['lotes'] = financeiro_lista($produto['lotes'] ?? null, 'lotes');
            foreach ($produto['lotes'] as &$lote) {
                $lote = [
                    'quantidade' => financeiro_inteiro($lote['quantidade'] ?? null, 'quantidade', 1, 1000000),
                    'custo_centavos' => financeiro_inteiro($lote['custo_centavos'] ?? null, 'custo unitario'),
                    'reaproveitavel' => financeiro_inteiro($lote['reaproveitavel'] ?? null, 'reaproveitavel', 0, 1000000),
                    'origem' => $lote['origem'] ?? ''
                ];
            }
            unset($lote);
        }
        unset($produto);
        if (fin_importados($produtos) !== fin_importados($anterior['documento']['produtos'] ?? [])) {
            throw new InvalidArgumentException('Nao altere quantidade, custo, produto ou origem de lotes transferidos. Use a importacao de sobras.');
        }
        $doc = [
            'observacoes' => financeiro_texto($dados['observacoes'] ?? '', 'observacoes', 5000),
            'produtos' => $produtos, 'despesas' => $despesas
        ];
        if (!$id) {
            fin_query('INSERT INTO financeiro_eventos (nome, data_evento, documento) VALUES (?, ?, ?)', 'sss', [$nome, $data, fin_json($doc)]);
            $id = $conn->insert_id;
        }
        $sessoes = financeiro_lista($dados['sessoes'] ?? null, 'caixas');
        $sessoes = array_map(function ($valor) { return financeiro_inteiro($valor, 'caixa', 1); }, $sessoes);
        if (count($sessoes) !== count(array_unique($sessoes))) {
            throw new InvalidArgumentException('Caixa repetido.');
        }
        fin_query('DELETE FROM financeiro_evento_caixas WHERE evento_id = ?', 'i', [$id]);
        $abertos = 0;
        foreach ($sessoes as $sessao) {
            $caixa = fin_query('SELECT status FROM caixa_sessoes WHERE id_sessao = ? FOR UPDATE', 'i', [$sessao])
                ->get_result()->fetch_assoc();
            if (!$caixa) {
                throw new InvalidArgumentException('Caixa nao encontrado.');
            }
            if ($caixa['status'] !== 'fechado') {
                $abertos++;
            }
            $vinculo = fin_query('SELECT evento_id FROM financeiro_evento_caixas WHERE sessao_id = ?', 'i', [$sessao])
                ->get_result()->fetch_assoc();
            if ($vinculo) {
                throw new InvalidArgumentException('Um caixa selecionado ja pertence a outro evento.');
            }
            fin_query('INSERT INTO financeiro_evento_caixas (sessao_id, evento_id) VALUES (?, ?)', 'ii', [$sessao, $id]);
        }
        $apuracao = financeiro_apurar($produtos, $despesas, fin_vendas($id));
        if ($action === 'finalizar') {
            if (!$sessoes || $abertos || !$produtos || $apuracao['pendencias']) {
                throw new InvalidArgumentException('Para finalizar: vincule caixas fechados, cadastre os custos de todos os produtos e confira os destinos. ' . implode(' ', $apuracao['pendencias']));
            }
            $doc['apuracao'] = $apuracao;
            $doc['produtos'] = $apuracao['produtos'];
        }
        $status = $action === 'finalizar' ? 'finalizado' : 'rascunho';
        fin_query(
            'UPDATE financeiro_eventos SET nome = ?, data_evento = ?, documento = ?, status = ?, versao = versao + 1 WHERE id = ?',
            'ssssi', [$nome, $data, fin_json($doc), $status, $id]
        );
    } elseif ($action === 'importar') {
        $id = financeiro_inteiro($dados['id'] ?? null, 'destino', 1);
        $origemId = financeiro_inteiro($dados['origem_id'] ?? null, 'origem', 1);
        if ($id === $origemId) {
            throw new InvalidArgumentException('Escolha outro evento como origem.');
        }
        $ids = [$id, $origemId];
        sort($ids);
        foreach ($ids as $lockId) {
            fin_evento($lockId, true);
        }
        $destino = fin_evento($id);
        $origem = fin_evento($origemId);
        fin_editavel($destino, $dados['versao'] ?? null);
        if ($origem['status'] !== 'finalizado' || $origem['data_evento'] > $destino['data_evento']) {
            throw new InvalidArgumentException('A origem deve ser um evento finalizado, de data anterior ou igual.');
        }
        $doc = $destino['documento'];
        $importados = 0;
        foreach ($origem['documento']['produtos'] as $produto) {
            foreach ($produto['lotes'] as $indice => $lote) {
                if ($lote['reaproveitavel'] <= 0) {
                    continue;
                }
                $usado = fin_query(
                    'SELECT destino_evento FROM financeiro_transferencias WHERE origem_evento = ? AND produto_id = ? AND lote_indice = ?',
                    'iii', [$origemId, $produto['produto_id'], $indice]
                )->get_result()->fetch_assoc();
                if ($usado) {
                    continue;
                }
                $posicao = array_search($produto['produto_id'], array_column($doc['produtos'], 'produto_id'), true);
                if ($posicao === false) {
                    $doc['produtos'][] = [
                        'produto_id' => $produto['produto_id'], 'nome' => $produto['nome'], 'lotes' => [],
                        'perdida' => 0, 'doada' => 0, 'equipe' => 0, 'observacoes' => ''
                    ];
                    $posicao = count($doc['produtos']) - 1;
                }
                $doc['produtos'][$posicao]['lotes'][] = [
                    'quantidade' => $lote['reaproveitavel'],
                    'custo_centavos' => $lote['custo_centavos'],
                    'reaproveitavel' => $lote['reaproveitavel'],
                    'origem' => "{$origemId}:{$produto['produto_id']}:{$indice}"
                ];
                fin_query(
                    'INSERT INTO financeiro_transferencias (origem_evento, produto_id, lote_indice, destino_evento) VALUES (?, ?, ?, ?)',
                    'iiii', [$origemId, $produto['produto_id'], $indice, $id]
                );
                $importados++;
            }
        }
        if (!$importados) {
            throw new InvalidArgumentException('Nao ha sobras disponiveis nesta origem; elas podem ja ter sido transferidas.');
        }
        fin_query('UPDATE financeiro_eventos SET documento = ?, versao = versao + 1 WHERE id = ?', 'si', [fin_json($doc), $id]);
    } else {
        throw new InvalidArgumentException('Acao nao reconhecida.');
    }
    $conn->commit();
    $transacao = false;
    json_success('Dados salvos com sucesso.', ['id' => $id]);
} catch (InvalidArgumentException $e) {
    if ($transacao) {
        $conn->rollback();
    }
    json_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    if ($transacao) {
        $conn->rollback();
    }
    log_error($e->getMessage(), __FILE__);
    json_fail('Nao foi possivel processar a apuracao. Consulte o log da API e verifique o banco de dados.', 500);
}
