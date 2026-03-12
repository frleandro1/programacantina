<?php
// API para gerenciar configurações (cores, logo, banner)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Erro reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../db/config.php';

// Função para responder com JSON
function responder($sucesso, $mensagem, $dados = null) {
    echo json_encode([
        'sucesso' => $sucesso,
        'mensagem' => $mensagem,
        'dados' => $dados
    ]);
    exit;
}

// Verificar ação solicitada
$action = isset($_GET['action']) ? $_GET['action'] : 'obter';

switch ($action) {
    case 'obter':
        // Obter todas as configurações
        $sql = "SELECT chave, valor FROM configuracoes";
        $result = $conn->query($sql);
        
        if ($result) {
            $configs = [];
            while ($row = $result->fetch_assoc()) {
                $configs[$row['chave']] = $row['valor'];
            }
            responder(true, 'Configurações obtidas', $configs);
        }
        responder(false, 'Erro ao obter configurações');
        break;

    case 'obter-uma':
        // Obter uma configuração específica
        $chave = isset($_GET['chave']) ? $_GET['chave'] : '';
        
        if (!$chave) {
            responder(false, 'Chave não fornecida');
        }
        
        $sql = "SELECT valor FROM configuracoes WHERE chave = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $chave);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            responder(true, 'Configuração obtida', ['valor' => $row['valor']]);
        }
        responder(false, 'Configuração não encontrada');
        break;

    case 'atualizar':
        // Atualizar uma configuração
        $chave = isset($_POST['chave']) ? $_POST['chave'] : '';
        $valor = isset($_POST['valor']) ? $_POST['valor'] : '';
        
        if (!$chave || $valor === '') {
            responder(false, 'Chave ou valor não fornecidos');
        }
        
        $sql = "UPDATE configuracoes SET valor = ? WHERE chave = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $valor, $chave);
        
        if ($stmt->execute()) {
            responder(true, 'Configuração atualizada com sucesso');
        }
        responder(false, 'Erro ao atualizar configuração');
        break;

    case 'upload-logo':
        // Upload de logo
        if (!isset($_FILES['arquivo'])) {
            responder(false, 'Arquivo não fornecido');
        }
        
        $arquivo = $_FILES['arquivo'];
        $diretorio = '../uploads/';
        
        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0755, true);
        }
        
        // Validar tipo de arquivo
        $tipos_permitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($arquivo['type'], $tipos_permitidos)) {
            responder(false, 'Tipo de arquivo não permitido');
        }
        
        $nome_arquivo = 'logo_' . time() . '_' . basename($arquivo['name']);
        $caminho_upload = $diretorio . $nome_arquivo;
        
        if (move_uploaded_file($arquivo['tmp_name'], $caminho_upload)) {
            // Salvar no banco de dados
            $url_relativa = '/Cantina/uploads/' . $nome_arquivo;
            $sql = "UPDATE configuracoes SET valor = ? WHERE chave = 'logo_url'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $url_relativa);
            
            if ($stmt->execute()) {
                responder(true, 'Logo enviado com sucesso', ['url' => $url_relativa]);
            }
        }
        responder(false, 'Erro ao fazer upload do arquivo');
        break;

    case 'upload-banner':
        // Upload de banner
        if (!isset($_FILES['arquivo'])) {
            responder(false, 'Arquivo não fornecido');
        }
        
        $arquivo = $_FILES['arquivo'];
        $diretorio = '../uploads/';
        
        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0755, true);
        }
        
        // Validar tipo de arquivo
        $tipos_permitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($arquivo['type'], $tipos_permitidos)) {
            responder(false, 'Tipo de arquivo não permitido');
        }
        
        $nome_arquivo = 'banner_' . time() . '_' . basename($arquivo['name']);
        $caminho_upload = $diretorio . $nome_arquivo;
        
        if (move_uploaded_file($arquivo['tmp_name'], $caminho_upload)) {
            // Salvar no banco de dados
            $url_relativa = '/Cantina/uploads/' . $nome_arquivo;
            $sql = "UPDATE configuracoes SET valor = ? WHERE chave = 'banner_url'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $url_relativa);
            
            if ($stmt->execute()) {
                responder(true, 'Banner enviado com sucesso', ['url' => $url_relativa]);
            }
        }
        responder(false, 'Erro ao fazer upload do arquivo');
        break;

    case 'deletar-logo':
        // Deletar logo
        $sql = "UPDATE configuracoes SET valor = '' WHERE chave = 'logo_url'";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute()) {
            responder(true, 'Logo deletado com sucesso');
        }
        responder(false, 'Erro ao deletar logo');
        break;

    case 'deletar-banner':
        // Deletar banner
        $sql = "UPDATE configuracoes SET valor = '' WHERE chave = 'banner_url'";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute()) {
            responder(true, 'Banner deletado com sucesso');
        }
        responder(false, 'Erro ao deletar banner');
        break;

    default:
        responder(false, 'Ação não reconhecida');
}

$conn->close();
?>
