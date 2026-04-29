<?php
// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'programa_cantina');

// Criar conexão
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Verificar conexão
if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}

// Criar banco de dados se não existir
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sql) === TRUE) {
    // Selecionar banco de dados
    $conn->select_db(DB_NAME);
} else {
    die("Erro ao criar banco de dados: " . $conn->error);
}

// Criar tabelas
$sql_tables = "
-- Tabela de Usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('admin', 'gerente', 'atendente', 'produtor') DEFAULT 'atendente',
    ativo BOOLEAN DEFAULT TRUE,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acesso TIMESTAMP NULL
);

-- Tabela de Produtos
CREATE TABLE IF NOT EXISTS produtos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL UNIQUE,
    categoria VARCHAR(50) NOT NULL,
    preco_venda DECIMAL(10, 2) NOT NULL,
    preco_custo DECIMAL(10, 2),
    estoque INT DEFAULT 0,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Movimentações (Entrada/Saída)
CREATE TABLE IF NOT EXISTS movimentacoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    produto_id INT NOT NULL,
    tipo ENUM('entrada', 'saida') NOT NULL,
    quantidade INT NOT NULL,
    motivo VARCHAR(255),
    responsavel VARCHAR(100),
    data_movimentacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES produtos(id)
);

-- Tabela de Vendas
CREATE TABLE IF NOT EXISTS vendas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    data_venda TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atendente VARCHAR(100) NOT NULL,
    total DECIMAL(10, 2) NOT NULL
);

-- Tabela de Itens de Vendas
CREATE TABLE IF NOT EXISTS itens_venda (
    id INT PRIMARY KEY AUTO_INCREMENT,
    venda_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (venda_id) REFERENCES vendas(id),
    FOREIGN KEY (produto_id) REFERENCES produtos(id)
);
";

// Executar criação de tabelas
foreach (explode(';', $sql_tables) as $statement) {
    $statement = trim($statement);
    if (!empty($statement)) {
        if ($conn->query($statement) === FALSE) {
            // Ignorar erros de tabelas já existentes
            if (strpos($conn->error, 'already exists') === false) {
                error_log("Erro ao criar tabela: " . $conn->error);
            }
        }
    }
}

// Inserir usuários padrão se não existirem
$check_usuarios = $conn->query("SELECT COUNT(*) as count FROM usuarios");
$row_usuarios = $check_usuarios->fetch_assoc();

if ($row_usuarios['count'] == 0) {
    $usuarios_padrao = [
        ['Administrador', 'admin@cantina.com', 'admin123', 'admin'],
        ['Gerente Cantina', 'gerente@cantina.com', 'gerente123', 'gerente'],
        ['Vendedor João', 'vendedor@cantina.com', 'vendedor123', 'atendente'],
        ['Produtor Maria', 'produtor@cantina.com', 'produtor123', 'produtor']
    ];
    
    foreach ($usuarios_padrao as $usuario) {
        $nome = $usuario[0];
        $email = $usuario[1];
        $senha = password_hash($usuario[2], PASSWORD_BCRYPT);
        $tipo = $usuario[3];
        
        $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nome, $email, $senha, $tipo);
        $stmt->execute();
        $stmt->close();
    }
}

// Inserir produtos padrão se não existirem
$check_produtos = $conn->query("SELECT COUNT(*) as count FROM produtos");
$row = $check_produtos->fetch_assoc();

if ($row['count'] == 0) {
    $produtos_padrao = [
        ['Pão de Queijo', 'Salgado', 3.50, 1.50],
        ['Bolo de Cenoura', 'Doce', 5.00, 2.00],
        ['Coxinha', 'Salgado', 4.00, 1.80],
        ['Brigadeiro', 'Doce', 2.50, 1.00],
        ['Suco Natural', 'Bebida', 4.50, 1.50],
        ['Café', 'Bebida', 2.00, 0.50],
        ['Sanduíche', 'Salgado', 8.00, 3.50],
        ['Salada', 'Refeição', 6.00, 2.50]
    ];

    foreach ($produtos_padrao as $produto) {
        $stmt = $conn->prepare("INSERT INTO produtos (nome, categoria, preco_venda, preco_custo) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssdd", $produto[0], $produto[1], $produto[2], $produto[3]);
        $stmt->execute();
        $stmt->close();
    }
}
?>
