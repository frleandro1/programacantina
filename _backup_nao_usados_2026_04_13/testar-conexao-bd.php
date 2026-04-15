<?php
/**
 * Script para testar conexão com o banco de dados
 * Acesse: http://localhost/Cantina/testar-conexao-bd.php
 */

// Carregar configurações
$db_config = [
    'host' => 'localhost',
    'user' => 'root',
    'password' => '',
    'database' => 'programa_cantina',
    'port' => 3306
];

echo "<!DOCTYPE html>";
echo "<html lang='pt-BR'>";
echo "<head>";
echo "    <meta charset='UTF-8'>";
echo "    <meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "    <title>Teste de Conexão - Banco de Dados</title>";
echo "    <style>";
echo "        * { margin: 0; padding: 0; box-sizing: border-box; }";
echo "        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; }";
echo "        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
echo "        h1 { color: #333; margin-bottom: 30px; text-align: center; }";
echo "        .status { margin: 20px 0; padding: 15px; border-radius: 5px; border-left: 4px solid; }";
echo "        .success { background: #d4edda; border-color: #28a745; color: #155724; }";
echo "        .error { background: #f8d7da; border-color: #dc3545; color: #721c24; }";
echo "        .info { background: #d1ecf1; border-color: #0c5460; color: #0c5460; }";
echo "        .section { margin: 25px 0; }";
echo "        .section h2 { font-size: 16px; color: #495057; margin-bottom: 10px; border-bottom: 2px solid #dee2e6; padding-bottom: 8px; }";
echo "        table { width: 100%; border-collapse: collapse; margin-top: 10px; }";
echo "        table td { padding: 10px; border-bottom: 1px solid #dee2e6; }";
echo "        table td:first-child { font-weight: 500; width: 40%; color: #495057; }";
echo "        .badge { display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; }";
echo "        .badge-success { background: #28a745; color: white; }";
echo "        .badge-danger { background: #dc3545; color: white; }";
echo "        .badge-warning { background: #ffc107; color: #333; }";
echo "        .badge-info { background: #17a2b8; color: white; }";
echo "        .commands { background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.6; margin-top: 10px; }";
echo "    </style>";
echo "</head>";
echo "<body>";
echo "    <div class='container'>";
echo "        <h1>🔗 Teste de Conexão - Banco de Dados</h1>";

// Teste 1: Verificar configurações
echo "        <div class='section'>";
echo "            <h2>📋 Configurações do Banco de Dados</h2>";
echo "            <table>";
echo "                <tr><td>Host:</td><td>" . $db_config['host'] . "</td></tr>";
echo "                <tr><td>Usuário:</td><td>" . $db_config['user'] . "</td></tr>";
echo "                <tr><td>Banco de Dados:</td><td>" . $db_config['database'] . "</td></tr>";
echo "                <tr><td>Porta:</td><td>" . $db_config['port'] . "</td></tr>";
echo "            </table>";
echo "        </div>";

// Teste 2: Tentar conectar
echo "        <div class='section'>";
echo "            <h2>🔌 Teste de Conexão</h2>";

$conn = @mysqli_connect(
    $db_config['host'],
    $db_config['user'],
    $db_config['password'],
    $db_config['database'],
    $db_config['port']
);

if ($conn && !mysqli_connect_error()) {
    echo "            <div class='status success'>";
    echo "                <strong>✅ Conexão Estabelecida com Sucesso!</strong>";
    echo "            </div>";
    
    // Teste 3: Versão do MySQL
    echo "        <div class='section'>";
    echo "            <h2>ℹ️ Informações do Servidor</h2>";
    $version = mysqli_get_server_info($conn);
    $client_version = mysqli_get_client_info();
    echo "            <table>";
    echo "                <tr><td>Versão MySQL (Servidor):</td><td><span class='badge badge-info'>" . $version . "</span></td></tr>";
    echo "                <tr><td>Versão MySQL (Cliente):</td><td><span class='badge badge-info'>" . $client_version . "</span></td></tr>";
    echo "            </table>";
    echo "        </div>";
    
    // Teste 4: Listar tabelas
    echo "        <div class='section'>";
    echo "            <h2>📊 Tabelas do Banco de Dados</h2>";
    $tables_result = mysqli_query($conn, "SHOW TABLES");
    
    if ($tables_result && mysqli_num_rows($tables_result) > 0) {
        echo "            <table>";
        while ($table = mysqli_fetch_row($tables_result)) {
            $table_name = $table[0];
            $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM " . $table_name));
            echo "                <tr>";
            echo "                    <td>" . $table_name . "</td>";
            echo "                    <td>";
            echo "                        <span class='badge badge-success'>" . $count['total'] . " registros</span>";
            echo "                    </td>";
            echo "                </tr>";
        }
        echo "            </table>";
    } else {
        echo "            <div class='status error'>";
        echo "                <strong>⚠️ Nenhuma tabela encontrada no banco de dados</strong>";
        echo "                <p>Execute o script de instalação para criar as tabelas.</p>";
        echo "            </div>";
    }
    echo "        </div>";
    
    // Teste 5: Charset
    echo "        <div class='section'>";
    echo "            <h2>🔤 Codificação</h2>";
    $charset_result = mysqli_query($conn, "SELECT @@character_set_database, @@collation_database");
    $charset = mysqli_fetch_assoc($charset_result);
    echo "            <table>";
    echo "                <tr><td>Charset:</td><td><span class='badge badge-info'>" . $charset['@@character_set_database'] . "</span></td></tr>";
    echo "                <tr><td>Collation:</td><td><span class='badge badge-info'>" . $charset['@@collation_database'] . "</span></td></tr>";
    echo "            </table>";
    echo "        </div>";
    
    // Resumo
    echo "        <div class='status success'>";
    echo "            <strong>✅ STATUS: TUDO OK!</strong>";
    echo "            <p>O banco de dados está funcionando corretamente e pronto para ser usado.</p>";
    echo "        </div>";
    
    mysqli_close($conn);
} else {
    echo "            <div class='status error'>";
    echo "                <strong>❌ Erro ao conectar!</strong>";
    echo "                <p>" . (mysqli_connect_error() ?: "Erro desconhecido") . "</p>";
    echo "            </div>";
    
    echo "        <div class='section'>";
    echo "            <h2>🔧 Passos para Resolver</h2>";
    echo "            <ol style='margin-left: 20px;'>";
    echo "                <li><strong>Verifique se o MySQL está rodando:</strong>";
    echo "                    <div class='commands'>";
    echo "                        • Abra o painel de controle do XAMPP<br>";
    echo "                        • Clique em 'Start' próximo ao MySQL<br>";
    echo "                    </div>";
    echo "                </li>";
    echo "                <li><strong>Verifique as credenciais em:</strong>";
    echo "                    <div class='commands'>";
    echo "                        e:\\htdocs\\Cantina\\db\\config.php<br>";
    echo "                        (ou config-pendrive.php se estiver usando pendrive)";
    echo "                    </div>";
    echo "                </li>";
    echo "                <li><strong>Crie o banco de dados executando:</strong>";
    echo "                    <div class='commands'>";
    echo "                        http://localhost/Cantina/install.php";
    echo "                    </div>";
    echo "                </li>";
    echo "            </ol>";
    echo "        </div>";
}

echo "    </div>";
echo "</body>";
echo "</html>";
?>
