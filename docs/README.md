# 🍴 Programa Cantina - Guia de Uso

## ✅ Arquivos Principais

```
ProgramaCantina Dezembro/
├── login.html              ← Página de login
├── dashboard.html          ← Dashboard principal
├── teste-conexao.php       ← Verificar conexão BD
├── install.php             ← Instalação automática do banco
├── api/
│   ├── auth.php            ← Autenticação
│   ├── produtos.php        ← CRUD Produtos
│   └── vendas.php          ← CRUD Vendas
├── db/
│   ├── config.php          ← Configuração BD
│   └── schema.sql          ← Script SQL
├── js/
│   └── dashboard.js        ← Lógica JavaScript
└── README.md               ← Este arquivo
```

---

## 🚀 Como Usar

### Passo 1: Verificar Conexão
1. Abra no navegador: `http://localhost/seu-projeto/teste-conexao.php`
2. Verifique se está conectado ao banco `programa_cantina`
3. Se falhar, ajuste as credenciais em `db/config.php`

### Passo 2: Instalar Banco (Se Necessário)
Se o teste mostrar que faltam tabelas:
1. Execute: `http://localhost/seu-projeto/install.php`
2. O script cria banco, tabelas e dados iniciais

### Passo 3: Fazer Login
1. Acesse: `http://localhost/seu-projeto/login.html`
2. Use: `admin` / `admin123`

### Passo 4: Usar o Dashboard
- 📊 **Dashboard:** Estatísticas e últimas vendas
- 📦 **Produtos:** CRUD completo de produtos
- 💰 **Vendas:** Registrar e consultar vendas

---

## 🔧 Configurar Banco de Dados

Se seus dados do MySQL são diferentes, edite `db/config.php`:

```php
define('DB_HOST', 'localhost');      // seu host
define('DB_USER', 'root');            // seu usuário
define('DB_PASSWORD', '');            // sua senha
define('DB_NAME', 'programa_cantina'); // seu banco
```

---

## 📊 Estrutura do Banco

### Tabela: usuarios
```
id         INT (PK)
username   VARCHAR(50) UNIQUE
password   VARCHAR(255) [hash SHA256]
email      VARCHAR(100)
tipo       VARCHAR(20) [admin/usuario]
criado_em  TIMESTAMP
```

### Tabela: produtos
```
id           INT (PK)
nome         VARCHAR(100)
descricao    TEXT
preco        DECIMAL(10,2)
categoria    VARCHAR(50)
estoque      INT
status       VARCHAR(20) [active/inactive]
criado_em    TIMESTAMP
atualizado_em TIMESTAMP
```

### Tabela: vendas
```
id              INT (PK)
numero_pedido   INT UNIQUE
produto_id      INT (FK produtos)
quantidade      INT
preco_unitario  DECIMAL(10,2)
total           DECIMAL(10,2)
usuario_id      INT (FK usuarios)
data_venda      TIMESTAMP
```

### Tabela: movimentacoes
```
id                  INT (PK)
produto_id          INT (FK produtos)
tipo                VARCHAR(20)
quantidade          INT
descricao           TEXT
usuario_id          INT (FK usuarios)
data_movimentacao   TIMESTAMP
```

---

## 🔐 Credenciais Padrão

**Usuário:** `admin`  
**Senha:** `admin123`

---

## 📚 API Endpoints

### Autenticação
- `POST /api/auth.php?action=login` - Fazer login
- `POST /api/auth.php?action=logout` - Sair
- `GET /api/auth.php?action=verificar` - Verificar sessão

### Produtos
- `GET /api/produtos.php?action=listar` - Listar produtos
- `GET /api/produtos.php?action=obter&id=1` - Obter produto
- `POST /api/produtos.php?action=criar` - Criar produto
- `PUT /api/produtos.php?action=atualizar` - Atualizar produto
- `DELETE /api/produtos.php?action=deletar` - Deletar produto

### Vendas
- `GET /api/vendas.php?action=listar` - Listar vendas
- `POST /api/vendas.php?action=criar` - Registrar venda

---

## 💡 Dicas Úteis

### Resetar Banco
Execute no phpMyAdmin:
```sql
DROP DATABASE programa_cantina;
CREATE DATABASE programa_cantina;
```

Depois execute `install.php` novamente.

### Mudar Senha do Admin
Execute no phpMyAdmin:
```sql
UPDATE usuarios 
SET password = SHA2('nova_senha', 256) 
WHERE username = 'admin';
```

### Verificar Estoque
```sql
SELECT nome, estoque FROM produtos;
```

---

## 🐛 Solução de Problemas

### Erro: "Access denied"
- Verifique `db/config.php`
- Confirme usuário e senha do MySQL

### Erro: "Table doesn't exist"
- Execute `teste-conexao.php`
- Se faltar tabelas, execute `install.php`

### Blank Login Page
- Abra o DevTools (F12) e verifique o Console
- Verifique se `teste-conexao.php` funciona

### Vendas não atualizam estoque
- Verifique se há erro no console (F12)
- Confirme permissões do banco

---

## 📞 Suporte

Se precisar de ajuda:
1. Execute `teste-conexao.php` para verificar a conexão
2. Verifique os logs do browser (F12)
3. Verifique o erro no phpMyAdmin

---

**Programa Cantina v1.0** | Dezembro 2025
