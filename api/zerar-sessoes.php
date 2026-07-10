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

// Verificar se é admin
if (!isset($_SESSION['id_usuario']) && !isset($GLOBALS['token_data'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode(['sucesso' => false, 'mensagem' => 'Não autorizado']));
}

if ($method === 'POST' && $action === 'zerar_tudo') {
    try {
        $conn->begin_transaction();
        
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
        
        // Resetar o AUTO_INCREMENT para começar do 1
        $sqlResetSessoes = "ALTER TABLE caixa_sessoes AUTO_INCREMENT = 1";
        $conn->query($sqlResetSessoes);
        
        $sqlResetVendas = "ALTER TABLE vendas AUTO_INCREMENT = 1";
        $conn->query($sqlResetVendas);
        
        $conn->commit();
        
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
        
        // Deletar vendas de sessões abertas
        $sqlVendas = "DELETE FROM vendas WHERE sessao_caixa_id IN (SELECT id_sessao FROM caixa_sessoes WHERE status = 'aberto')";
        $conn->query($sqlVendas);
        
        // Deletar apenas sessões abertas
        $sqlSessoes = "DELETE FROM caixa_sessoes WHERE status = 'aberto'";
        if (!$conn->query($sqlSessoes)) {
            throw new Exception('Erro ao deletar sessões abertas: ' . $conn->error);
        }
        
        // Resetar o AUTO_INCREMENT para começar do 1
        $sqlResetSessoes = "ALTER TABLE caixa_sessoes AUTO_INCREMENT = 1";
        $conn->query($sqlResetSessoes);
        
        $conn->commit();
        
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
