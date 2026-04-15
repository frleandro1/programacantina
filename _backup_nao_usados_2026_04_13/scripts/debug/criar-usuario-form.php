<?php
// Formulário para criar novo usuário no Programa Cantina
require_once dirname(__DIR__, 2) . '/db/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $papel = trim($_POST['papel'] ?? 'usuario');
    $nome = trim($_POST['nome'] ?? '');

    if ($login === '' || $senha === '' || $nome === '') {
        echo '<p style="color:red">Preencha todos os campos obrigatórios.</p>';
    } else {
        $hash = hash('sha256', $senha);
        // Usar nomes corretos: login, senha, papel
        $stmt = $conn->prepare('SELECT id_usuario FROM usuarios WHERE login = ? LIMIT 1');
        $stmt->bind_param('s', $login);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo '<p style="color:orange">Usuário já existe.</p>';
        } else {
            $stmt->close();
            $stmt = $conn->prepare('INSERT INTO usuarios (login, senha, papel, nome) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('ssss', $login, $hash, $papel, $nome);
            if ($stmt->execute()) {
                echo '<p style="color:green">Usuário criado com sucesso!</p>';
            } else {
                echo '<p style="color:red">Erro ao criar usuário: ' . $stmt->error . '</p>';
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Criar Novo Usuário</title>
</head>
<body>
    <h2>Criar Novo Usuário</h2>
    <form method="post">
        <label>Login*: <input type="text" name="login" required></label><br><br>
        <label>Senha*: <input type="password" name="senha" required></label><br><br>
        <label>Nome*: <input type="text" name="nome" required></label><br><br>
        <label>Papel:
            <select name="papel">
                <option value="usuario">Usuário</option>
                <option value="admin">Admin</option>
            </select>
        </label><br><br>
        <button type="submit">Criar Usuário</button>
    </form>
    <p>* Campos obrigatórios</p>
</body>
</html>
