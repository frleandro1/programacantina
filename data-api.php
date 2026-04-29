<?php
/**
 * Data-API.php - API REST para Persistência de Dados
 * 
 * Endpoints para operações CRUD de produtos, vendas e movimentações
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'database.php';
require_once 'permissions.php';

// Verificar se usuário está autenticado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_nome'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

header('Content-Type: application/json');

// Obter ação da requisição
$action = $_GET['action'] ?? '';

// ============================================================================
// ENDPOINTS PARA PRODUTOS
// ============================================================================

if ($action === 'produtos_listar') {
    // Verificar permissão
    if (!temPermissao('acessar_estoque')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
    
    $status = $_GET['status'] ?? '';
    $produtos = obterProdutos($status);
    echo json_encode(['success' => true, 'produtos' => $produtos]);
    exit;
}

if ($action === 'produto_obter') {
    // Verificar permissão
    if (!temPermissao('acessar_estoque')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
    
    $id = $_GET['id'] ?? 0;
    $produto = obterProdutoPorId($id);
    
    if ($produto) {
        echo json_encode(['success' => true, 'produto' => $produto]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Produto não encontrado']);
    }
    exit;
}

if ($action === 'produto_criar') {
    // Verificar permissão
    if (!temPermissao('gerenciar_produtos')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $resultado = criarProduto(
        $data['nome'] ?? '',
        $data['categoria'] ?? '',
        $data['preco_venda'] ?? 0,
        $data['preco_custo'] ?? null
    );
    
    echo json_encode($resultado);
    exit;
}

if ($action === 'produto_atualizar') {
    // Verificar permissão
    if (!temPermissao('gerenciar_produtos')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    
    $resultado = atualizarProduto($id, $data);
    echo json_encode($resultado);
    exit;
}

if ($action === 'produto_deletar') {
    // Verificar permissão
    if (!temPermissao('gerenciar_produtos')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
    
    $id = $_GET['id'] ?? 0;
    $resultado = deletarProduto($id);
    echo json_encode($resultado);
    exit;
}

// ============================================================================
// ENDPOINTS PARA VENDAS
// ============================================================================

if ($action === 'vendas_listar') {
    // Verificar permissão
    if (!temPermissao('ver_vendas')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
    
    $limite = $_GET['limite'] ?? 100;
    $offset = $_GET['offset'] ?? 0;
    
    $vendas = obterVendas($limite, $offset);
    echo json_encode(['success' => true, 'vendas' => $vendas]);
    exit;
}

if ($action === 'venda_obter') {
    // Verificar permissão
    if (!temPermissao('ver_vendas')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
    
    $id = $_GET['id'] ?? 0;
    $venda = obterVendaPorId($id);
    
    if ($venda) {
        echo json_encode(['success' => true, 'venda' => $venda]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Venda não encontrada']);
    }
    exit;
}

if ($action === 'venda_criar') {
    // Verificar permissão
    if (!temPermissao('registrar_venda')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $resultado = criarVenda(
        $data['atendente'] ?? '',
        $data['total'] ?? 0,
        $data['itens'] ?? []
    );
    
    echo json_encode($resultado);
    exit;
}

// ============================================================================
// ENDPOINTS PARA MOVIMENTAÇÕES
// ============================================================================

if ($action === 'movimentacoes_listar') {
    // Verificar permissão
    if (!temPermissao('ver_producao')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
    
    $limite = $_GET['limite'] ?? 100;
    $offset = $_GET['offset'] ?? 0;
    
    $movimentacoes = obterMovimentacoes($limite, $offset);
    echo json_encode(['success' => true, 'movimentacoes' => $movimentacoes]);
    exit;
}

if ($action === 'movimentacao_registrar') {
    // Verificar permissão
    if (!temPermissao('registrar_producao')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $resultado = registrarMovimentacao(
        $data['produto_id'] ?? 0,
        $data['tipo'] ?? '',
        $data['quantidade'] ?? 0,
        $data['responsavel'] ?? '',
        $data['motivo'] ?? ''
    );
    
    echo json_encode($resultado);
    exit;
}

if ($action === 'movimentacoes_produto') {
    // Verificar permissão
    if (!temPermissao('ver_producao')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
    
    $produto_id = $_GET['produto_id'] ?? 0;
    $limite = $_GET['limite'] ?? 50;
    
    $movimentacoes = obterMovimentacoesPorProduto($produto_id, $limite);
    echo json_encode(['success' => true, 'movimentacoes' => $movimentacoes]);
    exit;
}

// ============================================================================
// ENDPOINTS PARA ESTATÍSTICAS
// ============================================================================

if ($action === 'estatisticas') {
    // Verificar permissão
    if (!temPermissao('ver_visao_geral')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
    
    $stats = obterEstatisticas();
    echo json_encode(['success' => true, 'estatisticas' => $stats]);
    exit;
}

if ($action === 'estatisticas_vendas_periodo') {
    // Verificar permissão
    if (!temPermissao('ver_relatorios')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
    
    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
    $data_fim = $_GET['data_fim'] ?? date('Y-m-d');
    
    $stats = obterEstatisticasVendasPeriodo($data_inicio, $data_fim);
    echo json_encode(['success' => true, 'estatisticas' => $stats]);
    exit;
}

if ($action === 'produtos_baixo_estoque') {
    // Verificar permissão
    if (!temPermissao('acessar_estoque')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
    
    $minimo = $_GET['minimo'] ?? 10;
    $produtos = obterProdutosBaixoEstoque($minimo);
    echo json_encode(['success' => true, 'produtos' => $produtos]);
    exit;
}

if ($action === 'produtos_mais_vendidos') {
    // Verificar permissão
    if (!temPermissao('ver_relatorios')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado']);
        exit;
    }
    
    $limite = $_GET['limite'] ?? 10;
    $produtos = obterProdutosMaisVendidos($limite);
    echo json_encode(['success' => true, 'produtos' => $produtos]);
    exit;
}

// ============================================================================
// RESPOSTA PADRÃO PARA AÇÃO NÃO ENCONTRADA
// ============================================================================

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Ação não encontrada']);
?>
