-- Criar banco de dados
CREATE DATABASE IF NOT EXISTS programa_cantina;
USE programa_cantina;

-- Tabela de usuários (atualizada com campos faltando)
CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INT PRIMARY KEY AUTO_INCREMENT,
    id INT UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    login VARCHAR(50),
    password VARCHAR(255) NOT NULL,
    senha VARCHAR(255),
    email VARCHAR(100),
    tipo VARCHAR(20) DEFAULT 'usuario',
    papel VARCHAR(50) DEFAULT 'usuario',
    nome VARCHAR(100),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de produtos (atualizada com campos faltando)
CREATE TABLE IF NOT EXISTS produtos (
    id_produto INT PRIMARY KEY AUTO_INCREMENT,
    id INT UNIQUE,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    preco DECIMAL(10, 2) NOT NULL,
    preco_venda DECIMAL(10, 2),
    preco_custo DECIMAL(10, 2),
    categoria VARCHAR(50),
    tipo_categoria VARCHAR(50),
    estoque INT DEFAULT 0,
    estoque_atual INT DEFAULT 0,
    imagem VARCHAR(255),
    status VARCHAR(20) DEFAULT 'active',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_estoque (estoque_atual),
    INDEX idx_categoria (tipo_categoria)
);

-- Tabela de vendas (atualizada com campos faltando)
CREATE TABLE IF NOT EXISTS vendas (
    id_venda INT PRIMARY KEY AUTO_INCREMENT,
    id INT UNIQUE,
    numero_pedido INT UNIQUE,
    numero_venda_dia INT,
    numero_venda_turno INT,
    produto_id INT,
    id_produto INT,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10, 2),
    total DECIMAL(10, 2),
    subtotal DECIMAL(10, 2),
    usuario_id INT,
    id_usuario INT,
    valor_total DECIMAL(10, 2) DEFAULT 0,
    data_venda TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sessao_caixa_id INT,
    status VARCHAR(20) DEFAULT 'ativo',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_produto) REFERENCES produtos(id_produto) ON DELETE RESTRICT,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    INDEX idx_data (data_hora),
    INDEX idx_usuario (id_usuario),
    INDEX idx_sessao (sessao_caixa_id)
);

-- Tabela de itens de venda (detalhes)
CREATE TABLE IF NOT EXISTS itens_venda (
    id_item INT PRIMARY KEY AUTO_INCREMENT,
    id_venda INT NOT NULL,
    id_produto INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10, 2),
    subtotal DECIMAL(10, 2),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_venda) REFERENCES vendas(id_venda) ON DELETE CASCADE,
    FOREIGN KEY (id_produto) REFERENCES produtos(id_produto) ON DELETE RESTRICT,
    INDEX idx_venda (id_venda),
    INDEX idx_produto (id_produto)
);

-- Tabela de movimentações de estoque
CREATE TABLE IF NOT EXISTS movimentacoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    produto_id INT NOT NULL,
    id_produto INT,
    tipo VARCHAR(20),
    quantidade INT,
    descricao TEXT,
    usuario_id INT,
    id_usuario INT,
    data_movimentacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_produto) REFERENCES produtos(id_produto),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
    INDEX idx_produto (id_produto),
    INDEX idx_data (data_movimentacao)
);

-- Tabela de sessões de caixa
CREATE TABLE IF NOT EXISTS caixa_sessoes (
    id_sessao INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    login_usuario VARCHAR(100) NOT NULL,
    abertura_caixa DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fechamento_caixa DATETIME NULL,
    valor_abertura DECIMAL(10, 2) DEFAULT 0,
    valor_fechamento DECIMAL(10, 2) DEFAULT 0,
    total_vendas DECIMAL(10, 2) DEFAULT 0,
    observacoes TEXT,
    status ENUM('aberto','fechado') NOT NULL DEFAULT 'aberto',
    ip_origem VARCHAR(45) NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
    INDEX idx_usuario_status (id_usuario, status),
    INDEX idx_abertura (abertura_caixa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de configurações
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    tipo VARCHAR(50) DEFAULT 'string',
    descricao VARCHAR(255),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inserir usuário admin padrão
INSERT INTO usuarios (username, login, password, senha, email, tipo, papel, nome) 
VALUES ('admin', 'admin', SHA2('admin123', 256), SHA2('admin123', 256), 'admin@cantina.com', 'admin', 'admin', 'Administrador')
ON DUPLICATE KEY UPDATE password = SHA2('admin123', 256), senha = SHA2('admin123', 256);

-- Inserir configurações padrão
INSERT INTO configuracoes (chave, valor, tipo, descricao) VALUES
('nome_cantina', 'Programa Cantina', 'string', 'Nome da cantina exibido no sistema'),
('cor_primaria', '#ff7a45', 'color', 'Cor primária do tema'),
('cor_secundaria', '#3498db', 'color', 'Cor secundária do tema'),
('cor_acentuacao', '#e74c3c', 'color', 'Cor de acentuação'),
('cor_texto', '#ecf0f1', 'color', 'Cor do texto'),
('cor_fundo_card', '#34495e', 'color', 'Cor de fundo dos cards'),
('logo_url', '', 'string', 'URL da logo'),
('banner_url', '', 'string', 'URL do banner'),
('cupom_titulo', 'Programa Cantina', 'string', 'Título no cupom'),
('cupom_numero_colunas', '40', 'number', 'Número de colunas no cupom'),
('impressora_nome', '', 'string', 'Nome da impressora padrão para cupons (vazio = padrão do Windows)'),
('impressora_largura', '80', 'string', 'Largura do papel da impressora (58 ou 80 mm)'),
('impressora_autoprint', 'nao', 'string', 'Impressão automática ao finalizar venda (sim/nao)'),
('impressora_fonte', '9', 'number', 'Tamanho da fonte na impressão térmica (pt)')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

-- Inserir produtos de exemplo
INSERT INTO produtos (nome, descricao, preco_venda, preco_custo, tipo_categoria, estoque_atual, status) VALUES
('Coxinha', 'Coxinha de frango cremosa', 5.50, 3.00, 'Salgado', 50, 'active'),
('Pão de Queijo', 'Pão de queijo mineiro', 3.00, 1.50, 'Salgado', 60, 'active'),
('Pastel', 'Pastel de carne', 6.00, 3.50, 'Salgado', 40, 'active'),
('Suco de Laranja', 'Suco natural de laranja', 4.50, 2.00, 'Bebida', 30, 'active'),
('Refrigerante', 'Refrigerante lata 350ml', 5.00, 2.50, 'Bebida', 80, 'active'),
('Água Mineral', 'Água mineral 500ml', 3.00, 1.00, 'Bebida', 100, 'active'),
('Brigadeiro', 'Brigadeiro gourmet', 2.50, 1.00, 'Doce', 45, 'active'),
('Bolo de Chocolate', 'Fatia de bolo de chocolate', 7.00, 3.50, 'Doce', 25, 'active'),
('Pudim', 'Pudim de leite condensado', 6.50, 3.00, 'Doce', 20, 'active')
ON DUPLICATE KEY UPDATE estoque_atual = VALUES(estoque_atual), preco_venda = VALUES(preco_venda);
