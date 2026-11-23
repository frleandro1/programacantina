<?php
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/helpers.php';

session_start();
require_once __DIR__ . '/../db/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Garantir tabelas de eventos caso não existam
function ensure_financeiro_eventos_tables($conn) {
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

function obter_ou_criar_evento($conn, $eventoId, $eventoNome) {
    ensure_financeiro_eventos_tables($conn);
    $eventoId = (int)$eventoId;
    $eventoNome = trim((string)$eventoNome);

    if ($eventoId > 0) {
        $stmt = $conn->prepare("SELECT id, nome FROM financeiro_eventos WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $eventoId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return ['id' => (int)$row['id'], 'nome' => $row['nome']];
            }
        }
    }

    if ($eventoNome !== '') {
        $stmt = $conn->prepare("SELECT id, nome FROM financeiro_eventos WHERE LOWER(TRIM(nome)) = LOWER(?) ORDER BY id DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $eventoNome);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return ['id' => (int)$row['id'], 'nome' => $row['nome']];
            }
        }

        $docPadrao = json_encode([
            'observacoes' => 'Criado automaticamente na abertura de caixa',
            'produtos' => [],
            'despesas' => []
        ], JSON_UNESCAPED_UNICODE);
        $hoje = date('Y-m-d');
        $stmtIns = $conn->prepare("INSERT INTO financeiro_eventos (nome, data_evento, status, documento, versao) VALUES (?, ?, 'rascunho', ?, 1)");
        if ($stmtIns) {
            $stmtIns->bind_param("sss", $eventoNome, $hoje, $docPadrao);
            if ($stmtIns->execute()) {
                $novoId = (int)$stmtIns->insert_id;
                $stmtIns->close();
                return ['id' => $novoId, 'nome' => $eventoNome];
            }
            $stmtIns->close();
        }
    }

    return null;
}

function vincular_sessao_evento($conn, $sessaoId, $eventoId) {
    if ($sessaoId <= 0 || $eventoId <= 0) return false;
    ensure_financeiro_eventos_tables($conn);
    $stmt = $conn->prepare("INSERT INTO financeiro_evento_caixas (sessao_id, evento_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE evento_id = VALUES(evento_id)");
    if ($stmt) {
        $stmt->bind_param("ii", $sessaoId, $eventoId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
    return false;
}

// Listar eventos para seleção na abertura de caixa
if ($method === 'GET' && $action === 'listar_eventos') {
    ensure_financeiro_eventos_tables($conn);
    $res = $conn->query("SELECT id, nome, data_evento, status FROM financeiro_eventos ORDER BY (status = 'rascunho') DESC, data_evento DESC, id DESC LIMIT 50");
    $eventos = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $eventos[] = [
                'id' => (int)$r['id'],
                'nome' => $r['nome'],
                'data_evento' => $r['data_evento'],
                'status' => $r['status']
            ];
        }
    }
    json_response(true, 'Eventos listados', $eventos);
    exit;
}

// Vincular ou alterar evento de uma sessão ativa
if ($method === 'POST' && $action === 'vincular_evento') {
    $data = read_json_input();
    $sessao_id = isset($data['sessao_id']) ? (int)$data['sessao_id'] : 0;
    $evento_id = isset($data['evento_id']) ? (int)$data['evento_id'] : 0;
    $evento_nome = isset($data['evento_nome']) ? trim((string)$data['evento_nome']) : '';

    if ($sessao_id <= 0) {
        json_error('Sessão de caixa não informada');
        exit;
    }

    $eventoInfo = obter_ou_criar_evento($conn, $evento_id, $evento_nome);
    if (!$eventoInfo) {
        json_error('Selecione um evento válido ou digite o nome do novo evento');
        exit;
    }

    vincular_sessao_evento($conn, $sessao_id, $eventoInfo['id']);

    json_response(true, 'Evento vinculado ao caixa com sucesso', [
        'sessao_id' => $sessao_id,
        'evento_id' => $eventoInfo['id'],
        'evento_nome' => $eventoInfo['nome']
    ]);
    exit;
}

// Verificar abertura de caixa (prioriza sessao_id exata do login atual)
if ($method === 'GET' && $action === 'verificar_hoje') {
    $sessao_id  = (int)($_GET['sessao_id'] ?? ($_SESSION['caixa_sessao_id'] ?? 0));
    $usuario_id = (int)($_GET['usuario_id'] ?? ($_SESSION['usuario_id'] ?? 0));

    if ($sessao_id <= 0 && $usuario_id <= 0) {
        json_error('Sessão ou usuário não informado');
        exit;
    }

    $hoje = date('Y-m-d');
    ensure_financeiro_eventos_tables($conn);

    if ($sessao_id > 0) {
        $stmtVerificar = $conn->prepare(
            'SELECT cs.id_sessao, cs.status, cs.valor_abertura, cs.fechamento_caixa,
                    ec.evento_id, fe.nome as evento_nome
             FROM caixa_sessoes cs
             LEFT JOIN financeiro_evento_caixas ec ON ec.sessao_id = cs.id_sessao
             LEFT JOIN financeiro_eventos fe ON fe.id = ec.evento_id
             WHERE cs.id_sessao = ?
             LIMIT 1'
        );
        if (!$stmtVerificar) {
            json_error('Erro ao preparar verificação: ' . $conn->error);
            exit;
        }
        $stmtVerificar->bind_param('i', $sessao_id);
    } else {
        // Fallback: buscar sessão mais recente de hoje para o usuário
        $stmtVerificar = $conn->prepare(
            'SELECT cs.id_sessao, cs.status, cs.valor_abertura, cs.fechamento_caixa,
                    ec.evento_id, fe.nome as evento_nome
             FROM caixa_sessoes cs
             LEFT JOIN financeiro_evento_caixas ec ON ec.sessao_id = cs.id_sessao
             LEFT JOIN financeiro_eventos fe ON fe.id = ec.evento_id
             WHERE cs.id_usuario = ? AND DATE(cs.abertura_caixa) = ? 
             ORDER BY cs.id_sessao DESC LIMIT 1'
        );
        if (!$stmtVerificar) {
            json_error('Erro ao preparar verificação: ' . $conn->error);
            exit;
        }
        $stmtVerificar->bind_param('is', $usuario_id, $hoje);
    }
    $stmtVerificar->execute();
    $resultVerificar = $stmtVerificar->get_result();

    if ($resultVerificar->num_rows > 0) {
        $sessao = $resultVerificar->fetch_assoc();
        json_response(true, 'Sessão encontrada', [
            'id_sessao' => (int)$sessao['id_sessao'],
            'status' => $sessao['status'],
            'valor_abertura' => (float)$sessao['valor_abertura'],
            'fechamento_caixa' => $sessao['fechamento_caixa'],
            'evento_id' => $sessao['evento_id'] ? (int)$sessao['evento_id'] : null,
            'evento_nome' => $sessao['evento_nome'] ?: null
        ]);
    } else {
        json_response(true, 'Sem sessão aberta', null);
    }

    $stmtVerificar->close();
    exit;
}

if ($method === 'POST' && $action === 'registrar') {
    $data = read_json_input();

    $sessao_id = isset($data['sessao_id']) ? (int)$data['sessao_id'] : 0;
    $usuario_id = isset($data['usuario_id']) ? (int)$data['usuario_id'] : 0;
    $valor_abertura = isset($data['valor_abertura']) ? (float)$data['valor_abertura'] : 0;
    $observacoes = isset($data['observacoes']) ? trim((string)$data['observacoes']) : '';
    $evento_id = isset($data['evento_id']) ? (int)$data['evento_id'] : 0;
    $evento_nome = isset($data['evento_nome']) ? trim((string)$data['evento_nome']) : '';

    if (!$sessao_id) {
        json_error('Sessão de caixa não informada');
        exit;
    }

    if ($valor_abertura < 0) {
        json_error('Valor de abertura inválido');
        exit;
    }

    // Verificar se JÁ TEM abertura de caixa ABERTA hoje (diferente da atual)
    if ($usuario_id > 0) {
        $hoje = date('Y-m-d');
        $stmtJaAberto = $conn->prepare(
            'SELECT id_sessao FROM caixa_sessoes 
             WHERE id_usuario = ? AND DATE(abertura_caixa) = ? AND status = "aberto" AND id_sessao != ? LIMIT 1'
        );

        if (!$stmtJaAberto) {
            json_error('Erro ao verificar abertura existente: ' . $conn->error);
            exit;
        }

        $stmtJaAberto->bind_param('isi', $usuario_id, $hoje, $sessao_id);
        $stmtJaAberto->execute();
        $resultJaAberto = $stmtJaAberto->get_result();

        if ($resultJaAberto->num_rows > 0) {
            json_error('Você já tem outro caixa aberto hoje. Feche-o antes de abrir um novo.');
            exit;
        }

        $stmtJaAberto->close();
    }

    // Validar que a sessão existe e está aberta
    $stmtValidar = $conn->prepare(
        'SELECT id_sessao, status FROM caixa_sessoes WHERE id_sessao = ? AND status = "aberto" LIMIT 1'
    );

    if (!$stmtValidar) {
        json_error('Erro ao preparar validação: ' . $conn->error);
        exit;
    }

    $stmtValidar->bind_param('i', $sessao_id);
    $stmtValidar->execute();
    $resultValidar = $stmtValidar->get_result();

    if ($resultValidar->num_rows === 0) {
        json_error('Sessão de caixa não encontrada ou já foi fechada');
        exit;
    }

    $stmtValidar->close();

    // Atualizar caixa_sessoes com valor de abertura
    $stmtAtualizar = $conn->prepare(
        'UPDATE caixa_sessoes SET valor_abertura = ?, observacoes = ? WHERE id_sessao = ?'
    );

    if (!$stmtAtualizar) {
        json_error('Erro ao preparar atualização: ' . $conn->error);
        exit;
    }

    $stmtAtualizar->bind_param('dsi', $valor_abertura, $observacoes, $sessao_id);

    if (!$stmtAtualizar->execute()) {
        json_error('Erro ao registrar abertura de caixa: ' . $stmtAtualizar->error);
        exit;
    }

    $stmtAtualizar->close();

    // Vincular ou criar evento se fornecido
    $eventoInfo = obter_ou_criar_evento($conn, $evento_id, $evento_nome);
    if ($eventoInfo) {
        vincular_sessao_evento($conn, $sessao_id, $eventoInfo['id']);
    }

    // Recuperar dados da sessão atualizada
    $stmtRetorna = $conn->prepare(
        'SELECT id_sessao, id_usuario, login_usuario, abertura_caixa, valor_abertura, status FROM caixa_sessoes WHERE id_sessao = ?'
    );

    if (!$stmtRetorna) {
        json_error('Erro ao recuperar dados: ' . $conn->error);
        exit;
    }

    $stmtRetorna->bind_param('i', $sessao_id);
    $stmtRetorna->execute();
    $resultRetorna = $stmtRetorna->get_result();
    $sessaoDados = $resultRetorna->fetch_assoc();
    $stmtRetorna->close();

    json_response(true, 'Abertura de caixa registrada com sucesso', [
        'sessao_id' => (int)$sessaoDados['id_sessao'],
        'usuario' => $sessaoDados['login_usuario'],
        'abertura_caixa' => $sessaoDados['abertura_caixa'],
        'valor_abertura' => (float)$sessaoDados['valor_abertura'],
        'status' => $sessaoDados['status'],
        'evento_id' => $eventoInfo ? $eventoInfo['id'] : null,
        'evento_nome' => $eventoInfo ? $eventoInfo['nome'] : null
    ]);
    exit;
}

json_error('Ação não permitida');
