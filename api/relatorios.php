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

require_once __DIR__ . '/../db/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function error($m){ echo json_encode(['sucesso'=>false,'mensagem'=>$m]); exit; }
function ok($d){ echo json_encode(['sucesso'=>true,'dados'=>$d]); exit; }

function ensure_relatorios_schema($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS caixa_sessoes (
        id_sessao INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        login_usuario VARCHAR(100) NOT NULL,
        abertura_caixa DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fechamento_caixa DATETIME NULL,
        status ENUM('aberto','fechado') NOT NULL DEFAULT 'aberto',
        ip_origem VARCHAR(45) NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_usuario_status (id_usuario, status),
        INDEX idx_abertura (abertura_caixa)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $colSessao = $conn->query("SHOW COLUMNS FROM vendas LIKE 'sessao_caixa_id'");
    if (!$colSessao || $colSessao->num_rows === 0) {
        $conn->query("ALTER TABLE vendas ADD COLUMN sessao_caixa_id INT NULL AFTER id_usuario");
    }

    $colTurno = $conn->query("SHOW COLUMNS FROM vendas LIKE 'numero_venda_turno'");
    if (!$colTurno || $colTurno->num_rows === 0) {
        $conn->query("ALTER TABLE vendas ADD COLUMN numero_venda_turno INT NULL AFTER numero_venda_dia");
    }
}

ensure_relatorios_schema($conn);

if ($method==='GET' && $action==='usuarios_listar') {
    $sql = "SELECT id_usuario as id, COALESCE(nome, login) as nome, login FROM usuarios ORDER BY nome";
    $res = $conn->query($sql);
    if (!$res) error('Erro ao listar usuários: '.$conn->error);
    $out = [];
    while ($r = $res->fetch_assoc()) {
        $out[] = [
            'id' => (int)$r['id'],
            'nome' => $r['nome'],
            'login' => $r['login']
        ];
    }
    ok($out);
}

// RelatÃ³rio de vendas agregadas
if ($method==='GET' && $action==='vendas_resumo'){
    $inicio = $_GET['inicio'] ?? '';
    $fim    = $_GET['fim'] ?? '';
    $where = '';
    $inicioDt = '';
    $fimDt = '';
    if ($inicio && $fim){ $where = "WHERE v.data_hora BETWEEN ? AND ?"; }
    $sql = "SELECT DATE(v.data_hora) as dia, COUNT(*) as pedidos, SUM(v.valor_total) as total
            FROM vendas v $where GROUP BY dia ORDER BY dia DESC";
    if ($where){
        $inicioDt = $inicio . ' 00:00:00';
        $fimDt = $fim . ' 23:59:59';
        $stmt=$conn->prepare($sql);
        $stmt->bind_param('ss',$inicioDt,$fimDt);
        $stmt->execute();
        $res=$stmt->get_result();
    }
    else { $res = $conn->query($sql); }
    if (!$res) error('Erro ao gerar resumo: '.$conn->error);
    $out=[]; while($r=$res->fetch_assoc()){ $out[]=[ 'dia'=>$r['dia'], 'pedidos'=> (int)$r['pedidos'], 'total'=> (float)$r['total'] ]; }
    ok($out);
}

if ($method==='GET' && $action==='logins_caixa') {
    $inicio = $_GET['inicio'] ?? '';
    $fim = $_GET['fim'] ?? '';
    $usuarioId = (int)($_GET['usuario_id'] ?? 0);

    $where = [];
    $params = [];
    $types = '';

    if ($inicio && $fim) {
        $where[] = 'cs.abertura_caixa BETWEEN ? AND ?';
        $params[] = $inicio . ' 00:00:00';
        $params[] = $fim . ' 23:59:59';
        $types .= 'ss';
    }

    if ($usuarioId > 0) {
        $where[] = 'cs.id_usuario = ?';
        $params[] = $usuarioId;
        $types .= 'i';
    }

    $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sql = "SELECT
                cs.id_sessao,
                cs.id_usuario,
                cs.login_usuario,
                COALESCE(u.nome, cs.login_usuario) as nome_usuario,
                cs.abertura_caixa,
                cs.fechamento_caixa,
                cs.status,
                cs.ip_origem,
                COUNT(v.id_venda) as total_vendas,
                COALESCE(SUM(v.valor_total), 0) as valor_total
            FROM caixa_sessoes cs
            LEFT JOIN usuarios u ON u.id_usuario = cs.id_usuario
            LEFT JOIN vendas v ON v.sessao_caixa_id = cs.id_sessao
            $whereSql
            GROUP BY cs.id_sessao, cs.id_usuario, cs.login_usuario, nome_usuario, cs.abertura_caixa, cs.fechamento_caixa, cs.status, cs.ip_origem
            ORDER BY cs.abertura_caixa DESC";

    if (count($params)) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) error('Erro ao preparar relatório de caixa: '.$conn->error);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }

    if (!$res) error('Erro ao gerar relatório de caixa: '.$conn->error);

    $out = [];
    while ($r = $res->fetch_assoc()) {
        $out[] = [
            'sessao_id' => (int)$r['id_sessao'],
            'usuario_id' => (int)$r['id_usuario'],
            'login' => $r['login_usuario'],
            'nome' => $r['nome_usuario'],
            'abertura_caixa' => $r['abertura_caixa'],
            'fechamento_caixa' => $r['fechamento_caixa'],
            'status' => $r['status'],
            'ip_origem' => $r['ip_origem'],
            'total_vendas' => (int)$r['total_vendas'],
            'valor_total' => (float)$r['valor_total']
        ];
    }

    ok($out);
}

// RelatÃ³rio de itens vendidos (produÃ§Ã£o)
if ($method==='GET' && $action==='producao_resumo'){
    $inicio = $_GET['inicio'] ?? '';
    $fim    = $_GET['fim'] ?? '';
    $where = '';
    $inicioDt = '';
    $fimDt = '';
    if ($inicio && $fim){ $where = "WHERE v.data_hora BETWEEN ? AND ?"; }
    $sql = "SELECT p.id_produto as id, p.nome, COALESCE(SUM(i.quantidade),0) as quantidade, COALESCE(SUM(i.subtotal),0) as total
            FROM produtos p
            LEFT JOIN itens_venda i ON i.id_produto = p.id_produto
            LEFT JOIN vendas v ON v.id_venda = i.id_venda
            $where
            GROUP BY p.id_produto, p.nome
            ORDER BY quantidade DESC";
    if ($where){
        $inicioDt = $inicio . ' 00:00:00';
        $fimDt = $fim . ' 23:59:59';
        $stmt=$conn->prepare($sql);
        $stmt->bind_param('ss',$inicioDt,$fimDt);
        $stmt->execute();
        $res=$stmt->get_result();
    }
    else { $res = $conn->query($sql); }
    if (!$res) error('Erro ao gerar produÃ§Ã£o: '.$conn->error);
    $out=[]; while($r=$res->fetch_assoc()){ $out[]=[ 'id'=>(int)$r['id'], 'nome'=>$r['nome'], 'quantidade'=>(int)$r['quantidade'], 'total'=>(float)$r['total'] ]; }
    ok($out);
}

// Detalhamento de vendas
if ($method==='GET' && $action==='vendas_detalhes'){
    $inicio = $_GET['inicio'] ?? '';
    $fim    = $_GET['fim'] ?? '';
    $where = '';
    $inicioDt = '';
    $fimDt = '';
    if ($inicio && $fim){ $where = "WHERE v.data_hora BETWEEN ? AND ?"; }
    $sql = "SELECT v.id_venda as pedido, v.data_hora as data, u.nome as atendente, i.quantidade, i.preco_unitario, i.subtotal, p.nome as produto
            FROM vendas v
            LEFT JOIN usuarios u ON u.id_usuario = v.id_usuario
            LEFT JOIN itens_venda i ON i.id_venda = v.id_venda
            LEFT JOIN produtos p ON p.id_produto = i.id_produto
            $where
            ORDER BY v.data_hora DESC";
    if ($where){
        $inicioDt = $inicio . ' 00:00:00';
        $fimDt = $fim . ' 23:59:59';
        $stmt=$conn->prepare($sql);
        $stmt->bind_param('ss',$inicioDt,$fimDt);
        $stmt->execute();
        $res=$stmt->get_result();
    }
    else { $res = $conn->query($sql); }
    if (!$res) error('Erro ao listar detalhes: '.$conn->error);
    $out=[]; while($r=$res->fetch_assoc()){ $out[]=[
        'pedido'=>(int)$r['pedido'], 'data'=>$r['data'], 'atendente'=>$r['atendente'],
        'produto'=>$r['produto'], 'quantidade'=>(int)$r['quantidade'], 'preco_unitario'=>(float)$r['preco_unitario'], 'subtotal'=>(float)$r['subtotal']
    ]; }
    ok($out);
}

echo json_encode(['sucesso'=>false,'mensagem'=>'AÃ§Ã£o invÃ¡lida em relatÃ³rios']);
?>