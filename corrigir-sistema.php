<?php
require 'db/config.php';

echo "=== CORRIGINDO ERROS DO SISTEMA ===\n\n";

// 1. Criar tabela de relatórios
echo "1️⃣ Criando tabela 'relatorios'...\n";
$sql = "CREATE TABLE IF NOT EXISTS relatorios (
    id_relatorio INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    tipo ENUM('vendas', 'estoque', 'financeiro', 'geral'),
    data_inicio DATE,
    data_fim DATE,
    conteudo LONGTEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
)";

if ($conn->query($sql)) {
    echo "   ✅ Tabela 'relatorios' criada com sucesso!\n";
} else {
    echo "   ❌ Erro ao criar tabela: " . $conn->error . "\n";
}

// 2. Corrigir usuário João
echo "\n2️⃣ Corrigindo usuário 'joao'...\n";
$sql = "UPDATE usuarios SET papel = 'atendente' WHERE login = 'joao'";
if ($conn->query($sql)) {
    echo "   ✅ Usuário 'joao' agora tem papel 'atendente'\n";
} else {
    echo "   ❌ Erro: " . $conn->error . "\n";
}

// 3. Corrigir produtos com dados vazios
echo "\n3️⃣ Atualizando dados dos produtos...\n";
$updates = [
    "UPDATE produtos SET tipo_categoria = 'salgado' WHERE tipo_categoria IS NULL OR tipo_categoria = ''",
    "UPDATE produtos SET status = 'ativo' WHERE status IS NULL OR status = ''",
];

foreach ($updates as $sql) {
    if ($conn->query($sql)) {
        echo "   ✅ Executado: " . substr($sql, 0, 50) . "...\n";
    } else {
        echo "   ❌ Erro: " . $conn->error . "\n";
    }
}

// 4. Resumo final
echo "\n4️⃣ Verificando estado final do banco...\n\n";

$result = $conn->query("SELECT COUNT(*) as cnt FROM usuarios");
$usuarios = $result->fetch_assoc()['cnt'];
echo "   ✅ Usuários: $usuarios\n";

$result = $conn->query("SELECT COUNT(*) as cnt FROM produtos");
$produtos = $result->fetch_assoc()['cnt'];
echo "   ✅ Produtos: $produtos\n";

$result = $conn->query("SELECT COUNT(*) as cnt FROM vendas");
$vendas = $result->fetch_assoc()['cnt'];
echo "   ✅ Vendas: $vendas\n";

$result = $conn->query("SHOW TABLES LIKE 'relatorios'");
if ($result && $result->num_rows > 0) {
    echo "   ✅ Tabela 'relatorios': CRIADA\n";
} else {
    echo "   ❌ Tabela 'relatorios': NÃO ENCONTRADA\n";
}

$result = $conn->query("SELECT papel FROM usuarios WHERE login = 'joao'");
$papel = $result->fetch_assoc()['papel'];
echo "   ✅ Usuário 'joao' papel: $papel\n";

echo "\n✅ SISTEMA CORRIGIDO COM SUCESSO!\n";

$conn->close();
?>
