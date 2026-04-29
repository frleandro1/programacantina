# Documentação de Autenticação - Programa Cantina

## 📋 Índice
1. [Visão Geral](#visão-geral)
2. [Arquivos Principais](#arquivos-principais)
3. [Fluxo de Autenticação](#fluxo-de-autenticação)
4. [Código Detalhado](#código-detalhado)
5. [Banco de Dados](#banco-de-dados)
6. [Segurança](#segurança)
7. [Exemplos de Uso](#exemplos-de-uso)

---

## Visão Geral

O sistema de autenticação do Programa Cantina utiliza:
- **PHP Sessions** para gerenciar sessões de usuário
- **MySQL** para armazenar dados de usuários
- **Password Hashing (BCRYPT)** para segurança de senhas
- **Prepared Statements** para prevenir SQL Injection
- **Validação de Email** com filtros PHP

---

## Arquivos Principais

### 1. **config.php** - Configuração do Banco de Dados

```php
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
    $conn->select_db(DB_NAME);
} else {
    die("Erro ao criar banco de dados: " . $conn->error);
}
?>
```

**Responsabilidades:**
- Conectar ao MySQL
- Criar banco de dados automaticamente
- Criar tabelas necessárias
- Inserir usuário padrão (admin)
- Inserir produtos padrão

---

### 2. **auth.php** - Funções de Autenticação

#### 2.1 Função: `fazerLogin($email, $senha)`

```php
function fazerLogin($email, $senha) {
    global $conn;
    
    // 1. Validar entrada
    if (empty($email) || empty($senha)) {
        return ['success' => false, 'error' => 'Email e senha são obrigatórios'];
    }
    
    // 2. Buscar usuário no banco de dados
    $stmt = $conn->prepare("SELECT id, nome, email, senha, tipo, ativo FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);  // "s" = string
    $stmt->execute();
    $result = $stmt->get_result();
    
    // 3. Verificar se usuário existe
    if ($result->num_rows === 0) {
        return ['success' => false, 'error' => 'Email ou senha incorretos'];
    }
    
    // 4. Obter dados do usuário
    $usuario = $result->fetch_assoc();
    $stmt->close();
    
    // 5. Verificar se usuário está ativo
    if (!$usuario['ativo']) {
        return ['success' => false, 'error' => 'Usuário desativado'];
    }
    
    // 6. Verificar senha com hash BCRYPT
    if (!password_verify($senha, $usuario['senha'])) {
        return ['success' => false, 'error' => 'Email ou senha incorretos'];
    }
    
    // 7. Atualizar último acesso
    $agora = date('Y-m-d H:i:s');
    $update_stmt = $conn->prepare("UPDATE usuarios SET ultimo_acesso = ? WHERE id = ?");
    $update_stmt->bind_param("si", $agora, $usuario['id']);
    $update_stmt->execute();
    $update_stmt->close();
    
    // 8. Criar sessão PHP
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome'];
    $_SESSION['usuario_email'] = $usuario['email'];
    $_SESSION['usuario_tipo'] = $usuario['tipo'];
    $_SESSION['login_time'] = time();
    
    return ['success' => true, 'message' => 'Login realizado com sucesso'];
}
```

**Fluxo:**
1. Valida entrada (email e senha não vazios)
2. Busca usuário no banco usando Prepared Statement
3. Verifica se usuário existe
4. Verifica se usuário está ativo
5. Verifica senha com `password_verify()`
6. Atualiza timestamp do último acesso
7. Cria sessão PHP com dados do usuário
8. Retorna sucesso ou erro

---

#### 2.2 Função: `registrarUsuario($nome, $email, $senha, $confirmar_senha)`

```php
function registrarUsuario($nome, $email, $senha, $confirmar_senha) {
    global $conn;
    
    // 1. Validar entrada
    if (empty($nome) || empty($email) || empty($senha) || empty($confirmar_senha)) {
        return ['success' => false, 'error' => 'Todos os campos são obrigatórios'];
    }
    
    // 2. Validar email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Email inválido'];
    }
    
    // 3. Validar comprimento da senha
    if (strlen($senha) < 6) {
        return ['success' => false, 'error' => 'Senha deve ter no mínimo 6 caracteres'];
    }
    
    // 4. Validar confirmação de senha
    if ($senha !== $confirmar_senha) {
        return ['success' => false, 'error' => 'Senhas não conferem'];
    }
    
    // 5. Verificar se email já existe
    $check_stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $check_stmt->close();
        return ['success' => false, 'error' => 'Email já cadastrado'];
    }
    $check_stmt->close();
    
    // 6. Hash da senha com BCRYPT
    $senha_hash = password_hash($senha, PASSWORD_BCRYPT);
    $tipo = 'atendente'; // Tipo padrão para novos usuários
    
    // 7. Inserir usuário no banco
    $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nome, $email, $senha_hash, $tipo);
    
    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Usuário registrado com sucesso!'];
    } else {
        $stmt->close();
        return ['success' => false, 'error' => 'Erro ao registrar usuário: ' . $conn->error];
    }
}
```

**Validações:**
- Campos obrigatórios
- Formato de email válido
- Senha com mínimo 6 caracteres
- Confirmação de senha
- Email único no banco de dados

---

#### 2.3 Função: `fazerLogout()`

```php
function fazerLogout() {
    session_destroy();
    return ['success' => true, 'message' => 'Logout realizado com sucesso'];
}
```

**O que faz:**
- Destrói a sessão PHP
- Remove todos os dados do usuário

---

#### 2.4 Funções de Verificação

```php
// Verificar se usuário está logado
function usuarioLogado() {
    return isset($_SESSION['usuario_id']) && isset($_SESSION['usuario_nome']);
}

// Obter dados do usuário logado
function obterUsuarioLogado() {
    if (!usuarioLogado()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['usuario_id'],
        'nome' => $_SESSION['usuario_nome'],
        'email' => $_SESSION['usuario_email'],
        'tipo' => $_SESSION['usuario_tipo']
    ];
}

// Verificar permissão por tipo de usuário
function temPermissao($tipo_requerido) {
    if (!usuarioLogado()) {
        return false;
    }
    
    $tipo_usuario = $_SESSION['usuario_tipo'];
    
    // Admin tem acesso a tudo
    if ($tipo_usuario === 'admin') {
        return true;
    }
    
    // Verificar permissões específicas
    $permissoes = [
        'admin' => ['admin', 'gerente', 'atendente', 'produtor'],
        'gerente' => ['gerente', 'atendente', 'produtor'],
        'atendente' => ['atendente'],
        'produtor' => ['produtor']
    ];
    
    if (!isset($permissoes[$tipo_usuario])) {
        return false;
    }
    
    return in_array($tipo_requerido, $permissoes[$tipo_usuario]);
}
```

---

### 3. **check-auth.php** - Proteção de Rotas

```php
<?php
session_start();
require_once 'config.php';

// Verificar se usuário está autenticado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_nome'])) {
    // Se for AJAX, retornar JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado']);
        exit;
    }
    
    // Caso contrário, redirecionar para login
    header('Location: login.html');
    exit;
}

// Verificar timeout de sessão (1 hora)
function verificarTimeoutSessao($timeout = 3600) {
    if (isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > $timeout) {
            session_destroy();
            header('Location: login.html?msg=Sua+sessão+expirou');
            exit;
        }
    }
}

verificarTimeoutSessao();
?>
```

**Uso em páginas protegidas:**
```php
<?php
require_once 'check-auth.php';

// Agora você pode usar:
$usuario = obterUsuarioAtual();
echo "Bem-vindo, " . $usuario['nome'];
?>
```

---

## Fluxo de Autenticação

### 1. Login (Requisição AJAX)

```
Cliente (login.html)
    ↓
fetch('auth.php?api=login', {
    method: 'POST',
    body: JSON.stringify({email, senha})
})
    ↓
Servidor (auth.php)
    ↓
fazerLogin($email, $senha)
    ↓
Validar entrada → Buscar usuário → Verificar senha → Criar sessão
    ↓
Retornar JSON {success: true/false}
    ↓
Cliente redireciona para index.html
```

### 2. Verificação de Autenticação

```
Cliente (index.html)
    ↓
fetch('auth.php?api=usuario', {method: 'POST'})
    ↓
Servidor verifica $_SESSION
    ↓
Retorna dados do usuário ou erro
    ↓
Se não autenticado → redireciona para login.html
```

### 3. Logout

```
Cliente clica em "Sair"
    ↓
fetch('auth.php?api=logout', {method: 'POST'})
    ↓
Servidor destrói sessão
    ↓
Redireciona para login.html
```

---

## Banco de Dados

### Tabela: `usuarios`

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
- `nome`: Nome completo do usuário
- `email`: Email único (chave de login)
- `senha`: Hash BCRYPT da senha
- `tipo`: Tipo de usuário (define permissões)
- `ativo`: Se o usuário pode fazer login
- `data_criacao`: Quando a conta foi criada
- `ultimo_acesso`: Último login do usuário

---

## Segurança

### 1. **Password Hashing com BCRYPT**

```php
// Ao registrar:
$senha_hash = password_hash($senha, PASSWORD_BCRYPT);

// Ao fazer login:
if (password_verify($senha, $usuario['senha'])) {
    // Senha correta
}
```

**Por que BCRYPT?**
- Algoritmo criptográfico forte
- Resistente a força bruta
- Inclui salt automático
- Ajustável para futuro (cost factor)

### 2. **Prepared Statements (Previne SQL Injection)**

```php
// ❌ Inseguro:
$sql = "SELECT * FROM usuarios WHERE email = '$email'";

// ✅ Seguro:
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
```

### 3. **Validação de Email**

```php
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return ['success' => false, 'error' => 'Email inválido'];
}
```

### 4. **Sessão com Timeout**

```php
function verificarTimeoutSessao($timeout = 3600) { // 1 hora
    if (isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > $timeout) {
            session_destroy();
            header('Location: login.html?msg=Sua+sessão+expirou');
            exit;
        }
    }
}
```

### 5. **Verificação de Usuário Ativo**

```php
if (!$usuario['ativo']) {
    return ['success' => false, 'error' => 'Usuário desativado'];
}
```

---

## Exemplos de Uso

### Exemplo 1: Login via AJAX

**Cliente (JavaScript):**
```javascript
async function fazerLogin(email, senha) {
    const response = await fetch('auth.php?api=login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            email: email,
            senha: senha
        })
    });
    
    const data = await response.json();
    
    if (data.success) {
        window.location.href = 'index.html';
    } else {
        alert(data.error);
    }
}
```

### Exemplo 2: Proteger uma Página PHP

**Arquivo: `dashboard.php`**
```php
<?php
require_once 'check-auth.php';

// Agora a página está protegida
// Apenas usuários autenticados podem acessar

$usuario = obterUsuarioAtual();
echo "Bem-vindo, " . htmlspecialchars($usuario['nome']);

// Verificar permissão específica
if (verificarPermissao('admin')) {
    echo "Você é administrador";
}
?>
```

### Exemplo 3: Registrar Novo Usuário

**Cliente (JavaScript):**
```javascript
async function registrar(nome, email, senha, confirmarSenha) {
    const response = await fetch('auth.php?api=registrar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            nome: nome,
            email: email,
            senha: senha,
            confirmar_senha: confirmarSenha
        })
    });
    
    const data = await response.json();
    
    if (data.success) {
        alert('Conta criada! Faça login para continuar');
        window.location.href = 'login.html';
    } else {
        alert(data.error);
    }
}
```

### Exemplo 4: Fazer Logout

**Cliente (JavaScript):**
```javascript
async function logout() {
    const response = await fetch('auth.php?api=logout', {
        method: 'POST'
    });
    
    const data = await response.json();
    
    if (data.success) {
        window.location.href = 'login.html';
    }
}
```

### Exemplo 5: Obter Dados do Usuário Logado

**Cliente (JavaScript):**
```javascript
async function obterDadosUsuario() {
    const response = await fetch('auth.php?api=usuario', {
        method: 'POST'
    });
    
    const data = await response.json();
    
    if (data.success) {
        console.log('Usuário:', data.usuario);
        // {
        //   id: 1,
        //   nome: 'Administrador',
        //   email: 'admin@cantina.com',
        //   tipo: 'admin'
        // }
    } else {
        // Não autenticado
        window.location.href = 'login.html';
    }
}
```

---

## Tipos de Usuário e Permissões

| Tipo | Descrição | Permissões |
|------|-----------|-----------|
| **admin** | Administrador | Acesso total |
| **gerente** | Gerente | Gerenciar produção e vendas |
| **atendente** | Atendente | Registrar vendas |
| **produtor** | Produtor | Registrar produção |

---

## Checklist de Segurança

- [x] Senhas com hash BCRYPT
- [x] Prepared Statements para SQL Injection
- [x] Validação de email
- [x] Verificação de usuário ativo
- [x] Timeout de sessão
- [x] Proteção de rotas
- [x] Tratamento de erros
- [x] Email único no banco

---

## Conclusão

O sistema de autenticação do Programa Cantina implementa as melhores práticas de segurança web, incluindo criptografia de senhas, proteção contra SQL Injection, validação de entrada e gerenciamento seguro de sessões.
