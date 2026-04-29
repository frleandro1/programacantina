<?php
header('Content-Type: application/json');
require_once 'config.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Funções de Produtos
function getProdutos() {
    global $conn;
    $result = $conn->query("SELECT * FROM produtos WHERE status = 'ativo' ORDER BY nome");
    $produtos = [];
    while ($row = $result->fetch_assoc()) {
        $produtos[] = $row;
    }
    return $produtos;
}

function getProdutoById($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM produtos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function adicionarProduto($nome, $categoria, $preco_venda, $preco_custo) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO produtos (nome, categoria, preco_venda, preco_custo) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssdd", $nome, $categoria, $preco_venda, $preco_custo);
    
    if ($stmt->execute()) {
        return ['success' => true, 'id' => $conn->insert_id];
    } else {
        return ['success' => false, 'error' => $conn->error];
    }
}

// Funções de Movimentações
function registrarMovimentacao($produto_id, $tipo, $quantidade, $responsavel, $motivo = null) {
    global $conn;
    
    // Verificar estoque
    $produto = getProdutoById($produto_id);
    if (!$produto) {
        return ['success' => false, 'error' => 'Produto não encontrado'];
    }
    
    if ($tipo === 'saida' && $produto['estoque'] < $quantidade) {
        return ['success' => false, 'error' => 'Estoque insuficiente'];
    }
    
    // Registrar movimentação
    $stmt = $conn->prepare("INSERT INTO movimentacoes (produto_id, tipo, quantidade, motivo, responsavel) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isiss", $produto_id, $tipo, $quantidade, $motivo, $responsavel);
    
    if ($stmt->execute()) {
        // Atualizar estoque
        if ($tipo === 'entrada') {
            $novo_estoque = $produto['estoque'] + $quantidade;
        } else {
            $novo_estoque = $produto['estoque'] - $quantidade;
        }
        
        $update_stmt = $conn->prepare("UPDATE produtos SET estoque = ? WHERE id = ?");
        $update_stmt->bind_param("ii", $novo_estoque, $produto_id);
        $update_stmt->execute();
        
        return ['success' => true, 'novo_estoque' => $novo_estoque];
    } else {
        return ['success' => false, 'error' => $conn->error];
    }
}

function getMovimentacoes($limite = 50) {
    global $conn;
    $result = $conn->query("
        SELECT m.*, p.nome as produto_nome 
        FROM movimentacoes m 
        JOIN produtos p ON m.produto_id = p.id 
        ORDER BY m.data_movimentacao DESC 
        LIMIT $limite
    ");
    $movimentacoes = [];
    while ($row = $result->fetch_assoc()) {
        $movimentacoes[] = $row;
    }
    return $movimentacoes;
}

// Funções de Vendas
function registrarVenda($atendente, $itens) {
    global $conn;
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        $total = 0;
        
        // Calcular total
        foreach ($itens as $item) {
            $total += $item['quantidade'] * $item['preco'];
        }
        
        // Inserir venda
        $stmt = $conn->prepare("INSERT INTO vendas (atendente, total) VALUES (?, ?)");
        $stmt->bind_param("sd", $atendente, $total);
        $stmt->execute();
        $venda_id = $conn->insert_id();
        
        // Inserir itens da venda
        foreach ($itens as $item) {
            $subtotal = $item['quantidade'] * $item['preco'];
            
            // Inserir item
            $stmt = $conn->prepare("INSERT INTO itens_venda (venda_id, produto_id, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiidd", $venda_id, $item['produto_id'], $item['quantidade'], $item['preco'], $subtotal);
            $stmt->execute();
            
            // Atualizar estoque
            $produto = getProdutoById($item['produto_id']);
            $novo_estoque = $produto['estoque'] - $item['quantidade'];
            
            $update_stmt = $conn->prepare("UPDATE produtos SET estoque = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $novo_estoque, $item['produto_id']);
            $update_stmt->execute();
            
            // Registrar movimentação
            $tipo = 'saida';
            $motivo = 'Venda';
            $mov_stmt = $conn->prepare("INSERT INTO movimentacoes (produto_id, tipo, quantidade, motivo, responsavel) VALUES (?, ?, ?, ?, ?)");
            $mov_stmt->bind_param("isiss", $item['produto_id'], $tipo, $item['quantidade'], $motivo, $atendente);
            $mov_stmt->execute();
        }
        
        $conn->commit();
        return ['success' => true, 'venda_id' => $venda_id, 'total' => $total];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getVendas($limite = 50) {
    global $conn;
    $result = $conn->query("
        SELECT * FROM vendas 
        ORDER BY data_venda DESC 
        LIMIT $limite
    ");
    $vendas = [];
    while ($row = $result->fetch_assoc()) {
        $row['itens'] = getItensVenda($row['id']);
        $vendas[] = $row;
    }
    return $vendas;
}

function getItensVenda($venda_id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT iv.*, p.nome as produto_nome 
        FROM itens_venda iv 
        JOIN produtos p ON iv.produto_id = p.id 
        WHERE iv.venda_id = ?
    ");
    $stmt->bind_param("i", $venda_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $itens = [];
    while ($row = $result->fetch_assoc()) {
        $itens[] = $row;
    }
    return $itens;
}

function getEstatisticas() {
    global $conn;
    
    $total_vendas = $conn->query("SELECT COUNT(*) as count FROM vendas")->fetch_assoc()['count'];
    $receita_total = $conn->query("SELECT SUM(total) as total FROM vendas")->fetch_assoc()['total'] ?? 0;
    $estoque_total = $conn->query("SELECT SUM(estoque) as total FROM produtos")->fetch_assoc()['total'] ?? 0;
    $produtos_total = $conn->query("SELECT COUNT(*) as count FROM produtos WHERE status = 'ativo'")->fetch_assoc()['count'];
    
    return [
        'total_vendas' => $total_vendas,
        'receita_total' => round($receita_total, 2),
        'estoque_total' => $estoque_total,
        'produtos_total' => $produtos_total,
        'ticket_medio' => $total_vendas > 0 ? round($receita_total / $total_vendas, 2) : 0
    ];
}

// Processar requisições
if ($method === 'GET') {
    switch ($action) {
        case 'produtos':
            echo json_encode(getProdutos());
            break;
        case 'movimentacoes':
            echo json_encode(getMovimentacoes());
            break;
        case 'vendas':
            echo json_encode(getVendas());
            break;
        case 'estatisticas':
            echo json_encode(getEstatisticas());
            break;
        default:
            echo json_encode(['error' => 'Ação não encontrada']);
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'registrar_producao':
            $resultado = registrarMovimentacao(
                $data['produto_id'],
                'entrada',
                $data['quantidade'],
                $data['responsavel'],
                'Produção'
            );
            echo json_encode($resultado);
            break;
        case 'registrar_venda':
            $resultado = registrarVenda($data['atendente'], $data['itens']);
            echo json_encode($resultado);
            break;
        case 'adicionar_produto':
            $resultado = adicionarProduto(
                $data['nome'],
                $data['categoria'],
                $data['preco_venda'],
                $data['preco_custo']
            );
            echo json_encode($resultado);
            break;
        default:
            echo json_encode(['error' => 'Ação não encontrada']);
    }
}
?>
