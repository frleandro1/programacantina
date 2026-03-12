# 📋 Análise Completa - Arquivos a Adicionar/Corrigir
## Programa Cantina - Data: 11.03.2026

---

## ✅ ARQUIVOS JÁ PRESENTES

### 📄 HTML (10 arquivos)
- ✅ `login.html` - Página de login
- ✅ `index.html` - Redirecionador para dashboard
- ✅ `dashboard.html` - Painel principal
- ✅ `vendas.html` - Sistema de vendas
- ✅ `admin.html` - Painel administrativo completo
- ✅ `admin-imagens.html` - Gerenciamento de imagens
- ✅ `relatorios.html` - Sistema de relatórios
- ✅ `monitoramento.html` - Monitoramento de estoque
- ✅ `login-menu.html` - Menu de seleção de login
- ✅ `customizador-cupom.html` - Customização de cupom

### 🔧 API PHP (9 arquivos + 1 utilitário)
- ✅ `api/auth.php` - Autenticação e gerenciamento de caixa
- ✅ `api/produtos.php` - CRUD de produtos
- ✅ `api/vendas.php` - CRUD de vendas
- ✅ `api/imagens.php` - Upload e gerenciamento de imagens  
- ✅ `api/configuracoes.php` - Gerenciamento de configurações
- ✅ `api/cupom-config.php` - Configurações de cupom
- ✅ `api/diagnostico.php` - Diagnóstico do sistema
- ✅ `api/relatorios.php` - Relatórios analíticos
- ✅ `api/system-status.php` - Health check do sistema
- ✅ `criar-usuario-vendas.php` - Script de setup

### 📦 JavaScript (5 arquivos)
- ✅ `js/vendas.js` - Lógica de carrinho e vendas
- ✅ `js/vendas-config.js` - Configurações dinâmicas
- ✅ `js/dashboard.js` - Dashboard com gráficos
- ✅ `js/relatorios.js` - Relatórios e exportação
- ✅ `js/configuracoes.js` - Gerenciamento de configs

### 🗄️ Banco de Dados
- ✅ `db/config.php` - Configuração de conexão
- ✅ `db/schema.sql` - Script de criação de tabelas

### 🎨 Estilos
- ✅ `css/theme.css` - Theme principal com variáveis CSS

---

## ⚠️ ARQUIVOS FALTANDO OU INCOMPLETOS

### 1. 🚨 TABELA CONFIGURAÇÕES (BANCO DE DADOS)
**Status:** ❌ FALTANDO  
**Arquivo:** `db/schema.sql`  
**Problema:** A tabela `configuracoes` é referenciada em:
- `api/configuracoes.php` (linhas 25-44)
- `js/configuracoes.js` (carrega dados da tabela)
- `admin.html` (seção de configurações)

**SQL Necessário:**
```sql
CREATE TABLE IF NOT EXISTS configuracoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    tipo VARCHAR(50) DEFAULT 'string',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inserir valores padrão
INSERT INTO configuracoes (chave, valor, tipo) VALUES
('nome_cantina', 'Programa Cantina', 'string'),
('cor_primaria', '#ff7a45', 'color'),
('cor_secundaria', '#3498db', 'color'),
('cor_acentuacao', '#e74c3c', 'color'),
('cor_texto', '#ecf0f1', 'color'),
('cor_fundo_card', '#34495e', 'color'),
('logo_url', '', 'string'),
('banner_url', '', 'string'),
('cupom_titulo', 'Programa Cantina', 'string'),
('cupom_numero_colunas', '40', 'number');
```

---

### 2. 📤 API DE UPLOAD (api/upload.php)
**Status:** ❌ FALTANDO  
**Chamado por:**
- `admin.html` (funções `salvarLogo()`, `salvarBanner()`, `deletarLogo()`, `deletarBanner()`)
- `js/configuracoes.js` (eventos de input de arquivo)

**Funcionalidades Necessárias:**
- Upload de logo (JPG, PNG, GIF, WebP)
- Upload de banner (JPG, PNG, GIF, WebP)
- Delete de logo e banner
- Validação de tipo e tamanho
- Path: `/uploads/` e `/uploads/produtos/`

**Endpoints Esperados:**
```
POST /api/upload.php?action=upload_logo
POST /api/upload.php?action=upload_banner
DELETE /api/upload.php?action=delete_logo
DELETE /api/upload.php?action=delete_banner
```

---

### 3. 🔐 ARQUIVO DE AUTENTICAÇÃO (js/auth.js)
**Status:** ❌ FALTANDO  
**Necessário para:** Gerenciar tokens e sessões client-side

**Funcionalidades:**
- Verificar se usuário está logado
- Armazenar/recuperar token JWT
- Renovar token
- Logout
- Redirecionar para login se token expirado

**Métodos Esperados:**
```javascript
obterToken()
armazenarToken(token)
limparToken()
usuarioLogado()
obterDadosUsuario()
logout()
```

---

### 4. 📊 ARQUIVO DE UTILITÁRIOS (js/utils.js)
**Status:** ⚠️ PARCIALMENTE FALTANDO  
**Necessário para:** Funções compartilhadas

**Funções Necessárias:**
- `formatarMoeda()` - Já existe em alguns arquivos, poderia ser centralizada
- `formatarData()` - Formatação de datas
- `obterDataAtual()` - Data no formato correto
- `mostrarNotificacao()` - Sistema de notificações
- `exibirErro()` - Tratamento de erros
- `exibirSucesso()` - Feedback de sucesso
- `validarEmail()` - Validação de email
- `validarTelefone()` - Validação de telefone
- `gerarID()` - Gerar IDs únicos
- `downloadJSON()` e `downloadCSV()` - Já existem em relatorios.js

---

### 5. ⚙️ ARQUIVO DE CONFIGURAÇÃO GERAL (config.js)
**Status:** ⚠️ PARCIALMENTE FALTANDO  
**Necessário para:** Centralizarassistência de constantes

**Constantes Necessárias:**
```javascript
const API_BASE_URL = 'http://localhost/Cantina/api';
const CATEGORIAS_PRODUTO = ['Salgado', 'Doce', 'Bebida', 'Outro'];
const TIPOS_USUARIO = ['admin', 'atendente', 'producao', 'gestor'];
const MOEDA_LOCALE = 'pt-BR';
const MOEDA_CURRENCY = 'BRL';
```

---

### 6. 🏠 ARQUIVO DE HOME/DASHBOARD (home.html ou dashboard-public.html)
**Status:** ⚠️ PODE ESTAR INCOMPLETO  
**Observação:** `index.html` apenas redireciona. Talvez falte página pública?

---

## 🔧 ARQUIVOS QUE PRECISAM DE CORREÇÕES/COMPLEMENTOS

### 1. ❌ admin.html - Linha 716
**Problema:** Chama `fetch('criar-usuario-vendas.php')` diretamente
```javascript
// ATUAL (INCORRETO)
const response = await fetch('criar-usuario-vendas.php', {
```

**Deveria ser:**
```javascript
// CORRETO
const response = await fetch(API_URL + '/auth.php?action=criar_usuario', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ login, senha, nome, papel, email })
});
```

---

### 2. ⚠️ db/schema.sql - Estrutura de Tabelas
**Problemas Identificados:**

#### a. Tabela `usuarios` 
Missing fields:
```sql
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS id_usuario INT PRIMARY KEY AUTO_INCREMENT FIRST;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS papel VARCHAR(50) DEFAULT 'usuario';
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS nome VARCHAR(100);
```

#### b. Tabela `produtos`
Missing fields:
```sql
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS id_produto INT PRIMARY KEY AUTO_INCREMENT FIRST;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS tipo_categoria VARCHAR(50);
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS preco_venda DECIMAL(10,2);
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS preco_custo DECIMAL(10,2);
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS estoque_atual INT DEFAULT 0;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS imagem VARCHAR(255);
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE produtos ADD COLUMN IF NOT EXISTS data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
```

#### c. Tabela `vendas`
Missing fields:
```sql
ALTER TABLE vendas ADD COLUMN IF NOT EXISTS id_venda INT PRIMARY KEY AUTO_INCREMENT FIRST;
ALTER TABLE vendas ADD COLUMN IF NOT EXISTS id_usuario INT;
ALTER TABLE vendas ADD COLUMN IF NOT EXISTS data_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE vendas ADD COLUMN IF NOT EXISTS valor_total DECIMAL(10,2) DEFAULT 0;
ALTER TABLE vendas ADD COLUMN IF NOT EXISTS numero_venda_dia INT;
ALTER TABLE vendas ADD COLUMN IF NOT EXISTS sessao_caixa_id INT;
ALTER TABLE vendas ADD COLUMN IF NOT EXISTS numero_venda_turno INT;
```

#### d. Tabela `itens_venda` (FALTANDO COMPLETAMENTE)
```sql
CREATE TABLE IF NOT EXISTS itens_venda (
    id_item INT PRIMARY KEY AUTO_INCREMENT,
    id_venda INT NOT NULL,
    id_produto INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10,2),
    subtotal DECIMAL(10,2),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_venda) REFERENCES vendas(id_venda) ON DELETE CASCADE,
    FOREIGN KEY (id_produto) REFERENCES produtos(id_produto)
);
```

---

### 3. 📁 Diretório de Uploads
**Status:** ⚠️ Pode não existir  
**Necessário:**
```
/uploads/
/uploads/produtos/
/uploads/logos/
/uploads/banners/
```

---

## 📋 CHECKLIST DE AÇÕES

### Imediato (Priority: 🔴 ALTA)
- [ ] Adicionar tabela `configuracoes` ao `db/schema.sql`
- [ ] Adicionar tabela `itens_venda` ao `db/schema.sql`
- [ ] Corrigir campos faltando em `usuarios`, `produtos`, `vendas`
- [ ] Criar `api/upload.php` completo
- [ ] Criar `js/auth.js` para gerenciar autenticação
- [ ] Atualizar chamada em `admin.html` linha 716

### Importante (Priority: 🟡 MÉDIA)
- [ ] Criar `js/utils.js` com funções compartilhadas
- [ ] Criar `js/config.js` com constantes
- [ ] Validar `api/relatorios.php` completamente
- [ ] Validar `api/cupom-config.php`
- [ ] Criar diretórios de uploads se não existirem

### Complementar (Priority: 🟢 BAIXA)
- [ ] Adicionar mais validações em APIs
- [ ] Implementar logs mais robustos
- [ ] Adicionar rate limiting
- [ ] Melhorar tratamento de erros global
- [ ] Adicionar comentários em arquivos críticos

---

## 📊 Resumo

| Categoria | Presentes | Faltando | Incompletos |
|-----------|-----------|----------|------------|
| HTML | 10 | 0 | 1 |
| APIs | 10 | 1 | 3 |
| JavaScript | 5 | 3 | 2 |
| BD/Config | 2 | 0 | 2 |
| CSS | 1 | 0 | 0 |
| **TOTAL** | **28** | **4** | **8** |

---

## 🎯 Próximos Passos Recomendados

1. **Validar schema.sql** - Atualizar com as tabelas e campos faltando
2. **Criar api/upload.php** - Upload de logo e banner
3. **Criar js/auth.js** - Gerenciar autenticação no client
4. **Criar js/utils.js** - Funções compartilhadas
5. **Testar todas as APIs** - Validar respostas e erros
6. **Criar/validar diretórios** - Garantir permissões

---

**Última atualização:** 11.03.2026  
**Status:** Análise Completa e Pronta para Ação
