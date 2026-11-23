<?php
/**
 * Endpoint para ZERAR (deletar) todas as sessões de caixa
 * 
 * Uso:
 *   POST /api/zerar-sessoes.php?action=zerar_tudo
 *   POST /api/zerar-sessoes.php?action=zerar_abertas (apenas sessões abertas)
 */

require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/helpers.php';

session_start();
require_once __DIR__ . '/../db/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Verificar se está autenticado como administrador
if (
    (!isset($_SESSION['usuario_id']) && !isset($_SESSION['id_usuario']) && !isset($GLOBALS['token_data']))
    || (isset($_SESSION['tipo']) && $_SESSION['tipo'] !== 'admin')
) {
    header('HTTP/1.1 403 Forbidden');
    exit(json_encode(['sucesso' => false, 'mensagem' => 'Acesso restrito a administradores']));
}

if ($method === 'POST' && $action === 'zerar_tudo') {
    try {
        $conn->begin_transaction();

        // Remover movimentações vinculadas a vendas/sessões e vínculos com financeiro
        @$conn->query("DELETE FROM movimentacoes WHERE venda_id IS NOT NULL OR sessao_caixa_id IS NOT NULL");
        @$conn->query("DELETE FROM financeiro_evento_caixas");

        // Deletar itens de venda primeiro
        $conn->query("DELETE FROM itens_venda");

        // Deletar todas as vendas
        $sqlVendas = "DELETE FROM vendas";
        if (!$conn->query($sqlVendas)) {
            throw new Exception('Erro ao deletar vendas: ' . $conn->error);
        }

        // Deletar todas as sessões de caixa
        $sqlSessoes = "DELETE FROM caixa_sessoes";
        if (!$conn->query($sqlSessoes)) {
            throw new Exception('Erro ao deletar sessões: ' . $conn->error);
        }

        $conn->commit();

        // Resetar AUTO_INCREMENT fora da transação (DDL faz commit implícito no MySQL)
        @$conn->query("ALTER TABLE caixa_sessoes AUTO_INCREMENT = 1");
        @$conn->query("ALTER TABLE vendas AUTO_INCREMENT = 1");

        header('Content-Type: application/json');
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Todas as sessões e vendas foram zeradas com sucesso! ID resetado para 1.'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');
        echo json_encode([
            'sucesso' => false,
            'mensagem' => $e->getMessage()
        ]);
    }
    exit;
}

if ($method === 'POST' && $action === 'zerar_abertas') {
    try {
        $conn->begin_transaction();

        // Remover movimentações e vínculos das sessões abertas
        @$conn->query("DELETE m FROM movimentacoes m LEFT JOIN vendas v ON m.venda_id = v.id_venda INNER JOIN caixa_sessoes cs ON COALESCE(m.sessao_caixa_id, v.sessao_caixa_id) = cs.id_sessao WHERE cs.status = 'aberto'");
        @$conn->query("DELETE ec FROM financeiro_evento_caixas ec INNER JOIN caixa_sessoes cs ON ec.sessao_id = cs.id_sessao WHERE cs.status = 'aberto'");

        // Deletar itens das vendas de sessões abertas
        $conn->query("DELETE iv FROM itens_venda iv INNER JOIN vendas v ON iv.id_venda = v.id_venda INNER JOIN caixa_sessoes cs ON v.sessao_caixa_id = cs.id_sessao WHERE cs.status = 'aberto'");

        // Deletar vendas de sessões abertas
        $sqlVendas = "DELETE FROM vendas WHERE sessao_caixa_id IN (SELECT id_sessao FROM caixa_sessoes WHERE status = 'aberto')";
        $conn->query($sqlVendas);

        // Deletar apenas sessões abertas
        $sqlSessoes = "DELETE FROM caixa_sessoes WHERE status = 'aberto'";
        if (!$conn->query($sqlSessoes)) {
            throw new Exception('Erro ao deletar sessões abertas: ' . $conn->error);
        }

        $conn->commit();

        // Resetar AUTO_INCREMENT fora da transação
        @$conn->query("ALTER TABLE caixa_sessoes AUTO_INCREMENT = 1");

        header('Content-Type: application/json');
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Todas as sessões abertas foram zeradas com sucesso! ID resetado para 1.'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');
        echo json_encode([
            'sucesso' => false,
            'mensagem' => $e->getMessage()
        ]);
    }
    exit;
}

if ($method === 'GET' && $action === 'status') {
    $result = $conn->query("SELECT COUNT(*) as total FROM caixa_sessoes");
    $row = $result->fetch_assoc();
    
    header('Content-Type: application/json');
    echo json_encode([
        'total_sessoes' => (int)$row['total']
    ]);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['erro' => 'Action não reconhecida']);
?>
