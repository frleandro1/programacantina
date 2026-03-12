<?php
require 'db/config.php';

echo "<h1>🔍 DIAGNÓSTICO DE AUTENTICAÇÃO</h1>\n\n";

// 1. Listar usuários cadastrados
echo "<h2>1️⃣ Usuários no banco de dados:</h2>\n";
echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 8px; overflow-x: auto;'>\n";
$result = $conn->query("SELECT id_usuario, login, papel, nome FROM usuarios");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id_usuario'] . " | Login: " . $row['login'] . " | Papel: " . $row['papel'] . " | Nome: " . $row['nome'] . "\n";
    }
    echo "\nTotal: " . $result->num_rows . " usuários\n";
} else {
    echo "❌ NENHUM USUÁRIO CADASTRADO!\n";
}
echo "</pre>\n";

// 2. Testar login com cada usuário
echo "<h2>2️⃣ Teste de Login:</h2>\n";
echo "<p>Use um dos usuários listados acima com a senha para fazer login</p>\n";

echo "<h2>3️⃣ Testar API de Login (JSON):</h2>\n";
echo "<pre style='background: #e8f4f8; padding: 15px; border-radius: 8px;'>\n";
echo "POST /api/auth.php?action=login\n";
echo "Content-Type: application/json\n\n";
echo "{\n";
echo "  \"username\": \"admin\",\n";
echo "  \"password\": \"admin123\"\n";
echo "}\n";
echo "</pre>\n";

// 3. Verificar estrutura da tabela
echo "<h2>4️⃣ Estrutura da tabela usuarios:</h2>\n";
echo "<pre style='background: #fff9e6; padding: 15px; border-radius: 8px; overflow-x: auto;'>\n";
$result = $conn->query("DESCRIBE usuarios");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}
echo "</pre>\n";

// 4. Dicas
echo "<h2>💡 Dicas se não conectar:</h2>\n";
echo "<ul style='font-size: 16px; line-height: 1.8;'>\n";
echo "<li>Abra <strong>DevTools</strong> (F12) → <strong>Network</strong></li>\n";
echo "<li>Tente fazer login e observe a requisição para <code>/api/auth.php</code></li>\n";
echo "<li>Veja a resposta: deve retornar JSON com <code>sucesso: true</code> ou mensagem de erro</li>\n";
echo "<li>Se houver erro, copie a mensagem aqui</li>\n";
echo "<li>Verifique se o MySQL está rodando no XAMPP</li>\n";
echo "</ul>\n";

// 5. Testar com curl se quiser
echo "<h2>5️⃣ Comando para testar no terminal:</h2>\n";
echo "<pre style='background: #f0f0f0; padding: 15px; border-radius: 8px;'>\n";
echo "curl -X POST http://localhost/Cantina/api/auth.php?action=login \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -d '{\"username\":\"admin\",\"password\":\"admin123\"}'\n";
echo "</pre>\n";
?>
