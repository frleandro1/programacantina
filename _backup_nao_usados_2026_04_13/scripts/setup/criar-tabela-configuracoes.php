<?php
// Arquivo para criar a tabela de configurações no banco de dados
header('Content-Type: text/html; charset=utf-8');

// Incluir conexão com banco de dados
require_once dirname(__DIR__, 2) . '/db/config.php';

// Verificar conexão
if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}

// Comando SQL para criar tabela de configurações
$sql = "CREATE TABLE IF NOT EXISTS configuracoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(50) NOT NULL UNIQUE,
    valor LONGTEXT,
    tipo VARCHAR(20) DEFAULT 'texto',
    descricao TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "<h2 style='color: green;'>✓ Tabela de configurações criada com sucesso!</h2>";
} else {
    echo "<h2 style='color: red;'>✗ Erro ao criar tabela: " . $conn->error . "</h2>";
}

// Inserir configurações padrão
$configuracoes_padrao = [
    ['cor_primaria', '#2c3e50', 'cor', 'Cor primária do tema'],
    ['cor_secundaria', '#3498db', 'cor', 'Cor secundária do tema'],
    ['cor_acentuacao', '#e74c3c', 'cor', 'Cor de acentuação'],
    ['cor_texto', '#ecf0f1', 'cor', 'Cor do texto'],
    ['cor_fundo_card', '#34495e', 'cor', 'Cor de fundo dos cards'],
    ['logo_url', '', 'arquivo', 'URL do logo'],
    ['banner_url', '', 'arquivo', 'URL do banner de produtos'],
    ['nome_cantina', 'Programa Cantina', 'texto', 'Nome da cantina'],
    ['tema_modo', 'dark', 'texto', 'Modo do tema (dark/light)']
];

foreach ($configuracoes_padrao as $config) {
    $sql = "INSERT INTO configuracoes (chave, valor, tipo, descricao) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            tipo = VALUES(tipo), 
            descricao = VALUES(descricao)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ssss", $config[0], $config[1], $config[2], $config[3]);
        if (!$stmt->execute()) {
            echo "<p style='color: red;'>Erro ao inserir {$config[0]}: " . $stmt->error . "</p>";
        }
        $stmt->close();
    }
}

echo "<h3 style='color: green;'>✓ Configurações padrão inseridas!</h3>";
echo "<p><a href='admin.html'>← Voltar para o painel</a></p>";

$conn->close();
?>
