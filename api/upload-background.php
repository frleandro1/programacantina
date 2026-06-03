<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido']);
    exit;
}

if (!isset($_FILES['arquivo'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhum arquivo enviado']);
    exit;
}

$file = $_FILES['arquivo'];
$allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];

// Validar tipo MIME
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Tipo de arquivo não permitido. Use JPG ou PNG.']);
    exit;
}

// Validar tamanho (máximo 10MB)
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Arquivo muito grande (máximo 10MB)']);
    exit;
}

// Criar pasta se não existir
$upload_dir = __DIR__ . '/../uploads/backgrounds';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Salvar com nome fixo
$filename = 'cantina-background.jpg';
$filepath = $upload_dir . '/' . $filename;

// Deletar arquivo antigo se existir
if (file_exists($filepath)) {
    unlink($filepath);
}

// Se é PNG, converter para JPG
if ($file['type'] === 'image/png') {
    $image = imagecreatefrompng($file['tmp_name']);
    $bg = imagecreatetruecolor(imagesx($image), imagesy($image));
    imagefilledrectangle($bg, 0, 0, imagesx($image), imagesy($image), imagecolorallocate($bg, 255, 255, 255));
    imagecopy($bg, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
    imagejpeg($bg, $filepath, 90);
    imagedestroy($image);
    imagedestroy($bg);
} else {
    // Mover arquivo JPG
    move_uploaded_file($file['tmp_name'], $filepath);
}

// Verificar se o arquivo foi salvo
if (file_exists($filepath)) {
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Imagem salva com sucesso!',
        'arquivo' => $filename,
        'caminho' => '/Cantina/uploads/backgrounds/' . $filename
    ]);
} else {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao salvar o arquivo']);
}
?>
