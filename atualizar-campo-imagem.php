<?php
/**
 * Script para adicionar campo de imagem na tabela produtos
 * Execute este script uma única vez em /Cantina/atualizar-campo-imagem.php
 */

require_once __DIR__ . '/db/config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='utf-8'>
    <title>Atualizar Banco de Dados</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        .success { color: green; font-weight: bold; margin: 10px 0; }
        .error { color: red; font-weight: bold; margin: 10px 0; }
        .info { color: blue; margin: 10px 0; }
        hr { margin: 20px 0; }
    </style>
</head>
<body>
<div class='container'>
    <h1>⚙️ Atualizar Banco de Dados</h1>";

try {
    // Verificar se a coluna já existe
    $result = $conexao->query("SHOW COLUMNS FROM produtos LIKE 'imagem'");
    
    if ($result && $result->num_rows > 0) {
        echo "<p class='info'>✅ Campo 'imagem' já existe na tabela productos</p>";
    } else {
        // Adicionar coluna imagem
        $sql = "ALTER TABLE produtos ADD COLUMN imagem VARCHAR(255) NULL DEFAULT NULL";
        
        if ($conexao->query($sql) === TRUE) {
            echo "<p class='success'>✅ Campo 'imagem' adicionado com sucesso!</p>";
        } else {
            echo "<p class='error'>❌ Erro ao adicionar campo: " . $conexao->error . "</p>";
        }
    }

    // Criar diretório de uploads se não existir
    $uploadDir = __DIR__ . '/uploads/produtos';
    if (!is_dir($uploadDir)) {
        if (mkdir($uploadDir, 0755, true)) {
            echo "<p class='success'>✅ Diretório /uploads/produtos criado com sucesso!</p>";
        } else {
            echo "<p class='error'>❌ Erro ao criar diretório de uploads</p>";
        }
    } else {
        echo "<p class='info'>✅ Diretório /uploads/produtos já existe</p>";
    }

    // Verificar permissões
    if (is_writable($uploadDir)) {
        echo "<p class='success'>✅ Permissões de escrita configuradas</p>";
    } else {
        echo "<p class='error'>⚠️ Aviso: Diretório não tem permissão de escrita</p>";
    }

    echo "<hr>";
    echo "<h2>Estrutura Atual da Tabela</h2>";
    
    $result = $conexao->query("DESCRIBE produtos");
    echo "<table border='1' cellpadding='10' cellspacing='0' width='100%'>";
    echo "<tr style='background: #e0e0e0;'><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Padrão</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . ($row['Null'] === 'YES' ? 'Sim' : 'Não') . "</td>";
        echo "<td>" . ($row['Default'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<hr>";
    echo "<h2>✅ Banco de Dados Pronto</h2>";
    echo "<p>Sistema de imagens está configurado!</p>";
    echo "<p><strong>Próximos passos:</strong></p>";
    echo "<ul>";
    echo "<li>Acesse: <a href='admin-imagens.html' target='_blank'>admin-imagens.html</a></li>";
    echo "<li>Faça upload de imagens para os produtos</li>";
    echo "<li>As imagens aparecerão na galeria</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p class='error'>❌ Erro: " . $e->getMessage() . "</p>";
}

echo "
</div>
</body>
</html>";

$conexao->close();
?>
