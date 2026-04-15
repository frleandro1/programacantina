<?php
/**
 * Diagnóstico de Conectividade - Pendrive
 * Use para verificar se tudo está funcionando quando roda do pendrive
 */

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='utf-8'><title>Diagnóstico Pendrive</title>";
echo "<style>body{font-family:Arial;margin:20px;background:#f5f5f5}";
echo ".ok{color:green;background:#e8f5e9;padding:10px;margin:5px 0;border-left:4px solid green}";
echo ".erro{color:red;background:#ffebee;padding:10px;margin:5px 0;border-left:4px solid red}";
echo ".aviso{color:orange;background:#fff3e0;padding:10px;margin:5px 0;border-left:4px solid orange}";
echo ".info{background:#e3f2fd;padding:10px;margin:5px 0;border-left:4px solid blue}";
echo "</style></head><body>";
echo "<h1>🔍 Diagnóstico do Sistema - Pendrive</h1>";

// 1. Verificar se está no pendrive
$script_dir = __DIR__;
$is_pendrive = (strpos($script_dir, 'E:') !== false || strpos($script_dir, 'e:') !== false);

echo "<h2>1. Detecção de Unidade</h2>";
if ($is_pendrive) {
    echo "<div class='ok'>✓ Rodando do PENDRIVE (E:)</div>";
} else {
    echo "<div class='aviso'>⚠ Rodando do XAMPP Local (C:\xampp)</div>";
}
echo "<div class='info'>Caminho: " . htmlspecialchars($script_dir) . "</div>";

// 2. Verificar arquivo de configuração
echo "<h2>2. Arquivo de Configuração</h2>";
$config_paths = [
    'config-pendrive.php' => __DIR__ . '/config-pendrive.php',
    'config.php' => __DIR__ . '/config.php'
];

foreach ($config_paths as $name => $path) {
    if (file_exists($path)) {
        echo "<div class='ok'>✓ " . $name . " encontrado</div>";
    } else {
        echo "<div class='erro'>✗ " . $name . " NÃO encontrado</div>";
    }
}

// 3. Conectar ao banco de dados
echo "<h2>3. Conexão com Banco de Dados</h2>";
require_once __DIR__ . '/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    echo "<div class='erro'>✗ Erro ao conectar: " . htmlspecialchars($conn->connect_error) . "</div>";
    echo "<div class='info'>Host: " . htmlspecialchars(DB_HOST) . " | Banco: " . htmlspecialchars(DB_NAME) . "</div>";
} else {
    echo "<div class='ok'>✓ Conectado com sucesso</div>";
    echo "<div class='info'>Host: " . htmlspecialchars(DB_HOST) . " | Banco: " . htmlspecialchars(DB_NAME) . "</div>";
    
    // Verificar tabelas
    echo "<h2>4. Tabelas do Banco</h2>";
    $result = $conn->query("SHOW TABLES");
    if ($result && $result->num_rows > 0) {
        echo "<div class='ok'>✓ Tabelas encontradas:</div>";
        while ($row = $result->fetch_assoc()) {
            $table_name = array_values($row)[0];
            echo "<div class='info'>- " . htmlspecialchars($table_name) . "</div>";
        }
    } else {
        echo "<div class='aviso'>⚠ Nenhuma tabela encontrada. Execute a instalação.</div>";
    }
    
    // Verificar usuários
    echo "<h2>5. Usuários no Sistema</h2>";
    $result = $conn->query("SELECT COUNT(*) as total FROM usuarios");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row['total'] > 0) {
            echo "<div class='ok'>✓ " . $row['total'] . " usuário(s) encontrado(s)</div>";
        } else {
            echo "<div class='aviso'>⚠ Nenhum usuário cadastrado</div>";
        }
    }
}

// 6. Verificar pastas essenciais
echo "<h2>6. Estruturas de Pastas</h2>";
$folders = [
    'uploads' => dirname(__DIR__) . '/uploads',
    'logs' => dirname(__DIR__) . '/logs',
    'api' => dirname(__DIR__) . '/api',
    'css' => dirname(__DIR__) . '/css',
    'js' => dirname(__DIR__) . '/js'
];

foreach ($folders as $name => $path) {
    if (is_dir($path)) {
        echo "<div class='ok'>✓ Pasta " . $name . " existe</div>";
    } else {
        echo "<div class='erro'>✗ Pasta " . $name . " NÃO existe</div>";
    }
}

echo "<h2>7. Próximos Passos</h2>";
echo "<div class='info'>";
echo "Se tudo está OK acima, seu sistema está pronto!<br>";
echo "Se há erros, verifique:<br>";
echo "- XAMPP está rodando na unidade E:<br>";
echo "- Banco de dados 'programa_cantina' existe<br>";
echo "- Arquivo ../INICIAR_VENDAS.bat ou ../INICIAR_CANTINA.bat está correto<br>";
echo "</div>";

if (isset($conn)) {
    $conn->close();
}

echo "</body></html>";
?>
