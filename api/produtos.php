<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

error_reporting(0);

require_once __DIR__ . '/../db/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function json_error($msg) { echo json_encode(['sucesso'=>false,'mensagem'=>$msg]); exit; }

function map_produto_row($r) {
    $imagem_url = null;
    if (isset($r['imagem']) && $r['imagem']) {
        $imagem_url = '/Cantina/uploads/produtos/' . $r['imagem'];
    }
    return [
        'id' => (int)$r['id_produto'],
        'nome' => $r['nome'],
        'descricao' => $r['descricao'] ?? '',
        'categoria' => $r['tipo_categoria'],
        'preco' => (float)$r['preco_venda'],
        'preco_custo' => isset($r['preco_custo']) ? (float)$r['preco_custo'] : null,
        'estoque' => (int)$r['estoque_atual'],
        'status' => $r['status'],
        'imagem' => $r['imagem'] ?? null,
        'imagem_url' => $imagem_url,
        'criado_em' => $r['data_criacao'] ?? null,
        'atualizado_em' => $r['data_atualizacao'] ?? null,
    ];
}

if ($method === 'GET' && $action === 'listar') {
    $sql = "SELECT id_produto, nome, descricao, tipo_categoria, preco_venda, preco_custo, estoque_atual, status, imagem, data_criacao, data_atualizacao FROM produtos ORDER BY data_criacao DESC";
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
    $stmt = $conn->prepare('SELECT id_produto, nome, descricao, tipo_categoria, preco_venda, preco_custo, estoque_atual, status, imagem, data_criacao, data_atualizacao FROM produtos WHERE id_produto = ? LIMIT 1');
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
    $preco = isset($data['preco']) ? (float)$data['preco'] : 0;
    $preco_custo = isset($data['preco_custo']) ? (float)$data['preco_custo'] : $preco;
    $estoque = isset($data['estoque']) ? (int)$data['estoque'] : 0;
    $status = $data['status'] ?? 'ativo';
    if ($nome === '' || $preco <= 0) json_error('Nome e preço são obrigatórios');
    $stmt = $conn->prepare('INSERT INTO produtos (nome, descricao, tipo_categoria, preco_venda, preco_custo, estoque_atual, status) VALUES (?,?,?,?,?,?,?)');
    if (!$stmt) json_error('Erro na preparação: ' . $conn->error);
    $stmt->bind_param('sssddis', $nome, $descricao, $categoria, $preco, $preco_custo, $estoque, $status);
    if ($stmt->execute()) {
        echo json_encode(['sucesso'=>true,'mensagem'=>'Produto criado com sucesso','id'=>$stmt->insert_id]);
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
    $preco = isset($data['preco']) ? (float)$data['preco'] : 0;
    $preco_custo = isset($data['preco_custo']) ? (float)$data['preco_custo'] : $preco;
    $estoque = isset($data['estoque']) ? (int)$data['estoque'] : 0;
    $status = $data['status'] ?? 'ativo';
    $stmt = $conn->prepare('UPDATE produtos SET nome = ?, descricao = ?, tipo_categoria = ?, preco_venda = ?, preco_custo = ?, estoque_atual = ?, status = ?, data_atualizacao = CURRENT_TIMESTAMP WHERE id_produto = ?');
    if (!$stmt) json_error('Erro na preparação: ' . $conn->error);
    $stmt->bind_param('sssddisi', $nome, $descricao, $categoria, $preco, $preco_custo, $estoque, $status, $id);
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
    $stmt = $conn->prepare('DELETE FROM produtos WHERE id_produto = ?');
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
?>