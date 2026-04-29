# 🔐 Credenciais de Acesso - Programa Cantina

## Usuários Padrão

O sistema possui 4 tipos de usuários com diferentes permissões e acessos:

### 1️⃣ Administrador
**Email:** `admin@cantina.com`
**Senha:** `admin123`
**Tipo:** Admin

**Permissões:**
- ✅ Ver Visão Geral (Dashboard)
- ✅ Ver Produção
- ✅ Registrar Produção
- ✅ Ver Vendas
- ✅ Registrar Venda
- ✅ Ver Histórico de Vendas
- ✅ Acessar Estoque
- ✅ Ver Relatórios
- ✅ Gerenciar Usuários
- ✅ Gerenciar Produtos

**Acesso:** Completo a todas as funcionalidades

---

### 2️⃣ Gerente
**Email:** `gerente@cantina.com`
**Senha:** `gerente123`
**Tipo:** Gerente

**Permissões:**
- ✅ Ver Visão Geral (Dashboard)
- ✅ Ver Produção (apenas visualizar)
- ❌ Registrar Produção
- ✅ Ver Vendas (apenas visualizar)
- ❌ Registrar Venda
- ✅ Ver Histórico de Vendas
- ✅ Acessar Estoque
- ✅ Ver Relatórios
- ❌ Gerenciar Usuários
- ❌ Gerenciar Produtos

**Acesso:** Visualização completa de dados, análise de vendas e estoque, sem permissão para registrar operações

---

### 3️⃣ Vendedor (Atendente)
**Email:** `vendedor@cantina.com`
**Senha:** `vendedor123`
**Tipo:** Atendente

**Permissões:**
- ❌ Ver Visão Geral (Dashboard)
- ❌ Ver Produção
- ❌ Registrar Produção
- ✅ Ver Vendas
- ✅ Registrar Venda
- ❌ Ver Histórico de Vendas
- ❌ Acessar Estoque
- ❌ Ver Relatórios
- ❌ Gerenciar Usuários
- ❌ Gerenciar Produtos

**Acesso:** Apenas registrar vendas no carrinho e finalizar transações. Sem acesso a dados gerenciais ou histórico.

---

### 4️⃣ Produtor
**Email:** `produtor@cantina.com`
**Senha:** `produtor123`
**Tipo:** Produtor

**Permissões:**
- ❌ Ver Visão Geral (Dashboard)
- ✅ Ver Produção
- ✅ Registrar Produção
- ❌ Ver Vendas
- ❌ Registrar Venda
- ❌ Ver Histórico de Vendas
- ✅ Acessar Estoque (apenas visualizar)
- ❌ Ver Relatórios
- ❌ Gerenciar Usuários
- ❌ Gerenciar Produtos

**Acesso:** Apenas registrar produção e visualizar estoque disponível. Sem acesso a dados de vendas.

---

## Comparação de Permissões

| Funcionalidade | Admin | Gerente | Vendedor | Produtor |
|---|---|---|---|---|
| **Visão Geral (Dashboard)** | ✅ | ✅ | ❌ | ❌ |
| **Ver Produção** | ✅ | ✅ | ❌ | ✅ |
| **Registrar Produção** | ✅ | ❌ | ❌ | ✅ |
| **Ver Vendas** | ✅ | ✅ | ✅ | ❌ |
| **Registrar Venda** | ✅ | ❌ | ✅ | ❌ |
| **Histórico de Vendas** | ✅ | ✅ | ❌ | ❌ |
| **Acessar Estoque** | ✅ | ✅ | ❌ | ✅ |
| **Ver Relatórios** | ✅ | ✅ | ❌ | ❌ |
| **Gerenciar Usuários** | ✅ | ❌ | ❌ | ❌ |
| **Gerenciar Produtos** | ✅ | ❌ | ❌ | ❌ |

---

## Fluxo de Acesso

### 🔄 Fluxo Completo de Operação

```
1. PRODUTOR
   ├─ Acessa: dashboard.html
   ├─ Vê: Seção de Produção + Estoque
   ├─ Ações: Registrar produção, visualizar estoque
   └─ Não vê: Vendas, Dashboard, Relatórios

2. VENDEDOR
   ├─ Acessa: dashboard.html
   ├─ Vê: Seção de Vendas
   ├─ Ações: Adicionar produtos ao carrinho, finalizar venda
   └─ Não vê: Produção, Dashboard, Histórico, Estoque

3. GERENTE
   ├─ Acessa: dashboard.html
   ├─ Vê: Dashboard, Produção, Vendas, Histórico, Estoque
   ├─ Ações: Visualizar dados, analisar relatórios
   └─ Não faz: Registrar operações

4. ADMIN
   ├─ Acessa: dashboard.html
   ├─ Vê: Tudo
   ├─ Ações: Todas as operações
   └─ Gerencia: Usuários, Produtos, Sistema
```

---

## Como Fazer Login

### Via Interface Web

1. Acesse: `http://localhost:8000/login.html`
2. Digite o email do usuário
3. Digite a senha
4. Clique em "Entrar"
5. Será redirecionado para `dashboard.html` com as permissões específicas

### Via API (cURL)

```bash
curl -X POST http://localhost:8000/auth.php?api=login \
  -H "Content-Type: application/json" \
  -d '{"email":"gerente@cantina.com","senha":"gerente123"}'
```

**Resposta:**
```json
{
    "success": true,
    "message": "Login realizado com sucesso"
}
```

---

## Verificar Permissões do Usuário Logado

### Via API

```bash
curl -X POST http://localhost:8000/auth.php?api=usuario
```

**Resposta:**
```json
{
    "success": true,
    "usuario": {
        "id": 5,
        "nome": "Gerente Cantina",
        "email": "gerente@cantina.com",
        "tipo": "gerente"
    },
    "permissoes": {
        "ver_dashboard": true,
        "ver_visao_geral": true,
        "ver_producao": true,
        "registrar_producao": false,
        "ver_vendas": true,
        "registrar_venda": false,
        "ver_historico_vendas": true,
        "ver_relatorios": true,
        "gerenciar_usuarios": false,
        "gerenciar_produtos": false,
        "acessar_estoque": true
    }
}
```

---

## Logout

### Via Interface Web
Clique no avatar do usuário (canto superior direito) → "Sair"

### Via API
```bash
curl -X POST http://localhost:8000/auth.php?api=logout
```

---

## Segurança

### Proteção Implementada

✅ **Senhas com Hash BCRYPT** - Criptografia forte
✅ **Sessões PHP** - Gerenciamento seguro
✅ **Timeout de Sessão** - Expiração após 1 hora
✅ **Prepared Statements** - Proteção contra SQL Injection
✅ **Validação de Email** - Formato e unicidade
✅ **Verificação de Usuário Ativo** - Desativação de contas

### Boas Práticas

1. **Nunca compartilhe credenciais** - Cada usuário deve ter sua própria conta
2. **Altere a senha padrão** - Especialmente para Admin
3. **Use HTTPS em produção** - Proteja as credenciais em trânsito
4. **Monitore acessos** - Verifique o histórico de último acesso
5. **Desative usuários inativos** - Remova acessos desnecessários

---

## Troubleshooting

### "Email ou senha incorretos"
- Verifique se o email está correto
- Verifique se a senha está correta
- Certifique-se de que o usuário está ativo no banco de dados

### "Sessão expirada"
- Faça login novamente
- A sessão expira após 1 hora de inatividade

### "Acesso Restrito"
- Você não tem permissão para acessar essa funcionalidade
- Contate o administrador para solicitar acesso

---

## Próximos Passos

1. **Adicionar mais usuários** - Via interface de gerenciamento
2. **Alterar senhas padrão** - Por segurança
3. **Configurar permissões customizadas** - Se necessário
4. **Implementar auditoria** - Registrar todas as ações
5. **Backup de dados** - Proteger informações críticas

---

## Suporte

Para dúvidas sobre permissões ou acesso, consulte:
- `permissions.php` - Definição de permissões
- `auth.php` - Funções de autenticação
- `check-auth.php` - Proteção de rotas
- `AUTENTICACAO.md` - Documentação técnica
