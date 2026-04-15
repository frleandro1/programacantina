<?php
/**
 * Script de Instalação do Banco de Dados
 * Execute uma vez: http://localhost/seu-projeto/install.php
 */

// Configurações
$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'programa_cantina';

// Conectar ao MySQL (sem banco específico)
$conn = new mysqli($db_host, $db_user, $db_password);

if ($conn->connect_error) {
    die('❌ Erro na conexão: ' . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

echo "═══════════════════════════════════════<br>";
echo "🗄️  INSTALAÇÃO - PROGRAMA CANTINA<br>";
echo "═══════════════════════════════════════<br><br>";

// Ler o arquivo SQL
$sql_file = __DIR__ . '/db/schema.sql';

if (!file_exists($sql_file)) {
    die('❌ Arquivo schema.sql não encontrado!');
}

$sql_content = file_get_contents($sql_file);

// Separar commands por ponto-e-vírgula
$commands = array_filter(array_map('trim', explode(';', $sql_content)));

$success_count = 0;
$error_count = 0;

// Executar cada comando
foreach ($commands as $command) {
    if (empty($command)) continue;
    
    // Adicionar ponto-e-vírgula novamente se necessário
    if (substr($command, -1) !== ';') {
        $command .= ';';
    }
    
    if ($conn->multi_query($command)) {
        // Consumir todos os resultados
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        
        // Extrair descrição do comando
        $desc = strtoupper(substr(trim($command), 0, 50));
        echo "✅ $desc...<br>";
        $success_count++;
    } else {
        echo "⚠️  Erro: " . $conn->error . "<br>";
        $error_count++;
    }
}

echo "<br>";
echo "═══════════════════════════════════════<br>";
echo "📊 RESULTADO<br>";
echo "═══════════════════════════════════════<br>";
echo "✅ Sucesso: $success_count<br>";
echo "⚠️  Erros: $error_count<br>";

// Verificar tabelas criadas
echo "<br>🔍 Tabelas Criadas:<br>";
$result = $conn->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$db_name'");

if ($result) {
    $tables = [];
    while ($row = $result->fetch_assoc()) {
        $tables[] = $row['TABLE_NAME'];
        echo "  • " . $row['TABLE_NAME'] . "<br>";
    }
    
    if (count($tables) === 4) {
        echo "<br>✅ <strong>INSTALAÇÃO CONCLUÍDA COM SUCESSO!</strong><br>";
        echo "<br>📝 Credenciais padrão:<br>";
        echo "  Usuário: <strong>admin</strong><br>";
        echo "  Senha: <strong>admin123</strong><br>";
        echo "<br><a href='login.html' style='color: #667eea; text-decoration: none; font-weight: bold;'>→ Ir para Login</a>";
    } else {
        echo "<br>⚠️  Nem todas as tabelas foram criadas. Verifique os erros acima.";
    }
} else {
    echo "❌ Erro ao consultar tabelas: " . $conn->error;
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação - Programa Cantina</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        div {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            line-height: 1.6;
            font-size: 14px;
        }
        
        h1 {
            color: #333;
            text-align: center;
        }
        
        a {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div>
        <?php // Conteúdo gerado acima ?>
    </div>
</body>
</html>
