<?php
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../db/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function ensure_vendas_schema($conn) {
    $colSessao = $conn->query("SHOW COLUMNS FROM vendas LIKE 'sessao_caixa_id'");
    if (!$colSessao || $colSessao->num_rows === 0) {
        $conn->query("ALTER TABLE vendas ADD COLUMN sessao_caixa_id INT NULL AFTER id_usuario");
    }

    $colTurno = $conn->query("SHOW COLUMNS FROM vendas LIKE 'numero_venda_turno'");
    if (!$colTurno || $colTurno->num_rows === 0) {
        $conn->query("ALTER TABLE vendas ADD COLUMN numero_venda_turno INT NULL AFTER numero_venda_dia");
    }

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
}

function obterNumeroVendaTurno($conn, $sessaoCaixaId) {
    if ($sessaoCaixaId <= 0) {
        return 1;
    }

    $stmt = $conn->prepare('SELECT COALESCE(MAX(numero_venda_turno), 0) + 1 as proximo FROM vendas WHERE sessao_caixa_id = ?');
    if (!$stmt) {
        return 1;
    }
    $stmt->bind_param('i', $sessaoCaixaId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return (int)($row['proximo'] ?? 1);
}

ensure_vendas_schema($conn);

// Função para obter número de venda do dia
function obterNumeroVendaDia($conn) {
    $sql = "SELECT COALESCE(MAX(numero_venda_dia), 0) + 1 as proximo FROM vendas WHERE DATE(data_hora) = CURDATE()";
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        return (int)$row['proximo'];
    }
    return 1;
}

if ($method === 'GET' && $action === 'listar') {
    $somenteHoje = !isset($_GET['somente_hoje']) || $_GET['somente_hoje'] !== '0';
    $usuarioFiltro = (int)($_GET['usuario_id'] ?? 0);
    $agruparVenda = isset($_GET['agrupar_venda']) && $_GET['agrupar_venda'] === '1';

    $where = [];
    $params = [];
    $types = '';

    if ($somenteHoje) {
        $where[] = 'DATE(v.data_hora) = CURDATE()';
    }

    if ($usuarioFiltro > 0) {
        $where[] = 'v.id_usuario = ?';
        $params[] = $usuarioFiltro;
        $types .= 'i';
    }

    $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

    if ($agruparVenda) {
        $sql = "SELECT v.id_venda, v.data_hora, v.numero_venda_turno, v.numero_venda_dia,
                       COALESCE(NULLIF(u.nome, ''), NULLIF(u.login, ''), u.username, 'N/A') as vendedor,
                       COALESCE(v.valor_total, SUM(iv.subtotal), 0) as valor_total
                FROM vendas v
                LEFT JOIN itens_venda iv ON iv.id_venda = v.id_venda
                LEFT JOIN usuarios u ON u.id_usuario = v.id_usuario
                $whereSql
                GROUP BY v.id_venda, v.data_hora, v.numero_venda_turno, v.numero_venda_dia, vendedor, v.valor_total
                ORDER BY v.data_hora DESC
                LIMIT 300";
    } else {
        $sql = "SELECT iv.id_venda, iv.id_produto, p.nome as produto, iv.quantidade, iv.preco_unitario, iv.subtotal, v.data_hora,
                       v.numero_venda_turno, v.numero_venda_dia,
                       COALESCE(NULLIF(u.nome, ''), NULLIF(u.login, ''), u.username, 'N/A') as vendedor
                FROM itens_venda iv
                JOIN produtos p ON iv.id_produto = p.id_produto
                JOIN vendas v ON iv.id_venda = v.id_venda
                LEFT JOIN usuarios u ON u.id_usuario = v.id_usuario
                $whereSql
                ORDER BY v.data_hora DESC
                LIMIT 300";
    }

    if (count($params)) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) json_error('Erro ao preparar listagem de vendas: ' . $conn->error);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }

    if (!$res) json_error('Erro ao listar vendas: ' . $conn->error);
    $out = [];
    while ($r = $res->fetch_assoc()) {
        $out[] = [
            'id'=>(int)$r['id_venda'],
            'numero_pedido'=>(int)$r['id_venda'],
            'numero_venda_turno'=>(int)($r['numero_venda_turno'] ?? 0),
            'numero_venda_dia'=>(int)($r['numero_venda_dia'] ?? 0),
            'produto_nome'=>$r['produto'] ?? null,
            'quantidade'=>(int)($r['quantidade'] ?? 0),
            'preco_unitario'=>(float)($r['preco_unitario'] ?? 0),
            'total'=>(float)($r['subtotal'] ?? ($r['valor_total'] ?? 0)),
            'valor_total'=>(float)($r['valor_total'] ?? ($r['subtotal'] ?? 0)),
            'data_venda'=>$r['data_hora'],
            'vendedor'=>$r['vendedor']
        ];
    }
    echo json_encode(['sucesso'=>true,'dados'=>$out]);
    exit;
}

if ($method === 'GET' && $action === 'listar_por_sessao') {
    $sessaoId = (int)($_GET['sessao_id'] ?? 0);
    
    if ($sessaoId <= 0) {
        json_error('ID da sessão inválido');
    }
    
    $sql = "SELECT v.id_venda, v.data_hora, v.numero_venda_turno, v.numero_venda_dia,
                   COALESCE(NULLIF(u.nome, ''), NULLIF(u.login, ''), u.username, 'N/A') as vendedor,
                   COALESCE(v.valor_total, SUM(iv.subtotal), 0) as valor_total
            FROM vendas v
            LEFT JOIN itens_venda iv ON iv.id_venda = v.id_venda
            LEFT JOIN usuarios u ON u.id_usuario = v.id_usuario
            WHERE v.sessao_caixa_id = ?
            GROUP BY v.id_venda, v.data_hora, v.numero_venda_turno, v.numero_venda_dia, vendedor, v.valor_total
            ORDER BY v.data_hora DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $sessaoId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $out = [];
    while ($r = $result->fetch_assoc()) {
        $out[] = [
            'id_venda' => (int)$r['id_venda'],
            'numero_venda_turno' => (int)($r['numero_venda_turno'] ?? 0),
            'numero_venda_dia' => (int)($r['numero_venda_dia'] ?? 0),
            'vendedor' => $r['vendedor'],
            'data_hora' => $r['data_hora'],
            'valor_total' => (float)($r['valor_total'] ?? 0)
        ];
    }
    $stmt->close();
    
    echo json_encode(['sucesso'=>true,'dados'=>$out]);
    exit;
}

if ($method === 'GET' && $action === 'detalhes') {
    $idVenda = (int)($_GET['id'] ?? 0);
    if ($idVenda <= 0) {
        json_error('ID da venda inválido');
    }

    $sqlCab = "SELECT v.id_venda, v.data_hora, v.valor_total, v.numero_venda_turno, v.numero_venda_dia,
                      COALESCE(NULLIF(u.nome, ''), NULLIF(u.login, ''), u.username, 'N/A') as vendedor
               FROM vendas v
               LEFT JOIN usuarios u ON u.id_usuario = v.id_usuario
               WHERE v.id_venda = ?
               LIMIT 1";
    $stmtCab = $conn->prepare($sqlCab);
    if (!$stmtCab) {
        json_error('Erro ao preparar cabeçalho da venda: ' . $conn->error);
    }
    $stmtCab->bind_param('i', $idVenda);
    $stmtCab->execute();
    $cab = $stmtCab->get_result()->fetch_assoc();
    $stmtCab->close();

    if (!$cab) {
        json_error('Venda não encontrada');
    }

    $sqlItens = "SELECT i.id_produto, p.nome as produto, i.quantidade, i.preco_unitario, i.subtotal
                 FROM itens_venda i
                 LEFT JOIN produtos p ON p.id_produto = i.id_produto
                 WHERE i.id_venda = ?
                 ORDER BY i.id_item ASC";
    $stmtItens = $conn->prepare($sqlItens);
    if (!$stmtItens) {
        json_error('Erro ao preparar itens da venda: ' . $conn->error);
    }
    $stmtItens->bind_param('i', $idVenda);
    $stmtItens->execute();
    $resItens = $stmtItens->get_result();

    $itens = [];
    while ($row = $resItens->fetch_assoc()) {
        $itens[] = [
            'id_produto' => (int)$row['id_produto'],
            'produto' => $row['produto'] ?? 'Produto',
            'quantidade' => (int)$row['quantidade'],
            'preco_unitario' => (float)$row['preco_unitario'],
            'subtotal' => (float)$row['subtotal']
        ];
    }
    $stmtItens->close();

    echo json_encode([
        'sucesso' => true,
        'dados' => [
            'id_venda' => (int)$cab['id_venda'],
            'numero_venda_turno' => (int)($cab['numero_venda_turno'] ?? 0),
            'numero_venda_dia' => (int)($cab['numero_venda_dia'] ?? 0),
            'numero_exibicao' => (int)(($cab['numero_venda_turno'] ?? 0) ?: (($cab['numero_venda_dia'] ?? 0) ?: ($cab['id_venda'] ?? 0))),
            'data_hora' => $cab['data_hora'],
            'vendedor' => $cab['vendedor'],
            'valor_total' => (float)$cab['valor_total'],
            'itens' => $itens
        ]
    ]);
    exit;
}

if ($method === 'POST' && $action === 'criar_carrinho') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $itens = $data['itens'] ?? [];
    $usuario_id = (int)($data['usuario_id'] ?? 1);
    $sessao_caixa_id = (int)($data['sessao_caixa_id'] ?? 0);
    if (!is_array($itens) || count($itens)===0) json_error('Carrinho vazio');

    // Carregar produtos envolvidos
    $ids = array_map(fn($i)=>(int)($i['produto_id'] ?? 0), $itens);
    $ids = array_values(array_unique(array_filter($ids)));
    if (count($ids)===0) json_error('Itens inválidos');

    $in = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $conn->prepare('SELECT id_produto, nome, preco_venda, estoque_atual FROM produtos WHERE id_produto IN ('.str_replace('?', '?', $in).')');
    // MySQLi não suporta bind dinâmico diretamente no implode; contorno simples abaixo:
    $sql = 'SELECT id_produto, nome, preco_venda, estoque_atual FROM produtos WHERE id_produto IN ('.implode(',', $ids).')';
    $res = $conn->query($sql);
    if (!$res) json_error('Erro ao carregar produtos: '.$conn->error);
    $map = [];
    while ($p = $res->fetch_assoc()) { $map[(int)$p['id_produto']] = $p; }

    // Validar estoque e calcular totais
    $subtotalTotal = 0.0;
    foreach ($itens as $it){
        $pid = (int)($it['produto_id'] ?? 0);
        $qtd = (int)($it['quantidade'] ?? 0);
        if ($pid<=0 || $qtd<=0) json_error('Item inválido');
        if (!isset($map[$pid])) json_error('Produto '.$pid.' não encontrado');
        $preco = (float)$map[$pid]['preco_venda'];
        $estoque = (int)$map[$pid]['estoque_atual'];
        if ($estoque < $qtd) json_error('Estoque insuficiente para produto ID '.$pid);
        $subtotalTotal += $preco * $qtd;
    }

    $conn->begin_transaction();
    try {
        // Obter número de venda do dia
        $numero_venda_dia = obterNumeroVendaDia($conn);
        $numero_venda_turno = obterNumeroVendaTurno($conn, $sessao_caixa_id);
        
        // Inserir venda principal
        $stmtV = $conn->prepare('INSERT INTO vendas (id_usuario, sessao_caixa_id, valor_total, numero_venda_dia, numero_venda_turno, data_hora) VALUES (?,?,?,?,?,NOW())');
        if (!$stmtV) throw new Exception('Erro preparação venda: '.$conn->error);
        $stmtV->bind_param('iidii', $usuario_id, $sessao_caixa_id, $subtotalTotal, $numero_venda_dia, $numero_venda_turno);
        if (!$stmtV->execute()) throw new Exception('Erro ao criar venda: '.$stmtV->error);
        $id_venda = $stmtV->insert_id;

        // Inserir cada item e atualizar estoque
        $stmtI = $conn->prepare('INSERT INTO itens_venda (id_venda, id_produto, quantidade, preco_unitario, subtotal) VALUES (?,?,?,?,?)');
        if (!$stmtI) throw new Exception('Erro preparação item: '.$conn->error);

        $stmtP = $conn->prepare('UPDATE produtos SET estoque_atual = GREATEST(estoque_atual - ?, 0), data_atualizacao = CURRENT_TIMESTAMP WHERE id_produto = ? AND estoque_atual >= ?');
        if (!$stmtP) throw new Exception('Erro preparação estoque: '.$conn->error);

        foreach ($itens as $it){
            $pid = (int)$it['produto_id'];
            $qtd = (int)$it['quantidade'];
            $preco = (float)$map[$pid]['preco_venda'];
            $sub = $preco * $qtd;
            if (!$stmtI->bind_param('iiidd', $id_venda, $pid, $qtd, $preco, $sub) || !$stmtI->execute())
                throw new Exception('Erro ao inserir item: '.$stmtI->error);
            if (!$stmtP->bind_param('iii', $qtd, $pid, $qtd) || !$stmtP->execute())
                throw new Exception('Erro ao atualizar estoque: '.$stmtP->error);
            if ($stmtP->affected_rows === 0) {
                throw new Exception('Estoque insuficiente para produto ID '.$pid.' no momento da confirmação');
            }
        }

        $conn->commit();
        echo json_encode([
            'sucesso'=>true,
            'mensagem'=>'Venda registrada',
            'id'=>$id_venda,
            'numero_venda'=>$numero_venda_dia,
            'numero_venda_turno'=>$numero_venda_turno,
            'sessao_caixa_id'=>$sessao_caixa_id
        ]);
    } catch (Exception $e){
        $conn->rollback();
        json_error($e->getMessage());
    }
    exit;
}

// Fallback único item (compatibilidade)
if ($method === 'POST' && $action === 'criar') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $produto_id = (int)($data['produto_id'] ?? 0);
    $quantidade = (int)($data['quantidade'] ?? 0);
    $usuario_id = (int)($data['usuario_id'] ?? 1);
    $sessao_caixa_id = (int)($data['sessao_caixa_id'] ?? 0);
    if ($produto_id<=0 || $quantidade<=0) json_error('Dados inválidos');

    $p = $conn->prepare('SELECT preco_venda, estoque_atual FROM produtos WHERE id_produto = ?');
    if (!$p) json_error('Erro preparação produto: '.$conn->error);
    $p->bind_param('i', $produto_id); $p->execute();
    $prod = $p->get_result()->fetch_assoc();
    if (!$prod) json_error('Produto não encontrado');
    $preco = (float)$prod['preco_venda']; $estoque = (int)$prod['estoque_atual'];
    if ($estoque < $quantidade) json_error('Estoque insuficiente');

    $total = $preco*$quantidade;
    $conn->begin_transaction();
    try{
        // Obter número de venda do dia
        $numero_venda_dia = obterNumeroVendaDia($conn);
        $numero_venda_turno = obterNumeroVendaTurno($conn, $sessao_caixa_id);
        
        $v = $conn->prepare('INSERT INTO vendas (id_usuario, sessao_caixa_id, valor_total, numero_venda_dia, numero_venda_turno, data_hora) VALUES (?,?,?,?,?,NOW())');
        $v->bind_param('iidii', $usuario_id, $sessao_caixa_id, $total, $numero_venda_dia, $numero_venda_turno); $v->execute(); $id_venda = $v->insert_id;
        $i = $conn->prepare('INSERT INTO itens_venda (id_venda, id_produto, quantidade, preco_unitario, subtotal) VALUES (?,?,?,?,?)');
        $i->bind_param('iiidd', $id_venda, $produto_id, $quantidade, $preco, $total); $i->execute();
        $u = $conn->prepare('UPDATE produtos SET estoque_atual = GREATEST(estoque_atual - ?, 0), data_atualizacao = CURRENT_TIMESTAMP WHERE id_produto = ? AND estoque_atual >= ?');
        $u->bind_param('iii', $quantidade, $produto_id, $quantidade); $u->execute();
        if ($u->affected_rows === 0) throw new Exception('Estoque insuficiente para produto ID '.$produto_id.' no momento da confirmação');
        $conn->commit(); echo json_encode([
            'sucesso'=>true,
            'mensagem'=>'Venda criada com sucesso',
            'id'=>$id_venda,
            'numero_venda'=>$numero_venda_dia,
            'numero_venda_turno'=>$numero_venda_turno,
            'sessao_caixa_id'=>$sessao_caixa_id
        ]);
    }catch(Exception $e){ $conn->rollback(); json_error($e->getMessage()); }
    exit;
}

echo json_encode(['sucesso'=>false,'mensagem'=>'Ação não encontrada']);
?>