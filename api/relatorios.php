<?php
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/helpers.php';
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

// Relatorio de vendas agregadas
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

// Relatorio de itens vendidos (producao)
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
    if (!$res) error('Erro ao gerar producao: '.$conn->error);
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

// CRUD: Atualizar venda
if ($method==='POST' && $action==='atualizar_venda'){
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) error('Dados inválidos');
    
    $pedido_id = $data['pedido_id'] ?? null;
    $data_venda = $data['data'] ?? '';
    $quantidade = $data['quantidade'] ?? 0;
    $valor = $data['valor'] ?? 0;
    
    if (!$pedido_id || !$data_venda || $quantidade <= 0 || $valor < 0) {
        error('Campos obrigatórios não preenchidos');
    }
    
    $sql = "UPDATE vendas SET data_hora = ? WHERE id_venda = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) error('Erro ao preparar statement: '.$conn->error);
    
    $data_hora = $data_venda . ' ' . date('H:i:s');
    $stmt->bind_param('si', $data_hora, $pedido_id);
    
    if (!$stmt->execute()) {
        error('Erro ao atualizar venda: '.$conn->error);
    }
    
    // Atualizar itens da venda
    $sql_item = "UPDATE itens_venda SET quantidade = ?, subtotal = ? WHERE id_venda = ?";
    $stmt_item = $conn->prepare($sql_item);
    if (!$stmt_item) error('Erro ao preparar statement: '.$conn->error);
    
    $stmt_item->bind_param('ddi', $quantidade, $valor, $pedido_id);
    
    if (!$stmt_item->execute()) {
        error('Erro ao atualizar itens: '.$conn->error);
    }
    
    ok(['mensagem' => 'Venda atualizada com sucesso']);
}

// CRUD: Deletar venda
if ($method==='POST' && $action==='deletar_venda'){
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) error('Dados inválidos');
    
    $pedido_id = $data['pedido_id'] ?? null;
    
    if (!$pedido_id) {
        error('ID da venda não informado');
    }
    
    // Deletar itens primeiro
    $sql_itens = "DELETE FROM itens_venda WHERE id_venda = ?";
    $stmt_itens = $conn->prepare($sql_itens);
    if (!$stmt_itens) error('Erro ao preparar statement: '.$conn->error);
    
    $stmt_itens->bind_param('i', $pedido_id);
    if (!$stmt_itens->execute()) {
        error('Erro ao deletar itens: '.$conn->error);
    }
    
    // Deletar venda
    $sql_venda = "DELETE FROM vendas WHERE id_venda = ?";
    $stmt_venda = $conn->prepare($sql_venda);
    if (!$stmt_venda) error('Erro ao preparar statement: '.$conn->error);
    
    $stmt_venda->bind_param('i', $pedido_id);
    if (!$stmt_venda->execute()) {
        error('Erro ao deletar venda: '.$conn->error);
    }
    
    ok(['mensagem' => 'Venda deletada com sucesso']);
}

if ($method==='POST' && $action==='deletar_caixa'){
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) error('Dados inválidos');
    
    $sessao_id = $data['sessao_id'] ?? null;
    
    if (!$sessao_id) {
        error('ID da sessão não informado');
    }
    
    // Deletar vendas associadas primeiro (se houver)
    $sql_vendas = "DELETE FROM vendas WHERE sessao_caixa_id = ?";
    $stmt_vendas = $conn->prepare($sql_vendas);
    if ($stmt_vendas) {
        $stmt_vendas->bind_param('i', $sessao_id);
        $stmt_vendas->execute();
        $stmt_vendas->close();
    }
    
    // Deletar sessão de caixa
    $sql_caixa = "DELETE FROM caixa_sessoes WHERE id_sessao = ?";
    $stmt_caixa = $conn->prepare($sql_caixa);
    if (!$stmt_caixa) error('Erro ao preparar statement: '.$conn->error);
    
    $stmt_caixa->bind_param('i', $sessao_id);
    if (!$stmt_caixa->execute()) {
        error('Erro ao deletar sessão: '.$stmt_caixa->error);
    }
    
    ok(['mensagem' => 'Sessão de caixa deletada com sucesso']);
}

if ($method==='POST' && $action==='deletar_todos_caixas'){
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $inicio = trim((string)($data['inicio'] ?? ''));
        $fim = trim((string)($data['fim'] ?? ''));
        $usuarioId = (int)($data['usuario_id'] ?? 0);

        $where = [];
        $params = [];
        $types = '';

        if ($inicio !== '' && $fim !== '') {
            $where[] = 'abertura_caixa BETWEEN ? AND ?';
            $params[] = $inicio . ' 00:00:00';
            $params[] = $fim . ' 23:59:59';
            $types .= 'ss';
        }

        if ($usuarioId > 0) {
            $where[] = 'id_usuario = ?';
            $params[] = $usuarioId;
            $types .= 'i';
        }

        $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sqlSessoes = "SELECT id_sessao FROM caixa_sessoes $whereSql";
        if (count($params)) {
            $stmt = $conn->prepare($sqlSessoes);
            if (!$stmt) error('Erro ao preparar seleção de sessões: '.$conn->error);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $conn->query($sqlSessoes);
        }

        if (!$res) error('Erro ao buscar sessões para exclusão: '.$conn->error);

        $sessoes = [];
        while ($row = $res->fetch_assoc()) {
            $sessoes[] = (int)$row['id_sessao'];
        }

        if (count($sessoes) === 0) {
            ok(['mensagem' => 'Nenhuma sessão encontrada para deletar', 'sessoes_apagadas' => 0]);
        }

        $conn->begin_transaction();
        try {
            $stmtDelVendas = $conn->prepare("DELETE FROM vendas WHERE sessao_caixa_id = ?");
            if (!$stmtDelVendas) {
                throw new Exception('Erro ao preparar exclusão de vendas: '.$conn->error);
            }

            $stmtDelCaixa = $conn->prepare("DELETE FROM caixa_sessoes WHERE id_sessao = ?");
            if (!$stmtDelCaixa) {
                throw new Exception('Erro ao preparar exclusão de sessões: '.$conn->error);
            }

            foreach ($sessoes as $sessaoId) {
                $stmtDelVendas->bind_param('i', $sessaoId);
                if (!$stmtDelVendas->execute()) {
                    throw new Exception('Erro ao deletar vendas da sessão '.$sessaoId.': '.$stmtDelVendas->error);
                }

                $stmtDelCaixa->bind_param('i', $sessaoId);
                if (!$stmtDelCaixa->execute()) {
                    throw new Exception('Erro ao deletar sessão '.$sessaoId.': '.$stmtDelCaixa->error);
                }
            }

            $stmtDelVendas->close();
            $stmtDelCaixa->close();

            $conn->commit();
            ok([
                'mensagem' => 'Sessões de caixa deletadas com sucesso',
                'sessoes_apagadas' => count($sessoes)
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            error($e->getMessage());
        }
}

// Ação: Trazer dados de caixa com filtros
if ($method === 'GET' && $action === 'caixa_sessoes') {
    $dataInicial = $_GET['data_inicial'] ?? '';
    $dataFinal = $_GET['data_final'] ?? '';
    $usuarioId = (int)($_GET['usuario_id'] ?? 0);
    $status = $_GET['status'] ?? '';

    $where = [];
    $params = [];
    $types = '';

    if ($dataInicial && $dataFinal) {
        $where[] = 'cs.abertura_caixa BETWEEN ? AND ?';
        $params[] = $dataInicial . ' 00:00:00';
        $params[] = $dataFinal . ' 23:59:59';
        $types .= 'ss';
    }

    if ($usuarioId > 0) {
        $where[] = 'cs.id_usuario = ?';
        $params[] = $usuarioId;
        $types .= 'i';
    }

    if ($status && in_array($status, ['aberto', 'fechado'])) {
        $where[] = 'cs.status = ?';
        $params[] = $status;
        $types .= 's';
    }

    $whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT 
        cs.id_sessao,
        cs.id_usuario,
        cs.login_usuario,
        cs.abertura_caixa,
        cs.fechamento_caixa,
        cs.valor_abertura,
        cs.valor_fechamento,
        cs.observacoes,
        cs.status,
        COALESCE(SUM(COALESCE(v.valor_total, v.total, 0)), 0) as total_vendas
    FROM caixa_sessoes cs
    LEFT JOIN vendas v ON v.sessao_caixa_id = cs.id_sessao
    $whereSql
    GROUP BY cs.id_sessao
    ORDER BY cs.abertura_caixa DESC";

    if (count($params) > 0) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) error('Erro ao preparar query: ' . $conn->error);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    if (!$result) error('Erro ao buscar sessões de caixa: ' . $conn->error);

    $dados = [];
    while ($row = $result->fetch_assoc()) {
        $dados[] = [
            'id_sessao' => (int)$row['id_sessao'],
            'id_usuario' => (int)$row['id_usuario'],
            'login_usuario' => $row['login_usuario'],
            'abertura_caixa' => $row['abertura_caixa'],
            'fechamento_caixa' => $row['fechamento_caixa'],
            'valor_abertura' => (float)$row['valor_abertura'],
            'valor_fechamento' => (float)$row['valor_fechamento'],
            'observacoes' => $row['observacoes'],
            'status' => $row['status'],
            'total_vendas' => (float)$row['total_vendas']
        ];
    }

    ok($dados);
}

// Ação: Trazer último caixa aberto de um usuário
if ($method === 'GET' && $action === 'ultimo_caixa') {
    $usuarioId = (int)($_GET['usuario_id'] ?? 0);

    if ($usuarioId <= 0) {
        error('Usuário não informado');
    }

    $sql = "SELECT 
        id_sessao,
        abertura_caixa,
        valor_abertura,
        status
    FROM caixa_sessoes
    WHERE id_usuario = ?
    ORDER BY abertura_caixa DESC
    LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) error('Erro ao preparar query: ' . $conn->error);
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        ok(null);
    }

    $row = $result->fetch_assoc();
    ok([
        'id_sessao' => (int)$row['id_sessao'],
        'abertura_caixa' => $row['abertura_caixa'],
        'valor_abertura' => (float)$row['valor_abertura'],
        'status' => $row['status']
    ]);
}

echo json_encode(['sucesso'=>false,'mensagem'=>'Acao invalida em relatorios']);
?>