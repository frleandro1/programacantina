<?php
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/estoque-contabil.php';
require_once __DIR__ . '/produto-schema.php';

garantir_schema_estoque_contabil($conn);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function map_produto_row($r) {
    $imagem = produto_imagem_arquivo((int)$r['id_produto'], $r['imagem'] ?? '');
    $imagem_url = $imagem !== '' ? 'uploads/produtos/' . $imagem : null;
    $estoque = (float)($r['estoque_atual'] ?? 0);
    $estoqueMin = (float)($r['estoque_minimo'] ?? 5);
    $unidade = ($r['unidade'] ?? 'un') === 'kg' ? 'kg' : 'un';

    return [
        'id' => (int)$r['id_produto'],
        'nome' => $r['nome'],
        'descricao' => $r['descricao'] ?? '',
        'categoria' => $r['tipo_categoria'],
        'unidade' => $unidade,
        'preco' => (float)$r['preco_venda'],
        'preco_custo' => isset($r['preco_custo']) ? (float)$r['preco_custo'] : 0.0,
        'estoque' => $unidade === 'un' ? (int)round($estoque) : $estoque,
        'estoque_atual' => $estoque,
        'estoque_minimo' => $estoqueMin,
        'status' => $r['status'],
        'imagem' => $imagem !== '' ? $imagem : null,
        'imagem_url' => $imagem_url,
        'criado_em' => $r['data_criacao'] ?? null,
        'atualizado_em' => $r['data_atualizacao'] ?? null,
    ];
}

if ($method === 'GET' && $action === 'listar') {
    $id = produto_coluna($conn, ['id_produto', 'id']);
    $categoria = produto_coluna($conn, ['tipo_categoria', 'categoria']);
    $preco = produto_coluna($conn, ['preco_venda', 'preco']);
    $estoque = produto_coluna($conn, ['estoque_atual', 'estoque']);
    $estoqueMinimo = produto_coluna($conn, ['estoque_minimo']);
    $criado = produto_coluna($conn, ['data_criacao', 'criado_em']);
    $atualizado = produto_coluna($conn, ['data_atualizacao', 'atualizado_em']);
    $unidade = produto_coluna($conn, ['unidade']);
    $sql = "SELECT {$id} AS id_produto, nome, descricao, {$categoria} AS tipo_categoria,
                   " . ($unidade ? "COALESCE({$unidade}, 'un')" : "'un'") . " AS unidade,
                   {$preco} AS preco_venda, COALESCE(preco_custo, 0) AS preco_custo,
                   COALESCE({$estoque}, 0) AS estoque_atual,
                   " . ($estoqueMinimo ? "COALESCE({$estoqueMinimo}, 5)" : '5') . " AS estoque_minimo,
                   status, imagem,
                   " . ($criado ? $criado : 'NULL') . " AS data_criacao,
                   " . ($atualizado ? $atualizado : 'NULL') . " AS data_atualizacao
            FROM produtos
            ORDER BY " . ($criado ? $criado : $id) . " DESC";
    $res = $conn->query($sql);
    if (!$res) json_error('Erro ao listar produtos: ' . $conn->error);
    $out = [];
    while ($row = $res->fetch_assoc()) { $out[] = map_produto_row($row); }
    echo json_encode(['sucesso'=>true,'dados'=>$out]);
    exit;
}

if ($method === 'GET' && $action === 'obter') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) json_error('ID inválido');
    $idColuna = produto_coluna($conn, ['id_produto', 'id']);
    $categoria = produto_coluna($conn, ['tipo_categoria', 'categoria']);
    $preco = produto_coluna($conn, ['preco_venda', 'preco']);
    $estoque = produto_coluna($conn, ['estoque_atual', 'estoque']);
    $estoqueMinimo = produto_coluna($conn, ['estoque_minimo']);
    $criado = produto_coluna($conn, ['data_criacao', 'criado_em']);
    $atualizado = produto_coluna($conn, ['data_atualizacao', 'atualizado_em']);
    $unidade = produto_coluna($conn, ['unidade']);
    $stmt = $conn->prepare("SELECT {$idColuna} AS id_produto, nome, descricao, {$categoria} AS tipo_categoria,
                                   " . ($unidade ? "COALESCE({$unidade}, 'un')" : "'un'") . " AS unidade,
                                   {$preco} AS preco_venda, COALESCE(preco_custo, 0) AS preco_custo,
                                   COALESCE({$estoque}, 0) AS estoque_atual,
                                   " . ($estoqueMinimo ? "COALESCE({$estoqueMinimo}, 5)" : '5') . " AS estoque_minimo,
                                   status, imagem,
                                   " . ($criado ? $criado : 'NULL') . " AS data_criacao,
                                   " . ($atualizado ? $atualizado : 'NULL') . " AS data_atualizacao
                            FROM produtos WHERE {$idColuna} = ? LIMIT 1");
    if (!$stmt) json_error('Erro na preparação: ' . $conn->error);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        echo json_encode(['sucesso'=>true,'dados'=>map_produto_row($row)]);
    } else {
        json_error('Produto não encontrado');
    }
    exit;
}

if ($method === 'POST' && $action === 'criar') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $nome = trim($data['nome'] ?? '');
    $descricao = trim($data['descricao'] ?? '');
    $categoria = trim($data['categoria'] ?? '');
    if ($categoria === '') { $categoria = 'Outro'; }
    $unidade = ($data['unidade'] ?? 'un') === 'kg' ? 'kg' : 'un';
    $preco = isset($data['preco']) ? (float)$data['preco'] : 0;
    $preco_custo = isset($data['preco_custo']) && $data['preco_custo'] !== '' ? max(0.0, (float)$data['preco_custo']) : 0.0;
    $estoque = isset($data['estoque']) ? max(0.0, (float)$data['estoque']) : 0.0;
    $estoque_minimo = isset($data['estoque_minimo']) && $data['estoque_minimo'] !== '' ? max(0.0, (float)$data['estoque_minimo']) : 5.0;
    $status = $data['status'] ?? 'ativo';
    if ($nome === '' || $preco <= 0) json_error('Nome e preço são obrigatórios');

    $colsP = ec_mapa_colunas_produtos($conn);
    $catCol = $colsP['categoria'];
    $estCol = $colsP['estoque'];
    $colsProd = ec_colunas_tabela($conn, 'produtos');
    if (isset($colsProd['preco']) && isset($colsProd['preco_venda'])) {
        $stmt = $conn->prepare("INSERT INTO produtos (nome, descricao, {$catCol}, unidade, preco, preco_venda, preco_custo, {$estCol}, estoque_minimo, status) VALUES (?,?,?,?,?,?,?,?,?,?)");
        if (!$stmt) json_error('Erro na preparação: ' . $conn->error);
        $stmt->bind_param('ssssddddds', $nome, $descricao, $categoria, $unidade, $preco, $preco, $preco_custo, $estoque, $estoque_minimo, $status);
    } else {
        $precoCol = $colsP['preco'];
        $stmt = $conn->prepare("INSERT INTO produtos (nome, descricao, {$catCol}, unidade, {$precoCol}, preco_custo, {$estCol}, estoque_minimo, status) VALUES (?,?,?,?,?,?,?,?,?)");
        if (!$stmt) json_error('Erro na preparação: ' . $conn->error);
        $stmt->bind_param('ssssdddds', $nome, $descricao, $categoria, $unidade, $preco, $preco_custo, $estoque, $estoque_minimo, $status);
    }

    if ($stmt->execute()) {
        $novoId = (int)$stmt->insert_id;
        $stmt->close();
        if ($estoque > 0) {
            try {
                registrar_movimentacao_contabil($conn, [
                    'produto_id' => $novoId,
                    'tipo' => 'ENTRADA',
                    'quantidade' => $estoque,
                    'valor_unitario_praticado' => $preco_custo,
                    'custo_unitario_praticado' => $preco_custo,
                    'resultado_financeiro' => 0.00,
                    'descricao' => 'Estoque inicial no cadastro',
                    'usuario_id' => ec_obter_usuario_id(null),
                    'origem' => 'CADASTRO'
                ]);
            } catch (Exception $e) {
                // Não bloqueia criação do produto se registro auxiliar falhar
            }
        }
        echo json_encode(['sucesso'=>true,'mensagem'=>'Produto criado com sucesso','id'=>$novoId]);
    } else {
        json_error('Erro ao criar produto: ' . $stmt->error);
    }
    exit;
}

if (($method === 'PUT' || $method === 'POST') && $action === 'atualizar') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) json_error('ID inválido');
    $nome = trim($data['nome'] ?? '');
    $descricao = trim($data['descricao'] ?? '');
    $categoria = trim($data['categoria'] ?? '');
    if ($categoria === '') { $categoria = 'Outro'; }
    $unidade = ($data['unidade'] ?? 'un') === 'kg' ? 'kg' : 'un';
    $preco = isset($data['preco']) ? (float)$data['preco'] : 0;
    $estoque = isset($data['estoque']) ? max(0.0, (float)$data['estoque']) : 0.0;
    $status = $data['status'] ?? 'ativo';

    $colsP = ec_mapa_colunas_produtos($conn);
    $pkCol = $colsP['pk'];
    $catCol = $colsP['categoria'];
    $estCol = $colsP['estoque'];
    $updColSql = $colsP['atualizado'] ? ", {$colsP['atualizado']} = CURRENT_TIMESTAMP" : "";

    // Preservar custo e estoque mínimo atuais caso não venham na requisição
    $stmtCur = $conn->prepare("SELECT COALESCE(preco_custo, 0) AS preco_custo, COALESCE(estoque_minimo, 5) AS estoque_minimo FROM produtos WHERE {$pkCol} = ? LIMIT 1");
    $custoAtual = 0.0;
    $minAtual = 5.0;
    if ($stmtCur) {
        $stmtCur->bind_param('i', $id);
        $stmtCur->execute();
        if ($rCur = $stmtCur->get_result()->fetch_assoc()) {
            $custoAtual = (float)$rCur['preco_custo'];
            $minAtual = (float)$rCur['estoque_minimo'];
        }
        $stmtCur->close();
    }

    $preco_custo = (isset($data['preco_custo']) && $data['preco_custo'] !== '') ? max(0.0, (float)$data['preco_custo']) : $custoAtual;
    $estoque_minimo = (isset($data['estoque_minimo']) && $data['estoque_minimo'] !== '') ? max(0.0, (float)$data['estoque_minimo']) : $minAtual;

    $colsProd = ec_colunas_tabela($conn, 'produtos');
    if (isset($colsProd['preco']) && isset($colsProd['preco_venda'])) {
        $stmt = $conn->prepare("UPDATE produtos SET nome = ?, descricao = ?, {$catCol} = ?, unidade = ?, preco = ?, preco_venda = ?, preco_custo = ?, {$estCol} = ?, estoque_minimo = ?, status = ?{$updColSql} WHERE {$pkCol} = ?");
        if (!$stmt) json_error('Erro na preparação: ' . $conn->error);
        $stmt->bind_param('ssssdddddsi', $nome, $descricao, $categoria, $unidade, $preco, $preco, $preco_custo, $estoque, $estoque_minimo, $status, $id);
    } else {
        $precoCol = $colsP['preco'];
        $stmt = $conn->prepare("UPDATE produtos SET nome = ?, descricao = ?, {$catCol} = ?, unidade = ?, {$precoCol} = ?, preco_custo = ?, {$estCol} = ?, estoque_minimo = ?, status = ?{$updColSql} WHERE {$pkCol} = ?");
        if (!$stmt) json_error('Erro na preparação: ' . $conn->error);
        $stmt->bind_param('ssssddddsi', $nome, $descricao, $categoria, $unidade, $preco, $preco_custo, $estoque, $estoque_minimo, $status, $id);
    }

    if ($stmt->execute()) {
        echo json_encode(['sucesso'=>true,'mensagem'=>'Produto atualizado com sucesso']);
    } else {
        json_error('Erro ao atualizar produto: ' . $stmt->error);
    }
    exit;
}

if (($method === 'DELETE' || $method === 'POST') && $action === 'deletar') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) json_error('ID inválido');
    $colsP = ec_mapa_colunas_produtos($conn);
    $pkCol = $colsP['pk'];
    $stmt = $conn->prepare("DELETE FROM produtos WHERE {$pkCol} = ?");
    if (!$stmt) json_error('Erro na preparação: ' . $conn->error);
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        echo json_encode(['sucesso'=>true,'mensagem'=>'Produto deletado com sucesso']);
    } else {
        json_error('Erro ao deletar produto: ' . $stmt->error);
    }
    exit;
}

echo json_encode(['sucesso'=>false,'mensagem'=>'Ação não encontrada']);
