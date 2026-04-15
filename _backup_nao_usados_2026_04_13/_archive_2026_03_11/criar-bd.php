<?php
header('Content-Type: application/json');

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'programa_cantina');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    echo json_encode(['sucesso' => false, 'erro' => $conn->connect_error]);
    exit;
}

$conn->set_charset("utf8mb4");

// Primeiro, deletar as tabelas antigas se existirem
$conn->query("DROP TABLE IF EXISTS movimentacoes");
$conn->query("DROP TABLE IF EXISTS vendas");
$conn->query("DROP TABLE IF EXISTS produtos");
$conn->query("DROP TABLE IF EXISTS usuarios");

$sqls = [
    "CREATE TABLE usuarios (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100),
        tipo VARCHAR(20) DEFAULT 'usuario',
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE produtos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nome VARCHAR(100) NOT NULL,
        descricao TEXT,
        preco DECIMAL(10, 2) NOT NULL,
        categoria VARCHAR(50),
        estoque INT DEFAULT 0,
        status VARCHAR(20) DEFAULT 'active',
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE vendas (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE movimentacoes (
        id INT PRIMARY KEY AUTO_INCREMENT,
        produto_id INT NOT NULL,
        tipo VARCHAR(20),
        quantidade INT,
        descricao TEXT,
        usuario_id INT,
        data_movimentacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (produto_id) REFERENCES produtos(id),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

$results = [];
foreach ($sqls as $sql) {
    if ($conn->query($sql)) {
        $results[] = ['tabela' => 'criada', 'sucesso' => true];
    } else {
        $results[] = ['erro' => $conn->error, 'sucesso' => false];
    }
}

// Inserir dados
$password_hash = hash('sha256', 'admin123');
$insert_usuario = "INSERT INTO usuarios (username, password, email, tipo) VALUES ('admin', '$password_hash', 'admin@cantina.com', 'admin')";

$insert_produtos = "INSERT INTO produtos (nome, descricao, preco, categoria, estoque, status) VALUES
('Coxinha', 'Coxinha de frango cremosa', 5.50, 'Salgado', 50, 'active'),
('Pão de Queijo', 'Pão de queijo mineiro', 3.00, 'Salgado', 60, 'active'),
('Pastel', 'Pastel de carne', 6.00, 'Salgado', 40, 'active'),
('Suco de Laranja', 'Suco natural de laranja', 4.50, 'Bebida', 30, 'active'),
('Refrigerante', 'Refrigerante lata 350ml', 5.00, 'Bebida', 80, 'active'),
('Água Mineral', 'Água mineral 500ml', 3.00, 'Bebida', 100, 'active'),
('Brigadeiro', 'Brigadeiro gourmet', 2.50, 'Doce', 45, 'active'),
('Bolo de Chocolate', 'Fatia de bolo de chocolate', 7.00, 'Doce', 25, 'active'),
('Pudim', 'Pudim de leite condensado', 6.50, 'Doce', 20, 'active')";

if ($conn->query($insert_usuario)) {
    $results[] = ['tipo' => 'usuario admin inserido', 'sucesso' => true];
} else {
    $results[] = ['tipo' => 'usuario', 'erro' => $conn->error, 'sucesso' => false];
}

if ($conn->query($insert_produtos)) {
    $results[] = ['tipo' => 'produtos inseridos', 'sucesso' => true];
} else {
    $results[] = ['tipo' => 'produtos', 'erro' => $conn->error, 'sucesso' => false];
}

echo json_encode(['sucesso' => true, 'mensagem' => 'Banco de dados criado com sucesso', 'detalhes' => $results]);

$conn->close();
?>