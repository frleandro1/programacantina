<?php
/**
 * Database.php - Funções CRUD para Programa Cantina
 * 
 * Este arquivo contém todas as funções para manipular dados no banco de dados
 * Implementa operações Create, Read, Update e Delete (CRUD)
 */

require_once 'config.php';

// ============================================================================
// FUNÇÕES PARA PRODUTOS
// ============================================================================

/**
 * Obter todos os produtos
 * 
 * @param string $status - Filtrar por status ('ativo', 'inativo' ou vazio para todos)
 * @return array - Array com todos os produtos
 */
function obterProdutos($status = '') {
    global $conn;
    
    $sql = "SELECT * FROM produtos";
    
    if (!empty($status)) {
        $sql .= " WHERE status = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $result = $conn->query($sql);
    }
    
    $produtos = [];
    while ($row = $result->fetch_assoc()) {
        $produtos[] = $row;
    }
    
    return $produtos;
}

/**
 * Obter um produto específico pelo ID
 * 
 * @param int $id - ID do produto
 * @return array|null - Dados do produto ou null se não encontrado
 */
function obterProdutoPorId($id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM produtos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    return $result->fetch_assoc();
}

/**
 * Criar novo produto
 * 
 * @param string $nome - Nome do produto
 * @param string $categoria - Categoria
 * @param float $preco_venda - Preço de venda
 * @param float $preco_custo - Preço de custo
 * @return array - ['success' => bool, 'id' => int|null, 'error' => string|null]
 */
function criarProduto($nome, $categoria, $preco_venda, $preco_custo = null) {
    global $conn;
    
    // Validar entrada
    if (empty($nome) || empty($categoria) || empty($preco_venda)) {
        return ['success' => false, 'error' => 'Campos obrigatórios não preenchidos'];
    }
    
    // Verificar se produto já existe
    $check_stmt = $conn->prepare("SELECT id FROM produtos WHERE nome = ?");
    $check_stmt->bind_param("s", $nome);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $check_stmt->close();
        return ['success' => false, 'error' => 'Produto já existe'];
    }
    $check_stmt->close();
    
    // Inserir produto
    $stmt = $conn->prepare("INSERT INTO produtos (nome, categoria, preco_venda, preco_custo) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssdd", $nome, $categoria, $preco_venda, $preco_custo);
    
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        $stmt->close();
        return ['success' => true, 'id' => $id];
    } else {
        $stmt->close();
        return ['success' => false, 'error' => 'Erro ao criar produto: ' . $conn->error];
    }
}

/**
 * Atualizar produto
 * 
 * @param int $id - ID do produto
 * @param array $dados - Dados a atualizar
 * @return array - ['success' => bool, 'error' => string|null]
 */
function atualizarProduto($id, $dados) {
    global $conn;
    
    $campos = [];
    $valores = [];
    $tipos = '';
    
    // Construir query dinamicamente
    if (isset($dados['nome'])) {
        $campos[] = 'nome = ?';
        $valores[] = $dados['nome'];
        $tipos .= 's';
    }
    
    if (isset($dados['categoria'])) {
        $campos[] = 'categoria = ?';
        $valores[] = $dados['categoria'];
        $tipos .= 's';
    }
    
    if (isset($dados['preco_venda'])) {
        $campos[] = 'preco_venda = ?';
        $valores[] = $dados['preco_venda'];
        $tipos .= 'd';
    }
    
    if (isset($dados['preco_custo'])) {
        $campos[] = 'preco_custo = ?';
        $valores[] = $dados['preco_custo'];
        $tipos .= 'd';
    }
    
    if (isset($dados['estoque'])) {
        $campos[] = 'estoque = ?';
        $valores[] = $dados['estoque'];
        $tipos .= 'i';
    }
    
    if (isset($dados['status'])) {
        $campos[] = 'status = ?';
        $valores[] = $dados['status'];
        $tipos .= 's';
    }
    
    if (empty($campos)) {
        return ['success' => false, 'error' => 'Nenhum campo para atualizar'];
    }
    
    // Adicionar ID no final
    $valores[] = $id;
    $tipos .= 'i';
    
    $sql = "UPDATE produtos SET " . implode(', ', $campos) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($tipos, ...$valores);
    
    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true];
    } else {
        $stmt->close();
        return ['success' => false, 'error' => 'Erro ao atualizar produto: ' . $conn->error];
    }
}

/**
 * Deletar produto
 * 
 * @param int $id - ID do produto
 * @return array - ['success' => bool, 'error' => string|null]
 */
function deletarProduto($id) {
    global $conn;
    
    $stmt = $conn->prepare("DELETE FROM produtos WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true];
    } else {
        $stmt->close();
        return ['success' => false, 'error' => 'Erro ao deletar produto: ' . $conn->error];
    }
}

// ============================================================================
// FUNÇÕES PARA VENDAS
// ============================================================================

/**
 * Criar nova venda
 * 
 * @param string $atendente - Nome do atendente
 * @param float $total - Total da venda
 * @param array $itens - Array com itens da venda
 * @return array - ['success' => bool, 'id' => int|null, 'error' => string|null]
 */
function criarVenda($atendente, $total, $itens = []) {
    global $conn;
    
    // Validar entrada
    if (empty($atendente) || empty($total)) {
        return ['success' => false, 'error' => 'Dados obrigatórios não preenchidos'];
    }
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // Inserir venda
        $stmt = $conn->prepare("INSERT INTO vendas (atendente, total) VALUES (?, ?)");
        $stmt->bind_param("sd", $atendente, $total);
        $stmt->execute();
        $venda_id = $stmt->insert_id;
        $stmt->close();
        
        // Inserir itens da venda
        if (!empty($itens)) {
            $stmt_item = $conn->prepare("INSERT INTO itens_venda (venda_id, produto_id, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($itens as $item) {
                $stmt_item->bind_param("iiidd", 
                    $venda_id, 
                    $item['produto_id'], 
                    $item['quantidade'], 
                    $item['preco_unitario'], 
                    $item['subtotal']
                );
                $stmt_item->execute();
                
                // Atualizar estoque do produto
                $nova_quantidade = $item['quantidade'];
                $stmt_estoque = $conn->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?");
                $stmt_estoque->bind_param("ii", $nova_quantidade, $item['produto_id']);
                $stmt_estoque->execute();
                $stmt_estoque->close();
            }
            $stmt_item->close();
        }
        
        // Confirmar transação
        $conn->commit();
        
        return ['success' => true, 'id' => $venda_id];
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $conn->rollback();
        return ['success' => false, 'error' => 'Erro ao criar venda: ' . $e->getMessage()];
    }
}

/**
 * Obter todas as vendas
 * 
 * @param int $limite - Número de registros a retornar
 * @param int $offset - Número de registros a pular
 * @return array - Array com vendas
 */
function obterVendas($limite = 100, $offset = 0) {
    global $conn;
    
    $sql = "SELECT * FROM vendas ORDER BY data_venda DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limite, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $vendas = [];
    while ($row = $result->fetch_assoc()) {
        $vendas[] = $row;
    }
    
    return $vendas;
}

/**
 * Obter venda específica com seus itens
 * 
 * @param int $id - ID da venda
 * @return array|null - Dados da venda com itens
 */
function obterVendaPorId($id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM vendas WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $venda = $result->fetch_assoc();
    
    if (!$venda) {
        return null;
    }
    
    // Obter itens da venda
    $stmt_itens = $conn->prepare("
        SELECT iv.*, p.nome as produto_nome 
        FROM itens_venda iv 
        JOIN produtos p ON iv.produto_id = p.id 
        WHERE iv.venda_id = ?
    ");
    $stmt_itens->bind_param("i", $id);
    $stmt_itens->execute();
    $result_itens = $stmt_itens->get_result();
    $stmt_itens->close();
    
    $venda['itens'] = [];
    while ($item = $result_itens->fetch_assoc()) {
        $venda['itens'][] = $item;
    }
    
    return $venda;
}

// ============================================================================
// FUNÇÕES PARA MOVIMENTAÇÕES (PRODUÇÃO)
// ============================================================================

/**
 * Registrar movimentação (entrada/saída de produto)
 * 
 * @param int $produto_id - ID do produto
 * @param string $tipo - Tipo de movimentação ('entrada' ou 'saida')
 * @param int $quantidade - Quantidade
 * @param string $responsavel - Responsável pela movimentação
 * @param string $motivo - Motivo da movimentação
 * @return array - ['success' => bool, 'id' => int|null, 'error' => string|null]
 */
function registrarMovimentacao($produto_id, $tipo, $quantidade, $responsavel = '', $motivo = '') {
    global $conn;
    
    // Validar entrada
    if (empty($produto_id) || empty($tipo) || empty($quantidade)) {
        return ['success' => false, 'error' => 'Dados obrigatórios não preenchidos'];
    }
    
    if (!in_array($tipo, ['entrada', 'saida'])) {
        return ['success' => false, 'error' => 'Tipo de movimentação inválido'];
    }
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // Inserir movimentação
        $stmt = $conn->prepare("INSERT INTO movimentacoes (produto_id, tipo, quantidade, responsavel, motivo) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isiss", $produto_id, $tipo, $quantidade, $responsavel, $motivo);
        $stmt->execute();
        $movimentacao_id = $stmt->insert_id;
        $stmt->close();
        
        // Atualizar estoque do produto
        if ($tipo === 'entrada') {
            $stmt_estoque = $conn->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ?");
        } else {
            $stmt_estoque = $conn->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?");
        }
        
        $stmt_estoque->bind_param("ii", $quantidade, $produto_id);
        $stmt_estoque->execute();
        $stmt_estoque->close();
        
        // Confirmar transação
        $conn->commit();
        
        return ['success' => true, 'id' => $movimentacao_id];
    } catch (Exception $e) {
        // Reverter transação em caso de erro
        $conn->rollback();
        return ['success' => false, 'error' => 'Erro ao registrar movimentação: ' . $e->getMessage()];
    }
}

/**
 * Obter todas as movimentações
 * 
 * @param int $limite - Número de registros a retornar
 * @param int $offset - Número de registros a pular
 * @return array - Array com movimentações
 */
function obterMovimentacoes($limite = 100, $offset = 0) {
    global $conn;
    
    $sql = "
        SELECT m.*, p.nome as produto_nome, p.categoria 
        FROM movimentacoes m 
        JOIN produtos p ON m.produto_id = p.id 
        ORDER BY m.data_movimentacao DESC 
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limite, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $movimentacoes = [];
    while ($row = $result->fetch_assoc()) {
        $movimentacoes[] = $row;
    }
    
    return $movimentacoes;
}

/**
 * Obter movimentações de um produto específico
 * 
 * @param int $produto_id - ID do produto
 * @param int $limite - Número de registros a retornar
 * @return array - Array com movimentações
 */
function obterMovimentacoesPorProduto($produto_id, $limite = 50) {
    global $conn;
    
    $sql = "SELECT * FROM movimentacoes WHERE produto_id = ? ORDER BY data_movimentacao DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $produto_id, $limite);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $movimentacoes = [];
    while ($row = $result->fetch_assoc()) {
        $movimentacoes[] = $row;
    }
    
    return $movimentacoes;
}

// ============================================================================
// FUNÇÕES PARA ESTATÍSTICAS
// ============================================================================

/**
 * Obter estatísticas gerais
 * 
 * @return array - Array com estatísticas
 */
function obterEstatisticas() {
    global $conn;
    
    $stats = [];
    
    // Total de vendas
    $result = $conn->query("SELECT COUNT(*) as total FROM vendas");
    $row = $result->fetch_assoc();
    $stats['total_vendas'] = $row['total'];
    
    // Receita total
    $result = $conn->query("SELECT SUM(total) as receita FROM vendas");
    $row = $result->fetch_assoc();
    $stats['receita_total'] = $row['receita'] ?? 0;
    
    // Estoque total
    $result = $conn->query("SELECT SUM(estoque) as total FROM produtos WHERE status = 'ativo'");
    $row = $result->fetch_assoc();
    $stats['estoque_total'] = $row['total'] ?? 0;
    
    // Total de produtos
    $result = $conn->query("SELECT COUNT(*) as total FROM produtos WHERE status = 'ativo'");
    $row = $result->fetch_assoc();
    $stats['produtos_total'] = $row['total'];
    
    // Ticket médio
    if ($stats['total_vendas'] > 0) {
        $stats['ticket_medio'] = $stats['receita_total'] / $stats['total_vendas'];
    } else {
        $stats['ticket_medio'] = 0;
    }
    
    return $stats;
}

/**
 * Obter estatísticas de vendas por período
 * 
 * @param string $data_inicio - Data inicial (YYYY-MM-DD)
 * @param string $data_fim - Data final (YYYY-MM-DD)
 * @return array - Array com estatísticas
 */
function obterEstatisticasVendasPeriodo($data_inicio, $data_fim) {
    global $conn;
    
    $sql = "
        SELECT 
            DATE(data_venda) as data,
            COUNT(*) as num_vendas,
            SUM(total) as receita,
            AVG(total) as ticket_medio
        FROM vendas
        WHERE DATE(data_venda) BETWEEN ? AND ?
        GROUP BY DATE(data_venda)
        ORDER BY data DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $data_inicio, $data_fim);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $stats = [];
    while ($row = $result->fetch_assoc()) {
        $stats[] = $row;
    }
    
    return $stats;
}

/**
 * Obter produtos com baixo estoque
 * 
 * @param int $minimo - Quantidade mínima de estoque
 * @return array - Array com produtos
 */
function obterProdutosBaixoEstoque($minimo = 10) {
    global $conn;
    
    $sql = "SELECT * FROM produtos WHERE estoque <= ? AND status = 'ativo' ORDER BY estoque ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $minimo);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $produtos = [];
    while ($row = $result->fetch_assoc()) {
        $produtos[] = $row;
    }
    
    return $produtos;
}

/**
 * Obter produtos mais vendidos
 * 
 * @param int $limite - Número de produtos a retornar
 * @return array - Array com produtos
 */
function obterProdutosMaisVendidos($limite = 10) {
    global $conn;
    
    $sql = "
        SELECT 
            p.id,
            p.nome,
            p.categoria,
            p.preco_venda,
            SUM(iv.quantidade) as total_vendido,
            SUM(iv.subtotal) as receita_total
        FROM itens_venda iv
        JOIN produtos p ON iv.produto_id = p.id
        GROUP BY p.id, p.nome, p.categoria, p.preco_venda
        ORDER BY total_vendido DESC
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limite);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    $produtos = [];
    while ($row = $result->fetch_assoc()) {
        $produtos[] = $row;
    }
    
    return $produtos;
}

?>
