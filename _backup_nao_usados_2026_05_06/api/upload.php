<?php
/**
 * API de Upload de Arquivos
 * Gerencia upload de logo, banner e imagens de produtos
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/upload.log');

require_once __DIR__ . '/../db/config.php';

// Criar diretório de logs se não existir
@mkdir(__DIR__ . '/../logs', 0755, true);

// Diretórios de upload
$UPLOAD_DIRS = [
    'logo' => __DIR__ . '/../uploads/logos/',
    'banner' => __DIR__ . '/../uploads/banners/',
    'produto' => __DIR__ . '/../uploads/produtos/',
];

// Criar diretórios se não existirem
foreach ($UPLOAD_DIRS as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Tipos MIME permitidos
$ALLOWED_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    'image/x-icon' => 'ico'
];

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

/**
 * Função para retornar JSON com erro
 */
function responder($sucesso, $mensagem, $dados = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'sucesso' => $sucesso,
        'mensagem' => $mensagem,
        'dados' => $dados
    ]);
    exit;
}

/**
 * Validar arquivo
 */
function validarArquivo($arquivo) {
    global $ALLOWED_TYPES;
    
    // Verificar se arquivo foi enviado
    if (!isset($arquivo) || !isset($arquivo['tmp_name'])) {
        return ['valido' => false, 'erro' => 'Nenhum arquivo enviado'];
    }
    
    // Verificar se upload foi bem-sucedido
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        $erros = [
            UPLOAD_ERR_INI_SIZE => 'Arquivo maior que o permitido (ini_max_file_size)',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo maior que o permitido (form MAX_FILE_SIZE)',
            UPLOAD_ERR_PARTIAL => 'Upload parcial, tente novamente',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário não definido',
            UPLOAD_ERR_CANT_WRITE => 'Erro ao escrever arquivo',
            UPLOAD_ERR_EXTENSION => 'Extensão não permitida'
        ];
        return [
            'valido' => false,
            'erro' => $erros[$arquivo['error']] ?? 'Erro desconhecido'
        ];
    }
    
    // Verificar tipo MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $arquivo['tmp_name']);
    finfo_close($finfo);
    
    if (!isset($ALLOWED_TYPES[$mime])) {
        return [
            'valido' => false,
            'erro' => "Tipo de arquivo não permitido: $mime. Permitidos: JPG, PNG, GIF, WebP"
        ];
    }
    
    // Verificar tamanho (máximo 5MB)
    $maxSize = 5 * 1024 * 1024;
    if ($arquivo['size'] > $maxSize) {
        return [
            'valido' => false,
            'erro' => 'Arquivo maior que 5MB'
        ];
    }
    
    return [
        'valido' => true,
        'mime' => $mime,
        'extensao' => $ALLOWED_TYPES[$mime]
    ];
}

/**
 * Gerar nome de arquivo único e seguro
 */
function gerarNomeArquivo($original, $extensao) {
    // Remover caracteres especiais do nome original
    $nome = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($original, PATHINFO_FILENAME));
    $nome = substr($nome, 0, 50); // Limitar comprimento
    
    // Adicionar timestamp e hash para garantir unicidade
    $timestamp = time();
    $hash = substr(md5(uniqid()), 0, 8);
    
    return "{$nome}_{$timestamp}_{$hash}.{$extensao}";
}

/**
 * Deletar arquivo antigo se existir
 */
function deletarArquivoAntigo($caminhoAntigo) {
    if ($caminhoAntigo && file_exists($caminhoAntigo)) {
        @unlink($caminhoAntigo);
        return true;
    }
    return false;
}

/**
 * Obter caminho do arquivo armazenado
 */
function obterCaminhoArmazenado($tipo, $chave) {
    global $conn;
    
    $sql = "SELECT valor FROM configuracoes WHERE chave = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param('s', $chave);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row && $row['valor']) {
        // Extrair caminho do URL
        return __DIR__ . '/..' . parse_url($row['valor'], PHP_URL_PATH);
    }
    
    return null;
}

// ============================================
// UPLOAD DE LOGO
// ============================================
if ($method === 'POST' && $action === 'upload_logo') {
    if (!isset($_FILES['arquivo'])) {
        responder(false, 'Nenhum arquivo enviado', null, 400);
    }
    
    $validacao = validarArquivo($_FILES['arquivo']);
    if (!$validacao['valido']) {
        responder(false, $validacao['erro'], null, 400);
    }
    
    try {
        // Gerar nome único
        $nomeArquivo = gerarNomeArquivo($_FILES['arquivo']['name'], $validacao['extensao']);
        $caminhoFinal = $UPLOAD_DIRS['logo'] . $nomeArquivo;
        
        // Mover arquivo
        if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $caminhoFinal)) {
            responder(false, 'Erro ao salvar arquivo', null, 500);
        }
        
        // Deletar arquivo antigo se existir
        $caminhoAntigo = obterCaminhoArmazenado('logo', 'logo_url');
        deletarArquivoAntigo($caminhoAntigo);
        
        // URL do arquivo
        $urlLogo = '/Cantina/uploads/logos/' . $nomeArquivo;
        
        // Atualizar configuração no banco
        $sql = "UPDATE configuracoes SET valor = ? WHERE chave = 'logo_url'";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('s', $urlLogo);
            $stmt->execute();
            $stmt->close();
        }
        
        responder(true, 'Logo enviada com sucesso', [
            'url' => $urlLogo,
            'arquivo' => $nomeArquivo
        ]);
        
    } catch (Exception $e) {
        error_log('Erro ao fazer upload de logo: ' . $e->getMessage());
        responder(false, 'Erro ao processar upload: ' . $e->getMessage(), null, 500);
    }
}

// ============================================
// UPLOAD DE BANNER
// ============================================
if ($method === 'POST' && $action === 'upload_banner') {
    if (!isset($_FILES['arquivo'])) {
        responder(false, 'Nenhum arquivo enviado', null, 400);
    }
    
    $validacao = validarArquivo($_FILES['arquivo']);
    if (!$validacao['valido']) {
        responder(false, $validacao['erro'], null, 400);
    }
    
    try {
        // Gerar nome único
        $nomeArquivo = gerarNomeArquivo($_FILES['arquivo']['name'], $validacao['extensao']);
        $caminhoFinal = $UPLOAD_DIRS['banner'] . $nomeArquivo;
        
        // Mover arquivo
        if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $caminhoFinal)) {
            responder(false, 'Erro ao salvar arquivo', null, 500);
        }
        
        // Deletar arquivo antigo se existir
        $caminhoAntigo = obterCaminhoArmazenado('banner', 'banner_url');
        deletarArquivoAntigo($caminhoAntigo);
        
        // URL do arquivo
        $urlBanner = '/Cantina/uploads/banners/' . $nomeArquivo;
        
        // Atualizar configuração no banco
        $sql = "UPDATE configuracoes SET valor = ? WHERE chave = 'banner_url'";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('s', $urlBanner);
            $stmt->execute();
            $stmt->close();
        }
        
        responder(true, 'Banner enviado com sucesso', [
            'url' => $urlBanner,
            'arquivo' => $nomeArquivo
        ]);
        
    } catch (Exception $e) {
        error_log('Erro ao fazer upload de banner: ' . $e->getMessage());
        responder(false, 'Erro ao processar upload: ' . $e->getMessage(), null, 500);
    }
}

// ============================================
// DELETE LOGO
// ============================================
if ($method === 'DELETE' && $action === 'delete_logo') {
    try {
        // Obter caminho do arquivo
        $caminhoAntigo = obterCaminhoArmazenado('logo', 'logo_url');
        
        // Deletar arquivo
        if ($caminhoAntigo) {
            deletarArquivoAntigo($caminhoAntigo);
        }
        
        // Limpar configuração no banco
        $sql = "UPDATE configuracoes SET valor = '' WHERE chave = 'logo_url'";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->execute();
            $stmt->close();
        }
        
        responder(true, 'Logo removida com sucesso');
        
    } catch (Exception $e) {
        error_log('Erro ao deletar logo: ' . $e->getMessage());
        responder(false, 'Erro ao deletar logo', null, 500);
    }
}

// ============================================
// DELETE BANNER
// ============================================
if ($method === 'DELETE' && $action === 'delete_banner') {
    try {
        // Obter caminho do arquivo
        $caminhoAntigo = obterCaminhoArmazenado('banner', 'banner_url');
        
        // Deletar arquivo
        if ($caminhoAntigo) {
            deletarArquivoAntigo($caminhoAntigo);
        }
        
        // Limpar configuração no banco
        $sql = "UPDATE configuracoes SET valor = '' WHERE chave = 'banner_url'";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->execute();
            $stmt->close();
        }
        
        responder(true, 'Banner removido com sucesso');
        
    } catch (Exception $e) {
        error_log('Erro ao deletar banner: ' . $e->getMessage());
        responder(false, 'Erro ao deletar banner', null, 500);
    }
}

// ============================================
// UPLOAD DE IMAGEM DE PRODUTO
// ============================================
if ($method === 'POST' && $action === 'upload_produto') {
    if (!isset($_FILES['arquivo'])) {
        responder(false, 'Nenhum arquivo enviado', null, 400);
    }
    
    $produtoId = intval($_POST['produto_id'] ?? 0);
    if ($produtoId <= 0) {
        responder(false, 'ID do produto não fornecido', null, 400);
    }
    
    $validacao = validarArquivo($_FILES['arquivo']);
    if (!$validacao['valido']) {
        responder(false, $validacao['erro'], null, 400);
    }
    
    try {
        // Gerar nome único
        $nomeArquivo = gerarNomeArquivo($_FILES['arquivo']['name'], $validacao['extensao']);
        $caminhoFinal = $UPLOAD_DIRS['produto'] . $nomeArquivo;
        
        // Mover arquivo
        if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $caminhoFinal)) {
            responder(false, 'Erro ao salvar arquivo', null, 500);
        }
        
        // Atualizar produto no banco
        $sql = "UPDATE produtos SET imagem = ? WHERE id_produto = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('si', $nomeArquivo, $produtoId);
            $stmt->execute();
            $stmt->close();
        }
        
        $urlImagem = '/Cantina/uploads/produtos/' . $nomeArquivo;
        
        responder(true, 'Imagem do produto enviada com sucesso', [
            'url' => $urlImagem,
            'arquivo' => $nomeArquivo,
            'produto_id' => $produtoId
        ]);
        
    } catch (Exception $e) {
        error_log('Erro ao fazer upload de imagem: ' . $e->getMessage());
        responder(false, 'Erro ao processar upload: ' . $e->getMessage(), null, 500);
    }
}

// ============================================
// DELETE IMAGEM DE PRODUTO
// ============================================
if ($method === 'DELETE' && $action === 'delete_produto') {
    try {
        $produtoId = intval($_GET['produto_id'] ?? 0);
        if ($produtoId <= 0) {
            responder(false, 'ID do produto não fornecido', null, 400);
        }
        
        // Obter nome do arquivo
        $sql = "SELECT imagem FROM produtos WHERE id_produto = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            responder(false, 'Erro ao consultar produto', null, 500);
        }
        
        $stmt->bind_param('i', $produtoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $produto = $result->fetch_assoc();
        $stmt->close();
        
        if ($produto && $produto['imagem']) {
            $caminhoArquivo = $UPLOAD_DIRS['produto'] . $produto['imagem'];
            deletarArquivoAntigo($caminhoArquivo);
        }
        
        // Remover referência no banco
        $sql = "UPDATE produtos SET imagem = NULL WHERE id_produto = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $produtoId);
            $stmt->execute();
            $stmt->close();
        }
        
        responder(true, 'Imagem removida com sucesso');
        
    } catch (Exception $e) {
        error_log('Erro ao deletar imagem: ' . $e->getMessage());
        responder(false, 'Erro ao deletar imagem', null, 500);
    }
}

// ============================================
// AÇÃO NÃO ENCONTRADA
// ============================================
responder(false, "Ação '$action' não encontrada ou método não permitido", null, 400);
?>
