# 📊 Documentação de Persistência de Dados - Programa Cantina

## Visão Geral

O Programa Cantina implementa um **sistema completo de persistência de dados** com MySQL, incluindo:

- ✅ **5 Tabelas principais** com relacionamentos
- ✅ **Funções CRUD** (Create, Read, Update, Delete)
- ✅ **API REST** para operações de dados
- ✅ **Transações** para integridade de dados
- ✅ **Controle de permissões** por tipo de usuário

---

## 📁 Estrutura do Banco de Dados

### Banco de Dados: `programa_cantina`

#### 1. Tabela: `usuarios`
Armazena informações de usuários do sistema.

```sql
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('admin', 'gerente', 'atendente', 'produtor') DEFAULT 'atendente',
    ativo BOOLEAN DEFAULT TRUE,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acesso TIMESTAMP NULL
);
```

**Campos:**
- `id`: Identificador único
- `nome`: Nome completo
- `email`: Email único (login)
- `senha`: Hash BCRYPT da senha
- `tipo`: Tipo de usuário (define permissões)
- `ativo`: Se pode fazer login
- `data_criacao`: Quando foi criado
- `ultimo_acesso`: Último login

---

#### 2. Tabela: `produtos`
Armazena informações de produtos da cantina.

```sql
CREATE TABLE produtos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL UNIQUE,
    categoria VARCHAR(50) NOT NULL,
    preco_venda DECIMAL(10, 2) NOT NULL,
    preco_custo DECIMAL(10, 2),
    estoque INT DEFAULT 0,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Campos:**
- `id`: Identificador único
- `nome`: Nome do produto (único)
- `categoria`: Categoria (Salgado, Doce, Bebida, etc)
- `preco_venda`: Preço de venda ao cliente
- `preco_custo`: Custo de produção
- `estoque`: Quantidade disponível
- `status`: Ativo ou inativo
- `data_criacao`: Quando foi cadastrado

**Produtos Padrão:**
- Pão de Queijo (R$ 3.50)
- Bolo de Cenoura (R$ 5.00)
- Coxinha (R$ 4.00)
- Brigadeiro (R$ 2.50)
- Suco Natural (R$ 4.50)
- Café (R$ 2.00)
- Sanduíche (R$ 8.00)
- Salada (R$ 6.00)

---

#### 3. Tabela: `vendas`
Armazena informações de transações de venda.

```sql
CREATE TABLE vendas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    data_venda TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atendente VARCHAR(100) NOT NULL,
    total DECIMAL(10, 2) NOT NULL
);
```

**Campos:**
- `id`: Identificador único da venda
- `data_venda`: Data e hora da venda
- `atendente`: Nome do atendente que registrou
- `total`: Valor total da venda

---

#### 4. Tabela: `itens_venda`
Armazena os itens individuais de cada venda.

```sql
CREATE TABLE itens_venda (
    id INT PRIMARY KEY AUTO_INCREMENT,
    venda_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (venda_id) REFERENCES vendas(id),
    FOREIGN KEY (produto_id) REFERENCES produtos(id)
);
```

**Campos:**
- `id`: Identificador único
- `venda_id`: Referência à venda
- `produto_id`: Referência ao produto
- `quantidade`: Quantidade vendida
- `preco_unitario`: Preço do produto na venda
- `subtotal`: Quantidade × Preço

---

#### 5. Tabela: `movimentacoes`
Armazena entrada e saída de produtos (produção).

```sql
CREATE TABLE movimentacoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    produto_id INT NOT NULL,
    tipo ENUM('entrada', 'saida') NOT NULL,
    quantidade INT NOT NULL,
    motivo VARCHAR(255),
    responsavel VARCHAR(100),
    data_movimentacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES produtos(id)
);
```

**Campos:**
- `id`: Identificador único
- `produto_id`: Referência ao produto
- `tipo`: Entrada (produção) ou Saída (venda)
- `quantidade`: Quantidade movimentada
- `motivo`: Motivo da movimentação
- `responsavel`: Quem registrou
- `data_movimentacao`: Quando foi registrado

---

## 🔧 Funções CRUD (database.php)

### Funções para Produtos

#### `obterProdutos($status = '')`
Retorna lista de produtos, opcionalmente filtrada por status.

```php
$produtos = obterProdutos('ativo');
// Retorna: Array com todos os produtos ativos
```

#### `obterProdutoPorId($id)`
Retorna um produto específico.

```php
$produto = obterProdutoPorId(1);
// Retorna: Array com dados do produto ou null
```

#### `criarProduto($nome, $categoria, $preco_venda, $preco_custo)`
Cria novo produto.

```php
$resultado = criarProduto('Bolo de Chocolate', 'Doce', 6.00, 2.50);
// Retorna: ['success' => true, 'id' => 9]
```

#### `atualizarProduto($id, $dados)`
Atualiza um produto.

```php
$resultado = atualizarProduto(1, [
    'preco_venda' => 4.00,
    'estoque' => 50
]);
// Retorna: ['success' => true]
```

#### `deletarProduto($id)`
Deleta um produto.

```php
$resultado = deletarProduto(1);
// Retorna: ['success' => true]
```

---

### Funções para Vendas

#### `criarVenda($atendente, $total, $itens)`
Cria nova venda com itens.

```php
$resultado = criarVenda('João Silva', 25.50, [
    [
        'produto_id' => 1,
        'quantidade' => 2,
        'preco_unitario' => 3.50,
        'subtotal' => 7.00
    ],
    [
        'produto_id' => 2,
        'quantidade' => 1,
        'preco_unitario' => 5.00,
        'subtotal' => 5.00
    ]
]);
// Retorna: ['success' => true, 'id' => 1]
// Atualiza automaticamente o estoque
```

#### `obterVendas($limite, $offset)`
Retorna lista de vendas.

```php
$vendas = obterVendas(50, 0);
// Retorna: Array com últimas 50 vendas
```

#### `obterVendaPorId($id)`
Retorna venda com seus itens.

```php
$venda = obterVendaPorId(1);
// Retorna: Array com venda e array de itens
```

---

### Funções para Movimentações

#### `registrarMovimentacao($produto_id, $tipo, $quantidade, $responsavel, $motivo)`
Registra entrada ou saída de produto.

```php
$resultado = registrarMovimentacao(1, 'entrada', 100, 'Maria', 'Produção');
// Retorna: ['success' => true, 'id' => 1]
// Atualiza automaticamente o estoque
```

#### `obterMovimentacoes($limite, $offset)`
Retorna lista de movimentações.

```php
$movimentacoes = obterMovimentacoes(50, 0);
// Retorna: Array com últimas 50 movimentações
```

#### `obterMovimentacoesPorProduto($produto_id, $limite)`
Retorna movimentações de um produto específico.

```php
$movimentacoes = obterMovimentacoesPorProduto(1, 50);
// Retorna: Array com movimentações do produto 1
```

---

### Funções para Estatísticas

#### `obterEstatisticas()`
Retorna estatísticas gerais.

```php
$stats = obterEstatisticas();
// Retorna: [
//     'total_vendas' => 15,
//     'receita_total' => 125.50,
//     'estoque_total' => 450,
//     'produtos_total' => 8,
//     'ticket_medio' => 8.37
// ]
```

#### `obterEstatisticasVendasPeriodo($data_inicio, $data_fim)`
Retorna estatísticas de um período.

```php
$stats = obterEstatisticasVendasPeriodo('2024-01-01', '2024-01-31');
// Retorna: Array com vendas por dia
```

#### `obterProdutosBaixoEstoque($minimo)`
Retorna produtos com estoque baixo.

```php
$produtos = obterProdutosBaixoEstoque(10);
// Retorna: Array com produtos com estoque <= 10
```

#### `obterProdutosMaisVendidos($limite)`
Retorna produtos mais vendidos.

```php
$produtos = obterProdutosMaisVendidos(10);
// Retorna: Array com 10 produtos mais vendidos
```

---

## 🔌 API REST (data-api.php)

### Endpoints para Produtos

#### `GET /data-api.php?action=produtos_listar`
Lista todos os produtos ativos.

**Resposta:**
```json
{
    "success": true,
    "produtos": [
        {
            "id": 1,
            "nome": "Pão de Queijo",
            "categoria": "Salgado",
            "preco_venda": "3.50",
            "estoque": 50,
            "status": "ativo"
        }
    ]
}
```

#### `GET /data-api.php?action=produto_obter&id=1`
Obtém um produto específico.

#### `POST /data-api.php?action=produto_criar`
Cria novo produto.

**Body:**
```json
{
    "nome": "Bolo de Chocolate",
    "categoria": "Doce",
    "preco_venda": 6.00,
    "preco_custo": 2.50
}
```

#### `POST /data-api.php?action=produto_atualizar`
Atualiza um produto.

**Body:**
```json
{
    "id": 1,
    "preco_venda": 4.00,
    "estoque": 50
}
```

#### `GET /data-api.php?action=produto_deletar&id=1`
Deleta um produto.

---

### Endpoints para Vendas

#### `GET /data-api.php?action=vendas_listar`
Lista vendas.

#### `GET /data-api.php?action=venda_obter&id=1`
Obtém detalhes de uma venda.

#### `POST /data-api.php?action=venda_criar`
Cria nova venda.

**Body:**
```json
{
    "atendente": "João Silva",
    "total": 25.50,
    "itens": [
        {
            "produto_id": 1,
            "quantidade": 2,
            "preco_unitario": 3.50,
            "subtotal": 7.00
        }
    ]
}
```

---

### Endpoints para Movimentações

#### `GET /data-api.php?action=movimentacoes_listar`
Lista movimentações.

#### `POST /data-api.php?action=movimentacao_registrar`
Registra nova movimentação.

**Body:**
```json
{
    "produto_id": 1,
    "tipo": "entrada",
    "quantidade": 100,
    "responsavel": "Maria",
    "motivo": "Produção"
}
```

---

### Endpoints para Estatísticas

#### `GET /data-api.php?action=estatisticas`
Retorna estatísticas gerais.

#### `GET /data-api.php?action=produtos_baixo_estoque`
Retorna produtos com baixo estoque.

#### `GET /data-api.php?action=produtos_mais_vendidos`
Retorna produtos mais vendidos.

---

## 🔐 Segurança

### Proteções Implementadas

✅ **Transações ACID** - Integridade de dados garantida
✅ **Prepared Statements** - Proteção contra SQL Injection
✅ **Controle de Permissões** - Verificação em cada endpoint
✅ **Validação de Entrada** - Todos os dados são validados
✅ **Atualização Automática** - Estoque atualizado automaticamente

### Exemplo de Transação

```php
// Ao criar venda, a transação garante que:
// 1. Venda é inserida
// 2. Itens são inseridos
// 3. Estoque é atualizado
// Se algo falhar, tudo é revertido
```

---

## 📊 Fluxo de Dados

### Fluxo de Venda

```
1. Vendedor adiciona produtos ao carrinho
   ↓
2. Frontend envia POST para venda_criar
   ↓
3. Backend inicia transação
   ↓
4. Insere venda na tabela vendas
   ↓
5. Insere itens na tabela itens_venda
   ↓
6. Atualiza estoque em produtos
   ↓
7. Confirma transação
   ↓
8. Retorna ID da venda
```

### Fluxo de Produção

```
1. Produtor registra produção
   ↓
2. Frontend envia POST para movimentacao_registrar
   ↓
3. Backend inicia transação
   ↓
4. Insere movimentação na tabela movimentacoes
   ↓
5. Atualiza estoque em produtos (adiciona)
   ↓
6. Confirma transação
   ↓
7. Retorna ID da movimentação
```

---

## 🎯 Boas Práticas

1. **Sempre usar Prepared Statements** - Evita SQL Injection
2. **Validar entrada** - Nunca confiar em dados do cliente
3. **Usar transações** - Para operações que afetam múltiplas tabelas
4. **Verificar permissões** - Em cada operação
5. **Registrar ações** - Para auditoria
6. **Fazer backup** - Regularmente

---

## 📈 Exemplos de Uso

### Exemplo 1: Registrar Venda Completa

```php
// 1. Obter produtos
$produtos = obterProdutos('ativo');

// 2. Preparar itens
$itens = [
    [
        'produto_id' => 1,
        'quantidade' => 2,
        'preco_unitario' => 3.50,
        'subtotal' => 7.00
    ]
];

// 3. Calcular total
$total = array_sum(array_column($itens, 'subtotal'));

// 4. Criar venda
$resultado = criarVenda('João Silva', $total, $itens);

if ($resultado['success']) {
    echo "Venda #" . $resultado['id'] . " registrada!";
}
```

### Exemplo 2: Obter Relatório de Vendas

```php
// 1. Obter estatísticas gerais
$stats = obterEstatisticas();

// 2. Obter vendas do período
$vendas = obterEstatisticasVendasPeriodo('2024-01-01', '2024-01-31');

// 3. Obter produtos mais vendidos
$top_produtos = obterProdutosMaisVendidos(10);

// 4. Exibir relatório
echo "Total de vendas: " . $stats['total_vendas'];
echo "Receita: R$ " . $stats['receita_total'];
echo "Ticket médio: R$ " . $stats['ticket_medio'];
```

---

## 🔍 Troubleshooting

### "Erro ao criar venda"
- Verificar se produtos existem
- Verificar se estoque é suficiente
- Verificar conexão com banco de dados

### "Dados não aparecem"
- Verificar permissões do usuário
- Verificar se dados foram realmente salvos
- Verificar logs do servidor

### "Estoque não atualiza"
- Verificar se transação foi confirmada
- Verificar se há erro no SQL
- Verificar permissões de escrita

---

## 📚 Próximos Passos

1. **Implementar backup automático** - Proteger dados
2. **Adicionar auditoria** - Registrar todas as ações
3. **Criar relatórios avançados** - Análises detalhadas
4. **Implementar sincronização** - Com múltiplos terminais
5. **Adicionar cache** - Melhorar performance

---

## 📞 Suporte

Para dúvidas sobre persistência de dados:
- Consulte `database.php` - Funções CRUD
- Consulte `data-api.php` - Endpoints REST
- Consulte `persistence.js` - Integração frontend
