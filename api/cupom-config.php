<?php
// API para salvar configurações de cupom
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db/config.php';

function responder($sucesso, $msg, $dados = null) {
    echo json_encode(['sucesso' => $sucesso, 'mensagem' => $msg, 'dados' => $dados]);
    exit;
}

$action = $_GET['action'] ?? 'obter';
$method = $_SERVER['REQUEST_METHOD'];

// 1. OBTER CONFIGURAÇÕES ATUAIS
if ($action === 'obter' && $method === 'GET') {
    try {
        $sql = "SELECT chave, valor FROM configuracoes WHERE chave LIKE 'cupom_%' ORDER BY chave";
        $result = $conn->query($sql);
        
        $cupom_config = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $chave_limpa = str_replace('cupom_', '', $row['chave']);
                $cupom_config[$chave_limpa] = $row['valor'];
            }
        }
        
        responder(true, 'Configurações de cupom carregadas', $cupom_config);
    } catch (Exception $e) {
        responder(false, 'Erro ao carregar: ' . $e->getMessage());
    }
}

// 2. SALVAR CONFIGURAÇÕES
if ($action === 'salvar' && $method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!is_array($data)) {
            responder(false, 'Dados inválidos');
        }
        
        $campos_permitidos = [
            'nome_cantina',
            'vendedor',
            'caixa',
            'rotulo_cupom',
            'titulo_produtos',
            'formato_qtd',
            'rotulo_total',
            'mensagem_1',
            'mensagem_2',
            'mensagem_3',
            'separador_principal',
            'separador_secao'
        ];
        
        $conn->begin_transaction();
        
        foreach ($campos_permitidos as $campo) {
            if (isset($data[$campo])) {
                $chave = 'cupom_' . $campo;
                $valor = $data[$campo];
                
                // Verificar se já existe
                $sql_check = "SELECT id FROM configuracoes WHERE chave = ?";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->bind_param('s', $chave);
                $stmt_check->execute();
                $existe = $stmt_check->get_result()->num_rows > 0;
                
                if ($existe) {
                    // UPDATE
                    $sql = "UPDATE configuracoes SET valor = ? WHERE chave = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ss', $valor, $chave);
                } else {
                    // INSERT
                    $sql = "INSERT INTO configuracoes (chave, valor, tipo, descricao) VALUES (?, ?, 'cupom', 'Configuração de cupom')";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ss', $chave, $valor);
                }
                
                if (!$stmt->execute()) {
                    throw new Exception("Erro ao salvar $campo: " . $stmt->error);
                }
            }
        }
        
        $conn->commit();
        responder(true, 'Configurações de cupom salvas com sucesso!');
        
    } catch (Exception $e) {
        $conn->rollback();
        responder(false, 'Erro ao salvar: ' . $e->getMessage());
    }
}

// 3. RESETAR PARA PADRÃO
if ($action === 'resetar' && $method === 'POST') {
    try {
        $padrao = [
            'cupom_nome_cantina' => 'Cantina IBFC',
            'cupom_vendedor' => '',
            'cupom_caixa' => '',
            'cupom_rotulo_cupom' => 'Venda Nº',
            'cupom_titulo_produtos' => 'PRODUTOS',
            'cupom_formato_qtd' => '{qtd} x R$ {preco}',
            'cupom_rotulo_total' => 'TOTAL',
            'cupom_mensagem_1' => 'DEUS ABENÇOE!',
            'cupom_mensagem_2' => '',
            'cupom_mensagem_3' => '',
            'cupom_separador_principal' => '========================================',
            'cupom_separador_secao' => '----------------------------------------'
        ];
        
        $conn->begin_transaction();
        
        foreach ($padrao as $chave => $valor) {
            $sql_check = "SELECT id FROM configuracoes WHERE chave = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param('s', $chave);
            $stmt_check->execute();
            
            if ($stmt_check->get_result()->num_rows > 0) {
                $sql = "UPDATE configuracoes SET valor = ? WHERE chave = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ss', $valor, $chave);
            } else {
                $sql = "INSERT INTO configuracoes (chave, valor, tipo, descricao) VALUES (?, ?, 'cupom', 'Configuração de cupom')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ss', $chave, $valor);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao resetar: " . $stmt->error);
            }
        }
        
        $conn->commit();
        responder(true, 'Cupom resetado para padrão');
        
    } catch (Exception $e) {
        $conn->rollback();
        responder(false, 'Erro ao resetar: ' . $e->getMessage());
    }
}

responder(false, 'Ação não reconhecida: ' . $action);
?>
