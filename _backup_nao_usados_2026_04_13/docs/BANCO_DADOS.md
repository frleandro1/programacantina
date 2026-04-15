# Configuração do Banco de Dados - Programa Cantina

## 📋 Pré-requisitos
- MySQL/MariaDB instalado e rodando
- PHP 7.4 ou superior
- Um servidor web (Apache, Nginx, etc.)

## 🔧 Instalação

### 1. Criar o Banco de Dados

#### Opção A: Usando phpMyAdmin
1. Abra http://localhost/phpmyadmin
2. Clique em "SQL"
3. Copie todo o conteúdo de `db/schema.sql`
4. Cole no campo SQL e execute

#### Opção B: Usando MySQL CLI
```bash
mysql -u root < db/schema.sql
```

### 2. Configurar Credenciais do Banco

Se suas credenciais do MySQL são diferentes, edite o arquivo `db/config.php`:

```php
define('DB_HOST', 'localhost');      // seu host
define('DB_USER', 'root');            // seu usuário
define('DB_PASSWORD', '');            // sua senha
define('DB_NAME', 'programa_cantina'); // nome do banco
```

### 3. Testar Conexão

Abra no navegador:
```
http://localhost/seu-projeto/api/produtos.php?action=listar
```

Se receber um JSON com os produtos, está funcionando!

---

## 📚 Endpoints da API

### Autenticação
- **POST** `/api/auth.php?action=login`
  ```json
  { "username": "admin", "password": "admin123" }
  ```

- **POST** `/api/auth.php?action=logout`

- **GET** `/api/auth.php?action=verificar`

### Produtos
- **GET** `/api/produtos.php?action=listar` - Lista todos os produtos
- **GET** `/api/produtos.php?action=obter&id=1` - Obter um produto
- **POST** `/api/produtos.php?action=criar` - Criar novo produto
- **PUT** `/api/produtos.php?action=atualizar` - Atualizar produto
- **DELETE** `/api/produtos.php?action=deletar` - Deletar produto

### Vendas
- **GET** `/api/vendas.php?action=listar` - Lista todas as vendas
- **POST** `/api/vendas.php?action=criar` - Criar nova venda

---

## 📊 Credenciais Padrão

**Usuário:** `admin`
**Senha:** `admin123`

---

## 🚀 Próximos Passos

1. Criar interface HTML para o CRUD
2. Integrar JavaScript para fazer requisições à API
3. Adicionar validações no frontend
4. Implementar segurança (CSRF, sanitização, etc.)

Precisa de ajuda com mais alguma coisa?
