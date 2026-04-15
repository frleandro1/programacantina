<?php
// Migração: adiciona configurações de impressora térmica ao banco de dados
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../../db/config.php';

$novas_configs = [
    ['impressora_largura',   '80',  'string', 'Largura do papel da impressora (58 ou 80 mm)'],
    ['impressora_autoprint', 'nao', 'string', 'Impressão automática ao finalizar venda (sim/nao)'],
    ['impressora_fonte',     '9',   'number', 'Tamanho da fonte na impressão térmica (pt)'],
];

$sql = "INSERT INTO configuracoes (chave, valor, tipo, descricao) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE tipo = VALUES(tipo), descricao = VALUES(descricao)";
$stmt = $conn->prepare($sql);

foreach ($novas_configs as [$chave, $valor, $tipo, $desc]) {
    $stmt->bind_param("ssss", $chave, $valor, $tipo, $desc);
    if ($stmt->execute()) {
        echo "OK: $chave = $valor\n";
    } else {
        echo "ERRO: $chave — " . $stmt->error . "\n";
    }
}

echo "\nConcluído! Configure a impressora em: admin.html → Configurações → 🖨️ Impressora Térmica\n";
$conn->close();
