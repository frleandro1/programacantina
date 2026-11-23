<?php
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/estoque-contabil.php';

garantir_schema_estoque_contabil($conn);

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
        valor_abertura DECIMAL(10, 2) DEFAULT 0,
        valor_fechamento DECIMAL(10, 2) DEFAULT 0,
        total_vendas DECIMAL(10, 2) DEFAULT 0,
        observacoes TEXT NULL,
        status ENUM('aberto','fechado') NOT NULL DEFAULT 'aberto',
        ip_origem VARCHAR(45) NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_usuario_status (id_usuario, status),
        INDEX idx_abertura (abertura_caixa)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $extraCols = [
        'valor_abertura' => "ALTER TABLE caixa_sessoes ADD COLUMN valor_abertura DECIMAL(10, 2) DEFAULT 0 AFTER fechamento_caixa",
        'valor_fechamento' => "ALTER TABLE caixa_sessoes ADD COLUMN valor_fechamento DECIMAL(10, 2) DEFAULT 0 AFTER valor_abertura",
        'total_vendas' => "ALTER TABLE caixa_sessoes ADD COLUMN total_vendas DECIMAL(10, 2) DEFAULT 0 AFTER valor_fechamento",
        'observacoes' => "ALTER TABLE caixa_sessoes ADD COLUMN observacoes TEXT NULL AFTER total_vendas"
    ];
    foreach ($extraCols as $col => $alterSql) {
        $check = $conn->query("SHOW COLUMNS FROM caixa_sessoes LIKE '{$col}'");
        if (!$check || $check->num_rows === 0) {
            $conn->query($alterSql);
        }
    }

    $colSessao = $conn->query("SHOW COLUMNS FROM vendas LIKE 'sessao_caixa_id'");
    if (!$colSessao || $colSessao->num_rows === 0) {
        $conn->query("ALTER TABLE vendas ADD COLUMN sessao_caixa_id INT NULL AFTER id_usuario");
    }

    $colTurno = $conn->query("SHOW COLUMNS FROM vendas LIKE 'numero_venda_turno'");
    if (!$colTurno || $colTurno->num_rows === 0) {
        $conn->query("ALTER TABLE vendas ADD COLUMN numero_venda_turno INT NULL AFTER numero_venda_dia");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS financeiro_eventos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(150) NOT NULL,
        data_evento DATE NOT NULL,
        status ENUM('rascunho', 'finalizado') NOT NULL DEFAULT 'rascunho',
        documento LONGTEXT NOT NULL,
        versao INT NOT NULL DEFAULT 1,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS financeiro_evento_caixas (
        sessao_id INT PRIMARY KEY,
        evento_id INT NOT NULL,
        FOREIGN KEY (sessao_id) REFERENCES caixa_sessoes(id_sessao),
        FOREIGN KEY (evento_id) REFERENCES financeiro_eventos(id),
        INDEX idx_evento (evento_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
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
                fe.id as evento_id,
                fe.nome as evento_nome,
                cs.abertura_caixa,
                cs.fechamento_caixa,
                cs.status,
                cs.ip_origem,
                COUNT(v.id_venda) as total_vendas,
                COALESCE(SUM(v.valor_total), 0) as valor_total
            FROM caixa_sessoes cs
            LEFT JOIN usuarios u ON u.id_usuario = cs.id_usuario
            LEFT JOIN financeiro_evento_caixas ec ON ec.sessao_id = cs.id_sessao
            LEFT JOIN financeiro_eventos fe ON fe.id = ec.evento_id
            LEFT JOIN vendas v ON v.sessao_caixa_id = cs.id_sessao
            $whereSql
            GROUP BY cs.id_sessao, cs.id_usuario, cs.login_usuario, nome_usuario, fe.id, fe.nome, cs.abertura_caixa, cs.fechamento_caixa, cs.status, cs.ip_origem
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
            'evento_id' => $r['evento_id'] ? (int)$r['evento_id'] : null,
            'evento_nome' => $r['evento_nome'] ?? null,
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
    $produtoId = (int)($_GET['produto_id'] ?? 0);

    $joinDateCond = '';
    $where = [];
    $params = [];
    $types = '';

    if ($inicio && $fim) {
        $joinDateCond = ' AND v.data_hora BETWEEN ? AND ?';
        $params[] = $inicio . ' 00:00:00';
        $params[] = $fim . ' 23:59:59';
        $types .= 'ss';
    }

    if ($produtoId > 0) {
        $where[] = 'p.id_produto = ?';
        $params[] = $produtoId;
        $types .= 'i';
    }

    $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT p.id_produto as id, p.nome,
                   COALESCE(SUM(CASE WHEN v.id_venda IS NOT NULL THEN i.quantidade ELSE 0 END), 0) as quantidade,
                   COALESCE(SUM(CASE WHEN v.id_venda IS NOT NULL THEN i.subtotal ELSE 0 END), 0) as total
            FROM produtos p
            LEFT JOIN itens_venda i ON i.id_produto = p.id_produto
            LEFT JOIN vendas v ON v.id_venda = i.id_venda{$joinDateCond}
            $whereSql
            GROUP BY p.id_produto, p.nome
            ORDER BY quantidade DESC";

    if (count($params)) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) error('Erro ao preparar producao: '.$conn->error);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }
    if (!$res) error('Erro ao gerar producao: '.$conn->error);
    $out=[]; while($r=$res->fetch_assoc()){ $out[]=[ 'id'=>(int)$r['id'], 'nome'=>$r['nome'], 'quantidade'=>(int)$r['quantidade'], 'total'=>(float)$r['total'] ]; }
    ok($out);
}

// Detalhamento de vendas
if ($method==='GET' && $action==='vendas_detalhes'){
    $inicio = $_GET['inicio'] ?? '';
    $fim    = $_GET['fim'] ?? '';
    $produtoId = (int)($_GET['produto_id'] ?? 0);
    $atendenteId = (int)($_GET['atendente_id'] ?? 0);

    $where = [];
    $params = [];
    $types = '';

    if ($inicio && $fim) {
        $where[] = 'v.data_hora BETWEEN ? AND ?';
        $params[] = $inicio . ' 00:00:00';
        $params[] = $fim . ' 23:59:59';
        $types .= 'ss';
    }
    if ($produtoId > 0) {
        $where[] = 'i.id_produto = ?';
        $params[] = $produtoId;
        $types .= 'i';
    }
    if ($atendenteId > 0) {
        $where[] = 'v.id_usuario = ?';
        $params[] = $atendenteId;
        $types .= 'i';
    }

    $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "SELECT v.id_venda as pedido, i.id_item as item_id, i.id_produto as produto_id, v.id_usuario as atendente_id,
                   v.data_hora as data, COALESCE(u.nome, u.login) as atendente,
                   i.quantidade, i.preco_unitario, i.subtotal, p.nome as produto
            FROM vendas v
            LEFT JOIN usuarios u ON u.id_usuario = v.id_usuario
            LEFT JOIN itens_venda i ON i.id_venda = v.id_venda
            LEFT JOIN produtos p ON p.id_produto = i.id_produto
            $whereSql
            ORDER BY v.data_hora DESC, i.id_item ASC";

    if (count($params)) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) error('Erro ao preparar detalhes: '.$conn->error);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query($sql);
    }
    if (!$res) error('Erro ao listar detalhes: '.$conn->error);
    $out=[]; while($r=$res->fetch_assoc()){ $out[]=[
        'pedido'=>(int)$r['pedido'],
        'pedido_id'=>(int)$r['pedido'],
        'item_id'=>$r['item_id'] !== null ? (int)$r['item_id'] : null,
        'produto_id'=>$r['produto_id'] !== null ? (int)$r['produto_id'] : null,
        'atendente_id'=>$r['atendente_id'] !== null ? (int)$r['atendente_id'] : null,
        'data'=>$r['data'],
        'atendente'=>$r['atendente'],
        'produto'=>$r['produto'],
        'quantidade'=>(int)$r['quantidade'],
        'preco_unitario'=>(float)$r['preco_unitario'],
        'subtotal'=>(float)$r['subtotal']
    ]; }
    ok($out);
}

// CRUD: Atualizar venda
if ($method==='POST' && $action==='atualizar_venda'){
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) error('Dados inválidos');
    
    $pedido_id = (int)($data['pedido_id'] ?? 0);
    $item_id = (int)($data['item_id'] ?? 0);
    $data_venda = trim((string)($data['data'] ?? ''));
    $atendente = trim((string)($data['atendente'] ?? ''));
    $produto = trim((string)($data['produto'] ?? ''));
    $quantidade = (int)($data['quantidade'] ?? 0);
    $valor = (float)($data['valor'] ?? 0);
    
    if ($pedido_id <= 0 || $data_venda === '' || $quantidade <= 0 || $valor < 0) {
        error('Campos obrigatórios não preenchidos');
    }

    $conn->begin_transaction();
    try {
        // Preservar hora original da venda se data vier apenas como YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_venda)) {
            $stmtHora = $conn->prepare("UPDATE vendas SET data_hora = CONCAT(?, ' ', TIME(COALESCE(data_hora, NOW()))) WHERE id_venda = ?");
            if (!$stmtHora) throw new Exception('Erro ao preparar atualização de data: '.$conn->error);
            $stmtHora->bind_param('si', $data_venda, $pedido_id);
        } else {
            $stmtHora = $conn->prepare("UPDATE vendas SET data_hora = ? WHERE id_venda = ?");
            if (!$stmtHora) throw new Exception('Erro ao preparar atualização de data: '.$conn->error);
            $stmtHora->bind_param('si', $data_venda, $pedido_id);
        }
        if (!$stmtHora->execute()) {
            throw new Exception('Erro ao atualizar venda: '.$stmtHora->error);
        }
        $stmtHora->close();

        // Se atendente foi informado, tentar localizar usuário pelo nome ou login
        if ($atendente !== '') {
            $stmtUser = $conn->prepare("SELECT id_usuario FROM usuarios WHERE nome = ? OR login = ? LIMIT 1");
            if ($stmtUser) {
                $stmtUser->bind_param('ss', $atendente, $atendente);
                $stmtUser->execute();
                $resUser = $stmtUser->get_result();
                if ($rowUser = $resUser->fetch_assoc()) {
                    $novoUserId = (int)$rowUser['id_usuario'];
                    $stmtUpdUser = $conn->prepare("UPDATE vendas SET id_usuario = ? WHERE id_venda = ?");
                    if ($stmtUpdUser) {
                        $stmtUpdUser->bind_param('ii', $novoUserId, $pedido_id);
                        $stmtUpdUser->execute();
                        $stmtUpdUser->close();
                    }
                }
                $stmtUser->close();
            }
        }

        // Se produto foi informado, tentar localizar pelo nome
        $novoProdId = null;
        if ($produto !== '') {
            $stmtProd = $conn->prepare("SELECT id_produto FROM produtos WHERE nome = ? LIMIT 1");
            if ($stmtProd) {
                $stmtProd->bind_param('s', $produto);
                $stmtProd->execute();
                $resProd = $stmtProd->get_result();
                if ($rowProd = $resProd->fetch_assoc()) {
                    $novoProdId = (int)$rowProd['id_produto'];
                }
                $stmtProd->close();
            }
        }

        $precoUnitario = $quantidade > 0 ? round($valor / $quantidade, 2) : $valor;

        if ($item_id > 0) {
            if ($novoProdId !== null) {
                $stmtItem = $conn->prepare("UPDATE itens_venda SET id_produto = ?, quantidade = ?, preco_unitario = ?, subtotal = ? WHERE id_item = ? AND id_venda = ?");
                if (!$stmtItem) throw new Exception('Erro ao preparar item: '.$conn->error);
                $stmtItem->bind_param('iiddii', $novoProdId, $quantidade, $precoUnitario, $valor, $item_id, $pedido_id);
            } else {
                $stmtItem = $conn->prepare("UPDATE itens_venda SET quantidade = ?, preco_unitario = ?, subtotal = ? WHERE id_item = ? AND id_venda = ?");
                if (!$stmtItem) throw new Exception('Erro ao preparar item: '.$conn->error);
                $stmtItem->bind_param('iddii', $quantidade, $precoUnitario, $valor, $item_id, $pedido_id);
            }
        } else {
            if ($novoProdId !== null) {
                $stmtItem = $conn->prepare("UPDATE itens_venda SET id_produto = ?, quantidade = ?, preco_unitario = ?, subtotal = ? WHERE id_venda = ?");
                if (!$stmtItem) throw new Exception('Erro ao preparar item: '.$conn->error);
                $stmtItem->bind_param('iiddi', $novoProdId, $quantidade, $precoUnitario, $valor, $pedido_id);
            } else {
                $stmtItem = $conn->prepare("UPDATE itens_venda SET quantidade = ?, preco_unitario = ?, subtotal = ? WHERE id_venda = ?");
                if (!$stmtItem) throw new Exception('Erro ao preparar item: '.$conn->error);
                $stmtItem->bind_param('iddi', $quantidade, $precoUnitario, $valor, $pedido_id);
            }
        }

        if (!$stmtItem->execute()) {
            throw new Exception('Erro ao atualizar itens: '.$stmtItem->error);
        }
        $stmtItem->close();

        // Recalcular valor_total da venda
        $stmtTotal = $conn->prepare("UPDATE vendas SET valor_total = (SELECT COALESCE(SUM(subtotal), 0) FROM itens_venda WHERE id_venda = ?) WHERE id_venda = ?");
        if ($stmtTotal) {
            $stmtTotal->bind_param('ii', $pedido_id, $pedido_id);
            $stmtTotal->execute();
            $stmtTotal->close();
        }

        // Sincronizar lucro/prejuízo automático nas movimentações
        sincronizar_venda_contabil($conn, $pedido_id);

        $conn->commit();
        ok(['mensagem' => 'Venda atualizada com sucesso']);
    } catch (Exception $e) {
        $conn->rollback();
        error($e->getMessage());
    }
}

// CRUD: Deletar venda
if ($method==='POST' && $action==='deletar_venda'){
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) error('Dados inválidos');
    
    $pedido_id = (int)($data['pedido_id'] ?? 0);
    $item_id = (int)($data['item_id'] ?? 0);
    
    if ($pedido_id <= 0) {
        error('ID da venda não informado');
    }
    
    $conn->begin_transaction();
    try {
        // Buscar itens que serão removidos para estornar o estoque
        if ($item_id > 0) {
            $stmtBusca = $conn->prepare("SELECT id_produto, quantidade FROM itens_venda WHERE id_item = ? AND id_venda = ?");
            $stmtBusca->bind_param('ii', $item_id, $pedido_id);
        } else {
            $stmtBusca = $conn->prepare("SELECT id_produto, quantidade FROM itens_venda WHERE id_venda = ?");
            $stmtBusca->bind_param('i', $pedido_id);
        }
        if ($stmtBusca && $stmtBusca->execute()) {
            $resItens = $stmtBusca->get_result();
            $colsP = ec_mapa_colunas_produtos($conn);
            $stmtEstorno = $conn->prepare("UPDATE produtos SET {$colsP['estoque']} = {$colsP['estoque']} + ? WHERE {$colsP['pk']} = ?");
            while ($itemRow = $resItens->fetch_assoc()) {
                $qtdEstorno = (int)$itemRow['quantidade'];
                $prodEstorno = (int)$itemRow['id_produto'];
                if ($stmtEstorno && $qtdEstorno > 0 && $prodEstorno > 0) {
                    $stmtEstorno->bind_param('ii', $qtdEstorno, $prodEstorno);
                    $stmtEstorno->execute();
                }
            }
            if ($stmtEstorno) $stmtEstorno->close();
            $stmtBusca->close();
        }

        if ($item_id > 0) {
            // Deletar o item específico
            $stmtDelItem = $conn->prepare("DELETE FROM itens_venda WHERE id_item = ? AND id_venda = ?");
            if (!$stmtDelItem) throw new Exception('Erro ao preparar exclusão do item: '.$conn->error);
            $stmtDelItem->bind_param('ii', $item_id, $pedido_id);
            if (!$stmtDelItem->execute()) throw new Exception('Erro ao deletar item: '.$stmtDelItem->error);
            $stmtDelItem->close();

            // Verificar se ainda restam itens nessa venda
            $stmtRestantes = $conn->prepare("SELECT COUNT(*) as total, COALESCE(SUM(subtotal), 0) as soma FROM itens_venda WHERE id_venda = ?");
            $stmtRestantes->bind_param('i', $pedido_id);
            $stmtRestantes->execute();
            $rowRest = $stmtRestantes->get_result()->fetch_assoc();
            $stmtRestantes->close();

            if ((int)($rowRest['total'] ?? 0) > 0) {
                $novoTotal = (float)$rowRest['soma'];
                $stmtUpdVenda = $conn->prepare("UPDATE vendas SET valor_total = ? WHERE id_venda = ?");
                $stmtUpdVenda->bind_param('di', $novoTotal, $pedido_id);
                $stmtUpdVenda->execute();
                $stmtUpdVenda->close();
                sincronizar_venda_contabil($conn, $pedido_id);
            } else {
                remover_venda_contabil($conn, $pedido_id);
                $stmtDelVenda = $conn->prepare("DELETE FROM vendas WHERE id_venda = ?");
                $stmtDelVenda->bind_param('i', $pedido_id);
                $stmtDelVenda->execute();
                $stmtDelVenda->close();
            }
        } else {
            remover_venda_contabil($conn, $pedido_id);
            // Deletar todos os itens e a venda
            $stmt_itens = $conn->prepare("DELETE FROM itens_venda WHERE id_venda = ?");
            if (!$stmt_itens) throw new Exception('Erro ao preparar statement: '.$conn->error);
            $stmt_itens->bind_param('i', $pedido_id);
            if (!$stmt_itens->execute()) throw new Exception('Erro ao deletar itens: '.$stmt_itens->error);
            $stmt_itens->close();

            $stmt_venda = $conn->prepare("DELETE FROM vendas WHERE id_venda = ?");
            if (!$stmt_venda) throw new Exception('Erro ao preparar statement: '.$conn->error);
            $stmt_venda->bind_param('i', $pedido_id);
            if (!$stmt_venda->execute()) throw new Exception('Erro ao deletar venda: '.$stmt_venda->error);
            $stmt_venda->close();
        }

        $conn->commit();
        ok(['mensagem' => 'Venda deletada com sucesso']);
    } catch (Exception $e) {
        $conn->rollback();
        error($e->getMessage());
    }
}

if ($method==='POST' && $action==='deletar_caixa'){
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) error('Dados inválidos');
    
    $sessao_id = (int)($data['sessao_id'] ?? 0);
    
    if ($sessao_id <= 0) {
        error('ID da sessão não informado');
    }
    
    $conn->begin_transaction();
    try {
        // Remover movimentações contábeis vinculadas às vendas da sessão
        $stmt_mov = $conn->prepare("DELETE m FROM movimentacoes m LEFT JOIN vendas v ON m.venda_id = v.id_venda WHERE m.sessao_caixa_id = ? OR v.sessao_caixa_id = ?");
        if ($stmt_mov) {
            $stmt_mov->bind_param('ii', $sessao_id, $sessao_id);
            $stmt_mov->execute();
            $stmt_mov->close();
        }

        // Remover vínculo com eventos financeiros (evita erro de FK)
        $stmt_ev = $conn->prepare("DELETE FROM financeiro_evento_caixas WHERE sessao_id = ?");
        if ($stmt_ev) {
            $stmt_ev->bind_param('i', $sessao_id);
            $stmt_ev->execute();
            $stmt_ev->close();
        }

        // Deletar itens das vendas associadas primeiro
        $stmt_itens = $conn->prepare("DELETE iv FROM itens_venda iv INNER JOIN vendas v ON iv.id_venda = v.id_venda WHERE v.sessao_caixa_id = ?");
        if ($stmt_itens) {
            $stmt_itens->bind_param('i', $sessao_id);
            $stmt_itens->execute();
            $stmt_itens->close();
        }

        // Deletar vendas associadas
        $stmt_vendas = $conn->prepare("DELETE FROM vendas WHERE sessao_caixa_id = ?");
        if ($stmt_vendas) {
            $stmt_vendas->bind_param('i', $sessao_id);
            $stmt_vendas->execute();
            $stmt_vendas->close();
        }
        
        // Deletar sessão de caixa
        $stmt_caixa = $conn->prepare("DELETE FROM caixa_sessoes WHERE id_sessao = ?");
        if (!$stmt_caixa) throw new Exception('Erro ao preparar statement: '.$conn->error);
        
        $stmt_caixa->bind_param('i', $sessao_id);
        if (!$stmt_caixa->execute()) {
            throw new Exception('Erro ao deletar sessão: '.$stmt_caixa->error);
        }
        $stmt_caixa->close();

        $conn->commit();
        ok(['mensagem' => 'Sessão de caixa deletada com sucesso']);
    } catch (Exception $e) {
        $conn->rollback();
        error($e->getMessage());
    }
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
            $stmtDelMov = $conn->prepare("DELETE m FROM movimentacoes m LEFT JOIN vendas v ON m.venda_id = v.id_venda WHERE m.sessao_caixa_id = ? OR v.sessao_caixa_id = ?");
            $stmtDelEv = $conn->prepare("DELETE FROM financeiro_evento_caixas WHERE sessao_id = ?");
            $stmtDelItens = $conn->prepare("DELETE iv FROM itens_venda iv INNER JOIN vendas v ON iv.id_venda = v.id_venda WHERE v.sessao_caixa_id = ?");
            $stmtDelVendas = $conn->prepare("DELETE FROM vendas WHERE sessao_caixa_id = ?");
            if (!$stmtDelVendas) {
                throw new Exception('Erro ao preparar exclusão de vendas: '.$conn->error);
            }

            $stmtDelCaixa = $conn->prepare("DELETE FROM caixa_sessoes WHERE id_sessao = ?");
            if (!$stmtDelCaixa) {
                throw new Exception('Erro ao preparar exclusão de sessões: '.$conn->error);
            }

            foreach ($sessoes as $sessaoId) {
                if ($stmtDelMov) {
                    $stmtDelMov->bind_param('ii', $sessaoId, $sessaoId);
                    $stmtDelMov->execute();
                }
                if ($stmtDelEv) {
                    $stmtDelEv->bind_param('i', $sessaoId);
                    $stmtDelEv->execute();
                }
                if ($stmtDelItens) {
                    $stmtDelItens->bind_param('i', $sessaoId);
                    $stmtDelItens->execute();
                }

                $stmtDelVendas->bind_param('i', $sessaoId);
                if (!$stmtDelVendas->execute()) {
                    throw new Exception('Erro ao deletar vendas da sessão '.$sessaoId.': '.$stmtDelVendas->error);
                }

                $stmtDelCaixa->bind_param('i', $sessaoId);
                if (!$stmtDelCaixa->execute()) {
                    throw new Exception('Erro ao deletar sessão '.$sessaoId.': '.$stmtDelCaixa->error);
                }
            }

            if ($stmtDelMov) $stmtDelMov->close();
            if ($stmtDelEv) $stmtDelEv->close();
            if ($stmtDelItens) $stmtDelItens->close();
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
        fe.id as evento_id,
        fe.nome as evento_nome,
        cs.abertura_caixa,
        cs.fechamento_caixa,
        cs.valor_abertura,
        cs.valor_fechamento,
        cs.observacoes,
        cs.status,
        COALESCE(SUM(COALESCE(v.valor_total, 0)), 0) as total_vendas
    FROM caixa_sessoes cs
    LEFT JOIN financeiro_evento_caixas ec ON ec.sessao_id = cs.id_sessao
    LEFT JOIN financeiro_eventos fe ON fe.id = ec.evento_id
    LEFT JOIN vendas v ON v.sessao_caixa_id = cs.id_sessao
    $whereSql
    GROUP BY cs.id_sessao, cs.id_usuario, cs.login_usuario, fe.id, fe.nome, cs.abertura_caixa, cs.fechamento_caixa, cs.valor_abertura, cs.valor_fechamento, cs.observacoes, cs.status
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
            'evento_id' => $row['evento_id'] ? (int)$row['evento_id'] : null,
            'evento_nome' => $row['evento_nome'] ?? null,
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
