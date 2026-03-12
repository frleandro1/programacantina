<?php
// Verificador e Configurador de Numeração Diária de Vendas

require_once dirname(__DIR__, 2) . '/db/config.php';

header('Content-Type: application/json; charset=utf-8');

function responder($sucesso, $msg, $dados = null) {
    echo json_encode(['sucesso' => $sucesso, 'mensagem' => $msg, 'dados' => $dados]);
    exit;
}

$action = $_GET['action'] ?? 'verificar';

// 1. VERIFICAR ESTRUTURA DA TABELA
if ($action === 'verificar') {
    try {
        // Checar se a coluna numero_venda_dia existe
        $sql = "SHOW COLUMNS FROM vendas LIKE 'numero_venda_dia'";
        $result = $conn->query($sql);
        
        if ($result->num_rows === 0) {
            responder(false, 'Campo numero_venda_dia não encontrado na tabela vendas', 
                     ['acao_necessaria' => 'adicionar_campo']);
        } else {
            responder(true, 'Campo numero_venda_dia já existe', 
                     ['estrutura_ok' => true]);
        }
    } catch (Exception $e) {
        responder(false, 'Erro ao verificar: ' . $e->getMessage());
    }
}

// 2. ADICIONAR CAMPO numero_venda_dia
if ($action === 'criar_campo') {
    try {
        // Adicionar coluna numero_venda_dia
        $sql = "ALTER TABLE vendas ADD COLUMN numero_venda_dia INT DEFAULT 1";
        $result = $conn->query($sql);
        
        if ($result) {
            // Adicionar índice simples para melhor performance
            @$conn->query("ALTER TABLE vendas ADD INDEX idx_data_numero (data_hora, numero_venda_dia)");
            
            responder(true, 'Campo numero_venda_dia criado com sucesso!');
        } else {
            responder(false, 'Erro ao adicionar campo: ' . $conn->error);
        }
    } catch (Exception $e) {
        responder(false, 'Erro: ' . $e->getMessage());
    }
}

// 3. RESETAR NÚMEROS ANTIGOS (mantém histórico)
if ($action === 'resetar_antigos') {
    try {
        // Popula numeros_venda_dia para registros antigos (opcional)
        $sql = "UPDATE vendas 
                SET numero_venda_dia = (
                    SELECT COUNT(*) FROM (
                        SELECT * FROM vendas v2 
                        WHERE DATE(v2.data_hora) = DATE(vendas.data_hora) 
                        AND v2.id_venda <= vendas.id_venda
                    ) as contagem
                )
                WHERE numero_venda_dia IS NULL OR numero_venda_dia = 0";
        
        $result = $conn->query($sql);
        
        if ($result) {
            responder(true, 'Numeração retrospectiva aplicada com sucesso!');
        } else {
            responder(false, 'Erro ao resetar: ' . $conn->error);
        }
    } catch (Exception $e) {
        responder(false, 'Erro: ' . $e->getMessage());
    }
}

// 4. TESTAR SISTEMA
if ($action === 'testar') {
    try {
        // Simula uma venda para testar
        $usuario_id = 1;
        $valor_total = 25.50;
        
        // Verificar qual deve ser o número de hoje
        $sql_check = "SELECT MAX(numero_venda_dia) as maior FROM vendas WHERE DATE(data_hora) = CURDATE()";
        $result = $conn->query($sql_check);
        $row = $result->fetch_assoc();
        $numero_esperado = ($row['maior'] ?? 0) + 1;
        
        responder(true, 'Teste OK', [
            'ultima_venda_do_dia' => $row['maior'] ?? 0,
            'proximo_numero' => $numero_esperado,
            'hoje' => date('Y-m-d')
        ]);
    } catch (Exception $e) {
        responder(false, 'Erro no teste: ' . $e->getMessage());
    }
}

responder(false, 'Ação não reconhecida: ' . $action);
?>
