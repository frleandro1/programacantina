<?php
/**
 * Script de Atualização do Banco de Dados
 * Executa após install.php para adicionar tabelas e campos faltando
 * 
 * Execute em: http://localhost/seu-projeto/update-db.php
 */

// Configurações
$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'programa_cantina';

// Conectar ao MySQL
$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

if ($conn->connect_error) {
    die('❌ Erro na conexão: ' . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

echo "═══════════════════════════════════════<br>";
echo "🔄 ATUALIZAÇÃO - PROGRAMA CANTINA<br>";
echo "═══════════════════════════════════════<br><br>";

$atualizacoes = [];
$erros = [];

/**
 * Adicionar coluna se não existir
 */
function adicionarColuna($conn, $tabela, $coluna, $definicao) {
    global $atualizacoes, $erros;
    
    $resultado = $conn->query("SHOW COLUMNS FROM $tabela LIKE '$coluna'");
    
    if ($resultado && $resultado->num_rows === 0) {
        $sql = "ALTER TABLE $tabela ADD COLUMN $coluna $definicao";
        if ($conn->query($sql)) {
            $atualizacoes[] = "✅ Coluna `$tabela`.`$coluna` adicionada";
            return true;
        } else {
            $erros[] = "❌ Erro ao adicionar coluna `$tabela`.`$coluna`: " . $conn->error;
            return false;
        }
    } else {
        $atualizacoes[] = "ℹ️  Coluna `$tabela`.`$coluna` já existe";
        return true;
    }
}

/**
 * Criar tabela se não existir
 */
function criarTabela($conn, $nomeTabelal, $sql) {
    global $atualizacoes, $erros;
    
    if ($conn->query($sql)) {
        $atualizacoes[] = "✅ Tabela `$nomeTabelal` criada/verificada";
        return true;
    } else {
        if (!str_contains($conn->error, 'already exists')) {
            $erros[] = "❌ Erro ao criar tabela `$nomeTabelal`: " . $conn->error;
        } else {
            $atualizacoes[] = "ℹ️  Tabela `$nomeTabelal` já existe";
        }
        return false;
    }
}

// ============================================
// 1. TABELA USUARIOS - Adicionar campos
// ============================================
echo "<h3>👤 Atualizando tabela USUARIOS...</h3>";

adicionarColuna($conn, 'usuarios', 'id_usuario', 'INT PRIMARY KEY AUTO_INCREMENT FIRST');
adicionarColuna($conn, 'usuarios', 'login', 'VARCHAR(50)');
adicionarColuna($conn, 'usuarios', 'papel', 'VARCHAR(50) DEFAULT "usuario"');
adicionarColuna($conn, 'usuarios', 'nome', 'VARCHAR(100)');
adicionarColuna($conn, 'usuarios', 'atualizado_em', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

// ============================================
// 2. TABELA PRODUTOS - Adicionar campos
// ============================================
echo "<h3>📦 Atualizando tabela PRODUTOS...</h3>";

adicionarColuna($conn, 'produtos', 'id_produto', 'INT PRIMARY KEY AUTO_INCREMENT FIRST');
adicionarColuna($conn, 'produtos', 'tipo_categoria', 'VARCHAR(50)');
adicionarColuna($conn, 'produtos', 'preco_venda', 'DECIMAL(10,2)');
adicionarColuna($conn, 'produtos', 'preco_custo', 'DECIMAL(10,2)');
adicionarColuna($conn, 'produtos', 'estoque_atual', 'INT DEFAULT 0');
adicionarColuna($conn, 'produtos', 'imagem', 'VARCHAR(255)');
adicionarColuna($conn, 'produtos', 'data_criacao', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
adicionarColuna($conn, 'produtos', 'data_atualizacao', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

// Criar índices se não existirem
$conn->query("ALTER TABLE produtos ADD INDEX IF NOT EXISTS idx_estoque (estoque_atual)");
$conn->query("ALTER TABLE produtos ADD INDEX IF NOT EXISTS idx_categoria (tipo_categoria)");

// ============================================
// 3. TABELA VENDAS - Adicionar campos
// ============================================
echo "<h3>💰 Atualizando tabela VENDAS...</h3>";

adicionarColuna($conn, 'vendas', 'id_venda', 'INT PRIMARY KEY AUTO_INCREMENT FIRST');
adicionarColuna($conn, 'vendas', 'numero_venda_dia', 'INT');
adicionarColuna($conn, 'vendas', 'numero_venda_turno', 'INT');
adicionarColuna($conn, 'vendas', 'id_produto', 'INT');
adicionarColuna($conn, 'vendas', 'id_usuario', 'INT');
adicionarColuna($conn, 'vendas', 'valor_total', 'DECIMAL(10,2) DEFAULT 0');
adicionarColuna($conn, 'vendas', 'data_hora', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
adicionarColuna($conn, 'vendas', 'sessao_caixa_id', 'INT NULL');
adicionarColuna($conn, 'vendas', 'status', 'VARCHAR(20) DEFAULT "ativo"');
adicionarColuna($conn, 'vendas', 'subtotal', 'DECIMAL(10,2)');
adicionarColuna($conn, 'vendas', 'atualizado_em', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

// Criar índices
$conn->query("ALTER TABLE vendas ADD INDEX IF NOT EXISTS idx_data (data_hora)");
$conn->query("ALTER TABLE vendas ADD INDEX IF NOT EXISTS idx_usuario (id_usuario)");
$conn->query("ALTER TABLE vendas ADD INDEX IF NOT EXISTS idx_sessao (sessao_caixa_id)");

// ============================================
// 4. TABELA ITENS_VENDA - Criar se não existir
// ============================================
echo "<h3>📋 Criando tabela ITENS_VENDA...</h3>";

$sql_itens = "CREATE TABLE IF NOT EXISTS itens_venda (
    id_item INT PRIMARY KEY AUTO_INCREMENT,
    id_venda INT NOT NULL,
    id_produto INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10,2),
    subtotal DECIMAL(10,2),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_venda) REFERENCES vendas(id_venda) ON DELETE CASCADE,
    FOREIGN KEY (id_produto) REFERENCES produtos(id_produto) ON DELETE RESTRICT,
    INDEX idx_venda (id_venda),
    INDEX idx_produto (id_produto)
)";

criarTabela($conn, 'itens_venda', $sql_itens);

// ============================================
// 5. TABELA CAIXA_SESSOES - Criar se não existir
// ============================================
echo "<h3>💳 Criando tabela CAIXA_SESSOES...</h3>";

$sql_caixa = "CREATE TABLE IF NOT EXISTS caixa_sessoes (
    id_sessao INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    login_usuario VARCHAR(100) NOT NULL,
    abertura_caixa DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fechamento_caixa DATETIME NULL,
    valor_abertura DECIMAL(10,2) DEFAULT 0,
    valor_fechamento DECIMAL(10,2) DEFAULT 0,
    total_vendas DECIMAL(10,2) DEFAULT 0,
    observacoes TEXT,
    status ENUM('aberto','fechado') NOT NULL DEFAULT 'aberto',
    ip_origem VARCHAR(45) NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_usuario_status (id_usuario, status),
    INDEX idx_abertura (abertura_caixa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

criarTabela($conn, 'caixa_sessoes', $sql_caixa);

// ============================================
// 6. TABELA CONFIGURACOES - Criar se não existir
// ============================================
echo "<h3>⚙️  Criando tabela CONFIGURACOES...</h3>";

$sql_config = "CREATE TABLE IF NOT EXISTS configuracoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    tipo VARCHAR(50) DEFAULT 'string',
    descricao VARCHAR(255),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

criarTabela($conn, 'configuracoes', $sql_config);

// Inserir/Atualizar configurações padrão
echo "<p>👉 Inserindo configurações padrão...</p>";

$configs = [
    ['nome_cantina', 'Programa Cantina', 'string', 'Nome da cantina exibido no sistema'],
    ['cor_primaria', '#ff7a45', 'color', 'Cor primária do tema'],
    ['cor_secundaria', '#3498db', 'color', 'Cor secundária do tema'],
    ['cor_acentuacao', '#e74c3c', 'color', 'Cor de acentuação'],
    ['cor_texto', '#ecf0f1', 'color', 'Cor do texto'],
    ['cor_fundo_card', '#34495e', 'color', 'Cor de fundo dos cards'],
    ['logo_url', '', 'string', 'URL da logo'],
    ['banner_url', '', 'string', 'URL do banner'],
    ['cupom_titulo', 'Programa Cantina', 'string', 'Título no cupom'],
    ['cupom_numero_colunas', '40', 'number', 'Número de colunas no cupom']
];

foreach ($configs as $config) {
    $sql = "INSERT INTO configuracoes (chave, valor, tipo, descricao) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE valor = VALUES(valor)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ssss', $config[0], $config[1], $config[2], $config[3]);
        if ($stmt->execute()) {
            $atualizacoes[] = "✅ Configuração `{$config[0]}` inserida/atualizada";
        } else {
            $erros[] = "❌ Erro ao inserir configuração `{$config[0]}`: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ============================================
// 7. CRIAR DIRETÓRIOS
// ============================================
echo "<h3>📁 Criando diretórios...</h3>";

$diretorios = [
    __DIR__ . '/../uploads/',
    __DIR__ . '/../uploads/logos/',
    __DIR__ . '/../uploads/banners/',
    __DIR__ . '/../uploads/produtos/',
    __DIR__ . '/../logs/'
];

foreach ($diretorios as $dir) {
    if (!is_dir($dir)) {
        if (@mkdir($dir, 0755, true)) {
            $atualizacoes[] = "✅ Diretório criado: " . basename($dir);
        } else {
            $erros[] = "❌ Erro ao criar diretório: " . $dir;
        }
    } else {
        $atualizacoes[] = "ℹ️  Diretório já existe: " . basename($dir);
    }
}

// ============================================
// EXIBIR RESULTADOS
// ============================================
echo "<h2>📊 Resultados da Atualização</h2>";

if (!empty($atualizacoes)) {
    echo "<h3 style='color: #27ae60;'>✅ Sucessos:</h3>";
    echo "<ul>";
    foreach ($atualizacoes as $msg) {
        echo "<li>$msg</li>";
    }
    echo "</ul>";
}

if (!empty($erros)) {
    echo "<h3 style='color: #c0392b;'>❌ Erros:</h3>";
    echo "<ul>";
    foreach ($erros as $msg) {
        echo "<li>$msg</li>";
    }
    echo "</ul>";
}

if (empty($erros)) {
    echo "<h2 style='color: #27ae60;'>✅ Banco de dados atualizado com sucesso!</h2>";
    echo "<p>Próximos passos:</p>";
    echo "<ul>";
    echo "<li>Verifique os arquivos criados em <code>/api/upload.php</code></li>";
    echo "<li>Verifique os scripts JS criados em <code>/js/auth.js</code> e <code>/js/utils.js</code></li>";
    echo "<li>Teste o login em <code>login.html</code></li>";
    echo "<li>Acesse o painel administrativo em <code>admin.html</code></li>";
    echo "</ul>";
} else {
    echo "<h2 style='color: #c0392b;'>❌ Houve erros na atualização</h2>";
    echo "<p>Verifique os erros acima e tente novamente.</p>";
}

$conn->close();
?>

<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        line-height: 1.6;
        max-width: 900px;
        margin: 0 auto;
        padding: 20px;
        background: #f5f5f5;
    }
    h1, h2, h3 { color: #333; }
    ul { margin-left: 20px; }
    li { margin: 8px 0; }
    code { 
        background: #f0f0f0; 
        padding: 2px 6px; 
        border-radius: 3px; 
        font-family: monospace;
    }
    p { color: #666; }
</style>
