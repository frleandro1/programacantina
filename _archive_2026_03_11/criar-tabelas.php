<?php
/**
 * Script para criar tabelas no banco existente
 * Execute: http://localhost/seu-projeto/criar-tabelas.php
 */

$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'programa_cantina';

// Conectar ao banco existente
$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

if ($conn->connect_error) {
    die('❌ Erro: ' . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

echo "═══════════════════════════════════════<br>";
echo "🗄️  CRIANDO TABELAS - PROGRAMA CANTINA<br>";
echo "═══════════════════════════════════════<br><br>";

// SQL para criar as tabelas
$sql = "
-- Tabela de usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    tipo VARCHAR(20) DEFAULT 'usuario',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de produtos
CREATE TABLE IF NOT EXISTS produtos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    preco DECIMAL(10, 2) NOT NULL,
    categoria VARCHAR(50),
    estoque INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'active',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de vendas
CREATE TABLE IF NOT EXISTS vendas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_pedido INT UNIQUE,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10, 2),
    total DECIMAL(10, 2),
    usuario_id INT,
    data_venda TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES produtos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de movimentações de estoque
CREATE TABLE IF NOT EXISTS movimentacoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    produto_id INT NOT NULL,
    tipo VARCHAR(20),
    quantidade INT,
    descricao TEXT,
    usuario_id INT,
    data_movimentacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES produtos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Inserir usuário admin padrão
INSERT INTO usuarios (username, password, email, tipo) 
VALUES ('admin', SHA2('admin123', 256), 'admin@cantina.com', 'admin')
ON DUPLICATE KEY UPDATE password = SHA2('admin123', 256);

-- Inserir produtos de exemplo
INSERT INTO produtos (nome, descricao, preco, categoria, estoque, status) VALUES
('Coxinha', 'Coxinha de frango cremosa', 5.50, 'Salgado', 50, 'active'),
('Pão de Queijo', 'Pão de queijo mineiro', 3.00, 'Salgado', 60, 'active'),
('Pastel', 'Pastel de carne', 6.00, 'Salgado', 40, 'active'),
('Suco de Laranja', 'Suco natural de laranja', 4.50, 'Bebida', 30, 'active'),
('Refrigerante', 'Refrigerante lata 350ml', 5.00, 'Bebida', 80, 'active'),
('Água Mineral', 'Água mineral 500ml', 3.00, 'Bebida', 100, 'active'),
('Brigadeiro', 'Brigadeiro gourmet', 2.50, 'Doce', 45, 'active'),
('Bolo de Chocolate', 'Fatia de bolo de chocolate', 7.00, 'Doce', 25, 'active'),
('Pudim', 'Pudim de leite condensado', 6.50, 'Doce', 20, 'active')
ON DUPLICATE KEY UPDATE estoque = VALUES(estoque);
";

// Separar e executar cada comando
$commands = array_filter(array_map('trim', explode(';', $sql)));

$success = 0;
foreach ($commands as $command) {
    if (empty($command)) continue;
    
    if ($conn->query($command)) {
        $desc = strtoupper(substr(trim($command), 0, 50));
        echo "✅ $desc...<br>";
        $success++;
    } else {
        echo "❌ Erro: " . $conn->error . "<br>";
    }
}

echo "<br>";
echo "═══════════════════════════════════════<br>";
echo "✅ TABELAS CRIADAS COM SUCESSO!<br>";
echo "═══════════════════════════════════════<br><br>";

echo "📝 Credenciais de Login:<br>";
echo "  Usuário: <strong>admin</strong><br>";
echo "  Senha: <strong>admin123</strong><br><br>";

// Listar tabelas
$result = $conn->query("SHOW TABLES");
echo "🔍 Tabelas criadas:<br>";
while ($row = $result->fetch_row()) {
    echo "  ✓ " . $row[0] . "<br>";
}

echo "<br>";
echo "<a href='login.html' style='display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 5px;'>→ Ir para Login</a>";

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Tabelas - Programa Cantina</title>
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
            line-height: 1.8;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div>
        <!-- Conteúdo PHP acima -->
    </div>
</body>
</html>
