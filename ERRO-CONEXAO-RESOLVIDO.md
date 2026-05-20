# ❌ Erro de Conexão JSON Resolvido

## Problema Identificado
**Erro:** "Unexpected token '<', "\\n"... is not valid JSON"

**Causa:** O servidor MySQL não estava rodando, causando erro de conexão que retorna HTML ao invés de JSON.

---

## ✅ Solução Rápida

### Passo 1: Iniciar MySQL
1. Abra o **XAMPP Control Panel** (geralmente no ícone da bandeja do sistema)
2. Localize a linha **MySQL**
3. Clique no botão **Start** (à esquerda)
4. Aguarde até aparecer **Running** em verde

### Passo 2: Verificar Conexão
Após iniciar o MySQL, acesse: `http://localhost/Cantina/test-connection.php`

Esperado ver:
```json
"database": {
  "status": "ok",
  "database": "programa_cantina",
  "tables": [...]
}
```

### Passo 3: Fazer Login
Volte para `http://localhost/Cantina/login-menu.html` e tente novamente com:
- **Admin:** admin / admin123
- **Vendedor:** joao / vendedor123

---

## 🔧 Alternativa: Iniciar XAMPP via Terminal

Se o XAMPP Control Panel não abrir, use:

```bash
# Windows Command Prompt (cmd) ou PowerShell
cd C:\xampp
mysql\bin\mysqld.exe
```

Ou para executar como serviço:
```bash
cd C:\xampp\mysql\bin
mysqld.exe --install MySQL80
net start MySQL80
```

---

## 📋 Arquivos Melhorados

✅ **db/config.php** - Agora detecta erros de BD e retorna JSON válido
✅ **test-connection.php** - Diagnóstico automático adicionado

---

## 🚀 Como Evitar Esse Erro Novamente

**Inicie sempre os serviços nesta ordem:**
1. **Apache** (primeiro)
2. **MySQL** (segundo)
3. **Acesse a aplicação**

---

## 📞 Próximas Etapas

Se o erro persistir após iniciar MySQL:
1. ✅ Reinicie completamente o XAMPP
2. ✅ Verifique se as credenciais em `db/config.php` estão corretas
3. ✅ Consulte os logs do MySQL em `C:\xampp\mysql\data\*.err`

---

## Debug Rápido

Para verificar status do MySQL instantaneamente, execute:

```bash
# PowerShell
Get-Service | Where-Object {$_.Name -like "*MySQL*"}

# Ou acesse
http://localhost/Cantina/test-connection.php
```

Se vir `"database": {"status": "ok"}` ✅ tudo está funcionando!
