<?php
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/produto-schema.php';

try {
    require_once __DIR__ . '/../db/config.php';
    
    $action = $_GET['action'] ?? '';
    $conexao = $conn;
    
    // Diretório de uploads
    $uploadDir = __DIR__ . '/../uploads/produtos/';
    
    // Validar conexão
    if (!$conexao || $conexao->connect_error) {
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao conectar ao banco de dados'
        ]);
        exit;
    }
    
    switch ($action) {
        case 'upload':
            handleUpload($conexao, $uploadDir);
            break;
        
        case 'get':
            getImage($conexao);
            break;
        
        case 'delete':
            deleteImage($conexao, $uploadDir);
            break;
        
        case 'list':
            listImages($conexao);
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['sucesso' => false, 'mensagem' => 'Ação não definida: ' . $action]);
            break;
    }

} catch (Exception $e) {
    error_log('Erro na API de imagens: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao processar requisição: ' . $e->getMessage()
    ]);
    exit;
}

/**
 * Upload de imagem para produto
 */
function handleUpload($conexao, $uploadDir) {
    if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum arquivo enviado ou erro no upload']);
        return;
    }

    $produtoId = $_POST['produto_id'] ?? null;
    if (!$produtoId) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID do produto não informado']);
        return;
    }

    // Validar se o produto existe
    $idColuna = produto_coluna($conexao, ['id_produto', 'id']);
    $stmt = $conexao->prepare("SELECT {$idColuna} FROM produtos WHERE {$idColuna} = ?");
    $stmt->bind_param("i", $produtoId);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Produto não encontrado']);
        $stmt->close();
        return;
    }
    $stmt->close();

    // Validar tipo de arquivo
    $file = $_FILES['imagem'];
    $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($file['type'], $tiposPermitidos)) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WebP']);
        return;
    }

    // Validar tamanho (máx 5MB)
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Arquivo muito grande. Máximo 5MB']);
        return;
    }

    // Criar nome único para a imagem
    $extensoes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $ext = $extensoes[$file['type']] ?? 'jpg';
    $nomeArquivo = 'produto_' . $produtoId . '_' . time() . '.' . $ext;
    $caminhoCompleto = $uploadDir . $nomeArquivo;

    // Criar diretório se não existir
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Mover arquivo
    if (!move_uploaded_file($file['tmp_name'], $caminhoCompleto)) {
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao salvar arquivo']);
        return;
    }

    // Deletar imagem anterior se existir
    $idColuna = produto_coluna($conexao, ['id_produto', 'id']);
    $stmt = $conexao->prepare("SELECT imagem FROM produtos WHERE {$idColuna} = ?");
    $stmt->bind_param("i", $produtoId);
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($resultado['imagem'] && file_exists($uploadDir . $resultado['imagem'])) {
        @unlink($uploadDir . $resultado['imagem']);
    }

    // Atualizar referência no banco de dados
    $stmt = $conexao->prepare("UPDATE produtos SET imagem = ? WHERE {$idColuna} = ?");
    $stmt->bind_param("si", $nomeArquivo, $produtoId);
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Imagem enviada com sucesso',
            'arquivo' => $nomeArquivo,
            'url' => 'uploads/produtos/' . $nomeArquivo
        ]);
    } else {
        $stmt->close();
        @unlink($caminhoCompleto);
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao salvar informações no banco de dados']);
    }
}

/**
 * Obter imagem de um produto
 */
function getImage($conexao) {
    $produtoId = $_GET['produto_id'] ?? null;
    
    if (!$produtoId) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID do produto não informado']);
        return;
    }

    $idColuna = produto_coluna($conexao, ['id_produto', 'id']);
    $stmt = $conexao->prepare("SELECT imagem FROM produtos WHERE {$idColuna} = ?");
    $stmt->bind_param("i", $produtoId);
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $imagem = produto_imagem_arquivo((int)$produtoId, $resultado['imagem'] ?? '');
    if ($imagem === '') {
        http_response_code(404);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Imagem não encontrada']);
        return;
    }

    echo json_encode([
        'sucesso' => true,
        'imagem' => $imagem,
        'url' => 'uploads/produtos/' . $imagem
    ]);
}

/**
 * Deletar imagem de um produto
 */
function deleteImage($conexao, $uploadDir) {
    $produtoId = $_POST['produto_id'] ?? null;
    
    if (!$produtoId) {
        http_response_code(400);
        echo json_encode(['sucesso' => false, 'mensagem' => 'ID do produto não informado']);
        return;
    }

    $idColuna = produto_coluna($conexao, ['id_produto', 'id']);
    $stmt = $conexao->prepare("SELECT imagem FROM produtos WHERE {$idColuna} = ?");
    $stmt->bind_param("i", $produtoId);
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$resultado['imagem']) {
        http_response_code(404);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhuma imagem para deletar']);
        return;
    }

    $caminhoArquivo = $uploadDir . $resultado['imagem'];
    
    if (file_exists($caminhoArquivo)) {
        @unlink($caminhoArquivo);
    }

    $stmt = $conexao->prepare("UPDATE produtos SET imagem = NULL WHERE {$idColuna} = ?");
    $stmt->bind_param("i", $produtoId);
    
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['sucesso' => true, 'mensagem' => 'Imagem deletada com sucesso']);
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao deletar imagem']);
    }
}

/**
 * Listar todas as imagens de produtos
 */
function listImages($conexao) {
    $idColuna = produto_coluna($conexao, ['id_produto', 'id']);
    $sql = "SELECT {$idColuna} AS id_produto, nome, imagem FROM produtos ORDER BY {$idColuna}";
    
    $stmt = $conexao->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao preparar query: ' . $conexao->error
        ]);
        error_log('SQL Error em listImages: ' . $conexao->error);
        return;
    }
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao executar query: ' . $stmt->error
        ]);
        error_log('Execute Error em listImages: ' . $stmt->error);
        $stmt->close();
        return;
    }
    
    $resultado = $stmt->get_result();
    if (!$resultado) {
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao obter resultado: ' . $stmt->error
        ]);
        error_log('Get Result Error em listImages: ' . $stmt->error);
        $stmt->close();
        return;
    }
    
    $imagens = [];
    while ($row = $resultado->fetch_assoc()) {
        $arquivo = produto_imagem_arquivo((int)$row['id_produto'], $row['imagem']);
        if ($arquivo === '') {
            continue;
        }
        $imagens[] = [
            'produto_id' => $row['id_produto'],
            'nome' => $row['nome'],
            'imagem' => $arquivo,
            'url' => 'uploads/produtos/' . $arquivo
        ];
    }
    $stmt->close();

    echo json_encode([
        'sucesso' => true,
        'total' => count($imagens),
        'imagens' => $imagens
    ]);
}
?>
