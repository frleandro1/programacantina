<?php
// Script para criar usuário 'vendas' no Programa Cantina
require_once dirname(__DIR__, 2) . '/db/config.php';

$login = 'vendas';
$senha = hash('sha256', 'vendas123');
$papel = 'usuario';
$nome = 'Vendedor';

$stmt = $conn->prepare('SELECT id_usuario FROM usuarios WHERE login = ? LIMIT 1');
$stmt->bind_param('s', $login);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo "Usuario 'vendas' ja existe.";
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->close();
$stmt = $conn->prepare('INSERT INTO usuarios (login, senha, papel, nome) VALUES (?, ?, ?, ?)');
$stmt->bind_param('ssss', $login, $senha, $papel, $nome);

if ($stmt->execute()) {
    echo "Usuario 'vendas' criado com sucesso!";
} else {
    echo "Erro ao criar usuario: " . $stmt->error;
}

$stmt->close();
$conn->close();
