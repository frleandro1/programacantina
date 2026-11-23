CREATE TABLE IF NOT EXISTS financeiro_eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    data_evento DATE NOT NULL,
    status ENUM('rascunho', 'finalizado') NOT NULL DEFAULT 'rascunho',
    documento LONGTEXT NOT NULL,
    versao INT NOT NULL DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financeiro_evento_caixas (
    sessao_id INT PRIMARY KEY,
    evento_id INT NOT NULL,
    FOREIGN KEY (sessao_id) REFERENCES caixa_sessoes(id_sessao),
    FOREIGN KEY (evento_id) REFERENCES financeiro_eventos(id),
    INDEX idx_evento (evento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financeiro_transferencias (
    origem_evento INT NOT NULL,
    produto_id INT NOT NULL,
    lote_indice INT NOT NULL,
    destino_evento INT NOT NULL,
    PRIMARY KEY (origem_evento, produto_id, lote_indice),
    FOREIGN KEY (origem_evento) REFERENCES financeiro_eventos(id),
    FOREIGN KEY (destino_evento) REFERENCES financeiro_eventos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financeiro_receitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    documento LONGTEXT NOT NULL,
    versao INT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
