<?php
require_once dirname(__DIR__, 2) . '/db/config.php';

echo "<h2>🔧 Diagnóstico - Criar Usuário</h2>";

// Verificar estrutura da tabela
echo "<h3>1️⃣ Estrutura da Tabela 'usuarios':</h3>";
$estructura = $conn->query('DESCRIBE usuarios');
if ($estructura) {
    echo "<table border='1' cellpadding='10' style='margin-bottom: 20px;'>";
    echo "<tr><th>Campo</th><th>Tipo</th></tr>";
    $campos = [];
    while ($col = $estructura->fetch_assoc()) {
        echo "<tr><td><strong>" . $col['Field'] . "</strong></td><td>" . $col['Type'] . "</td></tr>";
        $campos[] = $col['Field'];
    }
    echo "</table>";
    echo "<p><strong>Campos disponíveis:</strong> " . implode(", ", $campos) . "</p>";
} else {
    echo "<p style='color: red;'><strong>Erro:</strong> Não consegui ler a tabela. " . $conn->error . "</p>";
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $nome = trim($_POST['nome'] ?? '');
    $tipo = trim($_POST['tipo'] ?? 'usuario');

    echo "<h3>2️⃣ Tentando criar usuário...</h3>";

    if (!$login || !$senha || !$nome) {
        echo "<p style='color: red;'><strong>❌ Erro:</strong> Preencha todos os campos!</p>";
    } else {
        $hash = hash('sha256', $senha);

        // Usar os nomes corretos: login, senha, papel
        $sql = "INSERT INTO usuarios (login, senha, papel, nome) VALUES ('$login', '$hash', '$tipo', '$nome')";
        
        echo "<p><strong>SQL:</strong> <code>" . htmlspecialchars($sql) . "</code></p>";

        if ($conn->query($sql)) {
            echo "<p style='color: green;'><strong>✅ Usuário criado com sucesso!</strong></p>";
            
            // Listar usuários
            echo "<h3>3️⃣ Usuários Registrados:</h3>";
            $users = $conn->query('SELECT * FROM usuarios');
            if ($users) {
                echo "<table border='1' cellpadding='10'>";
                $firstRow = true;
                while ($row = $users->fetch_assoc()) {
                    if ($firstRow) {
                        echo "<tr>";
                        foreach (array_keys($row) as $key) {
                            echo "<th>" . $key . "</th>";
                        }
                        echo "</tr>";
                        $firstRow = false;
                    }
                    echo "<tr>";
                    foreach ($row as $val) {
                        echo "<td>" . ($val ?? '-') . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            }
        } else {
            echo "<p style='color: red;'><strong>❌ Erro ao criar:</strong> " . $conn->error . "</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Criar Usuário - Diagnóstico</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 30px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h2, h3 {
            color: #333;
        }
        table {
            background: white;
            border-collapse: collapse;
        }
        form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        button {
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
        }
        button:hover {
            background: #764ba2;
        }
        code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h2>🔧 Diagnóstico - Criar Usuário</h2>

    <form method="post">
        <div class="form-group">
            <label for="login">Login *</label>
            <input type="text" id="login" name="login" required placeholder="Ex: joao.silva">
        </div>

        <div class="form-group">
            <label for="senha">Senha *</label>
            <input type="password" id="senha" name="senha" required placeholder="Mínimo 6 caracteres">
        </div>

        <div class="form-group">
            <label for="nome">Nome Completo *</label>
            <input type="text" id="nome" name="nome" required placeholder="Ex: João Silva">
        </div>

        <div class="form-group">
            <label for="tipo">Tipo de Usuário</label>
            <select id="tipo" name="tipo">
                <option value="usuario">Usuário Comum</option>
                <option value="vendedor">Vendedor</option>
                <option value="admin">Administrador</option>
            </select>
        </div>

        <button type="submit">✓ Criar Usuário</button>
    </form>

    <p style="text-align: center; color: #666; margin-top: 30px; font-size: 12px;">
        Esta página ajuda a diagnosticar e criar usuários. Use este formulário para testar a criação de usuários.
    </p>
</body>
</html>
